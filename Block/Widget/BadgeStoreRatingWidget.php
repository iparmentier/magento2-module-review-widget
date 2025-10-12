<?php
/**
 * Badge Store Rating Widget Block
 *
 * Widget block for displaying store rating badge with optimized caching
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
use Amadeco\ReviewWidget\ViewModel\BadgeRating;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;

/**
 * Class BadgeStoreRatingWidget
 *
 * Widget for displaying aggregated store rating with enhanced caching
 */
class BadgeStoreRatingWidget extends Template implements BlockInterface
{
    /**
     * Default configuration values
     */
    public const DEFAULT_SHOW_RATING_SUMMARY = true;

    /**
     * Cache configuration
     */
    public const CACHE_TAG_PREFIX = 'AMADECO_REVIEW_WIDGET_BADGE';

    /**
     * Default template path
     */
    protected $_template = 'Amadeco_ReviewWidget::widget/badge/block.phtml';

    /**
     * @param Context $context
     * @param BadgeRating $badgeRatingViewModel
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected readonly BadgeRating $badgeRatingViewModel,
        protected readonly Config $config,
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

        $this->addData([
            'cache_lifetime' => $this->config->getCacheLifetime(),
            'cache_tags' => $this->getCacheTags(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getCacheKeyInfo(): array
    {
        return [
            self::CACHE_TAG_PREFIX,
            $this->_storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            $this->getTemplateFile(),
            (int) $this->getShowRatingSummary(),
            $this->getCustomCssClass() ?: 'default'
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
            self::CACHE_TAG_PREFIX . $this->_storeManager->getStore()->getId()
        ];
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
     * Check if rating summary should be displayed
     *
     * @return bool
     */
    public function getShowRatingSummary(): bool
    {
        if ($this->hasData('show_rating_summary')) {
            return (bool) $this->getData('show_rating_summary');
        }
        return self::DEFAULT_SHOW_RATING_SUMMARY;
    }

    /**
     * Get custom CSS classes from widget configuration
     *
     * @return string
     */
    public function getCustomCssClass(): string
    {
        return trim((string) $this->getData('css_class'));
    }

    /**
     * Render block HTML
     *
     * Only render if we have rating data
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        $storeRating = $this->badgeRatingViewModel->getStoreRating();

        if (!$storeRating || $storeRating->getTotal() === 0) {
            return '';
        }

        return parent::_toHtml();
    }
}