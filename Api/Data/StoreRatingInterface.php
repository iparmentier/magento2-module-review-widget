<?php
/**
 * Store Rating Data Interface
 *
 * Data transfer object for store rating information
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Api\Data;

/**
 * Interface StoreRatingInterface
 *
 * Represents aggregated rating data for a store
 *
 * @api
 */
interface StoreRatingInterface
{
    /**
     * Get total number of reviews
     *
     * @return int
     */
    public function getTotal(): int;

    /**
     * Set total number of reviews
     *
     * @param int $total
     * @return $this
     */
    public function setTotal(int $total): self;

    /**
     * Get rating percentage (0-100)
     *
     * @return float
     */
    public function getPercentage(): float;

    /**
     * Set rating percentage
     *
     * @param float $percentage
     * @return $this
     */
    public function setPercentage(float $percentage): self;

    /**
     * Get rating note (0-5 stars)
     *
     * @return float
     */
    public function getNote(): float;

    /**
     * Set rating note
     *
     * @param float $note
     * @return $this
     */
    public function setNote(float $note): self;

    /**
     * Get average rating value
     *
     * @return float
     */
    public function getAverageRating(): float;

    /**
     * Set average rating value
     *
     * @param float $rating
     * @return $this
     */
    public function setAverageRating(float $rating): self;
}