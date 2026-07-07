let providers = [];
let selectedId = null;
const testHistory = { full: false, second: false, xcore: false };

const STATUS_ICON = { pass: '✅', warn: '⚠️', fail: '❌', skip: '⏭️' };

// ── Tabs ─────────────────────────────────────────────────────────────────────

document.querySelectorAll('.tab').forEach((tab) => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach((t) => t.classList.remove('active'));
    document.querySelectorAll('.panel').forEach((p) => p.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById(`panel-${tab.dataset.tab}`).classList.add('active');
    if (tab.dataset.tab === 'webhooks') refreshWebhooks();
    if (tab.dataset.tab === 'golive') updateGoLiveStatus();
  });
});

// ── API helpers ──────────────────────────────────────────────────────────────

async function api(path, opts = {}) {
  const res = await fetch(`/api${path}`, {
    headers: { 'Content-Type': 'application/json', ...opts.headers },
    ...opts,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Request failed (${res.status})`);
  return data;
}

function toast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3000);
}

function harnessWebhookUrl(id) {
  return `${window.location.origin}/api/webhooks/${id}`;
}

// ── Providers ────────────────────────────────────────────────────────────────

async function loadProviders() {
  providers = await api('/providers');
  renderProviderList();
  renderProviderSelects();
  if (selectedId) selectProvider(selectedId);
}

function renderProviderList() {
  const el = document.getElementById('provider-list');
  if (providers.length === 0) {
    el.innerHTML = '<div class="empty">No PSPs connected yet. Add one above.</div>';
    return;
  }
  el.innerHTML = providers.map((p) => `
    <div class="provider-item ${p.id === selectedId ? 'selected' : ''}" onclick="selectProvider('${p.id}')">
      <div>
        <span class="code">${p.code || '?'}</span>
        <strong>${p.name}</strong>
        <span style="color:#6c757d;font-size:13px;margin-left:8px">${p.environment || 'sandbox'}</span>
      </div>
      <span style="font-size:13px;color:#6c757d">${p.base_url ? 'configured' : 'incomplete'}</span>
    </div>
  `).join('');
}

function renderProviderSelects() {
  const opts = providers.length === 0
    ? '<p class="empty">Connect a PSP first.</p>'
    : `<select id="active-provider" onchange="onProviderSelectChange(this.value)">
        ${providers.map((p) => `<option value="${p.id}" ${p.id === selectedId ? 'selected' : ''}>${p.code} – ${p.name}</option>`).join('')}
       </select>`;
  document.getElementById('test-provider-select').innerHTML = opts;
  document.getElementById('webhook-provider-select').innerHTML = opts;
}

function onProviderSelectChange(id) {
  selectedId = id;
  renderProviderList();
  updateWebhookUrl();
}

async function addFromTemplate(code) {
  try {
    const p = await api(`/providers/from-template/${code}`, { method: 'POST', body: '{}' });
    selectedId = p.id;
    await loadProviders();
    selectProvider(p.id);
    toast(`${code} template created – enter your sandbox credentials`);
  } catch (e) {
    toast(e.message);
  }
}

function selectProvider(id) {
  selectedId = id;
  const p = providers.find((x) => x.id === id);
  if (!p) return;

  document.getElementById('provider-form-card').style.display = 'block';
  document.getElementById('form-title').textContent = `Edit ${p.name} (${p.code})`;

  const fields = [
    'id', 'name', 'code', 'environment', 'currency', 'merchant_id', 'api_key',
    'api_secret', 'base_url', 'webhook_secret', 'signature_header', 'callback_url',
    'test_amount', 'reference_prefix',
  ];
  fields.forEach((f) => {
    const el = document.getElementById(`f-${f}`);
    if (el) el.value = p[f] ?? '';
  });

  document.getElementById('f-three_ds').checked = p.three_ds !== false;
  document.getElementById('f-supports_refund').checked = !!p.supports_refund;
  document.getElementById('f-supports_void').checked = !!p.supports_void;
  document.getElementById('f-go_live_approved').checked = !!p.go_live_approved;

  const countries = Array.isArray(p.restricted_countries)
    ? p.restricted_countries.join(', ')
    : (p.restricted_countries || '');
  document.getElementById('f-restricted_countries').value = countries;

  document.getElementById('harness-webhook-display').style.display = 'block';
  document.getElementById('harness-webhook-url').textContent = harnessWebhookUrl(id);

  renderProviderList();
  renderProviderSelects();
}

async function saveProvider(e) {
  e.preventDefault();
  const countries = document.getElementById('f-restricted_countries').value
    .split(/[\s,|]+/).filter(Boolean).map((c) => c.toUpperCase());

  const body = {
    id: document.getElementById('f-id').value || undefined,
    name: document.getElementById('f-name').value,
    code: document.getElementById('f-code').value,
    environment: document.getElementById('f-environment').value,
    currency: document.getElementById('f-currency').value.toUpperCase(),
    merchant_id: document.getElementById('f-merchant_id').value,
    api_key: document.getElementById('f-api_key').value,
    api_secret: document.getElementById('f-api_secret').value,
    base_url: document.getElementById('f-base_url').value,
    webhook_secret: document.getElementById('f-webhook_secret').value,
    signature_header: document.getElementById('f-signature_header').value,
    callback_url: document.getElementById('f-callback_url').value,
    test_amount: parseFloat(document.getElementById('f-test_amount').value) || 1,
    reference_prefix: document.getElementById('f-reference_prefix').value,
    three_ds: document.getElementById('f-three_ds').checked,
    supports_refund: document.getElementById('f-supports_refund').checked,
    supports_void: document.getElementById('f-supports_void').checked,
    go_live_approved: document.getElementById('f-go_live_approved').checked,
    restricted_countries: countries,
  };

  try {
    const p = await api('/providers', { method: 'POST', body: JSON.stringify(body) });
    selectedId = p.id;
    await loadProviders();
    toast('PSP connection saved');
  } catch (err) {
    toast(err.message);
  }
}

async function deleteProvider() {
  if (!selectedId || !confirm('Delete this PSP connection?')) return;
  try {
    await api(`/providers/${selectedId}`, { method: 'DELETE' });
    selectedId = null;
    document.getElementById('provider-form-card').style.display = 'none';
    await loadProviders();
    toast('Provider deleted');
  } catch (e) {
    toast(e.message);
  }
}

// ── Tests ────────────────────────────────────────────────────────────────────

async function runTest(suite) {
  const id = selectedId || document.getElementById('active-provider')?.value;
  if (!id) { toast('Select a PSP first'); return; }

  const network = document.getElementById('live-ping').checked;
  const card = document.getElementById('test-results');
  card.style.display = 'block';
  card.classList.add('loading');
  document.getElementById('test-checks').innerHTML = '<div class="empty">Running tests…</div>';

  try {
    const result = await api(`/providers/${id}/test/${suite}?network=${network ? 1 : 0}`, {
      method: 'POST',
      body: '{}',
    });
    renderTestResults(result);
    if (suite === 'full') testHistory.full = true;
    if (suite === 'second') testHistory.second = true;
    if (suite === 'xcore') testHistory.xcore = true;
    updateGoLiveStatus();
    toast(`${suite} test complete – ${result.status}`);
  } catch (e) {
    document.getElementById('test-checks').innerHTML = `<div class="status fail">❌ ${e.message}</div>`;
    toast(e.message);
  } finally {
    card.classList.remove('loading');
  }
}

function renderTestResults(result) {
  document.getElementById('test-metrics').innerHTML = `
    <div class="metric"><div class="val score-pill ${result.status}">${result.score}%</div><div class="lbl">Readiness</div></div>
    <div class="metric"><div class="val" style="color:#28a745">${result.summary.pass}</div><div class="lbl">Passed</div></div>
    <div class="metric"><div class="val" style="color:#856404">${result.summary.warn}</div><div class="lbl">Warnings</div></div>
    <div class="metric"><div class="val" style="color:#dc3545">${result.summary.fail}</div><div class="lbl">Failed</div></div>
  `;

  document.getElementById('test-checks').innerHTML = `
    <h3>${result.provider.name} (${result.provider.code}) – ${result.suite} test</h3>
    ${result.checks.map((c) => `
      <div class="status ${c.status}">
        <div>
          ${STATUS_ICON[c.status] || ''} <strong>${c.name}</strong>
          <div class="status-detail">${c.message}</div>
          ${c.status !== 'pass' ? `<div class="status-detail">→ ${c.recommendation}</div>` : ''}
        </div>
      </div>
    `).join('')}
  `;

  const bobEl = document.getElementById('bob-recommendations');
  if (result.bob) {
    bobEl.innerHTML = `<h4>💡 Bob Recommendations</h4><div class="bob-box">${escapeHtml(result.bob)}</div>`;
  }
}

function escapeHtml(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ── Webhooks ─────────────────────────────────────────────────────────────────

function updateWebhookUrl() {
  const id = selectedId || document.getElementById('active-provider')?.value;
  const box = document.getElementById('webhook-url-box');
  if (!id) { box.style.display = 'none'; return; }
  box.style.display = 'block';
  document.getElementById('webhook-url-display').textContent = harnessWebhookUrl(id);
}

async function refreshWebhooks() {
  const id = selectedId || document.getElementById('active-provider')?.value;
  updateWebhookUrl();
  if (!id) return;

  try {
    const webhooks = await api(`/webhooks/${id}`);
    const el = document.getElementById('webhook-log');
    if (webhooks.length === 0) {
      el.innerHTML = '<div class="empty">No webhooks received yet. Simulate one or configure your PSP to send callbacks.</div>';
      return;
    }
    el.innerHTML = webhooks.map((w) => `
      <div class="webhook-entry">
        <strong>${w.simulated ? '[SIMULATED] ' : ''}${w.method}</strong>
        <span style="color:#6c757d;margin-left:8px">${new Date(w.received_at).toLocaleString()}</span>
        <pre>${JSON.stringify(w.body, null, 2)}</pre>
      </div>
    `).join('');
  } catch (e) {
    toast(e.message);
  }
}

async function simulateWebhook() {
  const id = selectedId || document.getElementById('active-provider')?.value;
  if (!id) { toast('Select a PSP first'); return; }
  try {
    await api(`/webhooks/${id}/simulate`, { method: 'POST', body: '{}' });
    await refreshWebhooks();
    toast('Simulated webhook received');
  } catch (e) {
    toast(e.message);
  }
}

async function verifyWebhook() {
  const id = selectedId || document.getElementById('active-provider')?.value;
  if (!id) { toast('Select a PSP first'); return; }
  try {
    const result = await api(`/webhooks/${id}/verify`, { method: 'POST', body: '{}' });
    toast(result.valid ? 'Signature verified ✅' : `Signature invalid: ${result.reason}`);
  } catch (e) {
    toast(e.message);
  }
}

// ── Go Live ──────────────────────────────────────────────────────────────────

function updateGoLiveStatus() {
  const el = document.getElementById('go-live-status');
  const ran = testHistory.full && testHistory.second && testHistory.xcore;
  if (!ran) {
    el.className = 'status muted';
    el.textContent = 'Run Full Test, Second Test, and Xcore P004 Test before go-live review.';
    return;
  }
  el.className = 'status warn';
  el.textContent = 'All test suites have run. Review flagged items and Bob recommendations before approving go-live.';
}

async function goLiveCheck() {
  if (!testHistory.full || !testHistory.second || !testHistory.xcore) {
    document.getElementById('go-live-status').className = 'status fail';
    document.getElementById('go-live-status').textContent = 'Blocked: run all three test suites first.';
    toast('Run all test suites first');
    return;
  }

  const id = selectedId || document.getElementById('active-provider')?.value;
  if (!id) { toast('Select a PSP'); return; }

  const network = true;
  const suites = ['full', 'second', 'xcore'];
  const results = [];

  for (const suite of suites) {
    try {
      const r = await api(`/providers/${id}/test/${suite}?network=${network ? 1 : 0}`, {
        method: 'POST',
        body: '{}',
      });
      results.push(r);
    } catch (e) {
      results.push({ suite, status: 'fail', error: e.message });
    }
  }

  const allPass = results.every((r) => r.status === 'pass');
  const hasFail = results.some((r) => r.status === 'fail');

  const statusEl = document.getElementById('go-live-status');
  const detailsEl = document.getElementById('go-live-details');
  detailsEl.style.display = 'block';

  if (allPass) {
    statusEl.className = 'status pass';
    statusEl.textContent = '✅ All checks passed. Ready for production integration review.';
  } else if (hasFail) {
    statusEl.className = 'status fail';
    statusEl.textContent = '❌ Blocking failures found. Resolve before go-live.';
  } else {
    statusEl.className = 'status warn';
    statusEl.textContent = '⚠️ Warnings remain. Review Bob recommendations before go-live.';
  }

  detailsEl.innerHTML = results.map((r) => `
    <h4>${r.suite} – ${r.status || 'error'} (${r.score ?? 0}%)</h4>
    ${r.checks ? r.checks.filter((c) => c.status !== 'pass').map((c) => `
      <div class="status ${c.status}">${STATUS_ICON[c.status]} ${c.name}: ${c.message}</div>
    `).join('') : `<div class="status fail">${r.error}</div>`}
  `).join('');
}

// ── Init ─────────────────────────────────────────────────────────────────────

loadProviders().catch((e) => toast(e.message));
