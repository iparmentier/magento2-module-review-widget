<?php
/**
 * Rating Calculator Interface
 *
 * Defines contract for calculating store ratings and statistics
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Api;

use Amadeco\ReviewWidget\Api\Data\StoreRatingInterface;
use Magento\Review\Model\ResourceModel\Review\Product\Collection;

/**
 * Interface RatingCalculatorInterface
 *
 * Provides methods to calculate rating statistics
 *
 * @api
 */
interface RatingCalculatorInterface
{
    /**
     * Calculate store rating from review collection
     *
     * @param Collection $reviewCollection Review collection to analyze
     * @return StoreRatingInterface|null Returns null if insufficient data
     */
    public function calculateStoreRating(Collection $reviewCollection): ?StoreRatingInterface;

    /**
     * Get cached store rating or calculate if not cached
     *
     * @param int|null $storeId Store ID
     * @return StoreRatingInterface|null
     */
    public function getStoreRating(?int $storeId = null): ?StoreRatingInterface;

    /**
     * Clear rating cache for specific store
     *
     * @param int|null $storeId Store ID (null for all stores)
     * @return void
     */
    public function clearCache(?int $storeId = null): void;
}