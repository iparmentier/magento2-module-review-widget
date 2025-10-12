<?php
/**
 * Schema.org Manager Service
 *
 * Manages Schema.org data generation to prevent duplicates on the same page
 * Uses non-shared service instance for request-scoped state management
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Helper;

/**
 * Class SchemaManager
 *
 * Ensures only one Schema.org markup is generated per page
 *
 * This service is configured as non-shared (shared="false" in di.xml)
 * which means Magento creates a new instance for each HTTP request.
 * This is the recommended Magento 2 approach for request-scoped services.
 *
 * @see etc/di.xml for the shared="false" configuration
 */
class SchemaManager
{
    /**
     * Flags to track if schema has been generated in current request
     * Safe to use instance properties because service is non-shared
     *
     * @var bool
     */
    protected bool $badgeSchemaGenerated = false;

    /**
     * @var bool
     */
    protected bool $reviewsSchemaGenerated = false;

    /**
     * Combined reviews data from all widgets on the page
     *
     * @var array
     */
    protected array $combinedReviews = [];

    /**
     * Store rating data for schema
     *
     * @var array|null
     */
    protected ?array $storeRatingData = null;

    /**
     * @param Config $config
     */
    public function __construct(
        protected readonly Config $config
    ) {
    }

    /**
     * Check if Schema.org is enabled in configuration
     *
     * @return bool
     */
    public function isSchemaEnabled(): bool
    {
        return $this->config->isSchemaOrgEnabled();
    }

    /**
     * Check if badge schema has already been generated on this page
     *
     * @return bool
     */
    public function isBadgeSchemaGenerated(): bool
    {
        return $this->badgeSchemaGenerated;
    }

    /**
     * Mark badge schema as generated for this page
     *
     * @return void
     */
    public function markBadgeSchemaAsGenerated(): void
    {
        $this->badgeSchemaGenerated = true;
    }

    /**
     * Check if reviews schema has already been generated on this page
     *
     * @return bool
     */
    public function isReviewsSchemaGenerated(): bool
    {
        return $this->reviewsSchemaGenerated;
    }

    /**
     * Mark reviews schema as generated for this page
     *
     * @return void
     */
    public function markReviewsSchemaAsGenerated(): void
    {
        $this->reviewsSchemaGenerated = true;
    }

    /**
     * Set store rating data for schema generation
     *
     * @param array $data
     * @return void
     */
    public function setStoreRatingData(array $data): void
    {
        if ($this->storeRatingData === null) {
            $this->storeRatingData = $data;
        }
    }

    /**
     * Get store rating data
     *
     * @return array|null
     */
    public function getStoreRatingData(): ?array
    {
        return $this->storeRatingData;
    }

    /**
     * Add review data to combined collection
     *
     * Allows multiple widgets to contribute reviews to a single schema output
     *
     * @param array $reviewData Review data in Schema.org format
     * @return void
     */
    public function addReviewToCombined(array $reviewData): void
    {
        $reviewHash = $this->createReviewHash($reviewData);

        if (!isset($this->combinedReviews[$reviewHash])) {
            $this->combinedReviews[$reviewHash] = $reviewData;
        }
    }

    /**
     * Get all combined review data
     *
     * @return array Array of review data without hash keys
     */
    public function getCombinedReviews(): array
    {
        return array_values($this->combinedReviews);
    }

    /**
     * Check if we should generate schema for this widget instance
     *
     * @param string $schemaType Type of schema: 'badge' or 'reviews'
     * @return bool
     */
    public function shouldGenerateSchema(string $schemaType): bool
    {
        if (!$this->isSchemaEnabled()) {
            return false;
        }

        return match ($schemaType) {
            'badge' => !$this->isBadgeSchemaGenerated(),
            'reviews' => !$this->isReviewsSchemaGenerated(),
            default => false
        };
    }

    /**
     * Create unique hash for review data
     *
     * @param array $reviewData
     * @return string
     */
    private function createReviewHash(array $reviewData): string
    {
        $hashData = [
            $reviewData['author']['name'] ?? '',
            $reviewData['datePublished'] ?? '',
            $reviewData['reviewBody'] ?? ''
        ];

        return hash('xxh128', json_encode($hashData));
    }
}