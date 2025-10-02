<?php
/**
 * Reviews List Widget Block
 *
 * Widget block for displaying customer reviews list with optimized caching
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Block\Widget;

use Amadeco\ReviewWidget\Helper\Config;
use Amadeco\ReviewWidget\Service\InputValidator;
use Amadeco\ReviewWidget\ViewModel\BadgeRating;
use Amadeco\ReviewWidget\ViewModel\ReviewsList;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ReviewsListWidget
 *
 * Widget for displaying filtered list of customer reviews with validation
 */
class ReviewsListWidget extends Template implements BlockInterface
{
    /**
     * Default template
     */
    protected $_template = 'Amadeco_ReviewWidget::widget/reviewslist/carousel.phtml';

    /**
     * Default configuration values
     */
    private const DEFAULT_SHOW_BADGE_RATING = false;

    /**
     * Cache configuration
     */
    private const CACHE_TAG_PREFIX = 'AMADECO_REVIEW_WIDGET_LIST';

    /**
     * Badge rating block instance
     *
     * @var BadgeStoreRatingWidget|null
     */
    private ?BadgeStoreRatingWidget $badgeRatingBlock = null;

    /**
     * @param Context $context
     * @param ReviewsList $reviewsListViewModel
     * @param BadgeRating $badgeRatingViewModel
     * @param Config $config
     * @param InputValidator $inputValidator
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ReviewsList $reviewsListViewModel,
        private readonly BadgeRating $badgeRatingViewModel,
        private readonly Config $config,
        private readonly InputValidator $inputValidator,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        parent::_construct();

        try {
            // Prepare and validate filters for view model
            $filters = $this->prepareAndValidateFilters();
            $this->reviewsListViewModel->setFilters($filters);

            $this->addData([
                'cache_lifetime' => $this->config->getCacheLifetime(),
                'cache_tags' => $this->getCacheTags(),
            ]);
        } catch (ValidatorException $e) {
            $this->logger->error(
                'Invalid widget configuration: ' . $e->getMessage(),
                [
                    'exception' => $e,
                    'widget_data' => $this->getData()
                ]
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getCacheKeyInfo(): array
    {
        $filters = $this->prepareFilters();

        return [
            self::CACHE_TAG_PREFIX,
            $this->_storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            $this->getTemplateFile(),
            hash('xxh3', $this->_serializer->serialize($filters)),
            $this->getShowBadgeRating() ? '1' : '0',
            $this->getBadgeRatingTemplate() ?: 'none'
        ];
    }

    /**
     * Get cache tags for this block
     *
     * @return array
     */
    public function getCacheTags(): array
    {
        return [
            \Magento\Review\Model\Review::CACHE_TAG,
            'AMADECO_REVIEW_WIDGET',
            self::CACHE_TAG_PREFIX . '_' . $this->_storeManager->getStore()->getId()
        ];
    }

    /**
     * Get reviews list view model
     *
     * @return ReviewsList
     */
    public function getReviewsListViewModel(): ReviewsList
    {
        return $this->reviewsListViewModel;
    }

    /**
     * Get badge rating view model
     *
     * @return BadgeRating
     */
    public function getBadgeRatingViewModel(): BadgeRating
    {
        return $this->badgeRatingViewModel;
    }

    /**
     * Check if badge rating should be displayed
     *
     * @return bool
     */
    public function getShowBadgeRating(): bool
    {
        if ($this->hasData('show_badge_rating')) {
            return (bool) $this->getData('show_badge_rating');
        }
        return self::DEFAULT_SHOW_BADGE_RATING;
    }

    /**
     * Get badge rating template
     *
     * @return string|null
     */
    public function getBadgeRatingTemplate(): ?string
    {
        return $this->getData('badge_rating_template')
            ? (string) $this->getData('badge_rating_template')
            : null;
    }

    /**
     * Get badge rating HTML
     *
     * @return string
     */
    public function getBadgeRatingHtml(): string
    {
        if (!$this->getShowBadgeRating()) {
            return '';
        }

        if ($this->badgeRatingBlock === null) {
            $this->badgeRatingBlock = $this->getLayout()->createBlock(
                BadgeStoreRatingWidget::class,
                'widget.badge.rating.' . uniqid('', true)
            );

            $this->badgeRatingBlock->setData('show_rating_summary', true);

            if ($template = $this->getBadgeRatingTemplate()) {
                $this->badgeRatingBlock->setTemplate($template);
            }
        }

        return $this->badgeRatingBlock->toHtml();
    }

    /**
     * Prepare filters array from widget configuration
     *
     * @return array
     */
    private function prepareFilters(): array
    {
        $filters = [];

        // Extract widget configuration data
        if ($this->hasData('min_rating')) {
            $filters['min_rating'] = $this->getData('min_rating');
        }

        if ($this->hasData('min_char_length')) {
            $filters['min_char_length'] = $this->getData('min_char_length');
        }

        if ($this->hasData('page_size')) {
            $filters['page_size'] = $this->getData('page_size');
        }

        if ($this->hasData('category_id')) {
            $filters['category_id'] = $this->getData('category_id');
        }

        if ($this->hasData('days_ago')) {
            $filters['days_ago'] = $this->getData('days_ago');
        }

        if ($this->hasData('sort_order')) {
            $filters['sort_order'] = $this->getData('sort_order');
        }

        return $filters;
    }

    /**
     * Prepare and validate filters
     *
     * @return array Validated filters
     * @throws ValidatorException
     */
    private function prepareAndValidateFilters(): array
    {
        $filters = $this->prepareFilters();
        return $this->inputValidator->validateFilters($filters);
    }

    /**
     * Get widget title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return trim((string) $this->getData('title'));
    }

    /**
     * Check if widget has title
     *
     * @return bool
     */
    public function hasTitle(): bool
    {
        return $this->getTitle() !== '';
    }

    /**
     * Render block HTML
     *
     * Only render if we have reviews
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        $reviewCollection = $this->reviewsListViewModel->getReviewCollection();

        if (!$reviewCollection || $reviewCollection->getSize() === 0) {
            return '';
        }

        return parent::_toHtml();
    }
}