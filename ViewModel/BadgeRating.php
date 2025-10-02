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
use Psr\Log\LoggerInterface;

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
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RatingCalculatorInterface $ratingCalculator,
        private readonly Config $config,
        private readonly SchemaManager $schemaManager,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get store rating data
     *
     * @return StoreRatingInterface|null
     */
    public function getStoreRating(): ?StoreRatingInterface
    {
        try {
            return $this->ratingCalculator->getStoreRating();
        } catch (\Exception $e) {
            $this->logger->error(
                'Error in BadgeRating ViewModel: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return null;
        }
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

        try {
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
        } catch (\Exception $e) {
            $this->logger->error(
                'Error preparing Schema.org data: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
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

        try {
            $json = $this->serializer->serialize($data);
            return '<script type="application/ld+json">' . $json . '</script>';
        } catch (\Exception $e) {
            $this->logger->error(
                'Error generating Schema.org JSON-LD: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return '';
        }
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