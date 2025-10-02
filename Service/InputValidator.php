<?php
/**
 * Input Validator Service
 *
 * Validates and sanitizes widget configuration inputs
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Service;

use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Phrase;

/**
 * Class InputValidator
 *
 * Provides validation methods for widget inputs to ensure data integrity and security
 */
class InputValidator
{
    /**
     * Validation constraints
     */
    private const MIN_RATING = 1.0;
    private const MAX_RATING = 5.0;
    private const MIN_CHAR_LENGTH = 0;
    private const MAX_CHAR_LENGTH = 10000;
    private const MIN_PAGE_SIZE = 1;
    private const MAX_PAGE_SIZE = 100;
    private const MIN_DAYS_AGO = 1;
    private const MAX_DAYS_AGO = 3650; // ~10 years
    private const MIN_CATEGORY_ID = 1;

    /**
     * Allowed sort orders
     */
    private const ALLOWED_SORT_ORDERS = ['recent', 'rating', 'random'];

    /**
     * Validate rating value
     *
     * @param float|string|null $rating
     * @return float|null
     * @throws ValidatorException
     */
    public function validateRating(float|string|null $rating): ?float
    {
        if ($rating === null || $rating === '') {
            return null;
        }

        $rating = (float) $rating;

        if ($rating < self::MIN_RATING || $rating > self::MAX_RATING) {
            throw new ValidatorException(
                new Phrase(
                    'Rating must be between %1 and %2',
                    [self::MIN_RATING, self::MAX_RATING]
                )
            );
        }

        return $rating;
    }

    /**
     * Validate character length
     *
     * @param int|string|null $length
     * @return int|null
     * @throws ValidatorException
     */
    public function validateCharLength(int|string|null $length): ?int
    {
        if ($length === null || $length === '') {
            return null;
        }

        $length = (int) $length;

        if ($length < self::MIN_CHAR_LENGTH || $length > self::MAX_CHAR_LENGTH) {
            throw new ValidatorException(
                new Phrase(
                    'Character length must be between %1 and %2',
                    [self::MIN_CHAR_LENGTH, self::MAX_CHAR_LENGTH]
                )
            );
        }

        return $length;
    }

    /**
     * Validate page size
     *
     * @param int|string|null $pageSize
     * @return int|null
     * @throws ValidatorException
     */
    public function validatePageSize(int|string|null $pageSize): ?int
    {
        if ($pageSize === null || $pageSize === '') {
            return null;
        }

        $pageSize = (int) $pageSize;

        if ($pageSize < self::MIN_PAGE_SIZE || $pageSize > self::MAX_PAGE_SIZE) {
            throw new ValidatorException(
                new Phrase(
                    'Page size must be between %1 and %2',
                    [self::MIN_PAGE_SIZE, self::MAX_PAGE_SIZE]
                )
            );
        }

        return $pageSize;
    }

    /**
     * Validate category ID
     *
     * @param int|string|null $categoryId
     * @return int|null
     * @throws ValidatorException
     */
    public function validateCategoryId(int|string|null $categoryId): ?int
    {
        if ($categoryId === null || $categoryId === '') {
            return null;
        }

        $categoryId = (int) $categoryId;

        if ($categoryId < self::MIN_CATEGORY_ID) {
            throw new ValidatorException(
                new Phrase('Category ID must be a positive integer')
            );
        }

        return $categoryId;
    }

    /**
     * Validate days ago
     *
     * @param int|string|null $daysAgo
     * @return int|null
     * @throws ValidatorException
     */
    public function validateDaysAgo(int|string|null $daysAgo): ?int
    {
        if ($daysAgo === null || $daysAgo === '') {
            return null;
        }

        $daysAgo = (int) $daysAgo;

        if ($daysAgo < self::MIN_DAYS_AGO || $daysAgo > self::MAX_DAYS_AGO) {
            throw new ValidatorException(
                new Phrase(
                    'Days ago must be between %1 and %2',
                    [self::MIN_DAYS_AGO, self::MAX_DAYS_AGO]
                )
            );
        }

        return $daysAgo;
    }

    /**
     * Validate sort order
     *
     * @param string|null $sortOrder
     * @return string|null
     * @throws ValidatorException
     */
    public function validateSortOrder(?string $sortOrder): ?string
    {
        if ($sortOrder === null || $sortOrder === '') {
            return null;
        }

        $sortOrder = strtolower(trim($sortOrder));

        if (!in_array($sortOrder, self::ALLOWED_SORT_ORDERS, true)) {
            throw new ValidatorException(
                new Phrase(
                    'Sort order must be one of: %1',
                    [implode(', ', self::ALLOWED_SORT_ORDERS)]
                )
            );
        }

        return $sortOrder;
    }

    /**
     * Validate and sanitize all filters at once
     *
     * @param array $filters Raw filter array from widget configuration
     * @return array Validated and sanitized filters
     * @throws ValidatorException
     */
    public function validateFilters(array $filters): array
    {
        $validated = [];

        if (isset($filters['min_rating'])) {
            $validated['min_rating'] = $this->validateRating($filters['min_rating']);
        }

        if (isset($filters['min_char_length'])) {
            $validated['min_char_length'] = $this->validateCharLength($filters['min_char_length']);
        }

        if (isset($filters['page_size'])) {
            $validated['page_size'] = $this->validatePageSize($filters['page_size']);
        }

        if (isset($filters['category_id'])) {
            $validated['category_id'] = $this->validateCategoryId($filters['category_id']);
        }

        if (isset($filters['days_ago'])) {
            $validated['days_ago'] = $this->validateDaysAgo($filters['days_ago']);
        }

        if (isset($filters['sort_order'])) {
            $validated['sort_order'] = $this->validateSortOrder($filters['sort_order']);
        }

        // Remove null values
        return array_filter($validated, fn($value) => $value !== null);
    }

    /**
     * Get validation constraints for documentation/testing
     *
     * @return array
     */
    public function getConstraints(): array
    {
        return [
            'rating' => [
                'min' => self::MIN_RATING,
                'max' => self::MAX_RATING
            ],
            'char_length' => [
                'min' => self::MIN_CHAR_LENGTH,
                'max' => self::MAX_CHAR_LENGTH
            ],
            'page_size' => [
                'min' => self::MIN_PAGE_SIZE,
                'max' => self::MAX_PAGE_SIZE
            ],
            'days_ago' => [
                'min' => self::MIN_DAYS_AGO,
                'max' => self::MAX_DAYS_AGO
            ],
            'category_id' => [
                'min' => self::MIN_CATEGORY_ID
            ],
            'sort_orders' => self::ALLOWED_SORT_ORDERS
        ];
    }
}