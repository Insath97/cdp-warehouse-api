# CDP Warehouse API

A comprehensive warehouse management RESTful API built with Laravel 12, featuring JWT authentication, role-based access control, and complete inventory management capabilities.

## Overview

CDP Warehouse API provides a robust backend for managing warehouse operations including stock intake, dispatch, quality inspections, vehicle tracking, supplier management, buyer management, invoicing, and comprehensive reporting. The API is designed for enterprise-grade warehouse operations with support for multiple warehouses, branches, and role-based access control.

## Tech Stack

- **Framework:** Laravel 12.x
- **PHP Version:** 8.2+
- **Authentication:** JWT (JSON Web Tokens) via `php-open-source-saver/jwt-auth`
- **Authorization:** Role-based permissions via `spatie/laravel-permission`
- **Database:** MySQL (primary), SQLite (testing)
- **Queue:** Database-backed queue
- **Frontend Build:** Vite 7.x with Tailwind CSS 4.x
- **Testing:** Pest PHP 4.x

## Features

### Authentication & Authorization
- JWT-based authentication with secure cookie storage
- Role-based access control (RBAC) with granular permissions
- Password reset flow with OTP verification via Email and/or SMS
- Self-service password change limits with admin override
- Activity logging for all authentication events

### Warehouse Management
- Multi-warehouse support with branch associations
- Warehouse capacity tracking and status management
- Warehouse accessibility control per user

### Stock Operations
- Stock-in batch management with auto-generated batch numbers
- Stock-in batch types (direct/supplier)
- Stock bag tracking with barcode/QR token generation
- Quality inspection workflow
- Stock dispatch with gate pass confirmation and exit tracking
- Receipt generation and management

### Inventory Management
- Item types and varieties catalog
- Inventory balance reports
- Inventory valuation reports
- Inventory aging reports
- Low stock alerts
- Batch-wise inventory reports

### Supply Chain
- Supplier management with bank account details
- Buyer management
- Invoice generation and payment status tracking

### Vehicle & Logistics
- Vehicle registration and availability tracking
- Vehicle entry/exit logging
- Driver details management

### Organization Structure
- Countries, provinces, districts, and branches
- Departments and designations
- Employee management with ID number lookup

### System Features
- Dashboard with summary, analytics, and operational metrics
- Bulk import engine with template generation
- Activity logging and audit trail
- System settings management
- SMS gateway integration (Dialog SMS)
- Database export functionality
- API rate limiting and throttling
- Security headers and input sanitization middleware

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/login` | User login |
| POST | `/api/v1/logout` | User logout |
| GET | `/api/v1/me` | Get authenticated user |
| POST | `/api/v1/forgot-password` | Request password reset OTP |
| POST | `/api/v1/verify-otp` | Verify OTP code |
| POST | `/api/v1/reset-password` | Reset password |

### Resources (All require authentication)

| Resource | Endpoint Prefix | Operations |
|----------|----------------|------------|
| Users | `/api/v1/users` | CRUD + toggle status, list |
| Roles | `/api/v1/roles` | CRUD + available roles list |
| Permissions | `/api/v1/permissions` | CRUD + permission list |
| Branches | `/api/v1/branches` | CRUD + toggle status, list |
| Departments | `/api/v1/departments` | CRUD + designations, toggle status |
| Designations | `/api/v1/designations` | CRUD + toggle status, list |
| Groups | `/api/v1/groups` | CRUD + toggle status, list |
| Countries | `/api/v1/countries` | CRUD + toggle status, list |
| Provinces | `/api/v1/provinces` | CRUD + toggle status, list |
| Districts | `/api/v1/districts` | CRUD + toggle status, list |
| Item Types | `/api/v1/item-types` | CRUD + toggle status, list |
| Item Varieties | `/api/v1/item-varieties` | CRUD + toggle status, list |
| Banks | `/api/v1/banks` | CRUD + toggle status, list |
| Warehouses | `/api/v1/warehouses` | CRUD + toggle status, list, accessible |
| Suppliers | `/api/v1/suppliers` | CRUD + toggle status, list |
| Vehicles | `/api/v1/vehicles` | CRUD + toggle status, list, availability |
| Vehicle Logs | `/api/v1/vehicle-logs` | CRUD + exit log |
| Stock In | `/api/v1/stock-ins` | CRUD + list, status update |
| Stock Bags | `/api/v1/stock-bags` | CRUD + list, status, batch details |
| Receipts | `/api/v1/receipts` | Index, show, status update |
| Quality Inspections | `/api/v1/quality-inspections` | CRUD |
| Buyers | `/api/v1/buyers` | CRUD + toggle status, list |
| Invoices | `/api/v1/invoices` | CRUD + payment status update |
| Stock Dispatches | `/api/v1/stock-dispatches` | CRUD + confirm, gate exit |
| Barcode Tokens | `/api/v1/barcode-tokens` | Index, show, verify, verify status |
| Activity Logs | `/api/v1/activity-logs` | Index, show (read-only) |

### Reports & Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/inventory-reports/balance` | Inventory balance report |
| GET | `/api/v1/inventory-reports/valuation` | Inventory valuation report |
| GET | `/api/v1/inventory-reports/aging` | Inventory aging report |
| GET | `/api/v1/inventory-reports/alerts` | Low stock alerts |
| GET | `/api/v1/reports/batch-wise` | Batch-wise report |
| GET | `/api/v1/dashboard/summary` | Dashboard summary |
| GET | `/api/v1/dashboard/analytics` | Dashboard analytics |
| GET | `/api/v1/dashboard/operational` | Operational metrics |

### Utilities
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/import/tables` | List importable tables |
| GET | `/api/v1/import/catalog` | Import catalog |
| GET | `/api/v1/import/{table}/template` | Download import template |
| POST | `/api/v1/import/{table}` | Import data |
| GET | `/api/v1/sms/logs` | SMS logs |
| POST | `/api/v1/sms/send` | Send SMS |
| GET | `/api/v1/sms/balance` | SMS balance |
| GET | `/api/v1/settings` | Get system settings |
| POST | `/api/v1/settings` | Update system settings |
| GET | `/api/v1/database/export` | Export database |

## Project Structure

```
app/
├── Http/
│   ├── Controllers/V1/       # API controllers (34 controllers)
│   ├── Middleware/            # Security headers, input sanitization
│   └── Requests/             # Form request validation
├── Mail/                     # Email templates (OTP)
├── Models/                   # Eloquent models (32 models)
├── Providers/                # Service providers
├── Services/                 # Business logic services
│   ├── BulkImportService.php
│   ├── DatabaseService.php
│   └── SmsService.php
└── Traits/                   # Shared traits (ActivityLogTrait)

database/
├── migrations/               # Database migrations (40 files)
└── seeders/                  # Database seeders

routes/
├── api.php                   # API route definitions
└── v1.php                    # V1 route group (267 lines)
```

## Installation

### Prerequisites
- PHP 8.2 or higher
- Composer
- MySQL 5.7+ or MariaDB 10.3+
- Node.js 18+ (for frontend assets)

### Setup

1. Clone the repository:
```bash
git clone <repository-url>
cd cdp-warehouse-api
```

2. Install PHP dependencies:
```bash
composer install
```

3. Copy environment file:
```bash
cp .env.example .env
```

4. Generate application key:
```bash
php artisan key:generate
```

5. Configure your database in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cdp_warehouse
DB_USERNAME=root
DB_PASSWORD=
```

6. Run migrations and seeders:
```bash
php artisan migrate --seed
```

7. Install Node.js dependencies:
```bash
npm install
```

8. Generate JWT secret:
```bash
php artisan jwt:secret
```

## Development

Start the development server with all services:

```bash
composer dev
```

This runs concurrently:
- `php artisan serve` - API server
- `php artisan queue:listen --tries=1` - Queue worker
- `php artisan pail --timeout=0` - Log viewer
- `npm run dev` - Vite dev server

### Individual Commands

```bash
php artisan serve              # Start API server
php artisan queue:listen       # Start queue worker
npm run dev                    # Start Vite dev server
npm run build                  # Build production assets
```

## Testing

```bash
composer test                  # Run all tests
php artisan test               # Run tests via artisan
php artisan test --unit        # Run unit tests only
php artisan test --feature     # Run feature tests only
```

## Environment Variables

Key environment variables to configure:

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application name | Laravel |
| `APP_ENV` | Environment | local |
| `APP_DEBUG` | Debug mode | true |
| `DB_CONNECTION` | Database driver | mysql |
| `DB_DATABASE` | Database name | pos_api |
| `JWT_SECRET` | JWT authentication secret | - |
| `JWT_TTL` | JWT token lifetime (minutes) | 60 |
| `MAIL_MAILER` | Mail driver | log |
| `SMS_API_KEY` | SMS gateway API key | - |

## Database Schema

The application uses 40 database tables covering:

- **Organization:** countries, provinces, districts, branches, departments, designations, groups
- **Users:** users, employees, roles, permissions, personal_access_tokens
- **Inventory:** item_types, item_varieties, warehouses, stock_in_batches, stock_in_batch_items, stock_bags
- **Supply Chain:** suppliers, supplier_bank_accounts, buyers
- **Operations:** vehicles, vehicle_logs, receipts, quality_inspections, stock_dispatches, dispatch_items
- **Billing:** invoices, banks
- **Tracking:** barcode_tokens, barcode_token_batches, activity_logs
- **System:** system_settings, password_otps, sms_logs, cache, jobs

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Copyright (c) 2025 Mohamed Insath
