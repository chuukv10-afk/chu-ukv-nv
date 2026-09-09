const DEFAULT_API_BASE_URL = "http://54.155.99.199";

const fs = require("fs");
const path = require("path");
const { app } = require("electron");
const { runMigrations } = require("./migrations.cjs");
const { slugify } = require("./utils.cjs");
const { openDatabaseFile } = require("./sqljs-adapter.cjs");

let db = null;
let config = null;

const CONFIG_FILENAME = "chu-ukv-desktop.json";

function isDevMode() {
  try {
    return process.env.NODE_ENV === "development" || !app.isPackaged;
  } catch {
    return process.env.NODE_ENV === "development";
  }
}

function getDataRoot() {
  if (isDevMode()) {
    return path.join(process.cwd(), "data");
  }
  return app.getPath("userData");
}

function getConfigPath() {
  return path.join(getDataRoot(), CONFIG_FILENAME);
}

function loadConfig() {
  const configPath = getConfigPath();
  if (!fs.existsSync(configPath)) return null;
  try {
    return JSON.parse(fs.readFileSync(configPath, "utf-8"));
  } catch {
    return null;
  }
}

function saveConfig(newConfig) {
  const configPath = getConfigPath();
  const dir = path.dirname(configPath);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(configPath, JSON.stringify(newConfig, null, 2), "utf-8");
  config = newConfig;
  return config;
}

function getDbDirectory() {
  return path.join(getDataRoot(), "databases");
}

function getDbPath(dbName) {
  return path.join(getDbDirectory(), `${dbName}.db`);
}

async function openDatabase(dbName) {
  const dbDir = getDbDirectory();
  if (!fs.existsSync(dbDir)) fs.mkdirSync(dbDir, { recursive: true });
  const dbPath = getDbPath(dbName);
  const instance = await openDatabaseFile(dbPath);
  instance.pragma("foreign_keys = ON");
  runMigrations(instance);
  return { instance, dbPath };
}

async function initDatabase() {
  config = loadConfig();
  if (!config?.dbName) {
    return null;
  }
  const { instance, dbPath } = await openDatabase(config.dbName);
  db = instance;
  config.dbPath = dbPath;
  saveConfig(config);
  return db;
}

async function createDatabase(siteName, siteCode, apiBaseUrl) {
  const dbName = `${slugify(siteName)}-${slugify(siteCode || "ukv")}`;
  const { instance, dbPath } = await openDatabase(dbName);

  instance.prepare(
    `INSERT OR REPLACE INTO app_settings (key, value, updated_at) VALUES (?, ?, datetime('now'))`,
  ).run("site_name", siteName);
  instance.prepare(
    `INSERT OR REPLACE INTO app_settings (key, value, updated_at) VALUES (?, ?, datetime('now'))`,
  ).run("site_code", siteCode || "UKV");
  instance.prepare(
    `INSERT OR REPLACE INTO app_settings (key, value, updated_at) VALUES (?, ?, datetime('now'))`,
  ).run("api_base_url", apiBaseUrl || DEFAULT_API_BASE_URL);

  db = instance;
  config = {
    siteName,
    siteCode: siteCode || "UKV",
    dbName,
    dbPath,
    apiBaseUrl: String(apiBaseUrl || DEFAULT_API_BASE_URL).replace(/\/$/, ""),
    createdAt: new Date().toISOString(),
  };
  saveConfig(config);
  return config;
}

function closeDatabase() {
  if (db) {
    db.close();
    db = null;
  }
}

function getDatabase() {
  return db;
}

function getAppConfig() {
  return config || loadConfig();
}

function isFirstRun() {
  return !loadConfig()?.dbName;
}

function getDbInfo() {
  const cfg = getAppConfig();
  if (!cfg?.dbName) {
    return { initialized: false };
  }
  const dbPath = cfg.dbPath || getDbPath(cfg.dbName);
  const stats = fs.existsSync(dbPath) ? fs.statSync(dbPath) : null;
  let tables = [];
  if (db) {
    tables = db
      .prepare("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")
      .all()
      .map((row) => row.name);
  }
  return {
    initialized: true,
    siteName: cfg.siteName,
    siteCode: cfg.siteCode,
    dbName: cfg.dbName,
    dbPath,
    apiBaseUrl: cfg.apiBaseUrl,
    sizeBytes: stats?.size || 0,
    tables,
  };
}

module.exports = {
  DEFAULT_API_BASE_URL,
  initDatabase,
  createDatabase,
  closeDatabase,
  getDatabase,
  getAppConfig,
  saveConfig,
  isFirstRun,
  getDbInfo,
  getDataRoot,
};
