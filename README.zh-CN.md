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

酒店合同管理包，为 Symfony 应用提供完整的酒店合同管理、库存管理和价格管理功能。支持批量操作、库存预警和自动同步功能。

该包深度集成 EasyAdmin 管理界面，提供完整的酒店合同生命周期管理，包括库存跟踪和价格优化。

## 目录

- [安装](#安装)
  - [系统要求](#系统要求)
  - [Composer 安装](#composer-安装)
- [功能特性](#功能特性)
- [使用方法](#使用方法)
  - [1. 注册 Bundle](#1-注册-bundle)
  - [2. 配置数据库](#2-配置数据库)
  - [3. 后台管理](#3-后台管理)
- [控制台命令](#控制台命令)
  - [app:inventory:check-warnings](#appinventorycheck-warnings)
- [实体说明](#实体说明)
  - [HotelContract](#hotelcontract)
  - [DailyInventory](#dailyinventory)
  - [InventorySummary](#inventorysummary)
- [枚举类型](#枚举类型)
  - [ContractStatusEnum](#contractstatusenum)
  - [ContractTypeEnum](#contracttypeenum)
  - [DailyInventoryStatusEnum](#dailyinventorystatusenum)
  - [InventorySummaryStatusEnum](#inventorysummarystatusenum)
- [服务说明](#服务说明)
  - [ContractService](#contractservice)
  - [InventoryUpdateService](#inventoryupdateservice)
  - [InventorySummaryService](#inventorysummaryservice)
  - [InventoryWarningService](#inventorywarningservice)
  - [PriceManagementService](#pricemanagementservice)
  - [RoomTypeInventoryService](#roomtypeinventoryservice)
- [开发指南](#开发指南)
  - [架构设计](#架构设计)
  - [自定义库存预警规则](#自定义库存预警规则)
  - [批量操作示例](#批量操作示例)
- [重要说明与最佳实践](#重要说明与最佳实践)
- [更新日志](#更新日志)
- [贡献指南](#贡献指南)
- [许可证](#许可证)
- [作者](#作者)
- [参考文档](#参考文档)

## 安装

### 系统要求

- PHP >= 8.1
- Symfony >= 6.4
- Doctrine ORM >= 3.0

### Composer 安装

```bash
composer require tourze/hotel-contract-bundle
```

## 功能特性

- **合同管理**：支持多种合同类型，包括固定价格合同和竞价合同
- **库存管理**：按房型和日期管理酒店房间库存
- **价格管理**：支持批量价格调整，成本价和销售价管理
- **库存预警**：自动检测库存不足并发送通知
- **批量操作**：支持批量创建库存、批量价格更新
- **EasyAdmin 集成**：提供完善的后台管理界面

## 使用方法

### 1. 注册 Bundle

```php
// config/bundles.php
return [
    // ...
    Tourze\HotelContractBundle\HotelContractBundle::class => ['all' => true],
];
```

### 2. 配置数据库

运行数据库迁移以创建所需的表结构：

```bash
php bin/console doctrine:migrations:migrate
```

### 3. 后台管理

该包提供 EasyAdmin CRUD 控制器用于综合管理：
- **酒店合同** (HotelContractCrudController)：管理合同生命周期、状态和定价
- **房型库存** (RoomTypeInventoryCrudController)：按房型控制每日库存
- **库存统计** (InventorySummaryCrudController)：查看聚合库存统计信息

## 控制台命令

### app:inventory:check-warnings

监控库存水平并在达到阈值时发送通知邮件。

**使用方法：**

```bash
# 直接检查库存预警
php bin/console app:inventory:check-warnings

# 检查前先同步库存统计数据
php bin/console app:inventory:check-warnings --sync

# 检查特定日期的库存
php bin/console app:inventory:check-warnings --date=2024-12-31
```

**选项说明：**
- `--sync`：在检查前同步库存统计数据
- `--date`：指定检查特定日期的库存（格式：Y-m-d）

## 实体说明

### HotelContract

酒店合同实体，核心字段：
- `contractNo`：合同编号
- `hotel`：关联酒店
- `contractType`：合同类型（固定价格/竞价）
- `startDate`：合同开始日期
- `endDate`：合同结束日期
- `totalRooms`：总房间数
- `totalAmount`：合同总金额
- `status`：合同状态（待审批/生效/终止）
- `priority`：优先级

### DailyInventory

每日库存实体，核心字段：
- `code`：库存编码（唯一标识符）
- `roomType`：房型
- `hotel`：酒店
- `date`：日期
- `contract`：关联合同（可选）
- `costPrice`：成本价（小数）
- `sellingPrice`：销售价（小数）
- `profitRate`：利润率（小数）
- `isReserved`：是否已预留（布尔值）
- `status`：库存状态（可用/已售/待确认/预留/禁用/已取消/已退款）
- `priceAdjustReason`：价格调整原因（可选）
- `lastModifiedBy`：最后修改人（可选）

### InventorySummary

库存统计实体，核心字段：
- `hotel`：酒店
- `roomType`：房型
- `date`：日期
- `totalRooms`：总房间数
- `availableRooms`：可用房间数
- `reservedRooms`：预留房间数
- `soldRooms`：已售房间数
- `status`：状态（正常/预警/售罄）
- `lowestPrice`：最低价格

## 枚举类型

### ContractStatusEnum
- `PENDING`：待审批
- `ACTIVE`：生效
- `TERMINATED`：终止

### ContractTypeEnum
- `FIXED_PRICE`：固定价格合同
- `BIDDING`：竞价合同

### DailyInventoryStatusEnum
- `AVAILABLE`：可用
- `SOLD`：已售
- `PENDING`：待确认
- `RESERVED`：预留
- `DISABLED`：禁用
- `CANCELLED`：已取消
- `REFUNDED`：已退款

### InventorySummaryStatusEnum
- `NORMAL`：正常
- `WARNING`：预警
- `SOLD_OUT`：售罄

## 服务说明

### ContractService
合同管理服务，提供合同的创建、更新、终止等操作。

### InventoryUpdateService
库存更新服务，处理库存的增减、状态变更等操作。

### InventorySummaryService
库存统计服务，自动汇总每日库存数据。

### InventoryWarningService
库存预警服务，检测库存不足并发送通知。

### PriceManagementService
价格管理服务，支持批量价格调整。

### RoomTypeInventoryService
房型库存服务，管理特定房型的库存操作。

## 开发指南

### 架构设计

该包遵循领域驱动设计原则：
- **实体**：核心业务对象 (HotelContract、DailyInventory、InventorySummary)
- **仓储**：数据访问层，包含优化查询
- **服务**：业务逻辑和操作
- **控制器**：EasyAdmin 集成和 API 端点
- **命令**：后台任务和维护操作

### 自定义库存预警规则

```php
// 扩展 InventoryWarningService
class CustomInventoryWarningService extends InventoryWarningService
{
    protected function checkWarningCondition(InventorySummary $summary): bool
    {
        // 自定义预警条件
        return $summary->getAvailableRooms() < 5;
    }
}
```

### 批量操作示例

```php
// 批量创建库存
$inventoryService->batchCreateInventory(
    $hotel,
    $roomType,
    $startDate,
    $endDate,
    $quantity
);

// 批量更新价格
$priceService->batchUpdatePrices(
    $inventoryIds,
    $costPrice,
    $sellingPrice
);
```

## 重要说明与最佳实践

- **合同验证**：日期范围不能重叠 - 系统强制自动验证
- **数据完整性**：库存数量变更自动触发统计数据更新
- **邮件配置**：预警通知需要正确的 Symfony Mailer 配置
- **性能优化**：在低流量时段安排批量操作
- **维护任务**：通过 cron 作业运行定期库存同步任务
- **包依赖**：需要 hotel-profile-bundle 进行酒店实体集成
- **数据库事务**：确保数据库事务正确处理
- **监控预警**：监控库存预警通知，确保及时响应

## 更新日志

请查看 [CHANGELOG.md](CHANGELOG.md) 了解版本更新记录。

## 贡献指南

欢迎提交 Issue 和 Pull Request。请确保：

1. 代码遵循 PSR-12 编码规范
2. 所有测试必须通过
3. 新功能需要编写相应的测试
4. 更新相关文档

## 许可证

本项目采用 MIT 许可证。详情请参阅 [LICENSE](LICENSE) 文件。

## 作者

- Tourze Team

## 参考文档

- [Symfony Console 文档](https://symfony.com/doc/current/console.html)
- [Doctrine ORM 文档](https://www.doctrine-project.org/projects/orm.html)
- [EasyAdmin 文档](https://symfony.com/bundles/EasyAdminBundle/current/index.html)
