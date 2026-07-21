# CDP Paddy Warehouse Management System (PWMS)

## Complete System Analysis & Technical Documentation

---

## 1. System Overview

The **CDP Paddy Warehouse Management System (PWMS)** is a comprehensive, end-to-end digital platform that centralises warehouse operations, inventory control, weighing processes, batch management, bag-level traceability, barcode and QR code management, supplier management, financial accounting, and reporting within a single integrated API backend.

The system covers the complete lifecycle of paddy — from the moment a supplier delivers to the warehouse, through storage, to the final dispatch or sale — with full traceability, accountability, and financial visibility at every step.

---

## 2. Module Summary

| #   | Module                        | Description                                                                |
| --- | ----------------------------- | -------------------------------------------------------------------------- |
| 1   | Master Data                   | Countries, Provinces, Districts, Branches, Departments, Item Types, Groups |
| 2   | Supplier Management           | Supplier profiles, bank info, payment terms, ledger, outstanding balance   |
| 3   | Vehicle Management            | Vehicle registration, driver details, IN/OUT logs                          |
| 4   | Weighbridge / Weighing        | Lorry gross/tare weight, net weight auto-calculation, bag-level weights    |
| 5   | Quality Inspection            | Paddy grade, moisture, foreign matter, remarks per batch                   |
| 6   | Paddy Batch Management        | Batch creation, unique batch number, full batch lifecycle management       |
| 7   | Paddy Bag Management          | Individual bag registration, bag number, weight, storage location          |
| 8   | Barcode & QR Code             | Unique code per bag, QR generation, scan-to-detail endpoint                |
| 9   | Warehouse Location Management | Warehouse → Section → Row → Rack → Shelf mapping, bag-location assignment  |
| 10  | Inventory Management          | Real-time stock balances, stock-in/out/transfer/adjustment/damage/return   |
| 11  | Dispatch Operations           | Batch or bag-level dispatch, vehicle loading, dispatch history             |
| 12  | Financial & Accounting        | Costs per batch, chart of accounts, journal entries, supplier payments     |
| 13  | Audit Log                     | Every user action logged with user, datetime, IP, action                   |
| 14  | Reporting & Analytics         | Stock, weight, financial, supplier, vehicle, batch, bag reports            |

---

## 3. Complete Database Schema

---

### 3.1 Suppliers Table (`suppliers`)

| Column              | Type          | Notes                                 |
| ------------------- | ------------- | ------------------------------------- |
| id                  | bigint PK     |                                       |
| code                | string(50)    | Unique supplier code (e.g., SUP-0001) |
| name                | string(255)   | Full name                             |
| phone_primary       | string(20)    |                                       |
| phone_secondary     | string(20)    | Nullable                              |
| email               | string(255)   | Nullable                              |
| address_line1       | string(255)   |                                       |
| address_line2       | string(255)   | Nullable                              |
| city                | string(100)   |                                       |
| district_id         | FK            | References districts                  |
| nic_number          | string(20)    | Nullable, unique                      |
| payment_terms       | enum          | immediate, net_7, net_15, net_30      |
| outstanding_balance | decimal(15,2) | Running balance                       |
| notes               | text          | Nullable                              |
| is_active           | boolean       | Default true                          |
| timestamps          |               |                                       |

> **Bank details are managed separately** — see `supplier_bank_accounts` table below. One supplier can have many bank accounts.

---

### 3.2 Supplier Bank Accounts Table (`supplier_bank_accounts`)

| Column            | Type        | Notes                                         |
| ----------------- | ----------- | --------------------------------------------- |
| id                | bigint PK   |                                               |
| supplier_id       | FK          | References suppliers                          |
| bank_name         | string(100) |                                               |
| bank_account_no   | string(50)  | Unique per supplier                           |
| bank_branch       | string(100) |                                               |
| bank_account_name | string(255) | Account holder name                           |
| account_type      | enum        | savings, current, fixed_deposit               |
| is_primary        | boolean     | Only one primary per supplier (default false) |
| is_active         | boolean     | Default true                                  |
| notes             | text        | Nullable                                      |
| timestamps        |             |                                               |

---

### 3.3 Vehicles Table (`vehicles`)

| Column         | Type          | Notes                                    |
| -------------- | ------------- | ---------------------------------------- |
| id             | bigint PK     |                                          |
| vehicle_number | string(20)    | Unique plate number                      |
| driver_name    | string(255)   | Nullable                                 |
| driver_phone   | string(20)    | Nullable                                 |
| driver_nic     | string(20)    | Nullable                                 |
| vehicle_type   | enum          | lorry, pickup, van, tractor, other       |
| tare_weight    | decimal(10,2) | Default known tare weight (kg), nullable |
| is_active      | boolean       |                                          |
| timestamps     |               |                                          |

---

### 3.4 Vehicle Logs Table (`vehicle_logs`)

| Column       | Type        | Notes                              |
| ------------ | ----------- | ---------------------------------- |
| id           | bigint PK   |                                    |
| log_number   | string(50)  | Unique auto-generated reference    |
| vehicle_id   | FK          | References vehicles                |
| batch_id     | FK          | References paddy_batches, nullable |
| direction    | enum        | in, out                            |
| entry_time   | datetime    | Vehicle entry datetime             |
| exit_time    | datetime    | Vehicle exit datetime, nullable    |
| driver_name  | string(255) | Driver at time of entry            |
| driver_phone | string(20)  | Nullable                           |
| purpose      | string(255) | Purpose of entry                   |
| notes        | text        | Nullable                           |
| logged_by    | FK (users)  | User who created the log           |
| timestamps   |             |                                    |

---

### 3.5 Weighing Records Table (`weighing_records`)

| Column          | Type          | Notes                                       |
| --------------- | ------------- | ------------------------------------------- |
| id              | bigint PK     |                                             |
| batch_id        | FK            | References paddy_batches                    |
| bag_id          | FK            | References paddy_bags (nullable for lorry)  |
| weigh_type      | enum          | lorry_gross, lorry_tare, bag                |
| measured_weight | decimal(10,2) | Weight in kg                                |
| measured_at     | datetime      | Date and time of measurement                |
| measured_by     | FK (users)    | Who recorded this weight                    |
| scale_reference | string(100)   | Weighbridge/scale ID or reference, nullable |
| notes           | text          | Nullable                                    |
| timestamps      |               |                                             |

---

### 3.6 Quality Inspections Table (`quality_inspections`)

| Column              | Type         | Notes                                     |
| ------------------- | ------------ | ----------------------------------------- |
| id                  | bigint PK    |                                           |
| batch_id            | FK           | References paddy_batches (unique, 1-to-1) |
| paddy_variety       | string(100)  | e.g., Samba, Nadu, Keeri Samba            |
| moisture_percentage | decimal(5,2) | e.g., 13.50                               |
| grade               | enum         | A, B, C, reject                           |
| foreign_materials   | decimal(5,2) | Percentage of foreign matter              |
| broken_percentage   | decimal(5,2) | Nullable                                  |  
| colour_quality      | enum         | good, acceptable, poor, nullable          |
| remarks             | text         | Quality notes                             |
| inspection_result   | enum         | approved, conditional, rejected           |
| inspected_by        | FK (users)   | Inspector                                 |
| inspected_at        | datetime     |                                           |
| timestamps          |              |                                           |
~!2


### 3.7 Paddy Batches Table (`paddy_batches`)

| Column           | Type          | Notes                                                          |
| ---------------- | ------------- | -------------------------------------------------------------- |
| id               | bigint PK     |                                                                |
| batch_number     | string(50)    | Unique auto-generated (e.g., BATCH-2026-0001)                  |
| supplier_id      | FK            | References suppliers                                           |
| vehicle_id       | FK            | References vehicles                                            |
| branch_id        | FK            | References branches (receiving warehouse)                      |
| item_type_id     | FK            | References item_types (paddy variety/type)                     |
| arrival_date     | date          |                                                                |
| purchase_price   | decimal(10,2) | Price per kg                                                   |
| gross_weight     | decimal(10,2) | Vehicle gross weight (kg)                                      |
| tare_weight      | decimal(10,2) | Vehicle tare weight (kg)                                       |
| net_weight       | decimal(10,2) | Auto-calculated: gross - tare                                  |
| total_bags       | integer       | Expected bags on delivery                                      |
| actual_bags      | integer       | Actual bags counted after unloading                            |
| total_bag_weight | decimal(10,2) | Sum of all individual bag weights                              |
| total_amount     | decimal(15,2) | net_weight \* purchase_price                                   |
| status           | enum          | pending, inspecting, approved, in_stock, dispatched, cancelled |
| notes            | text          | Nullable                                                       |
| created_by       | FK (users)    |                                                                |
| timestamps       |               |                                                                |

---

### 3.8 Warehouse Locations Table (`warehouse_locations`)

| Column     | Type        | Notes                                      |
| ---------- | ----------- | ------------------------------------------ |
| id         | bigint PK   |                                            |
| code       | string(50)  | Unique location code (e.g., WH-A-R1-S1)    |
| name       | string(255) | Human-readable name                        |
| type       | enum        | warehouse, section, row, rack, shelf, area |
| parent_id  | FK (self)   | Parent location (nullable for top-level)   |
| branch_id  | FK          | References branches                        |
| capacity   | integer     | Max bags this location can hold, nullable  |
| is_active  | boolean     |                                            |
| timestamps |             |                                            |

---

### 3.9 Paddy Bags Table (`paddy_bags`)

| Column       | Type          | Notes                                                |
| ------------ | ------------- | ---------------------------------------------------- |
| id           | bigint PK     |                                                      |
| bag_code     | string(50)    | Unique auto-generated code (barcode/QR value)        |
| batch_id     | FK            | References paddy_batches                             |
| supplier_id  | FK            | References suppliers (denormalised)                  |
| item_type_id | FK            | References item_types                                |
| bag_number   | integer       | Sequence within batch (e.g., 1, 2, 3…)               |
| bag_weight   | decimal(10,2) | Actual bag weight (kg)                               |
| location_id  | FK            | References warehouse_locations, nullable             |
| status       | enum          | in_stock, dispatched, transferred, damaged, returned |
| qr_code_path | string(500)   | File path to generated QR code image                 |
| barcode_path | string(500)   | File path to generated barcode image                 |
| timestamps   |               |                                                      |

---

### 3.10 Bag Movement History Table (`bag_movements`)

| Column           | Type       | Notes                                       |
| ---------------- | ---------- | ------------------------------------------- |
| id               | bigint PK  |                                             |
| bag_id           | FK         | References paddy_bags                       |
| from_location_id | FK         | References warehouse_locations, nullable    |
| to_location_id   | FK         | References warehouse_locations, nullable    |
| movement_type    | enum       | storage_in, storage_out, transfer, dispatch |
| moved_by         | FK (users) | User performing movement                    |
| moved_at         | datetime   |                                             |
| notes            | text       | Nullable                                    |
| timestamps       |            |                                             |

---

### 3.11 Stock Transactions Table (`stock_transactions`)

| Column             | Type          | Notes                                                     |
| ------------------ | ------------- | --------------------------------------------------------- |
| id                 | bigint PK     |                                                           |
| transaction_number | string(50)    | Unique auto-generated ref (e.g., TXN-2026-00001)          |
| transaction_type   | enum          | stock_in, stock_out, transfer, adjustment, damage, return |
| batch_id           | FK            | References paddy_batches, nullable                        |
| item_type_id       | FK            | References item_types                                     |
| supplier_id        | FK            | References suppliers, nullable                            |
| from_location_id   | FK            | References warehouse_locations, nullable                  |
| to_location_id     | FK            | References warehouse_locations, nullable                  |
| quantity_bags      | integer       | Bags involved                                             |
| total_weight       | decimal(10,2) | Total weight (kg)                                         |
| unit_price         | decimal(10,2) | Price per kg at time of transaction                       |
| total_amount       | decimal(15,2) | Total value                                               |
| reference_number   | string(100)   | External ref (invoice, DO, GRN), nullable                 |
| transaction_date   | date          |                                                           |
| notes              | text          | Nullable                                                  |
| created_by         | FK (users)    |                                                           |
| timestamps         |               |                                                           |

---

### 3.12 Stock Inventory Table (`stock_inventory`)

| Column          | Type          | Notes                        |
| --------------- | ------------- | ---------------------------- |
| id              | bigint PK     |                              |
| item_type_id    | FK            | References item_types        |
| branch_id       | FK            | References branches          |
| total_bags      | integer       | Current bag count in stock   |
| total_weight    | decimal(10,2) | Current total weight (kg)    |
| average_cost    | decimal(10,2) | Weighted average cost per kg |
| last_updated_at | datetime      |                              |
| timestamps      |               |                              |

---

### 3.13 Dispatch Orders Table (`dispatch_orders`)

| Column                | Type          | Notes                                           |
| --------------------- | ------------- | ----------------------------------------------- |
| id                    | bigint PK     |                                                 |
| dispatch_number       | string(50)    | Unique auto-generated (e.g., DIS-2026-0001)     |
| batch_id              | FK            | Source batch, nullable for multi-batch dispatch |
| vehicle_id            | FK            | Outgoing vehicle                                |
| driver_name           | string(255)   | Nullable                                        |
| driver_phone          | string(20)    | Nullable                                        |
| dispatch_date         | date          |                                                 |
| dispatch_time         | time          | Nullable                                        |
| destination           | string(255)   | Nullable                                        |
| total_bags            | integer       | Bags dispatched                                 |
| total_weight          | decimal(10,2) | Total dispatch weight (kg)                      |
| dispatch_gross_weight | decimal(10,2) | Loaded vehicle weight (kg), nullable            |
| dispatch_tare_weight  | decimal(10,2) | Empty vehicle weight (kg), nullable             |
| status                | enum          | pending, loading, dispatched, delivered         |
| notes                 | text          | Nullable                                        |
| created_by            | FK (users)    |                                                 |
| timestamps            |               |                                                 |

---

### 3.14 Dispatch Items Table (`dispatch_items`)

| Column      | Type          | Notes                      |
| ----------- | ------------- | -------------------------- |
| id          | bigint PK     |                            |
| dispatch_id | FK            | References dispatch_orders |
| bag_id      | FK            | References paddy_bags      |
| batch_id    | FK            | References paddy_batches   |
| bag_weight  | decimal(10,2) | Weight at time of dispatch |
| timestamps  |               |                            |

---

### 3.15 Batch Costs Table (`batch_costs`)

| Column      | Type          | Notes                                                               |
| ----------- | ------------- | ------------------------------------------------------------------- |
| id          | bigint PK     |                                                                     |
| batch_id    | FK            | References paddy_batches                                            |
| cost_type   | enum          | purchase, transportation, loading, unloading, labor, storage, other |
| description | string(255)   |                                                                     |
| amount      | decimal(15,2) |                                                                     |
| cost_date   | date          |                                                                     |
| reference   | string(100)   | Bill/invoice ref, nullable                                          |
| created_by  | FK (users)    |                                                                     |
| timestamps  |               |                                                                     |

---

### 3.16 Accounts Table (`accounts`)

| Column      | Type        | Notes                                        |
| ----------- | ----------- | -------------------------------------------- |
| id          | bigint PK   |                                              |
| code        | string(20)  | Unique account code                          |
| name        | string(255) |                                              |
| type        | enum        | asset, liability, equity, income, expense    |
| parent_id   | FK (self)   | Nullable, for hierarchical chart of accounts |
| description | text        | Nullable                                     |
| is_active   | boolean     |                                              |
| timestamps  |             |                                              |

---

### 3.17 Journal Entries Table (`journal_entries`)

| Column         | Type          | Notes                                           |
| -------------- | ------------- | ----------------------------------------------- |
| id             | bigint PK     |                                                 |
| entry_number   | string(50)    | Unique (e.g., JE-2026-00001)                    |
| entry_date     | date          |                                                 |
| description    | string(255)   |                                                 |
| reference_type | string(100)   | batch, stock_transaction, supplier_payment, etc |
| reference_id   | bigint        | Polymorphic ID of related entity                |
| total_amount   | decimal(15,2) | Total debit = total credit                      |
| created_by     | FK (users)    |                                                 |
| timestamps     |               |                                                 |

---

### 3.18 Journal Entry Lines Table (`journal_entry_lines`)

| Column           | Type          | Notes                      |
| ---------------- | ------------- | -------------------------- |
| id               | bigint PK     |                            |
| journal_entry_id | FK            | References journal_entries |
| account_id       | FK            | References accounts        |
| type             | enum          | debit, credit              |
| amount           | decimal(15,2) |                            |
| description      | string(255)   | Nullable                   |
| timestamps       |               |                            |

---

### 3.19 Supplier Payments Table (`supplier_payments`)

| Column           | Type          | Notes                          |
| ---------------- | ------------- | ------------------------------ |
| id               | bigint PK     |                                |
| payment_number   | string(50)    | Unique (e.g., PAY-2026-0001)   |
| supplier_id      | FK            | References suppliers           |
| batch_id         | FK            | Linked batch, nullable         |
| payment_date     | date          |                                |
| amount           | decimal(15,2) |                                |
| payment_method   | enum          | cash, bank_transfer, cheque    |
| reference_number | string(100)   | Bank ref / cheque no, nullable |
| notes            | text          | Nullable                       |
| created_by       | FK (users)    |                                |
| timestamps       |               |                                |

---

### 3.20 Activity Logs Table (`activity_logs`) _(already exists)_

| Column      | Type        | Notes                                       |
| ----------- | ----------- | ------------------------------------------- |
| id          | bigint PK   |                                             |
| user_id     | FK          | References users                            |
| action      | string(50)  | CREATE, UPDATE, DELETE, SCAN, DISPATCH, etc |
| module      | string(100) | Module name                                 |
| description | text        | Human-readable description                  |
| ip_address  | string(45)  | Requester IP                                |
| payload     | json        | Request data snapshot, nullable             |
| timestamps  |             |                                             |

---

## 4. Complete API Endpoints

### 4.1 Auth

| Method | Endpoint       | Auth | Description          |
| ------ | -------------- | ---- | -------------------- |
| POST   | /api/v1/login  | ✗    | Login (JWT)          |
| POST   | /api/v1/logout | ✓    | Logout               |
| GET    | /api/v1/me     | ✓    | Current user profile |

---

### 4.2 Suppliers

| Method | Endpoint                                                   | Auth | Description                      |
| ------ | ---------------------------------------------------------- | ---- | -------------------------------- |
| GET    | /api/v1/suppliers                                          | ✓    | List all suppliers (paginated)   |
| GET    | /api/v1/suppliers/list                                     | ✓    | Lightweight dropdown list        |
| POST   | /api/v1/suppliers                                          | ✓    | Create supplier                  |
| GET    | /api/v1/suppliers/{id}                                     | ✓    | Get supplier by ID               |
| PUT    | /api/v1/suppliers/{id}                                     | ✓    | Update supplier                  |
| DELETE | /api/v1/suppliers/{id}                                     | ✓    | Delete supplier                  |
| PATCH  | /api/v1/suppliers/{id}/toggle-status                       | ✓    | Toggle active/inactive           |
| GET    | /api/v1/suppliers/{id}/statement                           | ✓    | Supplier ledger statement        |
| GET    | /api/v1/suppliers/{id}/payments                            | ✓    | Supplier payment history         |
| GET    | /api/v1/suppliers/{id}/batches                             | ✓    | Batches linked to supplier       |
| GET    | /api/v1/suppliers/{id}/bank-accounts                       | ✓    | All bank accounts for supplier   |
| POST   | /api/v1/suppliers/{id}/bank-accounts                       | ✓    | Add new bank account to supplier |
| PUT    | /api/v1/suppliers/{id}/bank-accounts/{bank_id}             | ✓    | Update a bank account            |
| DELETE | /api/v1/suppliers/{id}/bank-accounts/{bank_id}             | ✓    | Delete a bank account            |
| PATCH  | /api/v1/suppliers/{id}/bank-accounts/{bank_id}/set-primary | ✓    | Set as primary account           |

---

### 4.3 Vehicles

| Method | Endpoint                            | Auth | Description           |
| ------ | ----------------------------------- | ---- | --------------------- |
| GET    | /api/v1/vehicles                    | ✓    | List all vehicles     |
| GET    | /api/v1/vehicles/list               | ✓    | Dropdown list         |
| POST   | /api/v1/vehicles                    | ✓    | Register vehicle      |
| GET    | /api/v1/vehicles/{id}               | ✓    | Get vehicle by ID     |
| PUT    | /api/v1/vehicles/{id}               | ✓    | Update vehicle        |
| DELETE | /api/v1/vehicles/{id}               | ✓    | Delete vehicle        |
| PATCH  | /api/v1/vehicles/{id}/toggle-status | ✓    | Toggle active status  |
| GET    | /api/v1/vehicles/{id}/logs          | ✓    | Vehicle movement logs |

---

### 4.4 Vehicle Logs

| Method | Endpoint                       | Auth | Description                  |
| ------ | ------------------------------ | ---- | ---------------------------- |
| GET    | /api/v1/vehicle-logs           | ✓    | All vehicle logs (paginated) |
| POST   | /api/v1/vehicle-logs           | ✓    | Create vehicle log (IN/OUT)  |
| GET    | /api/v1/vehicle-logs/{id}      | ✓    | Get specific log             |
| PATCH  | /api/v1/vehicle-logs/{id}/exit | ✓    | Record vehicle exit time     |

---

### 4.5 Weighing Records

| Method | Endpoint                            | Auth | Description                      |
| ------ | ----------------------------------- | ---- | -------------------------------- |
| GET    | /api/v1/weighing-records            | ✓    | List weighing records            |
| POST   | /api/v1/weighing-records            | ✓    | Record lorry or bag weight       |
| GET    | /api/v1/weighing-records/{id}       | ✓    | Get specific record              |
| PUT    | /api/v1/weighing-records/{id}       | ✓    | Update record                    |
| GET    | /api/v1/weighing-records/batch/{id} | ✓    | All weighing records for a batch |

---

### 4.6 Quality Inspections

| Method | Endpoint                               | Auth | Description                   |
| ------ | -------------------------------------- | ---- | ----------------------------- |
| GET    | /api/v1/quality-inspections            | ✓    | List all inspections          |
| POST   | /api/v1/quality-inspections            | ✓    | Create quality inspection     |
| GET    | /api/v1/quality-inspections/{id}       | ✓    | Get inspection by ID          |
| PUT    | /api/v1/quality-inspections/{id}       | ✓    | Update inspection             |
| GET    | /api/v1/quality-inspections/batch/{id} | ✓    | Inspection for specific batch |

---

### 4.7 Paddy Batches

| Method | Endpoint                                | Auth | Description                     |
| ------ | --------------------------------------- | ---- | ------------------------------- |
| GET    | /api/v1/paddy-batches                   | ✓    | List all batches (paginated)    |
| POST   | /api/v1/paddy-batches                   | ✓    | Create new batch                |
| GET    | /api/v1/paddy-batches/{id}              | ✓    | Get batch details               |
| PUT    | /api/v1/paddy-batches/{id}              | ✓    | Update batch                    |
| DELETE | /api/v1/paddy-batches/{id}              | ✓    | Delete batch                    |
| GET    | /api/v1/paddy-batches/{id}/bags         | ✓    | All bags in this batch          |
| GET    | /api/v1/paddy-batches/{id}/weighings    | ✓    | All weighing records for batch  |
| GET    | /api/v1/paddy-batches/{id}/costs        | ✓    | All costs attached to batch     |
| GET    | /api/v1/paddy-batches/{id}/transactions | ✓    | Stock transactions for batch    |
| PATCH  | /api/v1/paddy-batches/{id}/approve      | ✓    | Approve batch (post-inspection) |
| PATCH  | /api/v1/paddy-batches/{id}/complete     | ✓    | Mark batch fully processed      |

---

### 4.8 Warehouse Locations

| Method | Endpoint                                       | Auth | Description               |
| ------ | ---------------------------------------------- | ---- | ------------------------- |
| GET    | /api/v1/warehouse-locations                    | ✓    | List all locations        |
| GET    | /api/v1/warehouse-locations/list               | ✓    | Dropdown list             |
| POST   | /api/v1/warehouse-locations                    | ✓    | Create location           |
| GET    | /api/v1/warehouse-locations/{id}               | ✓    | Get location              |
| PUT    | /api/v1/warehouse-locations/{id}               | ✓    | Update location           |
| DELETE | /api/v1/warehouse-locations/{id}               | ✓    | Delete location           |
| PATCH  | /api/v1/warehouse-locations/{id}/toggle-status | ✓    | Toggle status             |
| GET    | /api/v1/warehouse-locations/{id}/bags          | ✓    | All bags at this location |

---

### 4.9 Paddy Bags

| Method | Endpoint                           | Auth | Description                             |
| ------ | ---------------------------------- | ---- | --------------------------------------- |
| GET    | /api/v1/paddy-bags                 | ✓    | List all bags (paginated)               |
| POST   | /api/v1/paddy-bags                 | ✓    | Register single bag                     |
| POST   | /api/v1/paddy-bags/bulk            | ✓    | Bulk register bags for a batch          |
| GET    | /api/v1/paddy-bags/{id}            | ✓    | Get bag details                         |
| PUT    | /api/v1/paddy-bags/{id}            | ✓    | Update bag                              |
| DELETE | /api/v1/paddy-bags/{id}            | ✓    | Delete bag                              |
| GET    | /api/v1/paddy-bags/scan/{bag_code} | ✓    | **Scan bag** – full detail view by code |
| GET    | /api/v1/paddy-bags/{id}/qr-code    | ✓    | Get/generate QR code image              |
| GET    | /api/v1/paddy-bags/{id}/barcode    | ✓    | Get/generate barcode image              |
| GET    | /api/v1/paddy-bags/{id}/movements  | ✓    | Full movement history for this bag      |

---

### 4.10 Batch Costs

| Method | Endpoint                 | Auth | Description          |
| ------ | ------------------------ | ---- | -------------------- |
| GET    | /api/v1/batch-costs      | ✓    | List all batch costs |
| POST   | /api/v1/batch-costs      | ✓    | Add cost to batch    |
| GET    | /api/v1/batch-costs/{id} | ✓    | Get cost entry       |
| PUT    | /api/v1/batch-costs/{id} | ✓    | Update cost          |
| DELETE | /api/v1/batch-costs/{id} | ✓    | Delete cost          |

---

### 4.11 Stock Transactions

| Method | Endpoint                           | Auth | Description                   |
| ------ | ---------------------------------- | ---- | ----------------------------- |
| GET    | /api/v1/stock-transactions         | ✓    | List transactions (paginated) |
| POST   | /api/v1/stock-transactions         | ✓    | Record stock movement         |
| GET    | /api/v1/stock-transactions/{id}    | ✓    | Get transaction               |
| GET    | /api/v1/stock-transactions/summary | ✓    | Aggregate stock summary       |

---

### 4.12 Stock Inventory

| Method | Endpoint                        | Auth | Description                 |
| ------ | ------------------------------- | ---- | --------------------------- |
| GET    | /api/v1/stock-inventory         | ✓    | Current inventory levels    |
| GET    | /api/v1/stock-inventory/history | ✓    | Full stock movement history |

---

### 4.13 Dispatch Orders

| Method | Endpoint                                    | Auth | Description                    |
| ------ | ------------------------------------------- | ---- | ------------------------------ |
| GET    | /api/v1/dispatch-orders                     | ✓    | List all dispatch orders       |
| POST   | /api/v1/dispatch-orders                     | ✓    | Create dispatch order          |
| GET    | /api/v1/dispatch-orders/{id}                | ✓    | Get dispatch order             |
| PUT    | /api/v1/dispatch-orders/{id}                | ✓    | Update dispatch order          |
| POST   | /api/v1/dispatch-orders/{id}/items          | ✓    | Add bags to dispatch (by scan) |
| DELETE | /api/v1/dispatch-orders/{id}/items/{bag_id} | ✓    | Remove bag from dispatch       |
| PATCH  | /api/v1/dispatch-orders/{id}/dispatch       | ✓    | Confirm and finalize dispatch  |

---

### 4.14 Accounts

| Method | Endpoint                     | Auth | Description                 |
| ------ | ---------------------------- | ---- | --------------------------- |
| GET    | /api/v1/accounts             | ✓    | List all accounts           |
| GET    | /api/v1/accounts/list        | ✓    | Dropdown list               |
| POST   | /api/v1/accounts             | ✓    | Create account              |
| GET    | /api/v1/accounts/{id}        | ✓    | Get account                 |
| PUT    | /api/v1/accounts/{id}        | ✓    | Update account              |
| DELETE | /api/v1/accounts/{id}        | ✓    | Delete account              |
| GET    | /api/v1/accounts/{id}/ledger | ✓    | Account ledger transactions |

---

### 4.15 Journal Entries

| Method | Endpoint                     | Auth | Description                 |
| ------ | ---------------------------- | ---- | --------------------------- |
| GET    | /api/v1/journal-entries      | ✓    | List entries                |
| POST   | /api/v1/journal-entries      | ✓    | Create manual journal entry |
| GET    | /api/v1/journal-entries/{id} | ✓    | Get entry with lines        |

---

### 4.16 Supplier Payments

| Method | Endpoint                       | Auth | Description         |
| ------ | ------------------------------ | ---- | ------------------- |
| GET    | /api/v1/supplier-payments      | ✓    | List all payments   |
| POST   | /api/v1/supplier-payments      | ✓    | Record payment      |
| GET    | /api/v1/supplier-payments/{id} | ✓    | Get payment details |

---

### 4.17 Reports

| Method | Endpoint                              | Auth | Description                    |
| ------ | ------------------------------------- | ---- | ------------------------------ |
| GET    | /api/v1/reports/stock-summary         | ✓    | Stock summary by type/location |
| GET    | /api/v1/reports/stock-movements       | ✓    | Stock movement report          |
| GET    | /api/v1/reports/batch-history         | ✓    | All batch records              |
| GET    | /api/v1/reports/bag-history           | ✓    | All bag records                |
| GET    | /api/v1/reports/supplier-transactions | ✓    | Supplier-level transactions    |
| GET    | /api/v1/reports/vehicle-movements     | ✓    | Vehicle IN/OUT report          |
| GET    | /api/v1/reports/weighing-summary      | ✓    | Weight records summary         |
| GET    | /api/v1/reports/financial-summary     | ✓    | Cost and payment summary       |
| GET    | /api/v1/reports/stock-valuation       | ✓    | Current stock value            |
| GET    | /api/v1/reports/dispatch-history      | ✓    | All dispatch records           |
| GET    | /api/v1/reports/activity-logs         | ✓    | Audit activity logs            |

---

## 5. Key Request Payloads

### Create Supplier

```json
{
    "code": "SUP-0001",
    "name": "Ranjith Farms",
    "phone_primary": "+94712345678",
    "phone_secondary": "+94772345678",
    "email": "ranjith@farm.com",
    "address_line1": "No. 12, Galle Road",
    "city": "Matara",
    "district_id": 3,
    "nic_number": "198765432V",
    "payment_terms": "net_15",
    "bank_name": "Bank of Ceylon",
    "bank_account_no": "12345678900",
    "bank_branch": "Matara",
    "bank_account_name": "R. D. Ranjith",
    "is_active": true
}
```

### Register Vehicle

```json
{
    "vehicle_number": "GA-1234",
    "driver_name": "Sunil Perera",
    "driver_phone": "+94712345678",
    "driver_nic": "198012345678",
    "vehicle_type": "lorry",
    "tare_weight": 4500.0,
    "is_active": true
}
```

### Create Paddy Batch

```json
{
    "supplier_id": 1,
    "vehicle_id": 2,
    "branch_id": 1,
    "item_type_id": 1,
    "arrival_date": "2026-07-17",
    "purchase_price": 125.0,
    "total_bags": 60,
    "notes": "Morning delivery – Samba Paddy"
}
```

### Record Lorry Gross Weight

```json
{
    "batch_id": 1,
    "weigh_type": "lorry_gross",
    "measured_weight": 9000.0,
    "measured_at": "2026-07-17 08:30:00",
    "scale_reference": "SCALE-WB-01",
    "notes": "Incoming lorry gross weight"
}
```

### Record Lorry Tare Weight

```json
{
    "batch_id": 1,
    "weigh_type": "lorry_tare",
    "measured_weight": 4500.0,
    "measured_at": "2026-07-17 10:15:00",
    "scale_reference": "SCALE-WB-01",
    "notes": "Empty lorry after unloading"
}
```

### Submit Quality Inspection

```json
{
    "batch_id": 1,
    "paddy_variety": "Samba",
    "moisture_percentage": 13.5,
    "grade": "A",
    "foreign_materials": 0.5,
    "broken_percentage": 2.0,
    "colour_quality": "good",
    "remarks": "Good quality, minimal impurities",
    "inspection_result": "approved"
}
```

### Register Single Bag

```json
{
    "batch_id": 1,
    "bag_number": 1,
    "bag_weight": 88.5,
    "location_id": 5
}
```

### Bulk Register Bags

```json
{
    "batch_id": 1,
    "bags": [
        { "bag_number": 1, "bag_weight": 88.5, "location_id": 5 },
        { "bag_number": 2, "bag_weight": 90.0, "location_id": 5 },
        { "bag_number": 3, "bag_weight": 87.0, "location_id": 6 }
    ]
}
```

### Scan Bag Response (`GET /paddy-bags/scan/{bag_code}`)

```json
{
    "status": "success",
    "data": {
        "bag_code": "BAG-2026-0001-001",
        "bag_number": 1,
        "bag_weight": 88.5,
        "status": "in_stock",
        "location": {
            "code": "WH-A-R1-S1",
            "name": "Warehouse A – Rack 1 – Shelf 1"
        },
        "batch": {
            "batch_number": "BATCH-2026-0001",
            "arrival_date": "2026-07-17",
            "net_weight": 4500.0,
            "status": "in_stock"
        },
        "supplier": {
            "name": "Ranjith Farms",
            "phone_primary": "+94712345678"
        },
        "item_type": { "name": "Samba Paddy", "code": "SAMBA" },
        "movement_history": []
    }
}
```

### Create Dispatch Order

```json
{
    "vehicle_id": 3,
    "driver_name": "Kamal Silva",
    "driver_phone": "+94712344321",
    "dispatch_date": "2026-07-20",
    "dispatch_time": "09:00",
    "destination": "Colombo Mill – No. 5",
    "notes": "Dispatch for milling"
}
```

### Add Bags to Dispatch (by Scan)

```json
{
    "bag_codes": ["BAG-2026-0001-001", "BAG-2026-0001-002", "BAG-2026-0001-003"]
}
```

### Record Batch Cost

```json
{
    "batch_id": 1,
    "cost_type": "transportation",
    "description": "Lorry hire charge for delivery",
    "amount": 15000.0,
    "cost_date": "2026-07-17",
    "reference": "BILL-TRN-001"
}
```

### Record Supplier Payment

```json
{
    "supplier_id": 1,
    "batch_id": 1,
    "payment_date": "2026-07-20",
    "amount": 562500.0,
    "payment_method": "bank_transfer",
    "reference_number": "TRN-20260720-001",
    "notes": "Full payment for Batch BATCH-2026-0001"
}
```

---

## 6. Permissions Structure

| Group                           | Permissions                                                    |
| ------------------------------- | -------------------------------------------------------------- |
| Supplier Management Permissions | Supplier Index, Create, Update, Delete, Toggle Status          |
| Vehicle Management Permissions  | Vehicle Index, Create, Update, Delete, Toggle Status           |
| Vehicle Log Permissions         | VehicleLog Index, Create, Update                               |
| Weighing Management Permissions | Weighing Index, Create, Update                                 |
| Quality Inspection Permissions  | QualityInspection Index, Create, Update                        |
| Paddy Batch Permissions         | PaddyBatch Index, Create, Update, Delete, Approve, Complete    |
| Warehouse Location Permissions  | WarehouseLocation Index, Create, Update, Delete, Toggle Status |
| Paddy Bag Permissions           | PaddyBag Index, Create, BulkCreate, Update, Delete, Scan       |
| Batch Cost Permissions          | BatchCost Index, Create, Update, Delete                        |
| Stock Transaction Permissions   | StockTransaction Index, Create, View                           |
| Stock Inventory Permissions     | StockInventory Index, History                                  |
| Dispatch Permissions            | Dispatch Index, Create, Update, AddItem, RemoveItem, Confirm   |
| Account Management Permissions  | Account Index, Create, Update, Delete                          |
| Journal Entry Permissions       | Journal Index, Create, View                                    |
| Supplier Payment Permissions    | SupplierPayment Index, Create, View                            |
| Report Permissions              | Report View, Export                                            |

---

## 7. Operational Workflow

```
[1] VEHICLE ARRIVAL
  → Log vehicle entry (VehicleLog: direction=in, entry_time)
  → Record lorry GROSS weight (WeighingRecord: lorry_gross)

[2] BATCH CREATION
  → Create PaddyBatch (supplier, vehicle, item_type, arrival_date, price)
  → Batch status: pending

[3] QUALITY INSPECTION
  → Create QualityInspection for batch
  → Batch status: inspecting → approved / rejected

[4] UNLOADING & BAG REGISTRATION
  → Register bags individually or in bulk for batch
  → Each bag gets unique bag_code → QR + barcode generated
  → Bags assigned to warehouse_locations
  → BagMovement recorded for each bag

[5] VEHICLE DEPARTS
  → Record lorry TARE weight (WeighingRecord: lorry_tare)
  → Net weight auto-calculated = gross − tare, batch updated
  → Update VehicleLog (exit_time)

[6] STOCK IN
  → Create StockTransaction (stock_in)
  → StockInventory updated automatically
  → JournalEntry auto-created: Dr. Paddy Stock / Cr. Supplier Payable

[7] INTERNAL STORAGE / TRANSFER
  → Move bags between locations
  → BagMovement records every transfer
  → StockTransaction (transfer) created

[8] DISPATCH
  → Create DispatchOrder (vehicle, driver, destination)
  → Scan bags by barcode/QR → added to DispatchItems
  → Confirm dispatch → bags.status = dispatched
  → StockTransaction (stock_out) created
  → StockInventory reduced
  → JournalEntry auto-created

[9] SUPPLIER PAYMENT
  → Record SupplierPayment (cash/transfer/cheque)
  → Supplier outstanding_balance updated
  → JournalEntry: Dr. Supplier Payable / Cr. Bank/Cash

[10] REPORTING
  → View/export stock, batch, bag, financial, vehicle, weight reports
```

---

## 8. Entity Relationship Overview

```
Country → Province → District → Branch
              ↑ (Org Hierarchy – already implemented)

Supplier ─────────────────────────────────────────────┐
Vehicle  ─────────────────────────────────────────────┤
ItemType ─────────────────────────────────────────────┤
Branch   ─────────────────────────────────────────────┤
                                                      ↓
                                               PaddyBatch ─── QualityInspection
                                                      │
                         ┌────────────────────────────┼────────────────────────┐
                         │                            │                        │
                  WeighingRecords               PaddyBags                BatchCosts
                                                      │
                               ┌──────────────────────┼──────────────────┐
                               │                      │                  │
                       WarehouseLocation         BagMovements     DispatchItems
                                                                        │
                                                                  DispatchOrders

StockTransactions  ←─── Batch / Bags / Dispatch
StockInventory     ←─── StockTransactions (aggregated per type/branch)
JournalEntries     ←─── Batch / StockTransactions / SupplierPayments
JournalEntryLines  ←─── JournalEntries + Accounts
SupplierPayments   ←─── Suppliers + Batches
ActivityLogs       ←─── All user actions (every module)
```

---

## 9. Development Phase Plan

| Phase | Module(s)                                                     | Status  |
| ----- | ------------------------------------------------------------- | ------- |
| 1     | Master Data (Country/Province/District/Branch/ItemType/Group) | ✅ DONE |
| 2     | Supplier Management + Vehicle Management + Vehicle Logs       | 🔜 NEXT |
| 3     | Weighing Records + Quality Inspections                        | Planned |
| 4     | Paddy Batch Management                                        | Planned |
| 5     | Warehouse Locations + Paddy Bags + Barcode/QR Generation      | Planned |
| 6     | Bag Movements + Stock Transactions + Inventory                | Planned |
| 7     | Dispatch Orders + Dispatch Items                              | Planned |
| 8     | Batch Costs                                                   | Planned |
| 9     | Chart of Accounts + Journal Entries + Supplier Payments       | Planned |
| 10    | Reports + PDF/Excel Export                                    | Planned |

---

## 10. Technology Stack

| Component          | Technology                                      |
| ------------------ | ----------------------------------------------- |
| Backend Framework  | Laravel 11 (PHP 8.3+)                           |
| Authentication     | JWT Auth (`php-open-source-saver/jwt-auth`)     |
| Authorization      | Spatie Laravel Permission                       |
| Database           | MySQL 8+                                        |
| QR Code Generation | `simplesoftwareio/simple-qrcode`                |
| Barcode Generation | `milon/barcode`                                 |
| PDF Export         | `barryvdh/laravel-dompdf`                       |
| Excel Export       | `maatwebsite/excel`                             |
| File Storage       | Laravel Storage (local or S3)                   |
| API Versioning     | `/api/v1/` prefix group                         |
| Activity Logging   | Custom `ActivityLogTrait` (already implemented) |
