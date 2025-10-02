<?php
/**
 * Review Repository Interface
 *
 * Defines contract for retrieving and filtering customer reviews
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Api;

use Magento\Review\Model\ResourceModel\Review\Product\Collection;

/**
 * Interface ReviewRepositoryInterface
 *
 * Provides methods to retrieve and filter review collections
 *
 * @api
 */
interface ReviewRepositoryInterface
{
    /**
     * Get approved reviews collection for current store
     *
     * @param int|null $storeId Store ID (null for current store)
     * @return Collection
     */
    public function getApprovedReviews(?int $storeId = null): Collection;

    /**
     * Get filtered reviews based on criteria
     *
     * @param array $filters Associative array of filters
     *                       Possible keys:
     *                       - 'min_rating' (float): Minimum rating filter
     *                       - 'min_char_length' (int): Minimum review length
     *                       - 'category_id' (int): Filter by category
     *                       - 'days_ago' (int): Reviews from last X days
     *                       - 'page_size' (int): Number of reviews
     *                       - 'sort_order' (string): Sort order (recent|rating|random)
     * @param int|null $storeId Store ID
     * @return Collection
     */
    public function getFilteredReviews(array $filters = [], ?int $storeId = null): Collection;

    /**
     * Get review count for store
     *
     * @param int|null $storeId Store ID
     * @return int
     */
    public function getReviewCount(?int $storeId = null): int;
}