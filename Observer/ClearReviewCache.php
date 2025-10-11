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
     */
    public function __construct(
        private readonly RatingCalculatorInterface $ratingCalculator,
        private readonly CacheInterface $cache
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
        /** @var Review|null $review */
        $review = $observer->getEvent()->getData('object')
            ?? $observer->getEvent()->getData('data_object')
            ?? $observer->getEvent()->getData('review');

        if (!$review instanceof Review) {
            return;
        }

        $storeIds = $review->getStores();

        if (empty($storeIds)) {
            $this->clearAllCache();
        } else {
            foreach ($storeIds as $storeId) {
                $this->clearStoreCache((int) $storeId);
            }
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
        $this->ratingCalculator->clearCache($storeId);
        $this->cache->clean([self::CACHE_TAG]);
    }

    /**
     * Clear all cache
     *
     * @return void
     */
    private function clearAllCache(): void
    {
        $this->ratingCalculator->clearCache();
        $this->cache->clean([self::CACHE_TAG]);
    }
}