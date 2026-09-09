const fs = require("fs");
const path = require("path");
const initSqlJs = require("sql.js");

class SqliteDatabase {
  constructor(sqlDb, dbPath) {
    this._db = sqlDb;
    this._dbPath = dbPath;
    this._persistTimer = null;
    this._closed = false;
  }

  pragma(statement) {
    this._db.run(`PRAGMA ${statement}`);
    this.persistSoon();
  }

  exec(sql) {
    this._db.exec(sql);
    this.persistSoon();
  }

  prepare(sql) {
    const self = this;
    return {
      run(...params) {
        self._db.run(sql, params);
        self.persistSoon();
        return { changes: self._db.getRowsModified() };
      },
      get(...params) {
        const stmt = self._db.prepare(sql);
        stmt.bind(params);
        let row = null;
        if (stmt.step()) row = stmt.getAsObject();
        stmt.free();
        return row;
      },
      all(...params) {
        const stmt = self._db.prepare(sql);
        stmt.bind(params);
        const rows = [];
        while (stmt.step()) rows.push(stmt.getAsObject());
        stmt.free();
        return rows;
      },
    };
  }

  persistSoon() {
    if (this._closed) return;
    if (this._persistTimer) clearTimeout(this._persistTimer);
    this._persistTimer = setTimeout(() => this.flush(), 80);
  }

  flush() {
    if (this._persistTimer) {
      clearTimeout(this._persistTimer);
      this._persistTimer = null;
    }
    if (this._closed) return;
    const dir = path.dirname(this._dbPath);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    const data = this._db.export();
    fs.writeFileSync(this._dbPath, Buffer.from(data));
  }

  close() {
    this.flush();
    this._closed = true;
    this._db.close();
  }
}

let SQL = null;

function resolveWasmPath() {
  const candidates = [
    path.join(__dirname, "..", "..", "node_modules", "sql.js", "dist", "sql-wasm.wasm"),
    path.join(process.cwd(), "node_modules", "sql.js", "dist", "sql-wasm.wasm"),
  ];

  try {
    const { app } = require("electron");
    if (app?.getAppPath) {
      candidates.unshift(
        path.join(app.getAppPath(), "node_modules", "sql.js", "dist", "sql-wasm.wasm"),
        path.join(process.resourcesPath || "", "app.asar.unpacked", "node_modules", "sql.js", "dist", "sql-wasm.wasm"),
      );
    }
  } catch {
    /* scripts CLI hors Electron */
  }

  return candidates.find((candidate) => fs.existsSync(candidate)) || null;
}

async function loadSqlJs() {
  if (!SQL) {
    const wasmPath = resolveWasmPath();
    const options = wasmPath ? { locateFile: () => wasmPath } : undefined;
    SQL = await initSqlJs(options);
  }
  return SQL;
}

async function openDatabaseFile(dbPath) {
  const SQL = await loadSqlJs();
  let sqlDb;
  if (fs.existsSync(dbPath)) {
    sqlDb = new SQL.Database(fs.readFileSync(dbPath));
  } else {
    sqlDb = new SQL.Database();
  }
  return new SqliteDatabase(sqlDb, dbPath);
}

module.exports = { SqliteDatabase, openDatabaseFile, loadSqlJs };
