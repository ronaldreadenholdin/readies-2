const crypto = require('crypto');

const STATUS = {
  PASS: 'pass',
  WARN: 'warn',
  FAIL: 'fail',
  SKIP: 'skip',
};

const TEMPLATES = {
  P003: {
    code: 'P003',
    name: 'FBLS',
    template: 'fbls',
    signature_header: 'X-FBLS-Signature',
    signature_algorithm: 'HMAC-SHA256',
    currency: 'PKR',
    three_ds: true,
    restricted_countries: ['IR', 'KP', 'SY'],
  },
  P004: {
    code: 'P004',
    name: 'Xcore',
    template: 'xcore',
    signature_header: 'X-Xcore-Signature',
    signature_algorithm: 'HMAC-SHA256',
    currency: 'USD',
    three_ds: true,
    restricted_countries: ['NL', 'IR', 'KP'],
  },
};

function check(name, category, status, message, recommendation, meta = {}) {
  return { name, category, status, message, recommendation, meta };
}

function present(value) {
  if (value === null || value === undefined) return false;
  if (typeof value === 'string') return value.trim() !== '';
  if (typeof value === 'boolean') return true;
  if (Array.isArray(value)) return value.length > 0;
  return true;
}

function firstPresent(data, keys) {
  for (const key of keys) {
    if (present(data[key])) return data[key];
  }
  return null;
}

function missingRequirements(data, requirements) {
  const missing = [];
  for (const [label, keys] of Object.entries(requirements)) {
    if (!keys.some((k) => present(data[k]))) {
      missing.push(label);
    }
  }
  return missing;
}

function worstStatus(checks) {
  if (checks.some((c) => c.status === STATUS.FAIL)) return STATUS.FAIL;
  if (checks.some((c) => c.status === STATUS.WARN)) return STATUS.WARN;
  if (checks.some((c) => c.status === STATUS.SKIP)) return STATUS.SKIP;
  return STATUS.PASS;
}

function scoreChecks(checks) {
  const scored = checks.filter((c) => c.status !== STATUS.SKIP);
  if (scored.length === 0) return 0;
  const points = scored.reduce((sum, c) => {
    if (c.status === STATUS.PASS) return sum + 100;
    if (c.status === STATUS.WARN) return sum + 50;
    return sum;
  }, 0);
  return Math.round(points / scored.length);
}

function maskUrl(url) {
  try {
    const u = new URL(url);
    return `${u.protocol}//${u.host}${u.pathname}`;
  } catch {
    return url;
  }
}

function isXcore(provider) {
  const id = `${provider.name || ''} ${provider.code || ''} ${provider.template || ''}`.toLowerCase();
  return id.includes('xcore') || id.includes('p004');
}

function testCredentials(data) {
  const fields = ['merchant_id', 'api_key', 'api_secret'];
  const missing = fields.filter((f) => !present(data[f]));
  if (missing.length === fields.length) {
    return check(
      'Credentials',
      'credentials',
      STATUS.FAIL,
      'No PSP credentials configured.',
      'Enter merchant ID, API key, and API secret from your PSP sandbox.'
    );
  }
  if (missing.length > 0) {
    return check(
      'Credentials',
      'credentials',
      STATUS.FAIL,
      `Missing credential field(s): ${missing.join(', ')}.`,
      'Fill all required credential fields before running live tests.',
      { missing }
    );
  }
  const placeholders = ['test', 'secret', 'changeme', 'todo', 'dummy', 'password'];
  const suspicious = fields.filter((f) => placeholders.includes(String(data[f]).toLowerCase().trim()));
  if (suspicious.length > 0) {
    return check(
      'Secret Hygiene',
      'credentials',
      STATUS.WARN,
      'Some credentials look like placeholders.',
      'Replace placeholder values with real PSP sandbox credentials.',
      { fields: suspicious }
    );
  }
  return check(
    'Credentials',
    'credentials',
    STATUS.PASS,
    'All required credentials are populated.',
    'No action needed.',
    { fields }
  );
}

async function testEndpoint(data, performHttpChecks) {
  const url = data.base_url;
  if (!present(url)) {
    return check(
      'API Endpoint',
      'connectivity',
      STATUS.FAIL,
      'No PSP API base URL configured.',
      'Add the sandbox base URL supplied by your PSP.'
    );
  }
  let parsed;
  try {
    parsed = new URL(url);
  } catch {
    return check(
      'API Endpoint',
      'connectivity',
      STATUS.FAIL,
      'PSP base URL is not a valid URL.',
      'Use a full HTTPS URL such as https://sandbox.psp.example.com/api'
    );
  }
  if (parsed.protocol !== 'https:') {
    return check(
      'API Endpoint',
      'connectivity',
      STATUS.WARN,
      'PSP endpoint is not HTTPS.',
      'Use HTTPS for all PSP communication.',
      { url: maskUrl(url) }
    );
  }
  if (!performHttpChecks) {
    return check(
      'API Endpoint',
      'connectivity',
      STATUS.PASS,
      'Valid HTTPS endpoint configured. Live ping disabled.',
      'Enable live endpoint ping to test reachability.',
      { url: maskUrl(url) }
    );
  }
  try {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 8000);
    const headers = { Accept: 'application/json' };
    if (present(data.api_key) && present(data.api_secret)) {
      headers.Authorization = `Basic ${Buffer.from(`${data.api_key}:${data.api_secret}`).toString('base64')}`;
    } else if (present(data.api_key)) {
      headers.Authorization = `Bearer ${data.api_key}`;
    }
    const response = await fetch(url, { method: 'GET', headers, signal: controller.signal });
    clearTimeout(timeout);
    const httpStatus = response.status;
    if (httpStatus >= 500) {
      return check(
        'API Endpoint',
        'connectivity',
        STATUS.FAIL,
        `Endpoint responded with HTTP ${httpStatus}.`,
        'Check PSP status, firewall rules, and endpoint configuration.',
        { url: maskUrl(url), http_status: httpStatus }
      );
    }
    return check(
      'API Endpoint',
      'connectivity',
      STATUS.PASS,
      `Endpoint reachable (HTTP ${httpStatus}).`,
      'No action needed.',
      { url: maskUrl(url), http_status: httpStatus }
    );
  } catch (err) {
    return check(
      'API Endpoint',
      'connectivity',
      STATUS.FAIL,
      `Endpoint ping failed: ${err.message}`,
      'Check DNS, firewall, SSL, and the configured PSP base URL.',
      { url: maskUrl(url) }
    );
  }
}

function testWebhookConfig(data, harnessWebhookUrl) {
  if (!present(harnessWebhookUrl)) {
    return check(
      'Webhook Receiver',
      'webhooks',
      STATUS.WARN,
      'Harness webhook URL could not be determined.',
      'Access the harness via a stable host so PSPs can deliver callbacks.'
    );
  }
  const pspCallback = data.callback_url || data.notification_url;
  if (present(pspCallback) && !pspCallback.startsWith('https://')) {
    return check(
      'Webhook / Callback',
      'webhooks',
      STATUS.WARN,
      'Configured PSP callback URL should use HTTPS.',
      'Switch callback URLs to HTTPS before external PSP testing.',
      { url: maskUrl(pspCallback) }
    );
  }
  return check(
    'Webhook Receiver',
    'webhooks',
    STATUS.PASS,
    'Harness webhook endpoint is ready to receive PSP callbacks.',
    `Give the PSP this URL: ${harnessWebhookUrl}`,
    { harness_webhook_url: harnessWebhookUrl, psp_callback_url: pspCallback || null }
  );
}

function testWebhookPayload(webhooks) {
  if (!webhooks || webhooks.length === 0) {
    return check(
      'Webhook Handling',
      'webhook_handling',
      STATUS.WARN,
      'No webhooks received yet.',
      'Send a test webhook from the PSP sandbox or use Simulate Webhook in the harness.'
    );
  }
  const latest = webhooks[0];
  const payload = latest.body || {};
  const missing = missingRequirements(payload, {
    'transaction id': ['transaction_id', 'payment_id', 'reference', 'merchant_reference', 'order_id'],
    status: ['status', 'payment_status', 'state'],
    BIN: ['bin', 'card_bin', 'first6'],
    last4: ['last4', 'card_last4', 'last_4'],
  });
  if (missing.length > 0) {
    return check(
      'Webhook Handling',
      'webhook_handling',
      STATUS.WARN,
      `Latest webhook is missing: ${missing.join(', ')}.`,
      'Request a complete webhook example with card BIN and last4 from the PSP.',
      { missing, webhook_id: latest.id }
    );
  }
  return check(
    'Webhook Handling',
    'webhook_handling',
    STATUS.PASS,
    'Latest webhook includes transaction identity, status, BIN, and last4.',
    'No action needed.',
    { webhook_id: latest.id }
  );
}

function verifySignature(body, signature, secret, algorithm = 'HMAC-SHA256') {
  if (!present(signature) || !present(secret)) {
    return { valid: false, reason: 'Missing signature or secret' };
  }
  const payload = typeof body === 'string' ? body : JSON.stringify(body);
  const expected = crypto.createHmac('sha256', secret).update(payload).digest('hex');
  const provided = String(signature).replace(/^sha256=/i, '').trim();
  if (expected.length !== provided.length) {
    return { valid: false, reason: 'Signature length mismatch', expected, provided, algorithm };
  }
  const valid = crypto.timingSafeEqual(Buffer.from(expected, 'utf8'), Buffer.from(provided, 'utf8'));
  return { valid, expected, provided, algorithm };
}

function testSignatureVerification(data, webhooks) {
  if (!present(data.webhook_secret)) {
    return check(
      'Signature Verification',
      'signature_verification',
      STATUS.WARN,
      'Webhook signing secret is not configured.',
      'Add the webhook secret from your PSP to verify incoming signatures.'
    );
  }
  if (!webhooks || webhooks.length === 0) {
    return check(
      'Signature Verification',
      'signature_verification',
      STATUS.WARN,
      'No webhooks received to verify signatures against.',
      'Receive or simulate a webhook, then re-run this check.'
    );
  }
  const latest = webhooks[0];
  const headerName = (data.signature_header || 'X-Signature').toLowerCase();
  const signature =
    latest.headers[headerName] ||
    latest.headers['x-signature'] ||
    latest.headers['x-fbls-signature'] ||
    latest.headers['x-xcore-signature'];
  if (!signature) {
    return check(
      'Signature Verification',
      'signature_verification',
      STATUS.WARN,
      `No signature header found (expected ${data.signature_header || 'X-Signature'}).`,
      'Confirm the signature header name with your PSP.',
      { headers_received: Object.keys(latest.headers) }
    );
  }
  const result = verifySignature(latest.rawBody || latest.body, signature, data.webhook_secret, data.signature_algorithm);
  if (!result.valid) {
    return check(
      'Signature Verification',
      'signature_verification',
      STATUS.FAIL,
      'Webhook signature does not match HMAC-SHA256 expectation.',
      'Confirm canonical payload string and signing secret with your PSP.',
      { algorithm: data.signature_algorithm || 'HMAC-SHA256', header: data.signature_header }
    );
  }
  return check(
    'Signature Verification',
    'signature_verification',
    STATUS.PASS,
    'Latest webhook signature verified successfully.',
    'No action needed.',
    { algorithm: data.signature_algorithm || 'HMAC-SHA256' }
  );
}

function test3dsGeo(data) {
  const has3ds = present(data.three_ds);
  const country = firstPresent(data, ['country', 'billing_country', 'customer_country']) || 'US';
  if (!has3ds) {
    return check(
      '3DS & Geo Strategy',
      '3ds_geo_support',
      STATUS.WARN,
      '3DS support not marked on provider.',
      'Confirm 3DS 2.0 support with your PSP and enable the flag.'
    );
  }
  return check(
    '3DS & Geo Strategy',
    '3ds_geo_support',
    STATUS.PASS,
    `3DS enabled. Test country routing set to ${country}.`,
    'No action needed.',
    { three_ds: true, test_country: country }
  );
}

function testRestrictedCountries(data) {
  const restricted = data.restricted_countries;
  if (!present(restricted)) {
    return check(
      'Restricted Countries',
      'restricted_countries',
      STATUS.WARN,
      'No restricted country list configured.',
      'Add blocked countries before enabling routing.'
    );
  }
  const list = Array.isArray(restricted) ? restricted : String(restricted).split(/[\s,|]+/);
  const invalid = list.filter((c) => !/^[A-Z]{2}$/i.test(String(c).trim()));
  if (invalid.length > 0) {
    return check(
      'Restricted Countries',
      'restricted_countries',
      STATUS.WARN,
      'Restricted country list contains invalid codes.',
      'Use ISO-3166 alpha-2 codes such as US, GB, NL.',
      { invalid }
    );
  }
  return check(
    'Restricted Countries',
    'restricted_countries',
    STATUS.PASS,
    `${list.length} restricted country code(s) configured.`,
    'No action needed.',
    { countries: list.map((c) => c.toUpperCase()) }
  );
}

function testFieldMapping(data) {
  const missing = missingRequirements(data, {
    amount: ['test_amount', 'amount'],
    currency: ['currency'],
    reference: ['reference_prefix'],
  });
  if (missing.length > 0) {
    return check(
      'Field Mapping',
      'field_mapping',
      STATUS.WARN,
      `Missing mapped field(s): ${missing.join(', ')}.`,
      'Configure test amount, currency, and reference prefix for dry-run payloads.',
      { missing }
    );
  }
  return check(
    'Field Mapping',
    'field_mapping',
    STATUS.PASS,
    'Required request fields are mapped for PSP payloads.',
    'No action needed.'
  );
}

function testDryRunPayload(data, provider) {
  const currency = (data.currency || 'USD').toUpperCase();
  const amount = Number(data.test_amount || data.minimum_amount || 1);
  const reference = `${data.reference_prefix || 'readies'}-${Date.now()}`;
  return check(
    'Dry-Run Payload',
    'transactions',
    STATUS.PASS,
    'Safe dry-run transaction payload assembled.',
    'Use this payload only against PSP sandbox endpoints.',
    {
      payload: {
        amount,
        currency,
        reference,
        description: `Readies harness validation – ${provider.name}`,
        merchant_id: data.merchant_id,
      },
    }
  );
}

function testSettlementFlow(data) {
  const hasSettlement = present(data.settlement_report_url) || present(data.settlement_enabled);
  if (!hasSettlement) {
    return check(
      'Settlement & Reconciliation',
      'settlement',
      STATUS.WARN,
      'Settlement report endpoint not configured.',
      'Add settlement report URL or enable settlement flag from PSP docs.'
    );
  }
  return check(
    'Settlement & Reconciliation',
    'settlement',
    STATUS.PASS,
    'Settlement configuration present.',
    'No action needed.'
  );
}

function testRefundVoid(data) {
  if (!data.supports_refund && !data.supports_void) {
    return check(
      'Refund / Void Handling',
      'refunds',
      STATUS.WARN,
      'Refund and void support not confirmed.',
      'Confirm refund/void API endpoints with your PSP.'
    );
  }
  return check(
    'Refund / Void Handling',
    'refunds',
    STATUS.PASS,
    `Refund: ${data.supports_refund ? 'yes' : 'no'}, Void: ${data.supports_void ? 'yes' : 'no'}.`,
    'No action needed.'
  );
}

function testChargebackAlerts(data) {
  if (!present(data.chargeback_webhook_url) && !data.chargeback_alerts_enabled) {
    return check(
      'Chargeback Alerts',
      'chargebacks',
      STATUS.WARN,
      'Chargeback alert format not configured.',
      'Request chargeback alert payload format and delivery timing from PSP.'
    );
  }
  return check(
    'Chargeback Alerts',
    'chargebacks',
    STATUS.PASS,
    'Chargeback alert configuration present.',
    'No action needed.'
  );
}

function testFailoverRouting(data) {
  if (!present(data.priority) && !present(data.failover_provider)) {
    return check(
      'Failover Routing',
      'routing',
      STATUS.WARN,
      'No routing priority or failover provider set.',
      'Configure routing priority for production failover.'
    );
  }
  return check(
    'Failover Routing',
    'routing',
    STATUS.PASS,
    'Routing metadata configured.',
    'No action needed.',
    { priority: data.priority, failover: data.failover_provider }
  );
}

function testGoLiveApproval(data) {
  if (!data.go_live_approved) {
    return check(
      'Go-Live Approval',
      'go_live',
      STATUS.WARN,
      'Signed PSP go-live checklist not recorded.',
      'Obtain signed go-live approval from PSP before production traffic.'
    );
  }
  return check(
    'Go-Live Approval',
    'go_live',
    STATUS.PASS,
    'Go-live approval recorded.',
    'No action needed.'
  );
}

function xcoreRequiredFields(data) {
  const missing = missingRequirements(data, {
    'merchant id': ['merchant_id'],
    'API key': ['api_key'],
    'API secret': ['api_secret'],
    'base URL': ['base_url'],
  });
  if (missing.length > 0) {
    return check(
      'Xcore Required Fields',
      'xcore_p004',
      STATUS.FAIL,
      `Missing Xcore field(s): ${missing.join(', ')}.`,
      'Add Xcore sandbox credentials and endpoint.',
      { missing }
    );
  }
  return check(
    'Xcore Required Fields',
    'xcore_p004',
    STATUS.PASS,
    'Xcore credentials and endpoint present.',
    'No action needed.'
  );
}

function bobRecommendations(checks, provider) {
  const flagged = checks.filter((c) => c.status === STATUS.WARN || c.status === STATUS.FAIL);
  if (flagged.length === 0) {
    return `All checks passed for ${provider.name} (${provider.code}). Ready for go-live review.`;
  }
  const lines = [`Dear ${provider.name} Team,\n`];
  flagged.forEach((c, i) => {
    lines.push(`${i + 1}. ${c.name}: ${c.recommendation}`);
  });
  lines.push('\nThank you.');
  return lines.join('\n');
}

async function runSuite(provider, webhooks, harnessWebhookUrl, suite, performHttpChecks) {
  const data = provider;
  let checks = [];

  if (suite === 'full' || suite === 'all') {
    checks = [
      testCredentials(data),
      await testEndpoint(data, performHttpChecks),
      testWebhookConfig(data, harnessWebhookUrl),
      testWebhookPayload(webhooks),
      testSignatureVerification(data, webhooks),
      test3dsGeo(data),
      testRestrictedCountries(data),
      testFieldMapping(data),
      testDryRunPayload(data, provider),
    ];
    if (isXcore(provider)) {
      checks.push(xcoreRequiredFields(data));
    }
  } else if (suite === 'second') {
    checks = [
      testSettlementFlow(data),
      testRefundVoid(data),
      testChargebackAlerts(data),
      testFailoverRouting(data),
      testGoLiveApproval(data),
    ];
  } else if (suite === 'xcore') {
    checks = [
      xcoreRequiredFields(data),
      await testEndpoint(data, performHttpChecks),
      testWebhookPayload(webhooks),
      testSignatureVerification(data, webhooks),
      test3dsGeo(data),
      testRestrictedCountries(data),
    ];
  } else if (suite === 'connectivity') {
    checks = [testCredentials(data), await testEndpoint(data, true)];
  } else {
    throw new Error(`Unknown test suite: ${suite}`);
  }

  const status = worstStatus(checks);
  const score = scoreChecks(checks);
  const summary = {
    pass: checks.filter((c) => c.status === STATUS.PASS).length,
    warn: checks.filter((c) => c.status === STATUS.WARN).length,
    fail: checks.filter((c) => c.status === STATUS.FAIL).length,
    skip: checks.filter((c) => c.status === STATUS.SKIP).length,
  };

  return {
    provider: { id: provider.id, name: provider.name, code: provider.code, template: provider.template },
    suite,
    status,
    score,
    summary,
    checks,
    bob: bobRecommendations(checks, provider),
    generated_at: new Date().toISOString(),
  };
}

function createFromTemplate(templateCode) {
  const base = TEMPLATES[templateCode];
  if (!base) throw new Error(`Unknown template: ${templateCode}`);
  return { ...base, enabled: true, environment: 'sandbox', test_amount: 1, reference_prefix: 'readies' };
}

function signWebhookPayload(body, secret) {
  const payload = typeof body === 'string' ? body : JSON.stringify(body);
  return crypto.createHmac('sha256', secret).update(payload).digest('hex');
}

module.exports = {
  STATUS,
  TEMPLATES,
  runSuite,
  createFromTemplate,
  verifySignature,
  signWebhookPayload,
  bobRecommendations,
};
