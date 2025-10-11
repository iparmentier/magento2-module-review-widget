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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Review\Model\ResourceModel\Review\Product\Collection;
use Magento\Review\Model\ResourceModel\Review\Product\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Store\Model\StoreManagerInterface;

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
    public const SORT_ORDER_RECENT = 'recent';
    public const SORT_ORDER_RATING = 'rating';
    public const SORT_ORDER_RANDOM = 'random';

    /**
     * Rating vote table alias
     */
    private const RATING_VOTE_ALIAS = 'rov';

    /**
     * Category product table alias
     */
    private const CATEGORY_PRODUCT_ALIAS = 'ccp';

    /**
     * @param ReviewCollectionFactory $reviewCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param InputValidator $inputValidator
     */
    public function __construct(
        protected readonly ReviewCollectionFactory $reviewCollectionFactory,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly InputValidator $inputValidator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getApprovedReviews(?int $storeId = null): Collection
    {
        $storeId = $this->getValidatedStoreId($storeId);

        /** @var Collection $collection */
        $collection = $this->reviewCollectionFactory->create();

        $collection->addStoreFilter($storeId)
            ->addStatusFilter(Review::STATUS_APPROVED);

        return $collection;
    }

    /**
     * @inheritDoc
     */
    public function getFilteredReviews(array $filters = [], ?int $storeId = null): Collection
    {
        $filters = $this->inputValidator->validateFilters($filters);
        $collection = $this->getApprovedReviews($storeId);

        $this->addRatingVotesToCollection($collection);
        $this->applyFilters($collection, $filters);

        return $collection;
    }

    /**
     * @inheritDoc
     */
    public function getReviewCount(?int $storeId = null): int
    {
        $collection = $this->getApprovedReviews($storeId);
        return $collection->getSize();
    }

    /**
     * Apply all filters to collection
     *
     * Filters are applied in optimal order for SQL performance:
     * 1. WHERE filters (indexed columns)
     * 2. JOIN filters (related tables)
     * 3. HAVING filters (aggregate functions)
     * 4. SORT and LIMIT
     *
     * @param Collection $collection
     * @param array $filters Validated filters
     * @return void
     */
    private function applyFilters(Collection $collection, array $filters): void
    {
        if (isset($filters['min_char_length']) && $filters['min_char_length'] > 0) {
            $this->applyMinCharLengthFilter($collection, (int) $filters['min_char_length']);
        }

        if (isset($filters['days_ago']) && $filters['days_ago'] > 0) {
            $this->applyDateFilter($collection, (int) $filters['days_ago']);
        }

        if (isset($filters['category_id']) && $filters['category_id'] > 0) {
            $this->applyCategoryFilter($collection, (int) $filters['category_id']);
        }

        if (isset($filters['min_rating']) && $filters['min_rating'] > 0) {
            $this->applyMinRatingFilter($collection, (float) $filters['min_rating']);
        }

        $sortOrder = $filters['sort_order'] ?? self::SORT_ORDER_RANDOM;
        $this->applySortOrder($collection, $sortOrder);

        if (isset($filters['page_size']) && $filters['page_size'] > 0) {
            $collection->getSelect()->limit((int) $filters['page_size']);
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
            'AVG(' . self::RATING_VOTE_ALIAS . '.percent) >= ?',
            $minPercentage
        );
    }

    /**
     * Add rating votes to collection with proper JOIN
     *
     * Uses Magento's standard approach with alias checking
     *
     * @param Collection $collection
     * @return void
     */
    private function addRatingVotesToCollection(Collection $collection): void
    {
        $select = $collection->getSelect();
        $fromTables = $select->getPart(Select::FROM);

        if ($this->isTableJoined($fromTables, self::RATING_VOTE_ALIAS, 'rating_option_vote')) {
            return;
        }

        $collection->getSelect()
            ->joinLeft(
                [self::RATING_VOTE_ALIAS => $collection->getTable('rating_option_vote')],
                self::RATING_VOTE_ALIAS . '.review_id = rt.review_id',
                [
                    'rating_summary' => 'AVG(' . self::RATING_VOTE_ALIAS . '.percent)',
                    'rating_value' => 'AVG(' . self::RATING_VOTE_ALIAS . '.value)'
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
        $select = $collection->getSelect();
        $fromTables = $select->getPart(Select::FROM);

        if ($this->isTableJoined($fromTables, self::CATEGORY_PRODUCT_ALIAS, 'catalog_category_product')) {
            $collection->getSelect()->where(self::CATEGORY_PRODUCT_ALIAS . '.category_id = ?', $categoryId);
            return;
        }

        $collection->getSelect()
            ->joinInner(
                [self::CATEGORY_PRODUCT_ALIAS => $collection->getTable('catalog_category_product')],
                self::CATEGORY_PRODUCT_ALIAS . '.product_id = rt.entity_pk_value',
                []
            )
            ->where(self::CATEGORY_PRODUCT_ALIAS . '.category_id = ?', $categoryId);
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
        $collection->getSelect()->reset(Select::ORDER);

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
     * Check if a table is already joined in the query
     *
     * @param array $fromTables Tables from SELECT
     * @param string $alias Alias to check
     * @param string $tableName Table name to check
     * @return bool
     */
    private function isTableJoined(array $fromTables, string $alias, string $tableName): bool
    {
        if (isset($fromTables[$alias])) {
            return true;
        }

        foreach ($fromTables as $tableInfo) {
            if (isset($tableInfo['tableName']) &&
                str_contains((string) $tableInfo['tableName'], $tableName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate and get store ID
     *
     * @param int|null $storeId
     * @return int
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function getValidatedStoreId(?int $storeId): int
    {
        if ($storeId === null) {
            return (int) $this->storeManager->getStore()->getId();
        }

        if ($storeId < 0) {
            throw new LocalizedException(__('Invalid store ID provided: %1', $storeId));
        }

        return $storeId;
    }
}