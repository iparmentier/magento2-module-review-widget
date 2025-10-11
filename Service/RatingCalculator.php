<?php
/**
 * Rating Calculator Service
 *
 * Calculates and caches store rating statistics
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Service;

use Amadeco\ReviewWidget\Api\Data\StoreRatingInterface;
use Amadeco\ReviewWidget\Api\Data\StoreRatingInterfaceFactory;
use Amadeco\ReviewWidget\Api\RatingCalculatorInterface;
use Amadeco\ReviewWidget\Helper\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Review\Model\ResourceModel\Review\Product\Collection;
use Magento\Review\Model\ResourceModel\Review\Product\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class RatingCalculator
 *
 * Service for calculating store ratings with caching support
 */
class RatingCalculator implements RatingCalculatorInterface
{
    /**
     * Cache key prefix
     */
    public const CACHE_KEY_PREFIX = 'amadeco_review_widget_rating_';

    /**
     * Cache tag
     */
    public const CACHE_TAG = 'AMADECO_REVIEW_WIDGET';

    /**
     * Minimum reviews required for rating calculation
     */
    public const MIN_REVIEWS_REQUIRED = 1;

    /**
     * @param StoreRatingInterfaceFactory $storeRatingFactory
     * @param ReviewCollectionFactory $reviewCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected readonly StoreRatingInterfaceFactory $storeRatingFactory,
        protected readonly ReviewCollectionFactory $reviewCollectionFactory,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly CacheInterface $cache,
        protected readonly SerializerInterface $serializer,
        protected readonly Config $config,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function calculateStoreRating(Collection $reviewCollection): ?StoreRatingInterface
    {
        try {
            $totalVotes = 0;
            $sumPercentages = 0;

            // CRITICAL FIX: Use rating_summary from our JOIN instead of getSum()
            foreach ($reviewCollection as $review) {
                // Try to get rating_summary (from our JOIN in ReviewRepository)
                $ratingSummary = $review->getData('rating_summary');

                if ($ratingSummary !== null && $ratingSummary > 0) {
                    $sumPercentages += (float) $ratingSummary;
                    $totalVotes++;
                    continue;
                }

                // Fallback: try sum/count method (legacy compatibility)
                $sum = $review->getData('sum');
                $count = $review->getData('count');

                if ($sum > 0 && $count > 0) {
                    $reviewAverage = $sum / $count;
                    $sumPercentages += $reviewAverage;
                    $totalVotes++;
                }
            }

            if ($totalVotes < self::MIN_REVIEWS_REQUIRED) {
                $this->logger->debug(
                    'Insufficient reviews for rating calculation',
                    ['total_votes' => $totalVotes, 'min_required' => self::MIN_REVIEWS_REQUIRED]
                );
                return null;
            }

            // Calculate average percentage (0-100)
            $averagePercentage = $sumPercentages / $totalVotes;

            // Convert to note (0-5)
            $note = round(($averagePercentage / 100) * 5, 2);

            // Convert to rating value (0-5 scale)
            $averageRating = $averagePercentage / 20;

            /** @var StoreRatingInterface $storeRating */
            $storeRating = $this->storeRatingFactory->create();
            $storeRating->setTotal($totalVotes)
                ->setPercentage($averagePercentage)
                ->setNote($note)
                ->setAverageRating($averageRating);

            $this->logger->debug(
                'Store rating calculated',
                [
                    'total_votes' => $totalVotes,
                    'average_percentage' => $averagePercentage,
                    'note' => $note,
                    'average_rating' => $averageRating
                ]
            );

            return $storeRating;

        } catch (\Exception $e) {
            $this->logger->error(
                'Error calculating store rating: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getStoreRating(?int $storeId = null): ?StoreRatingInterface
    {
        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            $cacheKey = self::CACHE_KEY_PREFIX . $storeId;

            // Try to load from cache
            $cachedData = $this->cache->load($cacheKey);
            if ($cachedData) {
                $data = $this->serializer->unserialize($cachedData);
                /** @var StoreRatingInterface $storeRating */
                $storeRating = $this->storeRatingFactory->create(['data' => $data]);

                $this->logger->debug(
                    'Store rating loaded from cache',
                    ['store_id' => $storeId, 'cache_key' => $cacheKey]
                );

                return $storeRating;
            }

            // CRITICAL FIX: Get ALL approved reviews with rating data
            // Do NOT use pagination here - we need the complete dataset
            $reviewCollection = $this->getAllApprovedReviewsWithRatings($storeId);

            // Calculate fresh rating
            $storeRating = $this->calculateStoreRating($reviewCollection);

            if ($storeRating) {
                // Save to cache
                $cacheLifetime = $this->config->getCacheLifetime();
                $this->cache->save(
                    $this->serializer->serialize($storeRating->getData()),
                    $cacheKey,
                    [self::CACHE_TAG],
                    $cacheLifetime
                );

                $this->logger->debug(
                    'Store rating calculated and cached',
                    [
                        'store_id' => $storeId,
                        'cache_key' => $cacheKey,
                        'cache_lifetime' => $cacheLifetime
                    ]
                );
            }

            return $storeRating;

        } catch (\Exception $e) {
            $this->logger->error(
                'Error getting store rating: ' . $e->getMessage(),
                ['exception' => $e, 'store_id' => $storeId]
            );
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function clearCache(?int $storeId = null): void
    {
        try {
            if ($storeId !== null) {
                $cacheKey = self::CACHE_KEY_PREFIX . $storeId;
                $this->cache->remove($cacheKey);

                $this->logger->debug(
                    'Store rating cache cleared for store',
                    ['store_id' => $storeId, 'cache_key' => $cacheKey]
                );
            } else {
                $this->cache->clean([self::CACHE_TAG]);

                $this->logger->debug('Store rating cache cleared for all stores');
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Error clearing rating cache: ' . $e->getMessage(),
                ['exception' => $e, 'store_id' => $storeId]
            );
        }
    }

    /**
     * Get ALL approved reviews with rating votes for store rating calculation
     *
     * CRITICAL: This method gets ALL reviews (no pagination) to calculate the global store rating
     *
     * @param int $storeId
     * @return Collection
     */
    private function getAllApprovedReviewsWithRatings(int $storeId): Collection
    {
        try {
            /** @var Collection $collection */
            $collection = $this->reviewCollectionFactory->create();

            // Add basic filters
            $collection->addStoreFilter($storeId)
                ->addStatusFilter(Review::STATUS_APPROVED);

            // CRITICAL: Add rating votes JOIN to get rating_summary
            // This is the same JOIN we use in ReviewRepository, but here we need ALL reviews
            $this->addRatingVotesToCollection($collection);

            // DO NOT add pagination here - we need all reviews for accurate store rating
            // DO NOT use setPageSize() or limit()

            $this->logger->debug(
                'Fetching all approved reviews with ratings for store rating calculation',
                [
                    'store_id' => $storeId,
                    'collection_size' => $collection->getSize()
                ]
            );

            return $collection;

        } catch (\Exception $e) {
            $this->logger->error(
                'Error getting approved reviews with ratings: ' . $e->getMessage(),
                ['exception' => $e, 'store_id' => $storeId]
            );

            // Return empty collection on error
            return $this->reviewCollectionFactory->create();
        }
    }

    /**
     * Add rating votes to collection with proper JOIN
     *
     * Same as ReviewRepository but isolated here for store rating calculation
     *
     * @param Collection $collection
     * @return void
     */
    private function addRatingVotesToCollection(Collection $collection): void
    {
        $select = $collection->getSelect();
        $fromTables = $select->getPart(Select::FROM);

        // Check if rating_option_vote JOIN already exists
        if (isset($fromTables['rov'])) {
            $this->logger->debug('Rating votes JOIN already exists in RatingCalculator');
            return;
        }

        // Check if any other alias points to rating_option_vote table
        foreach ($fromTables as $alias => $tableInfo) {
            if (isset($tableInfo['tableName']) &&
                strpos($tableInfo['tableName'], 'rating_option_vote') !== false) {
                $this->logger->debug(
                    'Rating votes table already joined with different alias in RatingCalculator',
                    ['existing_alias' => $alias]
                );
                return;
            }
        }

        // Safe to add our JOIN
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
}