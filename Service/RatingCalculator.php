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
    private const CACHE_KEY_PREFIX = 'amadeco_review_widget_rating_';

    /**
     * Cache tag
     */
    private const CACHE_TAG = 'AMADECO_REVIEW_WIDGET';

    /**
     * Minimum reviews required for rating calculation
     */
    private const MIN_REVIEWS_REQUIRED = 1;

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
        private readonly StoreRatingInterfaceFactory $storeRatingFactory,
        private readonly ReviewRepositoryInterface $reviewRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function calculateStoreRating(Collection $reviewCollection): ?StoreRatingInterface
    {
        try {
            $cumul = 0;
            $total = 0;

            foreach ($reviewCollection as $review) {
                $sum = $review->getSum();
                if ($sum > 0) {
                    $cumul += $sum;
                    $total++;
                }
            }

            if ($total < self::MIN_REVIEWS_REQUIRED) {
                return null;
            }

            $percentage = $cumul / $total;
            $note = round($percentage / 100 * 5, 2);
            $averageRating = $percentage / 20; // Convert percentage to 0-5 scale

            /** @var StoreRatingInterface $storeRating */
            $storeRating = $this->storeRatingFactory->create();
            $storeRating->setTotal($total)
                ->setPercentage($percentage)
                ->setNote($note)
                ->setAverageRating($averageRating);

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
                return $storeRating;
            }

            // Calculate fresh rating
            $reviewCollection = $this->reviewRepository->getApprovedReviews($storeId);
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
            }

            return $storeRating;
        } catch (\Exception $e) {
            $this->logger->error(
                'Error getting store rating: ' . $e->getMessage(),
                ['exception' => $e]
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
            } else {
                $this->cache->clean([self::CACHE_TAG]);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Error clearing rating cache: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}