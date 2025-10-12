<?php
/**
 * Store Rating Data Model
 *
 * Implementation of StoreRatingInterface
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Model\Data;

use Amadeco\ReviewWidget\Api\Data\StoreRatingInterface;
use Magento\Framework\Api\AbstractSimpleObject;

/**
 * Class StoreRating
 *
 * Immutable data transfer object for store rating
 */
class StoreRating extends AbstractSimpleObject implements StoreRatingInterface
{
    /**
     * Data keys
     */
    private const KEY_TOTAL = 'total';
    private const KEY_PERCENTAGE = 'percentage';
    private const KEY_NOTE = 'note';
    private const KEY_AVERAGE_RATING = 'average_rating';

    /**
     * @inheritDoc
     */
    public function getTotal(): int
    {
        return (int) $this->getData(self::KEY_TOTAL);
    }

    /**
     * @inheritDoc
     */
    public function setTotal(int $total): StoreRatingInterface
    {
        return $this->setData(self::KEY_TOTAL, $total);
    }

    /**
     * @inheritDoc
     */
    public function getPercentage(): float
    {
        return (float) $this->getData(self::KEY_PERCENTAGE);
    }

    /**
     * @inheritDoc
     */
    public function setPercentage(float $percentage): StoreRatingInterface
    {
        return $this->setData(self::KEY_PERCENTAGE, $percentage);
    }

    /**
     * @inheritDoc
     */
    public function getNote(): float
    {
        return (float) $this->getData(self::KEY_NOTE);
    }

    /**
     * @inheritDoc
     */
    public function setNote(float $note): StoreRatingInterface
    {
        return $this->setData(self::KEY_NOTE, $note);
    }

    /**
     * @inheritDoc
     */
    public function getAverageRating(): float
    {
        return (float) $this->getData(self::KEY_AVERAGE_RATING);
    }

    /**
     * @inheritDoc
     */
    public function setAverageRating(float $rating): StoreRatingInterface
    {
        return $this->setData(self::KEY_AVERAGE_RATING, $rating);
    }
}