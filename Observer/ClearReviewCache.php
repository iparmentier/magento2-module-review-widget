<?php
/**
 * Clear Review Cache Observer
 *
 * Automatically clears widget cache when reviews are modified
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Observer;

use Amadeco\ReviewWidget\Api\RatingCalculatorInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Review\Model\Review;
use Psr\Log\LoggerInterface;

/**
 * Class ClearReviewCache
 *
 * Observer that clears review widget cache when reviews are modified
 */
class ClearReviewCache implements ObserverInterface
{
    /**
     * Cache tag for review widgets
     */
    private const CACHE_TAG = 'AMADECO_REVIEW_WIDGET';

    /**
     * @param RatingCalculatorInterface $ratingCalculator
     * @param CacheInterface $cache
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RatingCalculatorInterface $ratingCalculator,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Clear review widget cache when a review is modified
     *
     * This ensures that widgets display fresh data after any review changes
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            /** @var Review|null $review */
            $review = $observer->getEvent()->getData('object')
                ?? $observer->getEvent()->getData('data_object')
                ?? $observer->getEvent()->getData('review');

            if (!$review instanceof Review) {
                return;
            }

            // Get store ID from review
            $storeIds = $review->getStores();

            if (empty($storeIds)) {
                // If no specific stores, clear all cache
                $this->clearAllCache();
            } else {
                // Clear cache for specific stores
                foreach ($storeIds as $storeId) {
                    $this->clearStoreCache((int) $storeId);
                }
            }

            $this->logger->info(
                'Review widget cache cleared after review modification',
                [
                    'review_id' => $review->getId(),
                    'store_ids' => $storeIds
                ]
            );

        } catch (\Exception $e) {
            $this->logger->error(
                'Error clearing review widget cache: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * Clear cache for specific store
     *
     * @param int $storeId
     * @return void
     */
    private function clearStoreCache(int $storeId): void
    {
        try {
            // Clear rating calculator cache
            $this->ratingCalculator->clearCache($storeId);

            // Clear block cache by tag
            $this->cache->clean([self::CACHE_TAG]);

        } catch (\Exception $e) {
            $this->logger->error(
                'Error clearing store cache: ' . $e->getMessage(),
                [
                    'store_id' => $storeId,
                    'exception' => $e
                ]
            );
        }
    }

    /**
     * Clear all cache
     *
     * @return void
     */
    private function clearAllCache(): void
    {
        try {
            // Clear all rating calculator cache
            $this->ratingCalculator->clearCache();

            // Clear all block cache
            $this->cache->clean([self::CACHE_TAG]);

        } catch (\Exception $e) {
            $this->logger->error(
                'Error clearing all cache: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}