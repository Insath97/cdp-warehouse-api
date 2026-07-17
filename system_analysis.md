# CDP Paddy Warehouse Management System (PWMS)
## Full System Analysis & Documentation

---

## 1. System Overview

The **CDP Paddy Warehouse Management System (PWMS)** is a comprehensive backend API designed to manage the complete lifecycle of paddy (rice) storage operations. It tracks everything from supplier deliveries, batch registrations, individual bag barcoding, vehicle weight measurements, stock flow, to full financial account management and audit ledger.

---

## 2. System Modules Summary

| #  | Module                      | Purpose                                                  |
|----|-----------------------------|----------------------------------------------------------|
| 1  | Supplier Management         | Register and manage paddy suppliers                      |
| 2  | Vehicle / Lorry Management  | Track inbound/outbound vehicle details and weights        |
| 3  | Batch Management            | Create paddy batches tied to suppliers and vehicles       |
| 4  | Bag Management              | Register individual paddy bags with barcodes/QR codes     |
| 5  | Weighing Module             | Record lorry tare/gross weight, bag-level weights         |
| 6  | Stock Management            | Manage stock-in and stock-out transactions                |
| 7  | Inventory / Stock History   | Full audit trail of all stock movements                   |
| 8  | Account Management          | Chart of accounts, supplier ledger, cost tracking         |
| 9  | Ledger & Journal Entries    | Double-entry bookkeeping for all transactions             |
| 10 | Reports & Logs              | Weight summaries, stock reports, transaction logs         |

---

## 3. Database Schema (All Modules)

### 3.1 Suppliers Table (`suppliers`)
| Column            | Type       | Notes                              |
|-------------------|------------|------------------------------------|
| id                | bigint PK  | Auto-increment                     |
| code              | string     | Unique supplier code               |
| name              | string     | Full supplier name                 |
| phone_primary     | string     | Primary contact number             |
| phone_secondary   | string     | Secondary number (nullable)        |
| email             | string     | Email (nullable)                   |
| address_line1     | string     | Address                            |
| city              | string     |                                    |
| nic_number        | string     | National ID (nullable, unique)     |
| bank_name         | string     | Bank name (nullable)               |
| bank_account_no   | string     | Bank account number (nullable)     |
| bank_branch       | string     | Bank branch (nullable)             |
| outstanding_balance| decimal   | Current outstanding balance        |
| is_active         | boolean    | Status                             |
| timestamps        |            |                                    |

---

### 3.2 Vehicles Table (`vehicles`)
| Column           | Type      | Notes                                |
|------------------|-----------|--------------------------------------|
| id               | bigint PK |                                      |
| vehicle_number   | string    | Unique license plate number          |
| driver_name      | string    | Driver's name (nullable)             |
| driver_phone     | string    | Driver's contact (nullable)          |
| vehicle_type     | enum      | lorry, pickup, van, other            |
| is_active        | boolean   |                                      |
| timestamps       |           |                                      |

---

### 3.3 Paddy Batches Table (`paddy_batches`)
| Column           | Type      | Notes                                       |
|------------------|-----------|---------------------------------------------|
| id               | bigint PK |                                             |
| batch_number     | string    | Unique batch code (auto-generated)          |
| supplier_id      | FK        | References suppliers                        |
| vehicle_id       | FK        | References vehicles                         |
| item_type_id     | FK        | References item_types (paddy variety)       |
| arrival_date     | date      | Date paddy arrived                          |
| gross_weight     | decimal   | Total weight of loaded vehicle (kg)         |
| tare_weight      | decimal   | Empty vehicle weight (kg)                   |
| net_weight       | decimal   | gross_weight - tare_weight (kg)             |
| total_bags       | integer   | Expected total bag count                    |
| actual_bags      | integer   | Actual bags counted on arrival              |
| unit_price       | decimal   | Price per kg for this batch                 |
| total_amount     | decimal   | net_weight * unit_price                     |
| status           | enum      | pending, in_progress, completed, cancelled  |
| notes            | text      | Optional remarks                            |
| timestamps       |           |                                             |

---

### 3.4 Paddy Bags Table (`paddy_bags`)
| Column           | Type      | Notes                                                |
|------------------|-----------|------------------------------------------------------|
| id               | bigint PK |                                                      |
| bag_code         | string    | Unique auto-generated code (used for barcode/QR)     |
| batch_id         | FK        | References paddy_batches                             |
| supplier_id      | FK        | References suppliers                                 |
| item_type_id     | FK        | References item_types (paddy variety)                |
| bag_weight       | decimal   | Weight of this individual bag (kg)                   |
| bag_number       | integer   | Bag sequence number within batch                     |
| status           | enum      | in_stock, dispatched, sold, damaged                  |
| location         | string    | Storage location/rack (nullable)                     |
| qr_code_url      | string    | Path to generated QR code image (nullable)           |
| timestamps       |           |                                                      |

---

### 3.5 Weighing Records Table (`weighing_records`)
| Column           | Type      | Notes                                           |
|------------------|-----------|------------------------------------------------|
| id               | bigint PK |                                                |
| batch_id         | FK        | References paddy_batches                       |
| weigh_type       | enum      | lorry_gross, lorry_tare, bag                   |
| bag_id           | FK        | References paddy_bags (nullable for lorry)     |
| measured_weight  | decimal   | Weight measured (kg)                           |
| measured_at      | datetime  | Date/time of measurement                       |
| measured_by      | FK (user) | User who recorded the measurement              |
| notes            | text      | Remarks (nullable)                             |
| timestamps       |           |                                                |

---

### 3.6 Vehicle Logs Table (`vehicle_logs`)
| Column           | Type      | Notes                                           |
|------------------|-----------|------------------------------------------------|
| id               | bigint PK |                                                |
| vehicle_id       | FK        | References vehicles                            |
| batch_id         | FK        | References paddy_batches (nullable)            |
| direction        | enum      | in, out                                        |
| entry_time       | datetime  | Vehicle entry time                             |
| exit_time        | datetime  | Vehicle exit time (nullable)                   |
| purpose          | string    | Reason for entry/exit                          |
| driver_name      | string    | Driver at time of entry (nullable)             |
| notes            | text      |                                                |
| timestamps       |           |                                                |

---

### 3.7 Stock Transactions Table (`stock_transactions`)
| Column              | Type      | Notes                                          |
|---------------------|-----------|------------------------------------------------|
| id                  | bigint PK |                                                |
| transaction_number  | string    | Unique auto-generated reference                |
| transaction_type    | enum      | stock_in, stock_out, transfer, adjustment      |
| batch_id            | FK        | References paddy_batches (nullable)            |
| item_type_id        | FK        | References item_types                          |
| supplier_id         | FK        | References suppliers (nullable for outgoing)   |
| quantity_bags       | integer   | Number of bags in this transaction             |
| total_weight        | decimal   | Total weight in kg                             |
| unit_price          | decimal   | Price per kg                                   |
| total_amount        | decimal   | Total transaction value                        |
| reference_number    | string    | External reference (invoice/DO no., nullable)  |
| transaction_date    | date      | Date of transaction                            |
| notes               | text      | Remarks (nullable)                             |
| created_by          | FK (user) | Who created the transaction                    |
| timestamps          |           |                                                |

---

### 3.8 Stock Inventory Table (`stock_inventory`)
| Column              | Type      | Notes                               |
|---------------------|-----------|-------------------------------------|
| id                  | bigint PK |                                     |
| item_type_id        | FK        | References item_types (paddy type)  |
| total_bags          | integer   | Current number of bags in stock     |
| total_weight        | decimal   | Current total weight in kg          |
| average_cost        | decimal   | Weighted average cost per kg        |
| last_updated_at     | datetime  | Last inventory update time          |
| timestamps          |           |                                     |

---

### 3.9 Accounts Table (`accounts`)
| Column           | Type      | Notes                                           |
|------------------|-----------|------------------------------------------------|
| id               | bigint PK |                                                |
| code             | string    | Unique account code                            |
| name             | string    | Account name                                   |
| type             | enum      | asset, liability, equity, income, expense      |
| parent_id        | FK (self) | Parent account (nullable for root accounts)    |
| is_active        | boolean   |                                                |
| timestamps       |           |                                                |

---

### 3.10 Journal Entries Table (`journal_entries`)
| Column           | Type      | Notes                                          |
|------------------|-----------|------------------------------------------------|
| id               | bigint PK |                                                |
| entry_number     | string    | Unique journal entry reference                 |
| entry_date       | date      | Date of journal entry                          |
| description      | string    | Entry description                              |
| reference_type   | string    | Related entity type (batch, stock_transaction) |
| reference_id     | bigint    | Related entity ID (polymorphic)                |
| total_amount     | decimal   | Total debit/credit amount                      |
| created_by       | FK (user) | Who created the entry                          |
| timestamps       |           |                                                |

---

### 3.11 Journal Entry Lines Table (`journal_entry_lines`)
| Column           | Type      | Notes                                          |
|------------------|-----------|------------------------------------------------|
| id               | bigint PK |                                                |
| journal_entry_id | FK        | References journal_entries                     |
| account_id       | FK        | References accounts                            |
| type             | enum      | debit, credit                                  |
| amount           | decimal   | Amount for this line                           |
| description      | string    | Line description (nullable)                    |
| timestamps       |           |                                                |

---

### 3.12 Supplier Payments Table (`supplier_payments`)
| Column           | Type      | Notes                                           |
|------------------|-----------|------------------------------------------------|
| id               | bigint PK |                                                |
| payment_number   | string    | Unique payment reference                       |
| supplier_id      | FK        | References suppliers                           |
| batch_id         | FK        | Linked batch (nullable)                        |
| payment_date     | date      |                                                |
| amount           | decimal   | Payment amount                                 |
| payment_method   | enum      | cash, bank_transfer, cheque                    |
| reference_number | string    | Bank ref / cheque no (nullable)                |
| notes            | text      | Remarks (nullable)                             |
| created_by       | FK (user) | Who recorded the payment                       |
| timestamps       |           |                                                |

---

## 4. API Modules & Endpoints

### 4.1 Authentication
| Method | Endpoint          | Description          |
|--------|-------------------|----------------------|
| POST   | /api/v1/login     | Login (JWT)          |
| POST   | /api/v1/logout    | Logout               |
| GET    | /api/v1/me        | Get current profile  |

---

### 4.2 Suppliers
| Method | Endpoint                              | Description                   |
|--------|---------------------------------------|-------------------------------|
| GET    | /api/v1/suppliers                     | List all suppliers             |
| GET    | /api/v1/suppliers/list                | Lightweight dropdown list      |
| POST   | /api/v1/suppliers                     | Create supplier                |
| GET    | /api/v1/suppliers/{id}                | Get supplier by ID             |
| PUT    | /api/v1/suppliers/{id}                | Update supplier                |
| DELETE | /api/v1/suppliers/{id}                | Delete supplier                |
| PATCH  | /api/v1/suppliers/{id}/toggle-status  | Toggle active status           |
| GET    | /api/v1/suppliers/{id}/statement      | Supplier ledger statement      |

---

### 4.3 Vehicles
| Method | Endpoint                              | Description                   |
|--------|---------------------------------------|-------------------------------|
| GET    | /api/v1/vehicles                      | List all vehicles              |
| GET    | /api/v1/vehicles/list                 | Lightweight dropdown list      |
| POST   | /api/v1/vehicles                      | Register vehicle               |
| GET    | /api/v1/vehicles/{id}                 | Get vehicle by ID              |
| PUT    | /api/v1/vehicles/{id}                 | Update vehicle                 |
| DELETE | /api/v1/vehicles/{id}                 | Delete vehicle                 |
| PATCH  | /api/v1/vehicles/{id}/toggle-status   | Toggle active status           |
| POST   | /api/v1/vehicles/{id}/log-entry       | Log vehicle entry (IN/OUT)     |

---

### 4.4 Paddy Batches
| Method | Endpoint                              | Description                        |
|--------|---------------------------------------|------------------------------------|
| GET    | /api/v1/paddy-batches                 | List all batches (paginated)        |
| POST   | /api/v1/paddy-batches                 | Create new paddy batch              |
| GET    | /api/v1/paddy-batches/{id}            | Get batch details                   |
| PUT    | /api/v1/paddy-batches/{id}            | Update batch                        |
| DELETE | /api/v1/paddy-batches/{id}            | Delete batch                        |
| GET    | /api/v1/paddy-batches/{id}/bags       | Get all bags for this batch         |
| GET    | /api/v1/paddy-batches/{id}/weighings  | Get weighing records for this batch |
| PATCH  | /api/v1/paddy-batches/{id}/complete   | Mark batch as completed             |

---

### 4.5 Paddy Bags (Barcode / QR)
| Method | Endpoint                              | Description                        |
|--------|---------------------------------------|------------------------------------|
| GET    | /api/v1/paddy-bags                    | List all bags (paginated)           |
| POST   | /api/v1/paddy-bags                    | Register a single bag               |
| POST   | /api/v1/paddy-bags/bulk               | Bulk register bags for a batch      |
| GET    | /api/v1/paddy-bags/{id}               | Get bag details                     |
| PUT    | /api/v1/paddy-bags/{id}               | Update bag details                  |
| DELETE | /api/v1/paddy-bags/{id}               | Delete bag                          |
| GET    | /api/v1/paddy-bags/scan/{bag_code}    | Scan bag by barcode/QR code         |
| GET    | /api/v1/paddy-bags/{id}/qr-code       | Get/Generate QR code image for bag  |

---

### 4.6 Weighing Records
| Method | Endpoint                              | Description                       |
|--------|---------------------------------------|-----------------------------------|
| GET    | /api/v1/weighing-records              | List weighing records              |
| POST   | /api/v1/weighing-records              | Record a new weight measurement    |
| GET    | /api/v1/weighing-records/{id}         | Get specific weighing record       |
| PUT    | /api/v1/weighing-records/{id}         | Update weighing record             |

---

### 4.7 Vehicle Logs (IN/OUT)
| Method | Endpoint                              | Description                      |
|--------|---------------------------------------|----------------------------------|
| GET    | /api/v1/vehicle-logs                  | List vehicle entry/exit logs      |
| POST   | /api/v1/vehicle-logs                  | Create log entry                  |
| GET    | /api/v1/vehicle-logs/{id}             | Get specific log                  |
| PATCH  | /api/v1/vehicle-logs/{id}/exit        | Mark vehicle exit time            |

---

### 4.8 Stock Transactions
| Method | Endpoint                              | Description                         |
|--------|---------------------------------------|-------------------------------------|
| GET    | /api/v1/stock-transactions            | List all transactions (paginated)    |
| POST   | /api/v1/stock-transactions            | Record stock IN or OUT              |
| GET    | /api/v1/stock-transactions/{id}       | Get transaction details              |
| GET    | /api/v1/stock-transactions/summary    | Stock summary totals                 |

---

### 4.9 Stock Inventory
| Method | Endpoint                              | Description                         |
|--------|---------------------------------------|-------------------------------------|
| GET    | /api/v1/stock-inventory               | Get current inventory levels         |
| GET    | /api/v1/stock-inventory/history       | Full stock movement history          |

---

### 4.10 Accounts
| Method | Endpoint                              | Description                         |
|--------|---------------------------------------|-------------------------------------|
| GET    | /api/v1/accounts                      | List all accounts                    |
| GET    | /api/v1/accounts/list                 | Lightweight dropdown                 |
| POST   | /api/v1/accounts                      | Create account                       |
| GET    | /api/v1/accounts/{id}                 | Get account details                  |
| PUT    | /api/v1/accounts/{id}                 | Update account                       |
| DELETE | /api/v1/accounts/{id}                 | Delete account                       |
| GET    | /api/v1/accounts/{id}/ledger          | Account-level ledger                 |

---

### 4.11 Journal Entries (Ledger)
| Method | Endpoint                              | Description                         |
|--------|---------------------------------------|-------------------------------------|
| GET    | /api/v1/journal-entries               | List all entries (paginated)         |
| POST   | /api/v1/journal-entries               | Create journal entry (manual)        |
| GET    | /api/v1/journal-entries/{id}          | Get journal entry details            |
| GET    | /api/v1/journal-entries/{id}/lines    | Get journal entry line items         |

---

### 4.12 Supplier Payments
| Method | Endpoint                              | Description                         |
|--------|---------------------------------------|-------------------------------------|
| GET    | /api/v1/supplier-payments             | List all payments                    |
| POST   | /api/v1/supplier-payments             | Record payment to supplier           |
| GET    | /api/v1/supplier-payments/{id}        | Get payment details                  |

---

## 5. Request Payloads (Key Modules)

### 5.1 Create Supplier
```json
{
  "code": "SUP-001",
  "name": "Ranjith Farms",
  "phone_primary": "+94712345678",
  "phone_secondary": "+94712345679",
  "email": "ranjith@farm.com",
  "address_line1": "No 12, Galle Road",
  "city": "Matara",
  "nic_number": "198765432V",
  "bank_name": "Bank of Ceylon",
  "bank_account_no": "12345678900",
  "bank_branch": "Matara",
  "is_active": true
}
```

### 5.2 Register Vehicle
```json
{
  "vehicle_number": "GA-1234",
  "driver_name": "Sunil Perera",
  "driver_phone": "+94712345678",
  "vehicle_type": "lorry",
  "is_active": true
}
```

### 5.3 Create Paddy Batch
```json
{
  "supplier_id": 1,
  "vehicle_id": 2,
  "item_type_id": 1,
  "arrival_date": "2026-07-17",
  "gross_weight": 5400.00,
  "tare_weight": 2500.00,
  "total_bags": 60,
  "unit_price": 125.00,
  "notes": "Morning delivery - Samba Paddy"
}
```

### 5.4 Register Single Bag
```json
{
  "batch_id": 1,
  "bag_weight": 88.50,
  "bag_number": 1,
  "location": "Rack-A1"
}
```

### 5.5 Bulk Register Bags for Batch
```json
{
  "batch_id": 1,
  "bags": [
    { "bag_number": 1, "bag_weight": 88.50, "location": "Rack-A1" },
    { "bag_number": 2, "bag_weight": 90.00, "location": "Rack-A2" },
    { "bag_number": 3, "bag_weight": 87.00, "location": "Rack-A3" }
  ]
}
```

### 5.6 Record Weighing
```json
{
  "batch_id": 1,
  "weigh_type": "lorry_gross",
  "measured_weight": 5400.00,
  "measured_at": "2026-07-17 08:30:00",
  "notes": "Gross weight on entry"
}
```
> For bag-level weighing:
```json
{
  "batch_id": 1,
  "bag_id": 5,
  "weigh_type": "bag",
  "measured_weight": 88.50,
  "measured_at": "2026-07-17 09:15:00"
}
```

### 5.7 Vehicle Log Entry
```json
{
  "vehicle_id": 2,
  "batch_id": 1,
  "direction": "in",
  "entry_time": "2026-07-17 08:00:00",
  "driver_name": "Sunil Perera",
  "purpose": "Paddy delivery - Batch #BATCH-2026-001",
  "notes": ""
}
```

### 5.8 Stock Transaction
```json
{
  "transaction_type": "stock_in",
  "batch_id": 1,
  "item_type_id": 1,
  "supplier_id": 1,
  "quantity_bags": 60,
  "total_weight": 2900.00,
  "unit_price": 125.00,
  "reference_number": "INV-2026-001",
  "transaction_date": "2026-07-17",
  "notes": "Stock in from batch BATCH-2026-001"
}
```

### 5.9 Supplier Payment
```json
{
  "supplier_id": 1,
  "batch_id": 1,
  "payment_date": "2026-07-17",
  "amount": 362500.00,
  "payment_method": "bank_transfer",
  "reference_number": "TRN-20260717-001",
  "notes": "Full payment for batch BATCH-2026-001"
}
```

### 5.10 Journal Entry (Manual)
```json
{
  "entry_date": "2026-07-17",
  "description": "Stock purchase - Batch BATCH-2026-001",
  "reference_type": "batch",
  "reference_id": 1,
  "lines": [
    { "account_id": 1, "type": "debit", "amount": 362500.00, "description": "Paddy Stock Asset" },
    { "account_id": 2, "type": "credit", "amount": 362500.00, "description": "Supplier Payable" }
  ]
}
```

---

## 6. Permissions Structure

| Group                          | Permissions                                                         |
|--------------------------------|---------------------------------------------------------------------|
| Supplier Management            | Supplier Index/Create/Update/Delete/Toggle Status                   |
| Vehicle Management             | Vehicle Index/Create/Update/Delete/Toggle Status                    |
| Paddy Batch Management         | PaddyBatch Index/Create/Update/Delete/Complete                      |
| Paddy Bag Management           | PaddyBag Index/Create/Update/Delete/Scan                           |
| Weighing Management            | Weighing Index/Create/Update                                        |
| Vehicle Log Management         | VehicleLog Index/Create/Update                                      |
| Stock Transaction Management   | StockTransaction Index/Create/View/Export                           |
| Stock Inventory Management     | StockInventory Index/View/History                                   |
| Account Management             | Account Index/Create/Update/Delete                                  |
| Journal Management             | Journal Index/Create/View                                           |
| Supplier Payment Management    | SupplierPayment Index/Create/View                                   |
| Reports                        | Report View/Export                                                  |

---

## 7. Entity Relationship Summary

```
Country → Province → District → Branch
       (Organizational Hierarchy)

Supplier ──────────────────────────────────────────────────┐
Vehicle ────────────────────────────────────────────┐      │
ItemType ──────────────────────────────────┐        │      │
                                           ↓        ↓      ↓
                                      PaddyBatch (batch)
                                           │
                                    ┌──────┴────────┐
                                    ↓               ↓
                               PaddyBags    WeighingRecords
                                    │
                                    ↓
                               StockTransactions
                                    │
                                    ↓
                              StockInventory
                                    │
                                    ↓
                              JournalEntries → JournalEntryLines → Accounts
                                    │
                                    ↓
                            SupplierPayments
```

---

## 8. Development Phases Recommended

### Phase 1 - Core Master Data (DONE ✅)
- Countries, Provinces, Districts, Branches, Groups
- Departments, Designations
- Users & Employees
- Item Types

### Phase 2 - Supplier & Vehicle Module
- Supplier CRUD + ledger
- Vehicle CRUD + vehicle logs (IN/OUT)

### Phase 3 - Batch & Bag Module
- Paddy Batch creation & workflow
- Bag registration (single and bulk)
- Barcode/QR code generation per bag
- Bag scanning endpoint

### Phase 4 - Weighing Module
- Lorry gross and tare weight recording
- Individual bag weight recording
- Net weight auto-calculation on batch

### Phase 5 - Stock Management
- Stock In / Stock Out transactions
- Real-time inventory ledger
- Stock history & audit log

### Phase 6 - Account Management
- Chart of accounts setup
- Journal entry creation (auto + manual)
- Supplier payment recording
- Supplier statement/ledger

### Phase 7 - Reports & Exports
- Daily/weekly/monthly stock reports
- Vehicle log reports
- Supplier payment reports
- Stock history exports (CSV/PDF)

---

## 9. Technology Stack

| Layer              | Technology                            |
|--------------------|---------------------------------------|
| Backend Framework  | Laravel 11 (PHP 8.3+)                 |
| Authentication     | JWT Auth (PHP Open Source Saver)      |
| Authorization      | Spatie Laravel Permission             |
| Database           | MySQL 8+                              |
| Barcode / QR       | `milon/barcode` or `simplesoftwareio/simple-qrcode` |
| File Storage       | Laravel Storage (local/S3)            |
| API Versioning     | `/api/v1/` prefix                     |
| Activity Logging   | Custom `ActivityLogTrait`             |
