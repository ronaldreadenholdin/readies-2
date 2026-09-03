<?php
/**
 * 0609 admin backend — FTD vs trusted list (staff only).
 * Merchants do not upload. Admins upload and maintain the list for a merchant.
 */
$api = 'api.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FTD vs trusted — 0609 admin</title>
  <style>
    :root { --bg:#07111f; --panel:#0d1b2e; --line:#1e3a5f; --text:#e8f1ff; --muted:#8aa4c4; --ok:#3dffa0; --warn:#ffb020; --bad:#ff6b6b; --accent:#3d8bff; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: Inter, system-ui, sans-serif; background:var(--bg); color:var(--text); }
    .wrap { max-width: 980px; margin: 0 auto; padding: 28px 20px 64px; }
    h1 { margin:0 0 6px; font-size: 26px; }
    .sub { color:var(--muted); margin:0 0 22px; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    @media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
    .card { background:var(--panel); border:1px solid var(--line); border-radius:12px; padding:16px; }
    label { display:block; font-size:12px; color:var(--muted); margin:10px 0 4px; }
    input, textarea, select { width:100%; background:#081421; color:var(--text); border:1px solid var(--line); border-radius:8px; padding:9px 10px; }
    button { background:var(--accent); color:#fff; border:0; border-radius:8px; padding:10px 14px; cursor:pointer; font-weight:600; margin-top:12px; }
    button.secondary { background:#16355c; }
    .result { margin-top:12px; white-space:pre-wrap; font-family: ui-monospace, monospace; font-size:12px; background:#061018; padding:10px; border-radius:8px; min-height:48px; }
    .pill { display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; font-weight:700; }
    .pill.ftd { background:#4a1a1a; color:var(--bad); }
    .pill.trusted { background:#123d2a; color:var(--ok); }
    .note { font-size:13px; color:var(--muted); line-height:1.45; }
    code { color:#9fd0ff; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>FTD vs trusted</h1>
    <p class="sub">0609 admin backend. Staff upload the list <strong>for</strong> a merchant. Merchants do not upload.</p>

    <div class="grid">
      <div class="card">
        <h2>Classify a customer</h2>
        <p class="note">Not on the merchant’s list → <span class="pill ftd">FTD</span>. On the list, or paid once successfully → <span class="pill trusted">trusted</span>.</p>
        <label>Merchant ID</label>
        <input id="c_merchant" value="demo-merchant">
        <label>Email</label>
        <input id="c_email" placeholder="customer@example.com">
        <label>Phone</label>
        <input id="c_phone" placeholder="+31612345678">
        <label>Card first 6</label>
        <input id="c_first6" maxlength="6" placeholder="424242">
        <label>Card last 4</label>
        <input id="c_last4" maxlength="4" placeholder="4242">
        <label>Birthday</label>
        <input id="c_bday" placeholder="1990-01-15">
        <label>Full name</label>
        <input id="c_name" placeholder="Jane Doe">
        <button onclick="classify()">Classify</button>
        <div id="c_out" class="result"></div>
      </div>

      <div class="card">
        <h2>Mark paid once (makes trusted)</h2>
        <label>Merchant ID</label>
        <input id="p_merchant" value="demo-merchant">
        <label>Email</label>
        <input id="p_email">
        <label>Phone</label>
        <input id="p_phone">
        <label>Card first 6</label>
        <input id="p_first6" maxlength="6">
        <label>Card last 4</label>
        <input id="p_last4" maxlength="4">
        <label>Birthday</label>
        <input id="p_bday">
        <label>Full name</label>
        <input id="p_name">
        <button class="secondary" onclick="paid()">Record successful payment</button>
        <div id="p_out" class="result"></div>
      </div>
    </div>

    <div class="card" style="margin-top:16px;">
      <h2>Admin upload for a merchant</h2>
      <p class="note">Upload replaces that merchant’s entire list. CSV columns: <code>email,phone,card_first6,card_last4,birthday,full_name,biz</code>. <code>biz</code> is one of: gambling, gaming, mlm, food_supplements, pharma, forex, digital_products, other. Never store a full card number.</p>
      <label>Merchant ID (admin chooses which merchant this list belongs to)</label>
      <input id="u_merchant" value="demo-merchant">
      <label>CSV file</label>
      <input id="u_file" type="file" accept=".csv,text/csv">
      <button onclick="upload()">Upload list for this merchant</button>
      <div id="u_out" class="result"></div>
    </div>
  </div>
  <script>
    const API = <?= json_encode($api) ?>;
    async function post(action, body, out) {
      const res = await fetch(API + '?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const data = await res.json();
      document.getElementById(out).textContent = JSON.stringify(data, null, 2);
      return data;
    }
    function fields(prefix) {
      return {
        merchant: document.getElementById(prefix + '_merchant').value,
        merchant_id: document.getElementById(prefix + '_merchant').value,
        email: document.getElementById(prefix + '_email').value,
        phone: document.getElementById(prefix + '_phone').value,
        card_first6: document.getElementById(prefix + '_first6').value,
        card_last4: document.getElementById(prefix + '_last4').value,
        birthday: document.getElementById(prefix + '_bday').value,
        full_name: document.getElementById(prefix + '_name').value
      };
    }
    async function classify() { await post('classify', fields('c'), 'c_out'); }
    async function paid() { await post('paid', fields('p'), 'p_out'); }
    async function upload() {
      const file = document.getElementById('u_file').files[0];
      if (!file) { document.getElementById('u_out').textContent = 'Choose a CSV file.'; return; }
      const fd = new FormData();
      fd.append('merchant', document.getElementById('u_merchant').value);
      fd.append('merchant_id', document.getElementById('u_merchant').value);
      fd.append('file', file);
      const res = await fetch(API + '?action=upload', { method: 'POST', body: fd });
      document.getElementById('u_out').textContent = JSON.stringify(await res.json(), null, 2);
    }
  </script>
</body>
</html>
