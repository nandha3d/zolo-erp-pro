/**
 * FMCG Enterprise End-to-End Life Cycle Scenario Data
 * Vertical: Fast-Moving Consumer Goods (FMCG) / Food & Beverage Manufacturing & Multi-Tier Distribution
 * Company: NutriCrunch Foods Private Limited (FSSAI Lic # 12423001000456)
 * Product: NutriCrunch Almond & Honey Granola Bar 40g (LOT-NC-202610-A)
 * Supply Chain:
 *   1. Raw Ingredients & Packaging Procurement (Agro Supplier + PolyPrint Supplier)
 *   2. In-house bulk production & Outsourced Subcontract Packaging under GST Sec 143 (FreshPack Services)
 *   3. Multi-tier Distribution: Super Stockist (FTL), Wholesaler (Master Carton), Modern Retailer (Caddy)
 *   4. Reverse Logistics: Market Return Authorization (MRA) with QA grading (restock sound vs scrap damage)
 *   5. Expiry & Damage Disposal: Certified destruction with CGST Sec 17(5)(h) ITC Reversal
 *   6. Multi-party Double-Entry Settlement and Gross Margin Profit Realization
 */
module.exports = {
  company: {
    legal_name: 'NutriCrunch Foods Private Limited',
    trade_name: 'NutriCrunch Foods',
    state_code: '33',
    state_name: 'Tamil Nadu',
    gstin: '33AAACN5432B1Z4',
    fssai_license: '12423001000456',
    address: 'Plot 15, Agro-Food Processing Cluster, Kangeyam Road',
    city: 'Coimbatore',
    postal_code: '641018',
    currency: 'INR'
  },
  categories: {
    ingredients: 'Food & Agro Raw Ingredients',
    packaging: 'Flexible Packaging Materials',
    finished_goods: 'Ready-To-Eat Granola & Snack Bars'
  },
  units: {
    kg: { code: 'Kg', name: 'Kilogram' },
    pouch: { code: 'Pch', name: 'Pouch' },
    caddy: { code: 'Cdy', name: '12-Pouch Caddy' },
    carton: { code: 'Ctn', name: '72-Pouch Master Carton' }
  },
  suppliers: {
    agro: {
      name: 'Senthil Velan',
      company_name: 'GreenEarth Agro Commodities Pvt Ltd',
      vat_number: '33AABCG1122D1Z3',
      phone_number: '9443210987',
      email: 'supply@greenearthagro.com',
      address: '88, Agricultural APMC Market Yard, NH-47',
      city: 'Erode',
      state: 'Tamil Nadu',
      postal_code: '638002',
      country: 'India'
    },
    packaging: {
      name: 'Praveen Raj',
      company_name: 'Apex PolyPrint Solutions Pvt Ltd',
      vat_number: '33AAACA3344P1Z2',
      phone_number: '9843322110',
      email: 'sales@apexpolyprint.in',
      address: '14, SIDCO Packaging Industrial Estate',
      city: 'Karur',
      state: 'Tamil Nadu',
      postal_code: '639004',
      country: 'India'
    }
  },
  job_worker: {
    name: 'Gopalakrishnan K',
    company_name: 'FreshPack Contract Packaging Services',
    vat_number: '33AAACF8899K1Z7',
    phone_number: '9894455667',
    email: 'operations@freshpackservices.com',
    address: 'Plot 42, Food Processing Park, SIPCOT',
    city: 'Perundurai',
    state: 'Tamil Nadu',
    postal_code: '638052',
    country: 'India',
    packaging_rate_per_unit: 2.50
  },
  customers: {
    super_stockist: {
      name: 'Ramanathan Chettiar',
      company_name: 'Kovai Mega Distributing & C&F Network',
      vat_number: '33AABCK4455E1Z6',
      tier: 'Tier 1 - Super Stockist / C&F Agent',
      phone_number: '9842233445',
      email: 'orders@kovaimegadistributors.com',
      address: '105, Central Logistics Park, Pollachi Road',
      city: 'Coimbatore',
      state: 'Tamil Nadu',
      postal_code: '641021',
      country: 'India',
      ordered_qty: 3000,
      rate: 18.00
    },
    wholesaler: {
      name: 'Mohanlal Mehta',
      company_name: 'City Wholesale Provision Mart',
      vat_number: '33AAACC9988H1Z1',
      tier: 'Tier 2 - Wholesaler / Dealer',
      phone_number: '9822334455',
      email: 'billing@citywholesaleprov.com',
      address: '22, Big Bazaar Street, Town Hall',
      city: 'Coimbatore',
      state: 'Tamil Nadu',
      postal_code: '641001',
      country: 'India',
      ordered_qty: 1500,
      rate: 22.00
    },
    retailer: {
      name: 'K. Annapoorani',
      company_name: 'Annapoorna Supermarket & Provisions',
      vat_number: '33AAACA7766M1Z8',
      tier: 'Tier 3 - Modern General Trade Retailer',
      phone_number: '9443311223',
      email: 'store@annapoornasuper.com',
      address: '12, Cross Cut Road, Gandhipuram',
      city: 'Coimbatore',
      state: 'Tamil Nadu',
      postal_code: '641012',
      country: 'India',
      ordered_qty: 500,
      rate: 25.00
    }
  },
  products: {
    oats: {
      name: 'Rolled Oats (Food Grade)',
      code: 'FMCG-OATS-KG',
      cost: 40.00,
      price: 55.00,
      unit_name: 'Kg',
      tax_rate: 5,
      hsn: '1104',
      qty_procured: 500
    },
    almonds: {
      name: 'California Almond Kernels',
      code: 'FMCG-ALM-KG',
      cost: 450.00,
      price: 580.00,
      unit_name: 'Kg',
      tax_rate: 12,
      hsn: '0802',
      qty_procured: 200
    },
    honey: {
      name: 'Organic Wild Honey',
      code: 'FMCG-HNY-KG',
      cost: 200.00,
      price: 270.00,
      unit_name: 'Kg',
      tax_rate: 5,
      hsn: '0409',
      qty_procured: 150
    },
    pouches: {
      name: 'Printed Nitrogen Barrier Pouches 40g',
      code: 'FMCG-PKG-POUCH',
      cost: 1.80,
      price: 2.50,
      unit_name: 'Pch',
      tax_rate: 18,
      hsn: '3923',
      qty_procured: 10000
    },
    finished_granola: {
      name: 'NutriCrunch Almond & Honey Granola Bar 40g',
      code: 'FMCG-FG-NUTRICRUNCH-40G',
      cost: 16.50, // Rs. 14.00 materials + Rs. 2.50 contract packaging
      mrp: 30.00,
      unit_name: 'Pch',
      tax_rate: 18,
      hsn: '1904',
      batch_no: 'LOT-NC-202610-A',
      mfg_date: '2026-10-15',
      exp_date: '2027-04-15',
      shelf_life_days: 180,
      qty_produced: 5800,
      job_work_dispatched_pouches: 6000,
      packaging_scrap_pouches: 200
    }
  },
  lifecycle: {
    returns: {
      return_qty: 50,
      sound_restocked_qty: 20,
      damaged_quarantined_qty: 30,
      credit_note_rate: 25.00
    },
    destruction: {
      destroyed_damaged_qty: 30,
      itc_reversal_tax_rate: 18,
      destruction_cert_no: 'CERT-DISP-FMCG-2026-004'
    }
  },
  financials: {
    // Procurement
    oats_cost: 20000.00,      // 500 * 40
    oats_tax: 1000.00,        // 5%
    almonds_cost: 90000.00,   // 200 * 450
    almonds_tax: 10800.00,    // 12%
    honey_cost: 30000.00,     // 150 * 200
    honey_tax: 1500.00,       // 5%
    total_ingredients_cost: 140000.00,
    total_ingredients_tax: 13300.00,
    
    pouches_cost: 18000.00,   // 10000 * 1.80
    pouches_tax: 3240.00,     // 18%
    
    // Job Work Packaging
    job_work_charges: 14500.00, // 5800 * 2.50
    job_work_tax: 2610.00,     // 18%
    
    // Revenue
    stockist_subtotal: 54000.00, // 3000 * 18
    stockist_tax: 9720.00,
    stockist_total: 63720.00,
    
    wholesaler_subtotal: 33000.00, // 1500 * 22
    wholesaler_tax: 5940.00,
    wholesaler_total: 38940.00,
    
    retailer_subtotal: 12500.00, // 500 * 25
    retailer_tax: 2250.00,
    retailer_total: 14750.00,
    
    gross_sales_subtotal: 99500.00,
    gross_sales_tax: 17910.00,
    gross_sales_total: 117410.00,
    
    // Returns Credit Note
    return_subtotal: 1250.00, // 50 * 25
    return_tax: 225.00,
    return_total: 1475.00,
    
    net_sales_subtotal: 98250.00,
    net_sales_tax: 17685.00,
    net_sales_total: 115935.00,
    
    // COGS & Profit
    cogs_per_unit: 16.50,
    net_sold_qty: 4950, // 5000 - 50 returned
    total_cogs: 81675.00, // 4950 * 16.50
    gross_profit: 16575.00, // 98250 - 81675
    gross_margin_percent: 16.87,
    
    // Closing Inventory
    fg_closing_stock: 820, // 5800 - 5000 + 20 sound restocked
    fg_closing_valuation: 13530.00 // 820 * 16.50
  }
};
