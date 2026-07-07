const express = require('express');
const path = require('path');
const { v4: uuidv4 } = require('uuid');
const storage = require('./storage');
const harness = require('./harness');

const app = express();
const PORT = process.env.PORT || 3000;
const HOST = process.env.HOST || '0.0.0.0';

app.use(express.json({ verify: (req, _res, buf) => { req.rawBody = buf.toString('utf8'); } }));
app.use(express.urlencoded({ extended: true }));

function harnessWebhookUrl(req, providerId) {
  const proto = req.get('x-forwarded-proto') || req.protocol;
  const host = req.get('x-forwarded-host') || req.get('host');
  return `${proto}://${host}/api/webhooks/${providerId}`;
}

// ── API: health ──────────────────────────────────────────────────────────────

app.get('/api/health', (_req, res) => {
  res.json({ ok: true, service: 'readies-psp-harness', version: '1.0.0' });
});

// ── API: templates ───────────────────────────────────────────────────────────

app.get('/api/templates', (_req, res) => {
  res.json(harness.TEMPLATES);
});

// ── API: providers ─────────────────────────────────────────────────────────

app.get('/api/providers', (_req, res) => {
  const providers = storage.getProviders().map((p) => ({
    ...p,
    api_secret: p.api_secret ? '••••••••' : '',
    webhook_secret: p.webhook_secret ? '••••••••' : '',
  }));
  res.json(providers);
});

app.get('/api/providers/:id', (req, res) => {
  const provider = storage.getProvider(req.params.id);
  if (!provider) return res.status(404).json({ error: 'Provider not found' });
  res.json({
    ...provider,
    api_secret: provider.api_secret ? '••••••••' : '',
    webhook_secret: provider.webhook_secret ? '••••••••' : '',
  });
});

app.post('/api/providers', (req, res) => {
  const body = req.body;
  const existing = body.id ? storage.getProvider(body.id) : null;
  const provider = {
    ...(existing || {}),
    ...body,
    id: body.id || uuidv4(),
    updated_at: new Date().toISOString(),
    created_at: existing?.created_at || new Date().toISOString(),
  };
  if (body.api_secret === '••••••••' && existing) provider.api_secret = existing.api_secret;
  if (body.webhook_secret === '••••••••' && existing) provider.webhook_secret = existing.webhook_secret;
  storage.upsertProvider(provider);
  res.json({
    ...provider,
    api_secret: provider.api_secret ? '••••••••' : '',
    webhook_secret: provider.webhook_secret ? '••••••••' : '',
    harness_webhook_url: harnessWebhookUrl(req, provider.id),
  });
});

app.post('/api/providers/from-template/:code', (req, res) => {
  const template = harness.createFromTemplate(req.params.code.toUpperCase());
  const provider = {
    ...template,
    ...req.body,
    id: uuidv4(),
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  };
  storage.upsertProvider(provider);
  res.status(201).json({
    ...provider,
    api_secret: provider.api_secret ? '••••••••' : '',
    webhook_secret: provider.webhook_secret ? '••••••••' : '',
    harness_webhook_url: harnessWebhookUrl(req, provider.id),
  });
});

app.delete('/api/providers/:id', (req, res) => {
  storage.deleteProvider(req.params.id);
  res.json({ deleted: true });
});

// ── API: tests ───────────────────────────────────────────────────────────────

app.post('/api/providers/:id/test/:suite', async (req, res) => {
  const provider = storage.getProvider(req.params.id);
  if (!provider) return res.status(404).json({ error: 'Provider not found' });
  const performHttpChecks = req.query.network === '1' || req.body.network === true;
  const webhooks = storage.getWebhooks(provider.id);
  const harnessUrl = harnessWebhookUrl(req, provider.id);
  try {
    const result = await harness.runSuite(provider, webhooks, harnessUrl, req.params.suite, performHttpChecks);
    storage.addTestRun(provider.id, result);
    res.json(result);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

app.get('/api/providers/:id/test-runs', (req, res) => {
  res.json(storage.getTestRuns(req.params.id));
});

// ── API: webhooks ──────────────────────────────────────────────────────────

app.all('/api/webhooks/:providerId', (req, res) => {
  const provider = storage.getProvider(req.params.providerId);
  if (!provider) return res.status(404).json({ error: 'Provider not found' });

  let body = req.body;
  if (req.rawBody && typeof body === 'object' && Object.keys(body).length === 0) {
    try { body = JSON.parse(req.rawBody); } catch { body = { raw: req.rawBody }; }
  }

  const webhook = {
    id: uuidv4(),
    received_at: new Date().toISOString(),
    method: req.method,
    headers: Object.fromEntries(
      Object.entries(req.headers).map(([k, v]) => [k.toLowerCase(), v])
    ),
    body,
    rawBody: req.rawBody || JSON.stringify(body),
    query: req.query,
  };

  storage.addWebhook(provider.id, webhook);
  console.log(`[webhook] ${provider.code} ${req.method} ${webhook.id}`);
  res.status(200).json({ received: true, id: webhook.id });
});

app.get('/api/webhooks/:providerId', (req, res) => {
  res.json(storage.getWebhooks(req.params.providerId));
});

app.post('/api/webhooks/:providerId/simulate', (req, res) => {
  const provider = storage.getProvider(req.params.providerId);
  if (!provider) return res.status(404).json({ error: 'Provider not found' });

  const payload = req.body.payload || {
    transaction_id: `sim-${Date.now()}`,
    merchant_reference: `readies-${Date.now()}`,
    status: 'approved',
    amount: provider.test_amount || 1,
    currency: provider.currency || 'USD',
    bin: '411111',
    last4: '1111',
    payment_status: 'approved',
  };

  const rawBody = JSON.stringify(payload);
  const signature = harness.signWebhookPayload(rawBody, provider.webhook_secret || 'test-secret');
  const headerName = (provider.signature_header || 'X-Signature').toLowerCase();

  const webhook = {
    id: uuidv4(),
    received_at: new Date().toISOString(),
    method: 'POST',
    headers: { [headerName]: signature, 'content-type': 'application/json' },
    body: payload,
    rawBody,
    simulated: true,
  };

  storage.addWebhook(provider.id, webhook);
  res.json({ simulated: true, webhook, signature });
});

app.post('/api/webhooks/:providerId/verify', (req, res) => {
  const provider = storage.getProvider(req.params.providerId);
  if (!provider) return res.status(404).json({ error: 'Provider not found' });
  const webhooks = storage.getWebhooks(provider.id);
  if (webhooks.length === 0) return res.status(404).json({ error: 'No webhooks to verify' });

  const latest = webhooks[0];
  const headerName = (provider.signature_header || 'X-Signature').toLowerCase();
  const signature =
    latest.headers[headerName] ||
    latest.headers['x-signature'] ||
    req.body.signature;

  const result = harness.verifySignature(
    latest.rawBody || latest.body,
    signature,
    provider.webhook_secret || req.body.secret
  );
  res.json({ webhook_id: latest.id, ...result });
});

// ── Static UI ────────────────────────────────────────────────────────────────

app.use(express.static(path.join(__dirname, '..', 'public')));
app.get('/pre-flight-test', (_req, res) => {
  res.sendFile(path.join(__dirname, '..', 'public', 'index.html'));
});
app.get('/pre-flight-test/', (_req, res) => {
  res.sendFile(path.join(__dirname, '..', 'public', 'index.html'));
});
app.get('*', (req, res, next) => {
  if (req.path.startsWith('/api/')) return next();
  res.sendFile(path.join(__dirname, '..', 'public', 'index.html'));
});

app.listen(PORT, HOST, () => {
  console.log(`Readies PSP harness running at http://${HOST === '0.0.0.0' ? 'localhost' : HOST}:${PORT}`);
  console.log(`  UI:  http://localhost:${PORT}/`);
  console.log(`  API: http://localhost:${PORT}/api/health`);
});
