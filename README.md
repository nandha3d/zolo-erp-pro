# zoloERP &mdash; Enterprise ERP & Double-Entry Accounting Platform
**Proprietary Software developed & maintained by [Animazon](https://animazon.in)**

---

## 📌 Overview

**zoloERP** is an enterprise-grade ERP engine and financial management software platform designed and built by **Animazon**. Combining high-performance commercial operations (Point of Sale, Inventory Management, Multi-warehouse Logistics, Procurement, and Invoicing) with an automated, GAAP/IFRS-compliant **Double-Entry Accounting Engine** and a headless REST API v1 layer, zoloERP powers retail chains, wholesale distributors, and digital commerce enterprises.

---

## 🚀 Key Modules & Capabilities

### 1. 💼 Commercial & Inventory Engine
- **Point of Sale (POS)**: High-speed barcode-scanner-ready POS terminal with thermal/receipt printer support, split payments, coupons, gift cards, and multi-currency billing.
- **Inventory & Multi-Warehouse**: Real-time batch tracking, expiration management, automated average COGS calculation, stock adjustments, and multi-warehouse transfers.
- **Sales & Procurement**: Complete lifecycle management from quotations and sales orders to packing slips, challans, purchase orders, and supplier return debits.

### 2. 📊 Double-Entry Accounting Engine (Ledger & Financials)
- **Hierarchical Chart of Accounts (COA)**: Five standard top-level categories (*Assets, Liabilities, Equity, Revenue, Expenses*) with parent-child hierarchy and real-time balance propagation.
- **Journal Entries & General Ledger**: Automatic double-entry bookkeeping on all business transactions (Sales, Purchases, Payments, Returns, Expenses, Payroll) guaranteeing total debits equal total credits ($$\sum \text{Debit} = \sum \text{Credit}$$).
- **Financial Intelligence Reports**:
  - **Trial Balance**: Real-time balance verification.
  - **Profit & Loss (Income Statement)**: Operating revenue, COGS, gross margin, operating expenses, and net profit.
  - **Balance Sheet**: Point-in-time financial position balancing Assets against Liabilities + Equity.

### 3. 🌐 Headless RESTful API v1 Engine
zoloERP provides an integrated, token-secured RESTful API layer (`/api/v1/...`) enabling seamless omnichannel integration with mobile applications, eCommerce platforms, third-party ERPs, and automated workflows.

### 4. 🎨 Intelligent Adaptive UI System
- **Theme-Adaptive Side Panel**: Dedicated theme-colored side panel that seamlessly transitions between Light mode and Dark mode.
- **Module-Specific Colorful Badges**: Instant visual identification for each functional domain (Inventory, Commercial, Finance, Operations, Analytics).
- **Modern Analytical Graphics**: Data-driven Chart.js visualizations with smooth Bézier curvature, translucent dynamic gradients, and precision floating tooltips.
- **Intelligent Grid Alignment**: DataTables with pill search inputs, select controls, and monospace monetary alignments.

---

## 🔒 Licensing & Verification

zoloERP is proprietary enterprise software owned by **Animazon**. 
- **Official Portal**: [https://animazon.in](https://animazon.in)
- **License Verification Authority**: All software activation checks, license renewals, and official updates are processed exclusively via Animazon's secure licensing servers at:
  ```
  https://api.animazon.in/v1/zoloerp/
  ```

---

## ⚙️ Requirements & Installation

- **PHP**: 8.2 or higher (Extensions required: `BCMath`, `Ctype`, `cURL`, `DOM`, `Fileinfo`, `JSON`, `Mbstring`, `OpenSSL`, `PDO`, `pdo_mysql`, `Tokenizer`, `XML`, `Zip`)
- **Database**: MySQL 8.0+ or MariaDB 10.5+
- **Web Server**: Nginx or Apache with `mod_rewrite` enabled

### Setup Commands
```bash
# Clone or deploy zoloERP
composer install --no-dev --optimize-autoloader

# Run Database Migrations & Seeds
php artisan migrate --force
php artisan db:seed --force

# Clear and optimize application caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📄 Intellectual Property & Support

- **Software**: zoloERP Enterprise Platform
- **Developer & Rights Holder**: **Animazon**
- **Support & Inquiries**: `support@animazon.in` | [https://animazon.in](https://animazon.in)
- **Copyright**: &copy; 2026 Animazon. All Rights Reserved.
