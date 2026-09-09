export function unwrapData(response) {
  return response?.data ?? response;
}

export function buildQueryString(params = {}) {
  const searchParams = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value === true) {
      searchParams.set(key, '1');
      return;
    }
    if (value === false) {
      return;
    }
    if (value !== undefined && value !== null && value !== '') {
      searchParams.set(key, String(value));
    }
  });
  const query = searchParams.toString();
  return query ? `?${query}` : '';
}

export function paginatedResult(data, params = {}) {
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? {
      page: 1,
      limit: params.limit ?? 10,
      total: 0,
      totalPages: 0,
    },
  };
}
