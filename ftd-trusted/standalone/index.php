<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FTD vs trusted</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eef2f7; margin: 0; color: #0f172a; }
        main { max-width: 820px; margin: 32px auto; background: #fff; padding: 24px; border-radius: 12px; }
        label { display: block; font-size: 13px; font-weight: 700; margin: 10px 0 4px; }
        input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        button { margin: 16px 8px 0 0; padding: 10px 16px; border: 0; border-radius: 8px; background: #1e3a8a; color: #fff; font-weight: 700; }
        pre { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; white-space: pre-wrap; }
        .fine { color: #64748b; font-size: 13px; }
    </style>
</head>
<body>
<main>
    <h1>FTD vs trusted</h1>
    <p class="fine">Every provider. Not on the list = FTD. On the list, or paid once successfully = trusted. Match: email, then phone, then card first 6 + last 4, then birthday, then full name.</p>
    <section>
        <h2>Merchant list upload</h2>
        <p class="fine">The uploaded CSV becomes the whole trusted list for that merchant. Columns: email, phone, card_first6, card_last4, birthday, full_name, biz.</p>
        <form id="upload-form">
            <label>Merchant</label>
            <input name="merchant" required placeholder="merchant-id">
            <label>CSV file</label>
            <input name="file" type="file" accept=".csv,text/csv" required>
            <button type="submit">Upload merchant list</button>
        </form>
    </section>
    <form id="form">
        <label>Merchant</label>
        <input name="merchant" placeholder="merchant-id">
        <div class="row">
            <div><label>Email</label><input name="email" type="email"></div>
            <div><label>Phone</label><input name="phone"></div>
        </div>
        <div class="row">
            <div><label>Card first 6</label><input name="card_first6" maxlength="6"></div>
            <div><label>Card last 4</label><input name="card_last4" maxlength="4"></div>
        </div>
        <div class="row">
            <div><label>Birthday</label><input name="birthday" placeholder="1990-05-01"></div>
            <div><label>Full name</label><input name="full_name"></div>
        </div>
        <div class="row">
            <div>
                <label>Biz they pay for</label>
                <select name="biz" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
                    <option value="">Select</option>
                    <option value="gambling">gambling</option>
                    <option value="gaming">gaming</option>
                    <option value="mlm">mlm</option>
                    <option value="food_supplements">food supplements</option>
                    <option value="pharma">pharma</option>
                    <option value="forex">forex</option>
                    <option value="digital_products">digital products</option>
                    <option value="other">other</option>
                </select>
            </div>
            <div><label>Provider</label><input name="provider" placeholder="P003"></div>
        </div>
        <button type="submit">Classify</button>
        <button type="button" id="paid">Mark paid once</button>
    </form>
    <pre id="out">Ready.</pre>
</main>
<script>
const form = document.getElementById('form');
const out = document.getElementById('out');
function payload() {
    return Object.fromEntries(new FormData(form).entries());
}
async function send(action) {
    const res = await fetch('api.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload())
    });
    out.textContent = JSON.stringify(await res.json(), null, 2);
}
form.addEventListener('submit', function (e) { e.preventDefault(); send('classify'); });
document.getElementById('paid').addEventListener('click', function () { send('paid'); });
document.getElementById('upload-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const res = await fetch('api.php?action=upload', { method: 'POST', body: new FormData(e.target) });
    out.textContent = JSON.stringify(await res.json(), null, 2);
});
</script>
</body>
</html>
