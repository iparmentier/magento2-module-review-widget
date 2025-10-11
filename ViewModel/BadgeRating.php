<?php
/**
 * Badge Rating ViewModel
 *
 * Provides data and logic for badge rating display
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\ViewModel;

use Amadeco\ReviewWidget\Api\Data\StoreRatingInterface;
use Amadeco\ReviewWidget\Api\RatingCalculatorInterface;
use Amadeco\ReviewWidget\Helper\Config;
use Amadeco\ReviewWidget\Helper\SchemaManager;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Class BadgeRating
 *
 * ViewModel for badge rating widget
 */
class BadgeRating implements ArgumentInterface
{
    /**
     * @param RatingCalculatorInterface $ratingCalculator
     * @param Config $config
     * @param SchemaManager $schemaManager
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly RatingCalculatorInterface $ratingCalculator,
        private readonly Config $config,
        private readonly SchemaManager $schemaManager,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Get store rating data
     *
     * @return StoreRatingInterface|null
     */
    public function getStoreRating(): ?StoreRatingInterface
    {
        return $this->ratingCalculator->getStoreRating();
    }

    /**
     * Prepare Schema.org data for output
     *
     * @return void
     */
    public function prepareSchemaData(): void
    {
        if (!$this->schemaManager->shouldGenerateSchema('badge')) {
            return;
        }

        $storeRating = $this->getStoreRating();
        if (!$storeRating || $storeRating->getTotal() === 0) {
            return;
        }

        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'AggregateRating',
            'ratingValue' => number_format($storeRating->getNote(), 2),
            'bestRating' => '5',
            'worstRating' => '1',
            'ratingCount' => $storeRating->getTotal()
        ];

        $this->schemaManager->setStoreRatingData($schemaData);
        $this->schemaManager->markBadgeSchemaAsGenerated();
    }

    /**
     * Get Schema.org JSON-LD output
     *
     * @return string
     */
    public function getSchemaJsonLd(): string
    {
        $data = $this->schemaManager->getStoreRatingData();
        if (!$data) {
            return '';
        }

        $json = $this->serializer->serialize($data);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * Format rating value for display
     *
     * @param float $rating
     * @param int $decimals
     * @return string
     */
    public function formatRating(float $rating, int $decimals = 2): string
    {
        return number_format($rating, $decimals, '.', '');
    }

    /**
     * Check if Schema.org is enabled
     *
     * @return bool
     */
    public function isSchemaOrgEnabled(): bool
    {
        return $this->config->isSchemaOrgEnabled();
    }
}