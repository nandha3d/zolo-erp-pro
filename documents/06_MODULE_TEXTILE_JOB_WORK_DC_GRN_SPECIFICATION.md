# Section 06: Textile Job-Work Loop (DC & GRN)
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `DC AND GRN 05.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In the textile manufacturing hub of Tamil Nadu, goods undergo external specialized processing:
$$\\text{Grey Fabric (Mulls/Yarn)} \\xrightarrow{\\text{Delivery Challan (DC)}} \\text{Processing Factory (Bleaching/Dyeing/Ironing/Centering)} \\xrightarrow{\\text{Goods Received Note (GRN)}} \\text{Finished Fabric (Bedspreads/Dhotis)}$$

### Critical Domain Invariants:
1. **Non-Commercial Tax Movement:** An outward Delivery Challan is **NOT** a sale. No sales tax (GST) is levied when fabric leaves for a processing mill. Under Section 143 of the CGST Act, goods may be moved to a job worker under a Delivery Challan without paying tax, provided they return within 1 year.
2. **Outside Stock Godown Tracking:** When 5,000 meters of Grey Mull are sent to *Radhakrishna Bleaching Works* on a DC, inventory drops from *Main Godown* and increases in *Jobwork Outside Godown (Radhakrishna Bleaching)*.
3. **Goods Received Note (GRN):** When processed goods return, a GRN is recorded noting the inward quantity, shrinkage/wastage percentage (typically 2% to 4%), and received finished pieces.
4. **Triangular Reconciliation:** The system links **DC No** $\\rightarrow$ **GRN No** $\\rightarrow$ **Service Purchase Bill**. When the dyer submits their tax invoice for processing charges (e.g. 5,000 meters at Rs. 2.50/meter = Rs. 12,500 + 5% GST), the invoice is booked under Service Purchase, relieving the GRN without altering fabric inventory quantity a second time.

---

## 2. Database Schema Specification

```sql
CREATE TABLE `optech_delivery_challans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `financial_year_id` BIGINT UNSIGNED NOT NULL,
  `challan_no` VARCHAR(50) NOT NULL COMMENT 'e.g. DC-26/0084',
  `challan_date` DATE NOT NULL,
  `job_worker_id` INT UNSIGNED NOT NULL COMMENT 'Links to suppliers/job workers',
  `process_type` ENUM('BLEACHING', 'DYEING', 'PRINTING', 'IRONING', 'CENTERING', 'STITCHING') NOT NULL,
  `from_warehouse_id` INT UNSIGNED NOT NULL COMMENT 'Origin raw godown',
  `to_jobworker_warehouse_id` INT UNSIGNED NOT NULL COMMENT 'Virtual outside godown',
  `transport_agency` VARCHAR(100) NULL,
  `vehicle_no` VARCHAR(30) NULL,
  `total_meters_dispatched` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `total_pieces_dispatched` INT UNSIGNED NOT NULL DEFAULT 0,
  `expected_return_date` DATE NULL,
  `status` ENUM('OPEN', 'PARTIALLY_RECEIVED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'OPEN',
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_dc_no` (`company_id`, `challan_no`),
  KEY `idx_optech_dc_worker` (`job_worker_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_delivery_challan_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `challan_id` BIGINT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL COMMENT 'Raw grey fabric',
  `hsn_code` VARCHAR(20) NOT NULL,
  `dispatched_qty` DECIMAL(12,3) NOT NULL,
  `unit_id` INT UNSIGNED NOT NULL,
  `rate_per_unit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Notional cost for insurance',
  `received_qty` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `pending_qty` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  KEY `fk_optech_dc_items` (`challan_id`),
  CONSTRAINT `fk_optech_dc_items` FOREIGN KEY (`challan_id`) REFERENCES `optech_delivery_challans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_goods_received_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `financial_year_id` BIGINT UNSIGNED NOT NULL,
  `grn_no` VARCHAR(50) NOT NULL COMMENT 'e.g. GRN-26/0045',
  `grn_date` DATE NOT NULL,
  `challan_id` BIGINT UNSIGNED NOT NULL COMMENT 'Origin DC reference',
  `job_worker_id` INT UNSIGNED NOT NULL,
  `to_finished_warehouse_id` INT UNSIGNED NOT NULL COMMENT 'Destination finished godown',
  `received_meters` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `wastage_shrinkage_meters` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `process_charge_per_meter` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `estimated_service_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `is_invoiced` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Linked to Service Purchase Bill',
  `service_purchase_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_grn_no` (`company_id`, `grn_no`),
  KEY `idx_optech_grn_dc` (`challan_id`),
  CONSTRAINT `fk_optech_grn_dc` FOREIGN KEY (`challan_id`) REFERENCES `optech_delivery_challans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Reconciliation State Machine

```
              +--------------------------+
              | 1. Issue Outward DC      |  Inventory moves from Raw Godown
              |    (Status: OPEN)        |  to "Jobworker Outside Godown"
              +--------------------------+
                           |
                           v
              +--------------------------+
              | 2. Inward GRN Receipt    |  Processed cloth returns.
              |    (Status: PARTIAL/DONE)|  Moves to "Finished Godown".
              +--------------------------+
                           |
                           v
              +--------------------------+
              | 3. Service Purchase Bill |  Jobworker submits invoice for charges.
              |    (F12 Service Type)    |  Books processing expense & GST.
              +--------------------------+  No duplicate stock addition!
```

---

## 4. Verification Checklist

- [ ] Creating an outward DC decreases available stock in Main Godown and increases stock in the job-worker's virtual warehouse.
- [ ] No sales tax or GST invoice is generated upon DC issuance.
- [ ] When recording a GRN, specifying `4,850 MTR` received with `150 MTR` shrinkage closes out a `5,000 MTR` DC cleanly.
- [ ] Booking the job-worker's service invoice links the GRN without double-counting fabric inventory.