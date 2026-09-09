function parseRow(row) {
  if (!row?.data) return null;
  try {
    return JSON.parse(row.data);
  } catch {
    return null;
  }
}

function parseCache(row) {
  if (!row) return undefined;
  try {
    return {
      key: row.key,
      payload: JSON.parse(row.payload),
      updatedAt: Number(row.updatedAt || 0),
    };
  } catch {
    return undefined;
  }
}

function requireDb(getDatabase) {
  const db = getDatabase();
  if (!db) throw new Error("Base SQLite non initialisée");
  return db;
}

const ALLOWED_FIELDS = {
  outbox: new Set(["clientId", "status", "createdAt", "id"]),
  conflicts: new Set(["clientId", "createdAt", "id"]),
};

function assertField(store, field) {
  if (!ALLOWED_FIELDS[store]?.has(field)) {
    throw new Error(`Champ SQLite non autorisé: ${store}.${field}`);
  }
  return field;
}

function storeOp(getDatabase, store, op, args = []) {
  const db = requireDb(getDatabase);

  if (store === "session") {
    if (op === "put") {
      const row = args[0] || {};
      db.prepare("INSERT OR REPLACE INTO kv_session (id, data) VALUES (?, ?)").run(String(row.id), JSON.stringify(row));
      return row;
    }
    if (op === "get") {
      return parseRow(db.prepare("SELECT data FROM kv_session WHERE id = ?").get(String(args[0])));
    }
    if (op === "clear") {
      db.prepare("DELETE FROM kv_session").run();
      return true;
    }
  }

  if (store === "cache") {
    if (op === "put") {
      const row = args[0] || {};
      db.prepare("INSERT OR REPLACE INTO kv_cache (key, payload, updatedAt) VALUES (?, ?, ?)").run(
        String(row.key),
        JSON.stringify(row.payload ?? null),
        Number(row.updatedAt || Date.now()),
      );
      return row;
    }
    if (op === "get") {
      return parseCache(db.prepare("SELECT key, payload, updatedAt FROM kv_cache WHERE key = ?").get(String(args[0])));
    }
    if (op === "delete") {
      db.prepare("DELETE FROM kv_cache WHERE key = ?").run(String(args[0]));
      return true;
    }
    if (op === "clear") {
      db.prepare("DELETE FROM kv_cache").run();
      return true;
    }
    if (op === "toArray") {
      return db.prepare("SELECT key, payload, updatedAt FROM kv_cache").all().map(parseCache).filter(Boolean);
    }
  }

  if (store === "outbox") {
    if (op === "add") {
      const row = args[0] || {};
      db.prepare(
        "INSERT INTO kv_outbox (clientId, status, createdAt, data) VALUES (?, ?, ?, ?)",
      ).run(String(row.clientId || ""), String(row.status || "pending"), String(row.createdAt || ""), JSON.stringify(row));
      const inserted = db.prepare("SELECT MAX(id) AS id FROM kv_outbox").get();
      return Number(inserted?.id);
    }
    if (op === "update") {
      const id = args[0];
      const patch = args[1] || {};
      const current = parseRow(db.prepare("SELECT data FROM kv_outbox WHERE id = ?").get(Number(id)));
      if (!current) return 0;
      const next = { ...current, ...patch, id: Number(id) };
      db.prepare("UPDATE kv_outbox SET clientId = ?, status = ?, createdAt = ?, data = ? WHERE id = ?").run(
        String(next.clientId || ""),
        String(next.status || ""),
        String(next.createdAt || ""),
        JSON.stringify(next),
        Number(id),
      );
      return 1;
    }
    if (op === "clear") {
      db.prepare("DELETE FROM kv_outbox").run();
      return true;
    }
    if (op === "whereEqualsFirst") {
      const [field, value] = args;
      const column = assertField("outbox", field);
      const row = db.prepare(`SELECT id, data FROM kv_outbox WHERE ${column} = ? LIMIT 1`).get(String(value));
      const parsed = parseRow(row);
      return parsed ? { ...parsed, id: Number(row.id) } : undefined;
    }
    if (op === "whereEqualsCount") {
      const [field, value] = args;
      const column = assertField("outbox", field);
      const row = db.prepare(`SELECT COUNT(*) AS n FROM kv_outbox WHERE ${column} = ?`).get(String(value));
      return Number(row?.n || 0);
    }
    if (op === "whereAnyOf") {
      const [field, values, sortField] = args;
      const column = assertField("outbox", field);
      const list = Array.isArray(values) ? values : [];
      if (list.length === 0) return [];
      const placeholders = list.map(() => "?").join(", ");
      const order = sortField && ALLOWED_FIELDS.outbox.has(sortField) ? ` ORDER BY ${sortField} ASC` : "";
      return db
        .prepare(`SELECT id, data FROM kv_outbox WHERE ${column} IN (${placeholders})${order}`)
        .all(...list.map(String))
        .map((row) => ({ ...parseRow(row), id: Number(row.id) }))
        .filter((row) => row.id != null);
    }
  }

  if (store === "conflicts") {
    if (op === "add") {
      const row = args[0] || {};
      db.prepare("INSERT INTO kv_conflicts (clientId, createdAt, data) VALUES (?, ?, ?)").run(
        String(row.clientId || ""),
        String(row.createdAt || ""),
        JSON.stringify(row),
      );
      const inserted = db.prepare("SELECT MAX(id) AS id FROM kv_conflicts").get();
      return Number(inserted?.id);
    }
    if (op === "delete") {
      db.prepare("DELETE FROM kv_conflicts WHERE id = ?").run(Number(args[0]));
      return true;
    }
    if (op === "clear") {
      db.prepare("DELETE FROM kv_conflicts").run();
      return true;
    }
    if (op === "whereEquals") {
      const [field, value] = args;
      const column = assertField("conflicts", field);
      return db
        .prepare(`SELECT id, data FROM kv_conflicts WHERE ${column} = ?`)
        .all(String(value))
        .map((row) => ({ ...parseRow(row), id: Number(row.id) }));
    }
    if (op === "orderByDesc") {
      const field = assertField("conflicts", args[0] || "createdAt");
      return db
        .prepare(`SELECT id, data FROM kv_conflicts ORDER BY ${field} DESC`)
        .all()
        .map((row) => ({ ...parseRow(row), id: Number(row.id) }));
    }
  }

  if (store === "stockLocal") {
    if (op === "put") {
      const row = args[0] || {};
      const id = String(row.medicamentId);
      db.prepare("INSERT OR REPLACE INTO kv_stock (medicamentId, data) VALUES (?, ?)").run(id, JSON.stringify(row));
      return row;
    }
    if (op === "get") {
      const row = db.prepare("SELECT data FROM kv_stock WHERE medicamentId = ?").get(String(args[0]));
      return parseRow(row);
    }
    if (op === "clear") {
      db.prepare("DELETE FROM kv_stock").run();
      return true;
    }
    if (op === "toArray") {
      return db.prepare("SELECT data FROM kv_stock").all().map(parseRow).filter(Boolean);
    }
    if (op === "bulkPut") {
      const rows = Array.isArray(args[0]) ? args[0] : [];
      for (const row of rows) {
        db.prepare("INSERT OR REPLACE INTO kv_stock (medicamentId, data) VALUES (?, ?)").run(
          String(row.medicamentId),
          JSON.stringify(row),
        );
      }
      return rows.length;
    }
  }

  if (store === "localUsers") {
    if (op === "put") {
      const row = args[0] || {};
      db.prepare("INSERT OR REPLACE INTO kv_users (telephone, data) VALUES (?, ?)").run(
        String(row.telephone),
        JSON.stringify(row),
      );
      return row;
    }
    if (op === "get") {
      return parseRow(db.prepare("SELECT data FROM kv_users WHERE telephone = ?").get(String(args[0])));
    }
    if (op === "clear") {
      db.prepare("DELETE FROM kv_users").run();
      return true;
    }
  }

  if (store === "idMap") {
    if (op === "put") {
      const row = args[0] || {};
      db.prepare("INSERT OR REPLACE INTO kv_idmap (localId, entityType, serverId, data) VALUES (?, ?, ?, ?)").run(
        String(row.localId),
        String(row.entityType || ""),
        String(row.serverId || ""),
        JSON.stringify(row),
      );
      return row;
    }
    if (op === "get") {
      return parseRow(db.prepare("SELECT data FROM kv_idmap WHERE localId = ?").get(String(args[0])));
    }
    if (op === "clear") {
      db.prepare("DELETE FROM kv_idmap").run();
      return true;
    }
  }

  if (store === "syncMeta") {
    if (op === "clear") {
      db.prepare("DELETE FROM kv_syncmeta").run();
      return true;
    }
    if (op === "put") {
      const row = args[0] || {};
      db.prepare("INSERT OR REPLACE INTO kv_syncmeta (id, data) VALUES (?, ?)").run(String(row.id), JSON.stringify(row));
      return row;
    }
    if (op === "get") {
      return parseRow(db.prepare("SELECT data FROM kv_syncmeta WHERE id = ?").get(String(args[0])));
    }
  }

  throw new Error(`Opération SQLite inconnue: ${store}.${op}`);
}

module.exports = { storeOp };
