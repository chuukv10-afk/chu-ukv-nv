const { contextBridge, ipcRenderer } = require("electron");

contextBridge.exposeInMainWorld("electronAPI", {
  isDesktop: true,
  getAppConfig: () => ipcRenderer.invoke("app:getConfig"),
  saveAppConfig: (config) => ipcRenderer.invoke("app:saveConfig", config),
  setupApp: (payload) => ipcRenderer.invoke("app:setup", payload),
  isFirstRun: () => ipcRenderer.invoke("app:isFirstRun"),
  getDbInfo: () => ipcRenderer.invoke("db:info"),
  dbOp: (store, op, ...args) => ipcRenderer.invoke("db:op", { store, op, args }),
  getAppPaths: () => ipcRenderer.invoke("system:paths"),
  getVersion: () => ipcRenderer.invoke("system:version"),
});
