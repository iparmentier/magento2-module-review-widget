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
use Amadeco\ReviewWidget\Api\ReviewRepositoryInterface;
use Amadeco\ReviewWidget\Helper\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Review\Model\ResourceModel\Review\Product\Collection;
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
     * Cache tags - using Magento's standard Review cache tag
     */
    public const CACHE_TAG = \Magento\Review\Model\Review::CACHE_TAG;

    /**
     * Minimum reviews required for rating calculation
     */
    public const MIN_REVIEWS_REQUIRED = 1;

    /**
     * @param StoreRatingInterfaceFactory $storeRatingFactory
     * @param ReviewRepositoryInterface $reviewRepository
     * @param StoreManagerInterface $storeManager
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected readonly StoreRatingInterfaceFactory $storeRatingFactory,
        protected readonly ReviewRepositoryInterface $reviewRepository,
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
        $totalVotes = 0;
        $sumPercentages = 0;

        foreach ($reviewCollection as $review) {
            $ratingSummary = $review->getData('rating_summary');

            if ($ratingSummary !== null && $ratingSummary > 0) {
                $sumPercentages += (float) $ratingSummary;
                $totalVotes++;
                continue;
            }

            $sum = $review->getData('sum');
            $count = $review->getData('count');

            if ($sum > 0 && $count > 0) {
                $reviewAverage = $sum / $count;
                $sumPercentages += $reviewAverage;
                $totalVotes++;
            }
        }

        if ($totalVotes < self::MIN_REVIEWS_REQUIRED) {
            $this->logger->info('Insufficient reviews for rating calculation', [
                'total_votes' => $totalVotes,
                'required' => self::MIN_REVIEWS_REQUIRED
            ]);
            return null;
        }

        $averagePercentage = $sumPercentages / $totalVotes;
        $note = round(($averagePercentage / 100) * 5, 2);
        $averageRating = $averagePercentage / 20;

        /** @var StoreRatingInterface $storeRating */
        $storeRating = $this->storeRatingFactory->create();
        $storeRating->setTotal($totalVotes)
            ->setPercentage($averagePercentage)
            ->setNote($note)
            ->setAverageRating($averageRating);

        return $storeRating;
    }

    /**
     * @inheritDoc
     */
    public function getStoreRating(?int $storeId = null): ?StoreRatingInterface
    {
        $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
        $cacheKey = self::CACHE_KEY_PREFIX . $storeId;

        $cachedData = $this->cache->load($cacheKey);
        if ($cachedData) {
            $data = $this->serializer->unserialize($cachedData);
            /** @var StoreRatingInterface $storeRating */
            return $this->storeRatingFactory->create(['data' => $data]);
        }

        // Calculate rating
        $reviewCollection = $this->reviewRepository->getApprovedReviews($storeId);
        $storeRating = $this->calculateStoreRating($reviewCollection);

        // Save to cache if we have a valid rating
        if ($storeRating) {
            $cacheLifetime = $this->config->getCacheLifetime();
            $this->cache->save(
                $this->serializer->serialize($storeRating->getData()),
                $cacheKey,
                [self::CACHE_TAG],
                $cacheLifetime
            );
        }

        return $storeRating;
    }

    /**
     * @inheritDoc
     */
    public function clearCache(?int $storeId = null): void
    {
        if ($storeId !== null) {
            $cacheKey = self::CACHE_KEY_PREFIX . $storeId;
            $this->cache->remove($cacheKey);
        } else {
            $this->cache->clean([self::CACHE_TAG]);
        }
    }
}