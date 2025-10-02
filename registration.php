<?php
/**
 * Amadeco ReviewWidget Module Registration
 *
 * @category  Amadeco
 * @package   Amadeco_ReviewWidget
 * @author    Ilan Parmentier <contact@amadeco.fr>
 * @copyright Copyright (c) 2025 Amadeco (https://www.amadeco.fr)
 * @license   Proprietary License
 */
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Amadeco_ReviewWidget',
    __DIR__
);