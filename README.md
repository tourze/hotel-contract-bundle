# hotel-contract-bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/hotel-contract-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/hotel-contract-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/hotel-contract-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/hotel-contract-bundle)
[![License](https://img.shields.io/packagist/l/tourze/hotel-contract-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/hotel-contract-bundle)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)]
(https://codecov.io/gh/tourze/php-monorepo)

Hotel contract management bundle for Symfony applications, providing comprehensive hotel contract
management, inventory management, and price management features. Supports batch operations,
inventory warnings, and automatic synchronization.

This bundle integrates deeply with EasyAdmin for administration interface and provides
complete hotel contract lifecycle management including inventory tracking and price optimization.

## Table of Contents

- [Installation](#installation)
  - [System Requirements](#system-requirements)
  - [Composer Installation](#composer-installation)
- [Features](#features)
- [Usage](#usage)
  - [1. Register Bundle](#1-register-bundle)
  - [2. Configure Database](#2-configure-database)
  - [3. Admin Interface](#3-admin-interface)
- [Console Commands](#console-commands)
  - [app:inventory:check-warnings](#appinventorycheck-warnings)
- [Entity Reference](#entity-reference)
  - [HotelContract](#hotelcontract)
  - [DailyInventory](#dailyinventory)
  - [InventorySummary](#inventorysummary)
- [Enum Types](#enum-types)
  - [ContractStatusEnum](#contractstatusenum)
  - [ContractTypeEnum](#contracttypeenum)
  - [DailyInventoryStatusEnum](#dailyinventorystatusenum)
  - [InventorySummaryStatusEnum](#inventorysummarystatusenum)
- [Service Reference](#service-reference)
  - [ContractService](#contractservice)
  - [InventoryUpdateService](#inventoryupdateservice)
  - [InventorySummaryService](#inventorysummaryservice)
  - [InventoryWarningService](#inventorywarningservice)
  - [PriceManagementService](#pricemanagementservice)
  - [RoomTypeInventoryService](#roomtypeinventoryservice)
- [Development Guide](#development-guide)
  - [Architecture](#architecture)
  - [Custom Warning Rules](#custom-warning-rules)
  - [Batch Operations Example](#batch-operations-example)
- [Important Notes & Best Practices](#important-notes-best-practices)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [License](#license)
- [Authors](#authors)
- [References](#references)

## Installation

### System Requirements

- PHP >= 8.1
- Symfony >= 6.4
- Doctrine ORM >= 3.0

### Composer Installation

```bash
composer require tourze/hotel-contract-bundle
```

## Features

- **Contract Management**: Support for multiple contract types, including fixed price and bidding contracts
- **Inventory Management**: Manage hotel room inventory by room type and date
- **Price Management**: Support batch price adjustments, cost and selling price management
- **Inventory Warnings**: Automatically detect low inventory and send notifications
- **Batch Operations**: Support batch inventory creation and batch price updates
- **EasyAdmin Integration**: Complete admin interface provided

## Usage

### 1. Register Bundle

```php
// config/bundles.php
return [
    // ...
    Tourze\HotelContractBundle\HotelContractBundle::class => ['all' => true],
];
```

### 2. Configure Database

Run database migrations to create required table structures:

```bash
php bin/console doctrine:migrations:migrate
```

### 3. Admin Interface

The bundle provides EasyAdmin CRUD controllers for comprehensive management:
- **Hotel Contracts** (HotelContractCrudController): Manage contract lifecycle, status, and pricing
- **Room Type Inventory** (RoomTypeInventoryCrudController): Control daily inventory by room type
- **Inventory Summary** (InventorySummaryCrudController): View aggregated inventory statistics

## Console Commands

### app:inventory:check-warnings

Monitor inventory levels and send notification emails when thresholds are reached.

**Usage:**

```bash
# Check inventory warnings directly
php bin/console app:inventory:check-warnings

# Sync inventory statistics before checking
php bin/console app:inventory:check-warnings --sync

# Check inventory for specific date
php bin/console app:inventory:check-warnings --date=2024-12-31
```

**Options:**
- `--sync`: Sync inventory statistics data before checking
- `--date`: Check inventory for specific date (format: Y-m-d)

## Entity Reference

### HotelContract

Hotel contract entity, key fields:
- `contractNo`: Contract number
- `hotel`: Associated hotel
- `contractType`: Contract type (Fixed Price/Bidding)
- `startDate`: Contract start date
- `endDate`: Contract end date
- `totalRooms`: Total room count
- `totalAmount`: Contract total amount
- `status`: Contract status (Pending/Active/Terminated)
- `priority`: Priority

### DailyInventory

Daily inventory entity, key fields:
- `code`: Inventory code (unique identifier)
- `roomType`: Room type
- `hotel`: Hotel
- `date`: Date
- `contract`: Associated contract (optional)
- `costPrice`: Cost price (decimal)
- `sellingPrice`: Selling price (decimal)
- `profitRate`: Profit rate (decimal)
- `isReserved`: Is reserved (boolean)
- `status`: Inventory status (Available/Sold/Pending/Reserved/Disabled/Cancelled/Refunded)
- `priceAdjustReason`: Price adjustment reason (optional)
- `lastModifiedBy`: Last modified by user (optional)

### InventorySummary

Inventory summary entity, key fields:
- `hotel`: Hotel
- `roomType`: Room type
- `date`: Date
- `totalRooms`: Total room count
- `availableRooms`: Available room count
- `reservedRooms`: Reserved room count
- `soldRooms`: Sold room count
- `status`: Status (Normal/Warning/Sold Out)
- `lowestPrice`: Lowest price

## Enum Types

### ContractStatusEnum
- `PENDING`: Pending approval
- `ACTIVE`: Active
- `TERMINATED`: Terminated

### ContractTypeEnum
- `FIXED_PRICE`: Fixed price contract
- `BIDDING`: Bidding contract

### DailyInventoryStatusEnum
- `AVAILABLE`: Available
- `SOLD`: Sold
- `PENDING`: Pending
- `RESERVED`: Reserved
- `DISABLED`: Disabled
- `CANCELLED`: Cancelled
- `REFUNDED`: Refunded

### InventorySummaryStatusEnum
- `NORMAL`: Normal
- `WARNING`: Warning
- `SOLD_OUT`: Sold out

## Service Reference

### ContractService
Contract management service, provides contract creation, update, termination operations.

### InventoryUpdateService
Inventory update service, handles inventory increase/decrease, status changes.

### InventorySummaryService
Inventory summary service, automatically aggregates daily inventory data.

### InventoryWarningService
Inventory warning service, detects low inventory and sends notifications.

### PriceManagementService
Price management service, supports batch price adjustments.

### RoomTypeInventoryService
Room type inventory service, manages inventory operations for specific room types.

## Development Guide

### Architecture

The bundle follows Domain-Driven Design principles:
- **Entities**: Core business objects (HotelContract, DailyInventory, InventorySummary)
- **Repositories**: Data access layer with optimized queries
- **Services**: Business logic and operations
- **Controllers**: EasyAdmin integration and API endpoints
- **Commands**: Background tasks and maintenance operations

### Custom Warning Rules

```php
// Extend InventoryWarningService
class CustomInventoryWarningService extends InventoryWarningService
{
    protected function checkWarningCondition(InventorySummary $summary): bool
    {
        // Custom warning conditions
        return $summary->getAvailableRooms() < 5;
    }
}
```

### Batch Operations Example

```php
// Batch create inventory
$inventoryService->batchCreateInventory(
    $hotel,
    $roomType,
    $startDate,
    $endDate,
    $quantity
);

// Batch update prices
$priceService->batchUpdatePrices(
    $inventoryIds,
    $costPrice,
    $sellingPrice
);
```

## Important Notes & Best Practices

- **Contract Validation**: Date ranges cannot overlap - system enforces automatic validation
- **Data Integrity**: Inventory quantity changes automatically trigger statistics updates
- **Email Configuration**: Warning notifications require proper Symfony Mailer configuration
- **Performance**: Schedule batch operations during low-traffic periods
- **Maintenance**: Run regular inventory synchronization tasks via cron jobs
- **Bundle Dependencies**: Requires hotel-profile-bundle for hotel entity integration
- **Database Transactions**: Ensure proper database transaction handling
- **Monitoring**: Monitor inventory warning notifications for timely response

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version update history.

## Contributing

Issues and Pull Requests welcome. Please ensure:

1. Code follows PSR-12 coding standards
2. All tests must pass
3. New features require corresponding tests
4. Update relevant documentation

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) file for details.

## Authors

- Tourze Team

## References

- [Symfony Console Documentation](https://symfony.com/doc/current/console.html)
- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/orm.html)
- [EasyAdmin Documentation](https://symfony.com/bundles/EasyAdminBundle/current/index.html)