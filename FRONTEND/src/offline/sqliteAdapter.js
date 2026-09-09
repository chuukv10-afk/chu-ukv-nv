function table(store) {
  const api = window.electronAPI;
  return {
    put: (row) => api.dbOp(store, "put", row),
    get: async (key) => (await api.dbOp(store, "get", key)) ?? undefined,
    add: (row) => api.dbOp(store, "add", row),
    update: (id, patch) => api.dbOp(store, "update", id, patch),
    delete: (id) => api.dbOp(store, "delete", id),
    clear: () => api.dbOp(store, "clear"),
    toArray: () => api.dbOp(store, "toArray"),
    bulkPut: (rows) => api.dbOp(store, "bulkPut", rows),
    where(field) {
      return {
        equals(value) {
          return {
            count: () => api.dbOp(store, "whereEqualsCount", field, value),
            first: async () => (await api.dbOp(store, "whereEqualsFirst", field, value)) ?? undefined,
            toArray: () => api.dbOp(store, "whereEquals", field, value),
          };
        },
        anyOf(values) {
          return {
            sortBy: (sortField) => api.dbOp(store, "whereAnyOf", field, values, sortField),
            toArray: () => api.dbOp(store, "whereAnyOf", field, values, null),
          };
        },
      };
    },
    orderBy(field) {
      return {
        reverse() {
          return {
            toArray: () => api.dbOp(store, "orderByDesc", field),
          };
        },
      };
    },
  };
}

export function createSqliteOfflineDb() {
  return {
    session: table("session"),
    cache: table("cache"),
    outbox: table("outbox"),
    syncMeta: table("syncMeta"),
    conflicts: table("conflicts"),
    stockLocal: table("stockLocal"),
    localUsers: table("localUsers"),
    idMap: table("idMap"),
  };
}
