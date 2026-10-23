/**
 * Animazon Textiles End-to-End Business Scenario Data
 * Story: Animazon Textiles buys 400 plain cloth from MCR Textiles,
 * sends for coloring to Vijay Dyeing via Delivery Challan for Job Work,
 * receives finished royal blue dyed cloth, and sells B2B to Vogue Apparels with profit.
 */
module.exports = {
  company: {
    legal_name: 'Animazon Textiles Private Limited',
    trade_name: 'Animazon Textiles',
    state_code: '33',
    state_name: 'Tamil Nadu',
    gstin: '33AAICA2026D1Z8',
    city: 'Erode',
    currency: 'INR'
  },
  supplier: {
    name: 'Karthik Subramanian',
    company_name: 'MCR Textiles Pvt Ltd',
    vat_number: '33AAACM1234D1Z5',
    phone_number: '9876543210',
    email: 'orders@mcrtextiles.in',
    address: '24, Textile Mill Road, Near Ring Road',
    city: 'Erode',
    state: 'Tamil Nadu',
    postal_code: '638001',
    country: 'India'
  },
  job_worker: {
    name: 'Vijay Anand',
    company_name: 'Vijay Dyeing Processors',
    vat_number: '33AAACV5678E1Z9',
    phone_number: '9842109876',
    email: 'info@vijaydyeing.com',
    address: '5, Industrial Dyeing Zone, SIPCOT',
    city: 'Perundurai',
    state: 'Tamil Nadu',
    postal_code: '638052',
    country: 'India'
  },
  customer: {
    name: 'Suresh Kumar',
    company_name: 'Vogue Apparels B2B',
    vat_number: '33AABCV9876E1Z1',
    phone_number: '9894012345',
    email: 'procurement@vogueapparels.in',
    address: '102, Garment Avenue, Avinashi Road',
    city: 'Coimbatore',
    state: 'Tamil Nadu',
    postal_code: '641018',
    country: 'India'
  },
  category: {
    name: 'Textiles & Handlooms'
  },
  products: {
    raw_material: {
      name: 'Plain Grey Fabric (Cloth)',
      code: 'TEX-PLAIN-400',
      type: 'standard',
      barcode_symbology: 'code128',
      cost: 150.00,
      price: 180.00,
      unit_id: 1, // Pc
      tax_id: 2,  // GST 5%
      initial_stock: 0
    },
    finished_good: {
      name: 'Animazon Dyed Premium Fabric (Royal Blue)',
      code: 'TEX-DYED-BLUE',
      type: 'standard',
      barcode_symbology: 'code128',
      cost: 210.00, // Rs. 150 base + Rs. 60 job work dyeing charge
      price: 380.00,
      unit_id: 1, // Pc
      tax_id: 2,  // GST 5%
      initial_stock: 0
    }
  },
  quantities: {
    purchased: 400,
    job_work_dispatched: 400,
    finished_received: 400,
    sold_b2b: 350,
    closing_stock: 50
  },
  financials: {
    purchase_unit_price: 150.00,
    purchase_subtotal: 60000.00,
    purchase_gst: 3000.00,       // 5%
    purchase_grand_total: 63000.00,
    
    dyeing_unit_charge: 60.00,
    dyeing_total_charge: 24000.00,

    finished_cost_per_unit: 210.00, // 150 + 60
    
    sale_unit_price: 380.00,
    sale_qty: 350,
    sale_subtotal: 133000.00,     // 350 * 380
    sale_gst: 6650.00,            // 5%
    sale_grand_total: 139650.00,
    
    cogs: 73500.00,               // 350 * 210
    gross_profit: 59500.00,       // 133000 - 73500
    profit_margin_percent: '80.95%' // (59500 / 73500) * 100
  }
};
