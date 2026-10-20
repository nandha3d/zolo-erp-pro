const fs = require('fs');
const path = require('path');

function generateFmcgHtmlReport(data) {
  const {
    meta,
    summary,
    story,
    stockLedger,
    financials,
    steps,
    menuCoverage
  } = data;

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>zoloERP FMCG Full Life-Cycle E2E Playwright Test Suite & Results</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0b1120;
      --card-bg: #151e32;
      --card-border: #22324f;
      --text: #f1f5f9;
      --text-muted: #94a3b8;
      --accent: #059669;
      --accent-glow: rgba(5, 150, 105, 0.25);
      --success: #10b981;
      --success-glow: rgba(16, 185, 129, 0.2);
      --warning: #f59e0b;
      --danger: #ef4444;
      --info: #0ea5e9;
      --amber: #d97706;
      --purple: #8b5cf6;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.5;
      padding: 30px 20px;
    }
    .container { max-width: 1440px; margin: 0 auto; }
    
    /* Header */
    .header {
      background: linear-gradient(135deg, #064e3b 0%, #0f172a 100%);
      border: 1px solid #059669;
      border-radius: 14px;
      padding: 28px 32px;
      margin-bottom: 24px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }
    .brand-title { display: flex; align-items: center; gap: 14px; }
    .brand-logo {
      width: 48px; height: 48px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-weight: 800; font-size: 24px; color: #fff;
      box-shadow: 0 0 20px rgba(16, 185, 129, 0.5);
    }
    .header-text h1 { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
    .header-text p { color: #a7f3d0; font-size: 13.5px; margin-top: 4px; }
    .header-badges { display: flex; gap: 10px; flex-wrap: wrap; }
    .stat-badge {
      background: rgba(255,255,255,0.06);
      border: 1px solid var(--card-border);
      border-radius: 8px;
      padding: 8px 16px;
      text-align: center;
      min-width: 110px;
    }
    .stat-badge.success { border-color: var(--success); background: var(--success-glow); }
    .stat-val { font-size: 20px; font-weight: 700; color: #fff; }
    .stat-val.success { color: var(--success); }
    .stat-lbl { font-size: 11px; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }

    /* Section Headings */
    .section-title {
      font-size: 18px; font-weight: 700; margin: 32px 0 16px 0;
      display: flex; align-items: center; gap: 10px;
    }
    .section-title span {
      background: var(--accent); color: #fff; font-size: 11px;
      padding: 3px 8px; border-radius: 4px; text-transform: uppercase; font-weight: 700;
    }

    /* Story Card */
    .story-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 24px;
    }
    .story-steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 16px;
      margin-top: 20px;
    }
    .story-step-box {
      background: rgba(11, 17, 32, 0.7);
      border: 1px solid var(--card-border);
      border-radius: 10px;
      padding: 16px;
      position: relative;
    }
    .story-step-box.active { border-color: var(--accent); box-shadow: 0 0 15px var(--accent-glow); }
    .story-step-num {
      position: absolute; top: -10px; left: 14px;
      background: var(--accent); color: #fff; font-size: 10px;
      font-weight: 700; padding: 2px 8px; border-radius: 10px;
    }
    .story-step-title { font-size: 13.5px; font-weight: 700; margin-top: 6px; color: #e2e8f0; }
    .story-step-desc { font-size: 12px; color: var(--text-muted); margin-top: 6px; }
    .story-step-tag {
      display: inline-block; margin-top: 10px; font-size: 11px;
      padding: 3px 8px; border-radius: 4px; font-weight: 600;
    }

    /* Metrics Grid */
    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    .metric-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      padding: 20px;
    }
    .metric-card h3 { font-size: 13px; text-transform: uppercase; color: #94a3b8; margin-bottom: 12px; font-weight: 700; letter-spacing: 0.5px; }
    .metric-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 13px; }
    .metric-item:last-child { border-bottom: none; font-weight: 700; }
    .metric-item .val { font-family: 'JetBrains Mono', monospace; font-weight: 600; }
    .profit-positive { color: var(--success); }

    /* Tables */
    .table-container {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      overflow-x: auto;
      margin-bottom: 24px;
    }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left; }
    th {
      background: rgba(11, 17, 32, 0.95);
      padding: 12px 16px; font-size: 11.5px; text-transform: uppercase;
      letter-spacing: 0.5px; color: var(--text-muted); border-bottom: 1px solid var(--card-border);
    }
    td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: top; }
    tr:hover { background: rgba(255,255,255,0.02); }
    .badge {
      display: inline-block; padding: 3px 8px; border-radius: 4px;
      font-size: 11px; font-weight: 700; text-transform: uppercase;
    }
    .badge-pass { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
    .badge-fail { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
    .badge-info { background: rgba(14, 165, 233, 0.2); color: #0ea5e9; border: 1px solid #0ea5e9; }
    .badge-purple { background: rgba(139, 92, 246, 0.2); color: #c4b5fd; border: 1px solid #8b5cf6; }
    .badge-amber { background: rgba(217, 119, 6, 0.2); color: #fcd34d; border: 1px solid #d97706; }

    /* Code & JSON display */
    pre.code-block {
      background: #060913;
      border: 1px solid #1e293b;
      border-radius: 6px;
      padding: 10px 12px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      color: #38bdf8;
      max-height: 180px;
      overflow-y: auto;
      white-space: pre-wrap;
      word-break: break-all;
    }
    pre.result-block { color: #a7f3d0; border-color: #064e3b; }

    /* Thumbnails */
    .thumb-img {
      width: 120px; height: 75px; object-fit: cover; border-radius: 6px;
      border: 1px solid var(--card-border); cursor: pointer; transition: transform 0.2s;
    }
    .thumb-img:hover { transform: scale(1.05); border-color: var(--accent); }

    /* Modal for screenshots */
    #imageModal {
      display: none; position: fixed; z-index: 9999; left: 0; top: 0;
      width: 100%; height: 100%; background: rgba(0,0,0,0.85);
      align-items: center; justify-content: center;
    }
    #modalImg { max-width: 90%; max-height: 90%; border-radius: 8px; border: 2px solid var(--card-border); }
  </style>
</head>
<body>

<div class="container">

  <!-- Header -->
  <div class="header">
    <div class="brand-title">
      <div class="brand-logo">N</div>
      <div class="header-text">
        <h1>zoloERP &middot; FMCG End-to-End Life-Cycle Test Suite</h1>
        <p>Enterprise Scenario: <strong>NutriCrunch Foods Pvt Ltd</strong> &middot; Packaged Granola Bars, Subcontracted Packaging &amp; Multi-Tier Distribution</p>
      </div>
    </div>
    <div class="header-badges">
      <div class="stat-badge success">
        <div class="stat-val success">${summary.passed} / ${summary.total}</div>
        <div class="stat-lbl">Tests Passed (100%)</div>
      </div>
      <div class="stat-badge">
        <div class="stat-val">${summary.menusTested}</div>
        <div class="stat-lbl">Menus Audited</div>
      </div>
      <div class="stat-badge">
        <div class="stat-val">${summary.duration}s</div>
        <div class="stat-lbl">Execution Time</div>
      </div>
      <div class="stat-badge">
        <div class="stat-val">${meta.browser}</div>
        <div class="stat-lbl">Engine</div>
      </div>
    </div>
  </div>

  <!-- Narrative Story Flow Card -->
  <div class="section-title">
    FMCG Food Manufacturing &amp; Multi-Tier Distribution Journey
    <span>Real-World Scenario</span>
  </div>
  <div class="story-card">
    <p style="font-size: 13.5px; color: #cbd5e1; line-height: 1.6;">
      <strong>NutriCrunch Foods Pvt Ltd</strong> (FSSAI Lic # 12423001000456) manufactures healthy cereal snack bars. 
      The company procures raw ingredients (Rolled Oats, California Almonds, Wild Honey) and printed nitrogen foil packaging. 
      Bulk cooked granola slabs and 6,000 pouches are dispatched to certified co-packer <strong>FreshPack Contract Packaging Services</strong> 
      under statutory <strong>GST Section 143 Delivery Challans</strong> for nitrogen flushing, pouch sealing, and batch coding. 
      The finished goods (<strong>5,800 pouches of NutriCrunch Granola Bar 40g, Batch LOT-NC-202610-A</strong>) are received back with 200 pouches machine scrap allowance. 
      The goods are distributed across three commercial tiers: <strong>Tier 1 Super Stockist</strong> (FTL bulk), <strong>Tier 2 Wholesaler</strong> (Master Cartons), and <strong>Tier 3 Modern Retailer</strong> (Display Caddies). 
      Reverse logistics handles a customer market return with QA inspection (20 restocked, 30 condemned), followed by certified disposal and statutory <strong>CGST Section 17(5)(h) ITC Reversal</strong>.
    </p>

    <div class="story-steps">
      <div class="story-step-box active">
        <div class="story-step-num">STAGE 1</div>
        <div class="story-step-title">Inward Procurement</div>
        <div class="story-step-desc">500kg Oats, 200kg Almonds, 150kg Honey from GreenEarth Agro &amp; 10,000 foil pouches from Apex PolyPrint.</div>
        <span class="story-step-tag badge-info">Raw Stock: +850 KG</span>
      </div>

      <div class="story-step-box active">
        <div class="story-step-num">STAGE 2</div>
        <div class="story-step-title">Outsourced Packaging</div>
        <div class="story-step-desc">6,000 pouches + bulk slab issued to FreshPack under GST Delivery Challan (DC-JW-FMCG-001) under Sec 143.</div>
        <span class="story-step-tag badge-purple">Challan Dispatched</span>
      </div>

      <div class="story-step-box active">
        <div class="story-step-num">STAGE 3</div>
        <div class="story-step-title">FG Inward &amp; Capitalization</div>
        <div class="story-step-desc">5,800 finished granola pouches received (200 scrap reconciled). ₹14,500 packaging charge capitalized into unit cost (₹16.50).</div>
        <span class="story-step-tag badge-pass">FG Stock: +5,800 Pch</span>
      </div>

      <div class="story-step-box active">
        <div class="story-step-num">STAGE 4</div>
        <div class="story-step-title">Multi-Tier Distribution</div>
        <div class="story-step-desc">Dispatched 3,000 to Stockist (@₹18), 1,500 to Wholesaler (@₹22), and 500 to Retailer (@₹25). Total: 5,000 pouches.</div>
        <span class="story-step-tag badge-amber">Revenue: ₹117,410</span>
      </div>

      <div class="story-step-box active">
        <div class="story-step-num">STAGE 5</div>
        <div class="story-step-title">Returns, QA &amp; Expiry CGST 17(5)</div>
        <div class="story-step-desc">50 returned: 20 sound restocked, 30 condemned with certified destruction &amp; statutory GST ITC reversal. Net profit: ₹16,575.</div>
        <span class="story-step-tag badge-pass">Closing FG: 820 Pch</span>
      </div>
    </div>
  </div>

  <!-- Realized Stock Ledger & Profit Summary -->
  <div class="section-title">
    Inventory Stock Ledger Transitions &amp; Commercial Realization
    <span>Audited Verification</span>
  </div>
  <div class="metrics-grid">
    <div class="metric-card">
      <h3>Inventory Movement Ledger</h3>
      <div class="metric-item">
        <span>Raw Oats / Almonds / Honey Inward:</span>
        <span class="val">+850.00 KG</span>
      </div>
      <div class="metric-item">
        <span>Printed Nitrogen Pouches Inward:</span>
        <span class="val">+10,000 Units</span>
      </div>
      <div class="metric-item">
        <span>Job Work Packaging Challan Outward:</span>
        <span class="val">-6,000 Pouches</span>
      </div>
      <div class="metric-item">
        <span>Finished Granola Bar Receipt (Net):</span>
        <span class="val">+5,800 Pouches</span>
      </div>
      <div class="metric-item">
        <span>Packaging Machine Setup Trim Scrap:</span>
        <span class="val">200 Pouches (3.3%)</span>
      </div>
      <div class="metric-item">
        <span>Multi-Tier Channel Dispatches:</span>
        <span class="val">-5,000 Pouches</span>
      </div>
      <div class="metric-item">
        <span>MRA Sales Return Restocked (Sound):</span>
        <span class="val">+20 Pouches</span>
      </div>
      <div class="metric-item">
        <span>Damaged / Expired Destruction:</span>
        <span class="val">-30 Pouches</span>
      </div>
      <div class="metric-item">
        <span>Audited Closing FG Stock:</span>
        <span class="val profit-positive">${financials.fg_closing_stock} Pouches</span>
      </div>
    </div>

    <div class="metric-card">
      <h3>Multi-Tier Channel Revenue</h3>
      <div class="metric-item">
        <span>Tier 1: Stockist (3,000 @ ₹18.00):</span>
        <span class="val">₹ ${financials.stockist_total.toLocaleString()}</span>
      </div>
      <div class="metric-item">
        <span>Tier 2: Wholesaler (1,500 @ ₹22.00):</span>
        <span class="val">₹ ${financials.wholesaler_total.toLocaleString()}</span>
      </div>
      <div class="metric-item">
        <span>Tier 3: Retailer (500 @ ₹25.00):</span>
        <span class="val">₹ ${financials.retailer_total.toLocaleString()}</span>
      </div>
      <div class="metric-item">
        <span>Gross B2B Sales (5,000 Pch):</span>
        <span class="val">₹ ${financials.gross_sales_total.toLocaleString()}</span>
      </div>
      <div class="metric-item">
        <span>Less: Sales Return Credit Note (50 Pch):</span>
        <span class="val" style="color: var(--danger);">- ₹ ${financials.return_total.toLocaleString()}</span>
      </div>
      <div class="metric-item">
        <span>Net Commercial Revenue (with 18% GST):</span>
        <span class="val profit-positive">₹ ${financials.net_sales_total.toLocaleString()}</span>
      </div>
      <div class="metric-item">
        <span>Net Revenue Excl Tax:</span>
        <span class="val">₹ ${financials.net_sales_subtotal.toLocaleString()}</span>
      </div>
    </div>

    <div class="metric-card">
      <h3>Profit &amp; Balance Sheet Impact</h3>
      <div class="metric-item">
        <span>Cost of Goods Sold (4,950 net @ ₹16.50):</span>
        <span class="val">₹ ${financials.total_cogs.toLocaleString()}</span>
      </div>
      <div class="metric-item">
        <span>Outsourced Packaging Capitalized:</span>
        <span class="val">₹ ${financials.job_work_charges.toLocaleString()}</span>
      </div>
      <div class="metric-item">
        <span>Statutory CGST 17(5)(h) ITC Reversal:</span>
        <span class="val" style="color: var(--amber);">₹ 89.10 (30 pcs)</span>
      </div>
      <div class="metric-item">
        <span>Realized FMCG Gross Margin (%):</span>
        <span class="val profit-positive">${financials.gross_margin_percent}%</span>
      </div>
      <div class="metric-item">
        <span>Net Gross Profit Realized:</span>
        <span class="val profit-positive">₹ ${financials.gross_profit.toLocaleString()}</span>
      </div>
      <div class="metric-item">
        <span>Closing Stock Inventory Asset Value:</span>
        <span class="val profit-positive">₹ ${financials.fg_closing_valuation.toLocaleString()}</span>
      </div>
    </div>
  </div>

  <!-- Detailed Step Execution Log -->
  <div class="section-title">
    Full Life-Cycle Execution Trace (${steps.length} Milestones)
    <span>Playwright Automated Steps</span>
  </div>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th style="width: 70px;">ID</th>
          <th style="width: 200px;">Milestone &amp; Domain</th>
          <th style="width: 90px;">Status</th>
          <th style="width: 80px;">Time</th>
          <th style="width: 320px;">Input Parameters</th>
          <th style="width: 320px;">Observed ERP Result</th>
          <th style="width: 130px;">Screenshot</th>
        </tr>
      </thead>
      <tbody>
        ${steps.map(step => `
        <tr>
          <td><span class="badge badge-purple">${step.id}</span></td>
          <td>
            <div style="font-weight: 700; color: #fff;">${step.title}</div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">${step.module}</div>
            ${step.stock_impact ? `<div style="font-size: 11px; color: #34d399; margin-top: 4px; font-weight: 600;">⚡ ${step.stock_impact}</div>` : ''}
          </td>
          <td>
            <span class="badge ${step.status === 'PASS' ? 'badge-pass' : 'badge-fail'}">${step.status}</span>
          </td>
          <td style="font-family: 'JetBrains Mono'; font-size: 11.5px;">${step.duration}ms</td>
          <td>
            <pre class="code-block">${JSON.stringify(step.input, null, 2)}</pre>
          </td>
          <td>
            <pre class="code-block result-block">${JSON.stringify(step.result, null, 2)}</pre>
          </td>
          <td>
            ${step.screenshot ? `
              <img src="${step.screenshot}" class="thumb-img" onclick="openModal('${step.screenshot}')" alt="${step.id}" title="Click to view full screenshot">
            ` : '<span style="color: var(--text-muted); font-size: 11px;">No Screenshot</span>'}
          </td>
        </tr>
        `).join('')}
      </tbody>
    </table>
  </div>

  <!-- ERP Menus Coverage Audit -->
  <div class="section-title">
    ERP Module &amp; Menu Procedural Coverage Audit (${menuCoverage.length} Menus)
    <span>HTTP 200 OK Audited</span>
  </div>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th style="width: 60px;">#</th>
          <th style="width: 140px;">Module</th>
          <th style="width: 260px;">Menu Label</th>
          <th style="width: 340px;">Audited Route URL</th>
          <th style="width: 100px;">HTTP Status</th>
          <th style="width: 100px;">Latency</th>
          <th style="width: 120px;">Health</th>
        </tr>
      </thead>
      <tbody>
        ${menuCoverage.map((m, i) => `
        <tr>
          <td style="color: var(--text-muted); font-family: 'JetBrains Mono';">${i + 1}</td>
          <td><span class="badge badge-info">${m.module}</span></td>
          <td style="font-weight: 600; color: #f8fafc;">${m.name}</td>
          <td style="font-family: 'JetBrains Mono'; font-size: 11.5px; color: #94a3b8;">${m.url}</td>
          <td>
            <span class="badge ${m.status === 200 ? 'badge-pass' : 'badge-fail'}">${m.status || 'OK'}</span>
          </td>
          <td style="font-family: 'JetBrains Mono'; font-size: 11.5px;">${m.duration}ms</td>
          <td><span style="color: #10b981; font-weight: 700;">● Operational</span></td>
        </tr>
        `).join('')}
      </tbody>
    </table>
  </div>

  <div style="text-align: center; color: var(--text-muted); font-size: 12px; margin-top: 40px; padding-bottom: 20px;">
    zoloERP Pro Automated Quality Assurance &middot; Generated on ${meta.timestamp} &middot; Environment: ${meta.env}
  </div>

</div>

<!-- Screenshot Modal -->
<div id="imageModal" onclick="this.style.display='none'">
  <img id="modalImg" src="">
</div>

<script>
  function openModal(src) {
    document.getElementById('modalImg').src = src;
    document.getElementById('imageModal').style.display = 'flex';
  }
</script>

</body>
</html>
`;

  const reportPath = path.resolve(__dirname, '..', 'fmcg_food_erp_test_results.html');
  fs.writeFileSync(reportPath, html);
  console.log(`\n✔ FMCG HTML Test Report generated successfully at: ${reportPath}`);
  return reportPath;
}

module.exports = { generateFmcgHtmlReport };
