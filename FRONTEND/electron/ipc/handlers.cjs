const { ipcMain, app } = require("electron");
const {
  getDatabase,
  getAppConfig,
  saveConfig,
  isFirstRun,
  getDbInfo,
  createDatabase,
  DEFAULT_API_BASE_URL,
} = require("../database/index.cjs");
const { storeOp } = require("../database/stores.cjs");

function registerIpcHandlers() {
  ipcMain.handle("app:getConfig", () => getAppConfig());
  ipcMain.handle("app:isFirstRun", () => isFirstRun());
  ipcMain.handle("app:saveConfig", (_event, partialConfig) => {
    const current = getAppConfig() || {};
    return saveConfig({ ...current, ...partialConfig });
  });

  ipcMain.handle("app:setup", async (_event, { siteName, siteCode, apiBaseUrl }) => {
    return createDatabase(siteName, siteCode, apiBaseUrl || DEFAULT_API_BASE_URL);
  });

  ipcMain.handle("db:info", () => getDbInfo());
  ipcMain.handle("db:op", (_event, { store, op, args = [] }) => storeOp(getDatabase, store, op, args));

  ipcMain.handle("system:paths", () => ({
    userData: app.getPath("userData"),
    appPath: app.getAppPath(),
  }));
  ipcMain.handle("system:version", () => app.getVersion());
}

module.exports = { registerIpcHandlers };
