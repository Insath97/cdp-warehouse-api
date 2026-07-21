# Comprehensive Project Analysis: `cdp-warehouse-api`

---

## 1. Executive Summary & Architecture Overview

`cdp-warehouse-api` is an enterprise-grade **Paddy & Generic Warehouse Management System (GWMS) REST API** built using **Laravel 11**.

### Core Architecture & Technical Design:
- **Framework**: Laravel 11 (PHP 8.2+)
- **Authentication**: JWT Auth via `auth:api` (`tymon/jwt-auth`)
- **Role-Based Access Control (RBAC)**: Spatie Permissions (`spatie/laravel-permission`) with `HasMiddleware` and granular permission groups.
- **User Scope Scoping & Security**:
  - `User` model features `getAccessibleWarehouseIds()` returning accessible warehouse IDs based on user scope:
    - **`global`**: Super Admin / System Admin (access to all warehouses across all branches).
    - **`branch`**: Branch Manager / Supervisor (access to all warehouses belonging to their assigned `branch_id`).
    - **`warehouse`**: Warehouse Storekeeper / Operator (access restricted strictly to their assigned `warehouse_id`).
  - Auth responses (`login` & `me`) eager load `branch.warehouses` and `warehouse.branch`.
  - Controllers apply scope filtering and enforce `403 Forbidden` checks on write/read operations.
- **Audit Logging**: `ActivityLogTrait` automatically records user actions, IP addresses, request payloads, user agents, methods, and URLs into `activity_logs`.

---

## 2. Completed Modules Inventory (22 Controllers)

```
cdp-warehouse-api
├── Master Data & Organization
│   ├── CountryController.php (Countries master data)
│   ├── ProvinceController.php (Provinces master data)
│   ├── DistrictController.php (Districts master data)
│   ├── GroupController.php (Organization groups)
│   ├── BranchController.php (Branches with warehouses relation)
│   ├── DepartmentController.php (Departments master data)
│   ├── DesignationController.php (Designations master data)
│   ├── WarehouseController.php (Warehouses linked to branches)
│   ├── BankController.php (Banks master data)
│   ├── ItemTypeController.php (Paddy/Rice Item Types)
│   ├── ItemVarietyController.php (Varieties: Samba, Nadu, Keeri Samba, etc.)
│   └── SupplierController.php (Suppliers & Bank Accounts)
├── User Access & Security
│   ├── AuthController.php (Login, Me, Refresh, Logout with scope eager-loading)
│   ├── UserController.php (User management with global/branch/warehouse scope)
│   ├── RoleController.php (Roles management)
│   └── PermissionController.php (Permissions list & groups)
├── Logistics & Gate Management
│   ├── VehicleController.php (Vehicles registry with driver info, tare weight, creator tracking)
│   └── VehicleLogController.php (Gate Entry/Exit logs, license plate & vehicle image uploads, driver NIC)
├── Stock Intake & Inventory Management
│   ├── StockInBatchController.php (Batch headers & multi-variety line items, auto STK-YYYYMMDD-XXXX)
│   └── StockBagController.php (Bag-by-bag weighing & entry, batch auto-fill helper, sequential bag numbers, QR/Barcode generation, pricing calculations)
└── Quality Assurance & System Audit
    ├── QualityInspectionController.php (Quality tests for batches & bags, moisture %, grade A/B/C/reject, broken %, color quality, weight difference loss/gain tracking)
    └── ActivityLogController.php (Read-only audit trail search & filter API)
```

---

## 3. Database Schema Overview

### Key Database Tables:
1. **`users`**: User accounts with `user_scope` (`global`, `branch`, `warehouse`), `branch_id`, `warehouse_id`.
2. **`branches` & `warehouses`**: Organization structure (`branch_id` FK on `warehouses`).
3. **`item_types` & `item_varieties`**: Grain category and variety catalog.
4. **`vehicles` & `vehicle_logs`**: Transport vehicle registry and gate entry/exit logs (`logged_by` FK).
5. **`stock_in_batches` & `stock_in_batch_items`**: Stock intake header (supplier, vehicle, warehouse, total gross/net weight) and multi-item line items.
6. **`stock_bags`**: Individual bag registry (`bag_number` sequence per batch, `bag_code`, barcode, QR, bag weight, cost/sales pricing, `in_stock`/`dispatched`/`damaged`/`returned` status, `created_by` FK).
7. **`quality_inspections`**: Inspection parameters (`stock_in_batch_id`, `stock_bag_id`, `item_variety_id`, `original_weight`, `current_weight`, `weight_difference`, `weight_change_type`, `moisture_percentage`, `grade`, `broken_percentage`, `colour_quality`, `inspection_result`, `inspected_by` FK).
8. **`activity_logs`**: System-wide activity audit trail (`user_id`, `action`, `module`, `description`, `payload`, `level`, `ip_address`).

---

## 4. Postman API Collections

Stored in `API Doc/` directory:
- `activity_log_postman_collection.json`
- `stock_bag_postman_collection.json`
- `quality_inspection_postman_collection.json`
- `stock_in_batch_postman_collection.json`
- `vehicle_log_postman_collection.json`
- `vehicle_postman_collection.json`
- `warehouse_postman_collection.json`
- `supplier_postman_collection.json`
- `user_postman_collection.json`
- `item_variety_postman_collection.json`
- `item_type_postman_collection.json`
- `bank_postman_collection.json`

---

## 5. Recommended Next Logical Modules

To complete the end-to-end warehouse & inventory lifecycle, here are the top 4 module options you can choose from next:

### 🚚 Option 1: Stock Movement / Internal Transfer (`stock_transfers` & `stock_transfer_items`)
- Transferring stock bags or batches from **Source Warehouse -> Destination Warehouse**.
- Tracks transfer status (`pending`, `in_transit`, `received`, `partially_received`, `cancelled`).
- Transport vehicle assignment, exit gate reference, and destination warehouse receipt verification.

### 📦 Option 2: Stock Dispatch / Sales Outward (`stock_dispatches` & `dispatch_items`)
- Dispatching bags/batches for sales orders, customer delivery, or processing.
- Updates bag status from `in_stock` to `dispatched`.
- Records selling price, buyer details, delivery note / invoice reference, and gate exit integration.

### 📊 Option 3: Warehouse Inventory Balance & Summary Reports Module (`inventory_reports`)
- Real-time stock balance summary by Warehouse, Branch, Item Type, Item Variety, Grade, and Bag Count.
- Stock valuation (total cost value vs expected sales value).
- Stock aging and moisture drop alerts.

### 🌾 Option 4: Paddy Milling & Processing Module (`milling_batches`)
- Converting raw paddy batches into milled rice varieties.
- Tracking output products (milled rice, head rice, broken rice, rice bran, husk) and yield ratios.
