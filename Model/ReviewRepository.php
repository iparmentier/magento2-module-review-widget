<?php
/**
 * Review Repository Implementation
 *
 * Handles retrieval and filtering of customer reviews with input validation
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Model;

use Amadeco\ReviewWidget\Api\ReviewRepositoryInterface;
use Amadeco\ReviewWidget\Service\InputValidator;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Review\Model\ResourceModel\Review\Product\Collection;
use Magento\Review\Model\ResourceModel\Review\Product\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ReviewRepository
 *
 * Repository for managing review data access with enhanced security
 */
class ReviewRepository implements ReviewRepositoryInterface
{
    /**
     * Sort order constants
     */
    private const SORT_ORDER_RECENT = 'recent';
    private const SORT_ORDER_RATING = 'rating';
    private const SORT_ORDER_RANDOM = 'random';

    /**
     * @param ReviewCollectionFactory $reviewCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param InputValidator $inputValidator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ReviewCollectionFactory $reviewCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly InputValidator $inputValidator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getApprovedReviews(?int $storeId = null): Collection
    {
        try {
            $storeId = $this->getValidatedStoreId($storeId);

            /** @var Collection $collection */
            $collection = $this->reviewCollectionFactory->create();
            $collection->addStoreFilter($storeId)
                ->addStatusFilter(Review::STATUS_APPROVED)
                ->addReviewSummary();

            return $collection;

        } catch (\Exception $e) {
            $this->logger->error(
                'Error getting approved reviews',
                [
                    'exception' => $e,
                    'store_id' => $storeId
                ]
            );
            return $this->reviewCollectionFactory->create();
        }
    }

    /**
     * @inheritDoc
     */
    public function getFilteredReviews(array $filters = [], ?int $storeId = null): Collection
    {
        try {
            // Validate all inputs first
            $filters = $this->inputValidator->validateFilters($filters);

            $collection = $this->getApprovedReviews($storeId);

            // Always add rating votes for proper rating calculation
            $this->addRatingVotesToCollection($collection);

            // Apply filters with validated inputs
            $this->applyFilters($collection, $filters);

            return $collection;

        } catch (\Exception $e) {
            $this->logger->error(
                'Error getting filtered reviews',
                [
                    'exception' => $e,
                    'filters' => $filters,
                    'store_id' => $storeId
                ]
            );
            return $this->reviewCollectionFactory->create();
        }
    }

    /**
     * @inheritDoc
     */
    public function getReviewCount(?int $storeId = null): int
    {
        try {
            $collection = $this->getApprovedReviews($storeId);
            return $collection->getSize();
        } catch (\Exception $e) {
            $this->logger->error(
                'Error getting review count',
                [
                    'exception' => $e,
                    'store_id' => $storeId
                ]
            );
            return 0;
        }
    }

    /**
     * Apply all filters to collection
     *
     * @param Collection $collection
     * @param array $filters Validated filters
     * @return void
     */
    private function applyFilters(Collection $collection, array $filters): void
    {
        if (isset($filters['min_rating']) && $filters['min_rating'] > 0) {
            $this->applyMinRatingFilter($collection, (float) $filters['min_rating']);
        }

        if (isset($filters['min_char_length']) && $filters['min_char_length'] > 0) {
            $this->applyMinCharLengthFilter($collection, (int) $filters['min_char_length']);
        }

        if (isset($filters['category_id']) && $filters['category_id'] > 0) {
            $this->applyCategoryFilter($collection, (int) $filters['category_id']);
        }

        if (isset($filters['days_ago']) && $filters['days_ago'] > 0) {
            $this->applyDateFilter($collection, (int) $filters['days_ago']);
        }

        // Apply sorting
        $sortOrder = $filters['sort_order'] ?? self::SORT_ORDER_RANDOM;
        $this->applySortOrder($collection, $sortOrder);

        // Apply pagination
        if (isset($filters['page_size']) && $filters['page_size'] > 0) {
            $collection->setPageSize((int) $filters['page_size'])
                ->setCurPage(1);
        }
    }

    /**
     * Apply minimum rating filter to collection
     *
     * Uses HAVING clause for aggregate function
     *
     * @param Collection $collection
     * @param float $minRating Validated rating (1-5)
     * @return void
     */
    private function applyMinRatingFilter(Collection $collection, float $minRating): void
    {
        $minPercentage = ($minRating / 5) * 100;

        $collection->getSelect()->having(
            'AVG(rov.percent) >= ?',
            $minPercentage
        );
    }

    /**
     * Add rating votes to collection with proper JOIN
     *
     * @param Collection $collection
     * @return void
     */
    private function addRatingVotesToCollection(Collection $collection): void
    {
        $collection->getSelect()
            ->joinLeft(
                ['rov' => $collection->getTable('rating_option_vote')],
                'rov.review_id = rt.review_id',
                [
                    'rating_summary' => 'AVG(rov.percent)',
                    'rating_value' => 'AVG(rov.value)'
                ]
            )
            ->group('rt.review_id');
    }

    /**
     * Apply minimum character length filter
     *
     * Uses MySQL CHAR_LENGTH function for accurate counting
     *
     * @param Collection $collection
     * @param int $minLength Validated length
     * @return void
     */
    private function applyMinCharLengthFilter(Collection $collection, int $minLength): void
    {
        $collection->getSelect()->where(
            'CHAR_LENGTH(rdt.detail) >= ?',
            $minLength
        );
    }

    /**
     * Apply category filter with JOIN
     *
     * @param Collection $collection
     * @param int $categoryId Validated category ID
     * @return void
     */
    private function applyCategoryFilter(Collection $collection, int $categoryId): void
    {
        $collection->getSelect()
            ->joinInner(
                ['ccp' => $collection->getTable('catalog_category_product')],
                'ccp.product_id = rt.entity_pk_value',
                []
            )
            ->where('ccp.category_id = ?', $categoryId);
    }

    /**
     * Apply date filter for recent reviews
     *
     * @param Collection $collection
     * @param int $daysAgo Validated days ago value
     * @return void
     */
    private function applyDateFilter(Collection $collection, int $daysAgo): void
    {
        $date = new \DateTime();
        $date->modify("-{$daysAgo} days");

        $collection->addFieldToFilter(
            'rt.created_at',
            ['gteq' => $date->format('Y-m-d H:i:s')]
        );
    }

    /**
     * Apply sort order to collection
     *
     * @param Collection $collection
     * @param string $sortOrder Validated sort order
     * @return void
     */
    private function applySortOrder(Collection $collection, string $sortOrder): void
    {
        switch ($sortOrder) {
            case self::SORT_ORDER_RECENT:
                $collection->setOrder('rt.created_at', Select::SQL_DESC);
                break;

            case self::SORT_ORDER_RATING:
                $collection->getSelect()->order('rating_summary ' . Select::SQL_DESC);
                break;

            case self::SORT_ORDER_RANDOM:
            default:
                $collection->getSelect()->orderRand();
                break;
        }
    }

    /**
     * Validate and get store ID
     *
     * @param int|null $storeId
     * @return int
     * @throws LocalizedException
     */
    private function getValidatedStoreId(?int $storeId): int
    {
        if ($storeId === null) {
            return (int) $this->storeManager->getStore()->getId();
        }

        if ($storeId < 0) {
            throw new LocalizedException(__('Invalid store ID: %1', $storeId));
        }

        return $storeId;
    }
}