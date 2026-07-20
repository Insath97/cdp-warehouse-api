# CDP Generic Warehouse Management System (GWMS)
## Multi-Commodity & Multi-Variety System Analysis & Architecture

This document provides a comprehensive technical analysis and system design to extend the system from a Paddy-specific Warehouse Management System (PWMS) into a **Generic Warehouse Management System (GWMS)**. The system is designed to handle any commodity types (e.g., Paddy, Sugar, Wheat, Flour) and their respective varieties (e.g., Samba/Nadu for Paddy, White/Brown for Sugar) using a hierarchical structure.

---

## 1. Architectural Transition: From Paddy to Multi-Commodity

To support multiple items (Paddy, Sugar, etc.) and their varieties while preserving clean database normalization, we transition from single-category fields to a generic **Item Type** and **Item Variety** hierarchy.

### 1.1 Item Hierarchy Design
```
Item Type (e.g., Paddy) ────────► Item Variety (e.g., Samba, Nadu, Keeri Samba)
Item Type (e.g., Sugar) ────────► Item Variety (e.g., White Sugar, Brown Sugar, Castor Sugar)
Item Type (e.g., Wheat) ────────► Item Variety (e.g., Durum Wheat, Hard Red Wheat)
```

1. **`item_types`** (already exists): Represents the parent category (e.g., Paddy, Sugar).
2. **`item_varieties`** (new): Links to `item_types` and represents the specific variety (e.g., Samba, White Sugar).
3. **Generic Batches & Packages**: Rename references of `paddy_batches` to `batches` (or `item_batches`) and `paddy_bags` to `bags` (or `stock_packages`). They will link to both the `item_type_id` and the `item_variety_id`.

---

## 2. Updated Database Schema Design

### 2.1 Item Varieties Table (`item_varieties`)
Stores the sub-types/varieties for each item category.
| Column       | Type       | Notes                                                 |
| ------------ | ---------- | ----------------------------------------------------- |
| id           | bigint PK  | Auto-increment                                        |
| item_type_id | FK         | References `item_types.id`                            |
| name         | string     | Variety Name (e.g., "White Sugar", "Samba Paddy")     |
| code         | string     | Unique Variety Code (e.g., "SGR-WHT", "PDY-SAM")      |
| description  | text       | Nullable description                                  |
| is_active    | boolean    | Status toggle (default true)                          |
| timestamps   |            |                                                       |

### 2.2 Batches Table (`batches`)
Replaces `paddy_batches` to handle batches of any item type/variety.
| Column           | Type          | Notes                                                 |
| ---------------- | ------------- | ----------------------------------------------------- |
| id               | bigint PK     |                                                       |
| batch_number     | string        | Unique auto-generated (e.g., BATCH-2026-0001)          |
| supplier_id      | FK            | References `suppliers.id`                             |
| vehicle_id       | FK            | References `vehicles.id`                              |
| branch_id        | FK            | References `branches.id` (receiving warehouse)        |
| item_type_id     | FK            | References `item_types.id` (e.g., Sugar)              |
| item_variety_id  | FK            | References `item_varieties.id` (e.g., White Sugar)    |
| arrival_date     | date          |                                                       |
| purchase_price   | decimal(10,2) | Price per unit/kg                                     |
| gross_weight     | decimal(10,2) | Weight with loaded vehicle (kg)                       |
| tare_weight      | decimal(10,2) | Weight of empty vehicle (kg)                          |
| net_weight       | decimal(10,2) | Auto-calculated: `gross_weight` − `tare_weight`       |
| total_bags       | integer       | Expected bags/packages on arrival                     |
| actual_bags      | integer       | Counted bags/packages after unloading                 |
| total_bag_weight | decimal(10,2) | Sum of individual bag/package weights                 |
| total_amount     | decimal(15,2) | `net_weight` * `purchase_price`                       |
| status           | enum          | `pending`, `inspecting`, `approved`, `completed` etc. |
| notes            | text          | Nullable                                              |
| created_by       | FK            | References `users.id`                                 |
| timestamps       |            |                                                       |

### 2.3 Bags / Packages Table (`bags`)
Replaces `paddy_bags` to represent stock items (bags, cartons, bags of sugar, etc.).
| Column          | Type          | Notes                                                 |
| --------------- | ------------- | ----------------------------------------------------- |
| id              | bigint PK     |                                                       |
| bag_code        | string        | Unique barcode/QR value                               |
| batch_id        | FK            | References `batches.id`                               |
| supplier_id     | FK            | References `suppliers.id`                             |
| item_type_id    | FK            | References `item_types.id`                            |
| item_variety_id | FK            | References `item_varieties.id`                        |
| bag_number      | integer       | Sequence within batch (e.g., 1, 2, 3...)              |
| bag_weight      | decimal(10,2) | Actual package weight (kg)                            |
| location_id     | FK            | References `warehouse_locations.id`                   |
| status          | enum          | `in_stock`, `dispatched`, `damaged`, `returned`       |
| qr_code_path    | string        | QR code image path                                    |
| barcode_path    | string        | Barcode image path                                    |
| timestamps      |               |                                                       |

---

## 3. Generic Quality Inspection Design

Since different item types have unique quality requirements (e.g., Paddy requires moisture/broken percentage checks, while Sugar requires purity/color/polarization checks), a static table schema is inefficient.

### 3.1 Solution: JSON Parameters Schema
We use a hybrid table design for **`quality_inspections`** where standard parameters are columns, and commodity-specific parameters are stored inside a dynamic **JSON** column.

| Column              | Type         | Notes                                                              |
| ------------------- | ------------ | ------------------------------------------------------------------ |
| id                  | bigint PK    |                                                                    |
| batch_id            | FK           | References `batches.id`                                            |
| grade               | enum         | `A`, `B`, `C`, `reject`                                            |
| moisture_percentage | decimal(5,2) | Common parameter (grain/sugar moisture, nullable)                  |
| inspection_result   | enum         | `approved`, `conditional`, `rejected`                              |
| parameters          | json         | **Dynamic Commodity Parameters** (e.g. Broken %, Polarization, Color)|
| remarks             | text         | Inspection remarks                                                 |
| inspected_by        | FK           | References `users.id`                                              |
| inspected_at        | datetime     |                                                                    |
| timestamps          |              |                                                                    |

#### Example JSON payloads in `parameters` column:
- **For Paddy**:
  ```json
  {
    "broken_percentage": 5.50,
    "foreign_materials": 1.20,
    "colour_quality": "good"
  }
  ```
- **For Sugar**:
  ```json
  {
    "polarization": 99.80,
    "color_icu": 45,
    "ash_content_percentage": 0.04
  }
  ```

---

## 4. API Endpoints for Varieties and Generic Flow

### 4.1 Master Data - Item Varieties
- `GET /api/v1/item-types/{type_id}/varieties`: List active varieties for a specific item type (e.g., list all varieties of Paddy).
- `POST /api/v1/item-varieties`: Create a new variety.
- `PUT /api/v1/item-varieties/{id}`: Update a variety.
- `DELETE /api/v1/item-varieties/{id}`: Delete a variety.
- `PATCH /api/v1/item-varieties/{id}/toggle-status`: Toggle active status.

### 4.2 Generic Batches Flow
- `POST /api/v1/batches`: Starts a batch, taking both `item_type_id` and `item_variety_id`.
- `POST /api/v1/batches/{id}/quality-inspection`: Records quality parameters. The request payload accepts dynamic keys representing the commodity's quality checklist, which are stored in the JSON `parameters` database column.

---

## 5. Summary of Refactoring Impact

- **Generalization**: Renaming tables (`paddy_batches` -> `batches`, `paddy_bags` -> `bags`) aligns the codebase to store sugar, wheat, grains, or packages of any merchandise.
- **Hierarchical Validation**: Frontends load `item_types` first (e.g., select box: Paddy vs Sugar), then query `/item-types/{id}/varieties` to fill the sub-variety select box (e.g. Samba/Nadu vs White/Brown Sugar).
- **Flexible Weighbridge**: Lorry weighbridge tare/gross logic, net weight calculations, bag counting, and storage location mapping remain identical, adapting dynamically based on the parent item category.
