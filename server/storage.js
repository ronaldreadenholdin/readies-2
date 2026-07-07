const fs = require('fs');
const path = require('path');

const DATA_DIR = path.join(__dirname, '..', 'data');

function ensureDataDir() {
  if (!fs.existsSync(DATA_DIR)) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
  }
}

function readJson(file, fallback) {
  ensureDataDir();
  const filePath = path.join(DATA_DIR, file);
  if (!fs.existsSync(filePath)) {
    return fallback;
  }
  try {
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
  } catch {
    return fallback;
  }
}

function writeJson(file, data) {
  ensureDataDir();
  const filePath = path.join(DATA_DIR, file);
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2));
}

function getProviders() {
  return readJson('providers.json', []);
}

function saveProviders(providers) {
  writeJson('providers.json', providers);
}

function getProvider(id) {
  return getProviders().find((p) => p.id === id) || null;
}

function upsertProvider(provider) {
  const providers = getProviders();
  const index = providers.findIndex((p) => p.id === provider.id);
  if (index >= 0) {
    providers[index] = provider;
  } else {
    providers.push(provider);
  }
  saveProviders(providers);
  return provider;
}

function deleteProvider(id) {
  const providers = getProviders().filter((p) => p.id !== id);
  saveProviders(providers);
}

function getWebhooks(providerId) {
  const all = readJson('webhooks.json', {});
  return all[providerId] || [];
}

function addWebhook(providerId, webhook) {
  const all = readJson('webhooks.json', {});
  if (!all[providerId]) {
    all[providerId] = [];
  }
  all[providerId].unshift(webhook);
  all[providerId] = all[providerId].slice(0, 100);
  writeJson('webhooks.json', all);
  return webhook;
}

function getTestRuns(providerId) {
  const all = readJson('test-runs.json', {});
  return all[providerId] || [];
}

function addTestRun(providerId, run) {
  const all = readJson('test-runs.json', {});
  if (!all[providerId]) {
    all[providerId] = [];
  }
  all[providerId].unshift(run);
  all[providerId] = all[providerId].slice(0, 50);
  writeJson('test-runs.json', all);
  return run;
}

module.exports = {
  getProviders,
  getProvider,
  upsertProvider,
  deleteProvider,
  getWebhooks,
  addWebhook,
  getTestRuns,
  addTestRun,
};
