import axios from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept-Language': 'ar'
  },
  timeout: 60000 // 60 seconds for signing operations
});

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// Token Management APIs
export const tokenAPI = {
  scan: () => api.get('/tokens/scan'),
  getInfo: (tokenId) => api.get(`/tokens/${tokenId}`),
  getCertificates: (tokenId) => api.get(`/tokens/${tokenId}/certificates`),
  connect: (tokenId, pin) => api.post(`/tokens/${tokenId}/connect`, { pin }),
  disconnect: (tokenId) => api.post(`/tokens/${tokenId}/disconnect`)
};

// Certificate Management APIs
export const certificateAPI = {
  list: () => api.get('/certificates'),
  getDetails: (certId) => api.get(`/certificates/${certId}`),
  validate: (certId) => api.post(`/certificates/${certId}/validate`),
  export: (certId, format, passphrase) => 
    api.post(`/certificates/${certId}/export`, { format, passphrase }),
  import: (formData) => 
    api.post('/certificates/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    }),
  createCSR: (data) => api.post('/certificates/csr', data),
  createSelfSigned: (data) => api.post('/certificates/self-signed', data)
};

// Document Signing APIs
export const signingAPI = {
  signDocument: (formData) => 
    api.post('/signing/sign', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      responseType: 'blob'
    }),
  signXML: (data) => api.post('/signing/xml', data),
  signPDF: (formData) => 
    api.post('/signing/pdf', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      responseType: 'blob'
    }),
  signZatca: (xmlData) => api.post('/signing/zatca', xmlData),
  verifySignature: (formData) => 
    api.post('/signing/verify', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    }),
  bulkSign: (data) => api.post('/signing/bulk', data)
};

// Audit Log APIs
export const auditAPI = {
  getLogs: (params) => api.get('/audit/logs', { params }),
  searchLogs: (query) => api.get('/audit/search', { params: { q: query } }),
  getStats: () => api.get('/audit/stats'),
  exportLogs: (dateFrom, dateTo) => 
    api.get('/audit/export', { 
      params: { date_from: dateFrom, date_to: dateTo },
      responseType: 'blob'
    })
};

// Provider APIs
export const providerAPI = {
  list: () => api.get('/providers'),
  getStatus: (providerId) => api.get(`/providers/${providerId}/status`),
  getCapabilities: (providerId) => api.get(`/providers/${providerId}/capabilities`)
};

// Settings APIs
export const settingsAPI = {
  get: () => api.get('/settings'),
  update: (data) => api.put('/settings', data),
  getTSAStatus: () => api.get('/settings/tsa-status'),
  testConnection: (providerId) => api.post(`/settings/test-connection/${providerId}`)
};

export default api;
