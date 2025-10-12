<?php
/**
 * Invalid Filter Exception
 *
 * Thrown when widget filter validation fails
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Class InvalidFilterException
 *
 * Exception for invalid widget filter parameters
 */
class InvalidFilterException extends LocalizedException
{
    /**
     * Invalid rating value
     */
    public static function invalidRating(float $value, float $min, float $max): self
    {
        return new self(
            __('Invalid rating value %1. Must be between %2 and %3', $value, $min, $max)
        );
    }

    /**
     * Invalid character length
     */
    public static function invalidCharLength(int $value, int $min, int $max): self
    {
        return new self(
            __('Invalid character length %1. Must be between %2 and %3', $value, $min, $max)
        );
    }

    /**
     * Invalid page size
     */
    public static function invalidPageSize(int $value, int $min, int $max): self
    {
        return new self(
            __('Invalid page size %1. Must be between %2 and %3', $value, $min, $max)
        );
    }

    /**
     * Invalid category ID
     */
    public static function invalidCategoryId(int $value): self
    {
        return new self(
            __('Invalid category ID %1. Must be greater than 0', $value)
        );
    }

    /**
     * Invalid days ago value
     */
    public static function invalidDaysAgo(int $value, int $min, int $max): self
    {
        return new self(
            __('Invalid days ago value %1. Must be between %2 and %3', $value, $min, $max)
        );
    }

    /**
     * Invalid sort order
     */
    public static function invalidSortOrder(string $value, array $allowed): self
    {
        return new self(
            __('Invalid sort order "%1". Allowed values: %2', $value, implode(', ', $allowed))
        );
    }

    /**
     * Unknown filter type
     */
    public static function unknownFilter(string $filterType): self
    {
        return new self(
            __('Unknown filter type: %1', $filterType)
        );
    }
}