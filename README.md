# Amadeco ReviewWidget - Magento 2 Module

[![Latest Stable Version](https://img.shields.io/github/v/release/iparmentier/magento2-module-review-widget)](https://github.com/iparmentier/magento2-module-review-widget/releases)
[![Magento 2](https://img.shields.io/badge/Magento-2.4.x-brightgreen.svg)](https://magento.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://www.php.net)

[SPONSOR: Amadeco](https://www.amadeco.fr)

Professional module for advanced display of customer reviews with SEO and Rich Snippets support.

### Backend Screenshots

<img width="948" height="638" alt="Image" src="https://github.com/user-attachments/assets/4f80b519-962c-42f4-a791-4d3dbc0b23a5" />

<img width="2314" height="1882" alt="Image" src="https://github.com/user-attachments/assets/4e0a8397-48c8-49e2-986a-00f7d45e2dd5" />

### Frontend Screenshots

<img width="1199" height="635" alt="Image" src="https://github.com/user-attachments/assets/1844f263-e4b1-4908-9426-791785cd198b" />
<img width="666" height="80" alt="Image" src="https://github.com/user-attachments/assets/28586f53-c7f2-4951-92f3-bdf04043d476" />

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
