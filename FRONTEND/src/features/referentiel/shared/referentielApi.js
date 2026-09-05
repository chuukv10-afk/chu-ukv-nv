import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
} from '../../../api/apiClient.js';

function unwrapData(response) {
  return response?.data ?? response;
}

function unwrapList(response) {
  const data = unwrapData(response);
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.items)) return data.items;
  return [];
}

function buildQueryString(params = {}) {
  const searchParams = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      searchParams.set(key, String(value));
    }
  });
  const query = searchParams.toString();
  return query ? `?${query}` : '';
}

export function createReferentielApi(endpoint) {
  return {
    async fetchList(params = {}) {
      const response = await callApiGet(`${endpoint}${buildQueryString(params)}`);
      const data = unwrapData(response);
      return {
        items: Array.isArray(data?.items) ? data.items : unwrapList(response),
        pagination: data?.pagination ?? {
          page: 1,
          limit: params.limit ?? 10,
          total: unwrapList(response).length,
          totalPages: 1,
        },
      };
    },

    async fetchLookup() {
      const response = await this.fetchList({ page: 1, limit: 100 });
      return response.items;
    },

    async fetchOne(id) {
      const response = await callApiGet(`${endpoint}/${id}`);
      return unwrapData(response);
    },

    async create(payload) {
      const response = await callApiPost(endpoint, payload);
      return unwrapData(response);
    },

    async update(id, payload) {
      const response = await callApiPut(`${endpoint}/${id}`, payload);
      return unwrapData(response);
    },

    async delete(id) {
      return callApiDelete(`${endpoint}/${id}`);
    },
  };
}
