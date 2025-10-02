<?php
/**
 * Configuration Helper
 *
 * Provides easy access to module configuration values
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco
 * @license   Proprietary License
 */
declare(strict_types=1);

namespace Amadeco\ReviewWidget\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Config
 *
 * Helper for accessing module configuration
 */
class Config extends AbstractHelper
{
    /**
     * Configuration paths
     */
    private const XML_PATH_ENABLE_SCHEMA = 'catalog/review_widget/enable_schema_org';
    private const XML_PATH_MIN_RATING = 'catalog/review_widget/default_min_rating';
    private const XML_PATH_MIN_CHAR_LENGTH = 'catalog/review_widget/default_min_char_length';
    private const XML_PATH_PAGE_SIZE = 'catalog/review_widget/default_page_size';
    private const XML_PATH_CACHE_LIFETIME = 'catalog/review_widget/cache_lifetime';
    private const XML_PATH_LAZY_LOADING = 'catalog/review_widget/enable_lazy_loading';

    /**
     * Default values
     */
    private const DEFAULT_MIN_RATING = 3.5;
    private const DEFAULT_PAGE_SIZE = 10;
    private const DEFAULT_CACHE_LIFETIME = 86400;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * Check if Schema.org markup is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSchemaOrgEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_SCHEMA,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get default minimum rating
     *
     * @param int|null $storeId
     * @return float
     */
    public function getDefaultMinRating(?int $storeId = null): float
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_MIN_RATING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value ? (float) $value : self::DEFAULT_MIN_RATING;
    }

    /**
     * Get default minimum character length
     *
     * @param int|null $storeId
     * @return int
     */
    public function getDefaultMinCharLength(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MIN_CHAR_LENGTH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get default page size
     *
     * @param int|null $storeId
     * @return int
     */
    public function getDefaultPageSize(?int $storeId = null): int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_PAGE_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value ? (int) $value : self::DEFAULT_PAGE_SIZE;
    }

    /**
     * Get cache lifetime in seconds
     *
     * @param int|null $storeId
     * @return int
     */
    public function getCacheLifetime(?int $storeId = null): int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_CACHE_LIFETIME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value ? (int) $value : self::DEFAULT_CACHE_LIFETIME;
    }

    /**
     * Check if lazy loading is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLazyLoadingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_LAZY_LOADING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}