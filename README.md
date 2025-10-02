# Amadeco ReviewWidget - Magento 2 Module

![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)
![Magento](https://img.shields.io/badge/magento-2.4.8-orange.svg)
![PHP](https://img.shields.io/badge/php-8.3-purple.svg)

Professional module for advanced display of customer reviews with SEO and Rich Snippets support.

---

## 📋 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#️-configuration)
- [Usage](#-usage)
- [Architecture](#-architecture)
- [Customization](#-customization)
- [Performance](#-performance)
- [Support](#-support)

---

## ✨ Features

### 🎯 Key Features

- ✅ **2 Configurable Widgets**:
  - Global store rating badge
  - Customer reviews list with advanced filters

- ✅ **Advanced review filtering**:
  - By minimum rating (1-5 stars)
  - By minimum text length
  - By product category
  - By period (last X days)
  - Customizable sorting (recent, rating, random)

- ✅ **3 Display modes**:
  - **Carousel**: Horizontal scrolling with navigation
  - **Grid**: Responsive card layout
  - **List**: Detailed vertical display

- ✅ **SEO & Rich Snippets**:
  - Automatic Schema.org markup
  - AggregateRating support
  - Review markup support
  - Optimized meta tags

- ✅ **Optimized performance**:
  - Smart cache per store
  - Optional lazy loading
  - Automatic cache invalidation
  - Repository Pattern for data access

- ✅ **Professional architecture**:
  - Service Layer (business logic separation)
  - ViewModel Pattern (presentation separation)
  - API interfaces for extensibility
  - PSR-12 compliant code

---

## 🔧 Requirements

- **Magento**: 2.4.8+ (Community or Commerce Edition)
- **PHP**: 8.1, 8.2 or 8.3
- **Composer**: 2.x
- **Required Magento modules**:
  - `Magento_Review`
  - `Magento_Catalog`
  - `Magento_Widget`
  - `Magento_Store`

---

## 📦 Installation

### Installation via Composer (recommended)

```bash
# Add the module
composer require artbambou/magento2-module-review-widget

# Enable the module
php bin/magento module:enable Amadeco_ReviewWidget

# Run upgrade scripts
php bin/magento setup:upgrade

# Compile code (production mode)
php bin/magento setup:di:compile

# Deploy static content
php bin/magento setup:static-content:deploy fr_FR

# Flush caches
php bin/magento cache:flush