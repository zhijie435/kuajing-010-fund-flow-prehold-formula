const API_BASE = 'http://localhost:8000/api';

const api = {
  async request(path, options = {}) {
    const url = `${API_BASE}${path}`;
    const config = {
      headers: {
        'Content-Type': 'application/json',
      },
      ...options,
    };
    if (options.body && typeof options.body !== 'string') {
      config.body = JSON.stringify(options.body);
    }
    try {
      const res = await fetch(url, config);
      const data = await res.json();
      return data;
    } catch (e) {
      return { code: -1, message: e.message, data: null };
    }
  },

  get(path) {
    return this.request(path, { method: 'GET' });
  },

  post(path, body = {}) {
    return this.request(path, { method: 'POST', body });
  },

  put(path, body = {}) {
    return this.request(path, { method: 'PUT', body });
  },

  delete(path) {
    return this.request(path, { method: 'DELETE' });
  },

  dashboard: {
    index() { return api.get('/dashboard'); },
  },

  formula: {
    list(params = {}) {
      const query = new URLSearchParams(params).toString();
      return api.get(`/withholding-formulas${query ? '?' + query : ''}`);
    },
    active() { return api.get('/withholding-formulas/active'); },
    detail(id) { return api.get(`/withholding-formulas/${id}`); },
    create(data) { return api.post('/withholding-formulas', data); },
    update(id, data) { return api.put(`/withholding-formulas/${id}`, data); },
    delete(id) { return api.delete(`/withholding-formulas/${id}`); },
    validate(data) { return api.post('/withholding-formulas/validate', data); },
  },

  withholding: {
    calculate(data) { return api.post('/withholding/calculate', data); },
    preview(data) { return api.post('/withholding/preview', data); },
    batchCalculate(data) { return api.post('/withholding/batch-calculate', data); },
    details(params = {}) {
      const query = new URLSearchParams(params).toString();
      return api.get(`/withholding/details${query ? '?' + query : ''}`);
    },
    detail(id) { return api.get(`/withholding/details/${id}`); },
    logs(id, params = {}) {
      const query = new URLSearchParams(params).toString();
      return api.get(`/withholding/details/${id}/logs${query ? '?' + query : ''}`);
    },
    changeStatus(id, data) { return api.put(`/withholding/details/${id}/status`, data); },
    addRemark(id, data) { return api.put(`/withholding/details/${id}/remark`, data); },
    statusTypes() { return api.get('/withholding/details/status-types'); },
    stats() { return api.get('/withholding/details/stats'); },
  },

  fundflow: {
    list(params = {}) {
      const query = new URLSearchParams(params).toString();
      return api.get(`/fund-flows${query ? '?' + query : ''}`);
    },
    types() { return api.get('/fund-flows/types'); },
    stats(params = {}) {
      const query = new URLSearchParams(params).toString();
      return api.get(`/fund-flows/stats${query ? '?' + query : ''}`);
    },
    detail(id) { return api.get(`/fund-flows/${id}`); },
    create(data) { return api.post('/fund-flows', data); },
    logs(id, params = {}) {
      const query = new URLSearchParams(params).toString();
      return api.get(`/fund-flows/${id}/logs${query ? '?' + query : ''}`);
    },
    changeStatus(id, data) { return api.put(`/fund-flows/${id}/status`, data); },
    addRemark(id, data) { return api.put(`/fund-flows/${id}/remark`, data); },
  },
};
