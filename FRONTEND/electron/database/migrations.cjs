const MIGRATIONS = [
  {
    version: 1,
    name: "desktop_replica",
    sql: `
      CREATE TABLE IF NOT EXISTS schema_migrations (
        version INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        applied_at TEXT NOT NULL DEFAULT (datetime('now'))
      );

      CREATE TABLE IF NOT EXISTS app_settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
      );

      CREATE TABLE IF NOT EXISTS kv_session (
        id TEXT PRIMARY KEY,
        data TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS kv_cache (
        key TEXT PRIMARY KEY,
        payload TEXT NOT NULL,
        updatedAt INTEGER NOT NULL DEFAULT 0
      );

      CREATE TABLE IF NOT EXISTS kv_outbox (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        clientId TEXT NOT NULL,
        status TEXT NOT NULL,
        createdAt TEXT NOT NULL,
        data TEXT NOT NULL
      );
      CREATE INDEX IF NOT EXISTS idx_outbox_status ON kv_outbox(status);
      CREATE INDEX IF NOT EXISTS idx_outbox_client ON kv_outbox(clientId);

      CREATE TABLE IF NOT EXISTS kv_conflicts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        clientId TEXT NOT NULL,
        createdAt TEXT NOT NULL,
        data TEXT NOT NULL
      );
      CREATE INDEX IF NOT EXISTS idx_conflicts_client ON kv_conflicts(clientId);

      CREATE TABLE IF NOT EXISTS kv_stock (
        medicamentId TEXT PRIMARY KEY,
        data TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS kv_users (
        telephone TEXT PRIMARY KEY,
        data TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS kv_idmap (
        localId TEXT PRIMARY KEY,
        entityType TEXT,
        serverId TEXT,
        data TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS kv_syncmeta (
        id TEXT PRIMARY KEY,
        data TEXT NOT NULL
      );
    `,
  },
];

function runMigrations(db) {
  db.exec(`
    CREATE TABLE IF NOT EXISTS schema_migrations (
      version INTEGER PRIMARY KEY,
      name TEXT NOT NULL,
      applied_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
  `);

  const applied = new Set(
    db.prepare("SELECT version FROM schema_migrations").all().map((row) => Number(row.version)),
  );

  for (const migration of MIGRATIONS) {
    if (applied.has(migration.version)) continue;
    db.exec(migration.sql);
    db.prepare(
      "INSERT INTO schema_migrations (version, name, applied_at) VALUES (?, ?, datetime('now'))",
    ).run(migration.version, migration.name);
  }
}

module.exports = { runMigrations };
