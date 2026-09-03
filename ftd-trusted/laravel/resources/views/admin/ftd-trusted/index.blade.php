@extends('layouts.adminpanel')

@section('title', 'FTD vs trusted')

@section('content')
<div class="container-fluid py-3">
  <h1 class="h3 mb-1">FTD vs trusted</h1>
  <p class="text-muted mb-4">
    Admin backend only. Staff upload and maintain the list <strong>for</strong> a merchant.
    Merchants do not upload. Extra product names are out of this pack.
  </p>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <h2 class="h5">Classify a customer</h2>
          <p class="small text-muted">Not on the merchant’s list → FTD. On the list, or paid once successfully → trusted.</p>
          <label class="form-label">Merchant ID</label>
          <input id="c_merchant" class="form-control" value="demo-merchant">
          <label class="form-label mt-2">Email</label>
          <input id="c_email" class="form-control">
          <label class="form-label mt-2">Phone</label>
          <input id="c_phone" class="form-control">
          <label class="form-label mt-2">Card first 6</label>
          <input id="c_first6" class="form-control" maxlength="6">
          <label class="form-label mt-2">Card last 4</label>
          <input id="c_last4" class="form-control" maxlength="4">
          <label class="form-label mt-2">Birthday</label>
          <input id="c_bday" class="form-control">
          <label class="form-label mt-2">Full name</label>
          <input id="c_name" class="form-control">
          <button class="btn btn-primary mt-3" type="button" onclick="ftdClassify()">Classify</button>
          <pre id="c_out" class="bg-light p-2 mt-3 small"></pre>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <h2 class="h5">Mark paid once (makes trusted)</h2>
          <label class="form-label">Merchant ID</label>
          <input id="p_merchant" class="form-control" value="demo-merchant">
          <label class="form-label mt-2">Email</label>
          <input id="p_email" class="form-control">
          <label class="form-label mt-2">Phone</label>
          <input id="p_phone" class="form-control">
          <label class="form-label mt-2">Card first 6</label>
          <input id="p_first6" class="form-control" maxlength="6">
          <label class="form-label mt-2">Card last 4</label>
          <input id="p_last4" class="form-control" maxlength="4">
          <label class="form-label mt-2">Birthday</label>
          <input id="p_bday" class="form-control">
          <label class="form-label mt-2">Full name</label>
          <input id="p_name" class="form-control">
          <button class="btn btn-secondary mt-3" type="button" onclick="ftdPaid()">Record successful payment</button>
          <pre id="p_out" class="bg-light p-2 mt-3 small"></pre>
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-body">
      <h2 class="h5">Admin upload for a merchant</h2>
      <p class="small text-muted">
        Choose the merchant, then upload their CSV. This replaces that merchant’s entire list.
        Columns: <code>email,phone,card_first6,card_last4,birthday,full_name,biz</code>.
        <code>biz</code> is one of: gambling, gaming, mlm, food_supplements, pharma, forex, digital_products, other.
        Never store a full card number.
      </p>
      <label class="form-label">Merchant ID</label>
      <input id="u_merchant" class="form-control" value="demo-merchant">
      <label class="form-label mt-2">CSV file</label>
      <input id="u_file" class="form-control" type="file" accept=".csv,text/csv">
      <button class="btn btn-primary mt-3" type="button" onclick="ftdUpload()">Upload list for this merchant</button>
      <pre id="u_out" class="bg-light p-2 mt-3 small"></pre>
    </div>
  </div>
</div>

<script>
  async function ftdPost(url, body, out) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' },
      body: JSON.stringify(body)
    });
    document.getElementById(out).textContent = JSON.stringify(await res.json(), null, 2);
  }
  function ftdFields(prefix) {
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
  function ftdClassify() { ftdPost(@json(route('admin.ftd-trusted.classify')), ftdFields('c'), 'c_out'); }
  function ftdPaid() { ftdPost(@json(route('admin.ftd-trusted.paid')), ftdFields('p'), 'p_out'); }
  async function ftdUpload() {
    const file = document.getElementById('u_file').files[0];
    if (!file) { document.getElementById('u_out').textContent = 'Choose a CSV file.'; return; }
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const fd = new FormData();
    fd.append('merchant', document.getElementById('u_merchant').value);
    fd.append('merchant_id', document.getElementById('u_merchant').value);
    fd.append('file', file);
    fd.append('_token', token || '');
    const res = await fetch(@json(route('admin.ftd-trusted.upload')), { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd });
    document.getElementById('u_out').textContent = JSON.stringify(await res.json(), null, 2);
  }
</script>
@endsection
