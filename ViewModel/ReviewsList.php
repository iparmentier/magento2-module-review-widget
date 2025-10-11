<?php
/**
 * Reviews List ViewModel
 *
 * Provides data and logic for reviews list display
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\ViewModel;

use Amadeco\ReviewWidget\Api\ReviewRepositoryInterface;
use Amadeco\ReviewWidget\Helper\Config;
use Amadeco\ReviewWidget\Helper\SchemaManager;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Review\Model\ResourceModel\Review\Product\Collection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ReviewsList
 *
 * ViewModel for reviews list widget
 */
class ReviewsList implements ArgumentInterface
{
    /**
     * @var Collection|null
     */
    private ?Collection $reviewCollection = null;

    /**
     * @var array|null
     */
    private ?array $filters = null;

    /**
     * Flag to track if collection was already loaded
     *
     * @var bool
     */
    private bool $collectionLoaded = false;

    /**
     * @param ReviewRepositoryInterface $reviewRepository
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param SchemaManager $schemaManager
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ReviewRepositoryInterface $reviewRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly SchemaManager $schemaManager,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Set filters for review collection
     *
     * CRITICAL: This resets the collection to force re-fetching with new filters
     *
     * @param array $filters
     * @return void
     */
    public function setFilters(array $filters): void
    {
        $this->filters = $filters;
        $this->reviewCollection = null;
        $this->collectionLoaded = false;
    }

    /**
     * Get filtered review collection
     *
     * CRITICAL FIX: Ensures collection is properly loaded with pagination limits
     *
     * @return Collection
     */
    public function getReviewCollection(): Collection
    {
        if ($this->reviewCollection === null || !$this->collectionLoaded) {
            $filters = $this->prepareFilters();

            // Get collection from repository (filters already applied)
            $this->reviewCollection = $this->reviewRepository->getFilteredReviews($filters);

            // Mark as loaded to prevent re-loading
            $this->collectionLoaded = true;
        }
        return $this->reviewCollection;
    }

    /**
     * Get collection size (total count respecting filters)
     *
     * @return int
     */
    public function getCollectionSize(): int
    {
        try {
            $collection = $this->getReviewCollection();
            return $collection->getSize();
        } catch (\Exception $e) {
            $this->logger->error(
                'Error getting collection size: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return 0;
        }
    }

    /**
     * Check if collection has items
     *
     * @return bool
     */
    public function hasReviews(): bool
    {
        return $this->getCollectionSize() > 0;
    }

    /**
     * Prepare Schema.org data
     *
     * @param int $limit Maximum number of reviews to include in structured data
     * @return void
     */
    public function prepareSchemaData(int $limit = 5): void
    {
        $this->addReviewsToCombined($limit);
    }

    /**
     * Get Schema.org JSON-LD output
     *
     * @return string
     */
    public function getSchemaJsonLd(): string
    {
        if (!$this->schemaManager->shouldGenerateSchema('reviews')) {
            return '';
        }

        try {
            $allReviews = $this->schemaManager->getCombinedReviews();

            if (empty($allReviews)) {
                return '';
            }

            // Limit to 5 reviews for Schema.org (Google recommendation)
            $allReviews = array_slice($allReviews, 0, 5);

            $structuredData = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => $allReviews
            ];

            $this->schemaManager->markReviewsSchemaAsGenerated();

            $json = $this->serializer->serialize($structuredData);
            return '<script type="application/ld+json">' . $json . '</script>';
        } catch (\Exception $e) {
            $this->logger->error(
                'Error generating Schema.org JSON-LD for reviews: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return '';
        }
    }

    /**
     * Add this widget's reviews to the combined collection
     *
     * CRITICAL FIX: Properly iterates over limited collection
     *
     * @param int $limit Maximum reviews from this widget
     * @return void
     */
    private function addReviewsToCombined(int $limit): void
    {
        if (!$this->schemaManager->isSchemaEnabled()) {
            return;
        }

        try {
            // Get the properly limited collection
            $collection = $this->getReviewCollection();
            $count = 0;

            // CRITICAL FIX: Use getItems() which now respects pagination
            foreach ($collection->getItems() as $review) {
                if ($count >= $limit) {
                    break;
                }

                $reviewData = [
                    '@type' => 'Review',
                    'author' => [
                        '@type' => 'Person',
                        'name' => $review->getNickname()
                    ],
                    'datePublished' => $review->getCreatedAt(),
                    'reviewBody' => $review->getDetail(),
                    'name' => $review->getTitle(),
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => number_format($this->getReviewRatingSummary($review) / 20, 1),
                        'bestRating' => '5',
                        'worstRating' => '1'
                    ]
                ];

                $this->schemaManager->addReviewToCombined($reviewData);
                $count++;
            }

            $this->logger->debug(
                'Added reviews to Schema.org combined data',
                [
                    'count' => $count,
                    'limit' => $limit
                ]
            );

        } catch (\Exception $e) {
            $this->logger->error(
                'Error adding reviews to combined schema: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * Prepare filters with defaults from configuration
     *
     * @return array
     */
    private function prepareFilters(): array
    {
        $filters = $this->filters ?? [];

        // Apply defaults only if not already set
        if (!isset($filters['min_rating']) || $filters['min_rating'] === null) {
            $filters['min_rating'] = $this->config->getDefaultMinRating();
        }

        if (!isset($filters['min_char_length']) || $filters['min_char_length'] === null) {
            $filters['min_char_length'] = $this->config->getDefaultMinCharLength();
        }

        // CRITICAL: Always ensure page_size is set
        if (!isset($filters['page_size']) || $filters['page_size'] === null || $filters['page_size'] <= 0) {
            $filters['page_size'] = $this->config->getDefaultPageSize();
        }

        if (!isset($filters['sort_order']) || $filters['sort_order'] === null) {
            $filters['sort_order'] = 'random';
        }

        $this->logger->debug(
            'Prepared filters for review collection',
            ['filters' => $filters]
        );

        return $filters;
    }

    /**
     * Get rating summary for a review
     *
     * @param \Magento\Review\Model\Review $review
     * @return float Rating percentage (0-100)
     */
    public function getReviewRatingSummary($review): float
    {
        // Try rating_summary first (from aggregate)
        $ratingSummary = $review->getData('rating_summary');
        if ($ratingSummary !== null && $ratingSummary > 0) {
            return (float) $ratingSummary;
        }

        // Try rating_value (from vote)
        $ratingValue = $review->getData('rating_value');
        if ($ratingValue !== null && $ratingValue > 0) {
            return (float) $ratingValue * 20;
        }

        // Fallback to sum/count calculation
        $sum = $review->getData('sum');
        $count = $review->getData('count');

        if ($sum > 0 && $count > 0) {
            return (float) ($sum / $count);
        }

        // Log warning if no rating found
        $this->logger->warning(
            'Could not determine rating for review',
            ['review_id' => $review->getId()]
        );

        return 0.0;
    }

    /**
     * Get store base URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }
}