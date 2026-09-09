const { app, BrowserWindow, ipcMain } = require("electron");
const path = require("path");
const { registerIpcHandlers } = require("./ipc/handlers.cjs");
const { initDatabase, closeDatabase } = require("./database/index.cjs");

const isDev = process.env.NODE_ENV === "development" || !app.isPackaged;
let mainWindow = null;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1360,
    height: 860,
    minWidth: 1024,
    minHeight: 640,
    title: "CHU UKV — Pharmacie",
    backgroundColor: "#111927",
    webPreferences: {
      preload: path.join(__dirname, "preload.cjs"),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
    show: false,
  });

  mainWindow.once("ready-to-show", () => mainWindow.show());

  if (isDev) {
    const port = process.env.VITE_DEV_PORT || "5173";
    mainWindow.loadURL(`http://localhost:${port}`);
    mainWindow.webContents.once("did-finish-load", () => {
      mainWindow.webContents.openDevTools({ mode: "detach" });
    });
  } else {
    mainWindow.loadFile(path.join(__dirname, "../dist/index.html"));
  }

  mainWindow.on("closed", () => {
    mainWindow = null;
  });
}

function registerDevShortcuts() {
  ipcMain.handle("devtools:toggle", () => {
    if (!mainWindow) return;
    if (mainWindow.webContents.isDevToolsOpened()) {
      mainWindow.webContents.closeDevTools();
    } else {
      mainWindow.webContents.openDevTools({ mode: "detach" });
    }
  });

  mainWindow?.webContents.on("before-input-event", (event, input) => {
    if (input.key === "F12") {
      event.preventDefault();
      if (mainWindow.webContents.isDevToolsOpened()) {
        mainWindow.webContents.closeDevTools();
      } else {
        mainWindow.webContents.openDevTools({ mode: "detach" });
      }
    }
  });
}

app.whenReady().then(async () => {
  try {
    await initDatabase();
    registerIpcHandlers();
    createWindow();
    registerDevShortcuts();
  } catch (error) {
    console.error("Erreur au démarrage:", error);
    app.quit();
  }

  app.on("activate", () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on("window-all-closed", () => {
  closeDatabase();
  if (process.platform !== "darwin") app.quit();
});

app.on("before-quit", () => {
  closeDatabase();
});
