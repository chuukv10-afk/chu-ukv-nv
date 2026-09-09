/**
 * Lance Vite + Electron pour le développement desktop.
 */
import { spawn, execSync } from "child_process";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, "..");
const isWin = process.platform === "win32";
const PORT = Number(process.env.VITE_DEV_PORT) || 5173;

function freePort(port) {
  if (!isWin) return;
  try {
    const output = execSync(`netstat -ano | findstr :${port}`, { encoding: "utf8" });
    const pids = new Set();
    for (const line of output.split("\n")) {
      if (!line.includes("LISTENING")) continue;
      const pid = line.trim().split(/\s+/).pop();
      if (pid && /^\d+$/.test(pid)) pids.add(pid);
    }
    for (const pid of pids) {
      try {
        execSync(`taskkill /PID ${pid} /F`, { stdio: "ignore" });
        console.log(`  Port ${port} libéré (PID ${pid})`);
      } catch {
        /* ignore */
      }
    }
  } catch {
    /* port déjà libre */
  }
}

function runVite() {
  const viteBin = path.join(root, "node_modules", "vite", "bin", "vite.js");
  return spawn(process.execPath, [viteBin], {
    cwd: root,
    stdio: "inherit",
    env: { ...process.env, NODE_ENV: "development" },
  });
}

function runElectron() {
  const electronCli = path.join(root, "node_modules", "electron", "cli.js");
  const electronMain = path.join(root, "electron", "main.cjs");
  return spawn(process.execPath, [electronCli, electronMain], {
    cwd: root,
    stdio: "inherit",
    env: { ...process.env, NODE_ENV: "development", VITE_DEV_PORT: String(PORT) },
  });
}

function waitForServer(url, timeoutMs = 45000) {
  return new Promise((resolve, reject) => {
    const start = Date.now();
    const check = async () => {
      try {
        const res = await fetch(url);
        if (res.ok || res.status < 500) return resolve();
      } catch {
        /* retry */
      }
      if (Date.now() - start > timeoutMs) {
        return reject(new Error(`Timeout: ${url} indisponible après ${timeoutMs}ms`));
      }
      setTimeout(check, 400);
    };
    check();
  });
}

console.log("\nCHU UKV — Démarrage desktop (Vite + Electron + SQLite)\n");
freePort(PORT);
console.log(`  Renderer : http://localhost:${PORT}`);
console.log("  Electron : fenêtre desktop\n");

const vite = runVite();
let electron = null;

const startElectron = async () => {
  if (electron) return;
  try {
    await waitForServer(`http://localhost:${PORT}`);
    electron = runElectron();
    electron.on("exit", (code) => {
      vite.kill();
      process.exit(code ?? 0);
    });
  } catch (err) {
    console.error(err.message);
    vite.kill();
    process.exit(1);
  }
};

startElectron();

vite.on("exit", (code) => {
  if (electron) electron.kill();
  process.exit(code ?? 0);
});

process.on("SIGINT", () => {
  vite.kill();
  if (electron) electron.kill();
  process.exit(0);
});
