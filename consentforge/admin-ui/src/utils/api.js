import apiFetch from '@wordpress/api-fetch';

const NS = '/consentforge/v1';

const api = {
    get:    (path)       => apiFetch({ path: NS + path }),
    post:   (path, data) => apiFetch({ path: NS + path, method: 'POST',   data }),
    put:    (path, data) => apiFetch({ path: NS + path, method: 'PUT',    data }),
    delete: (path)       => apiFetch({ path: NS + path, method: 'DELETE' }),
};

export const adminApi = {
    getDashboardStats:   ()             => api.get('/admin/dashboard/stats'),
    getCookies:          (page = 1, q)  => api.get(`/admin/cookies?page=${page}${q ? '&category=' + q : ''}`),
    createCookie:        (data)         => api.post('/admin/cookies', data),
    updateCookie:        (id, data)     => api.put(`/admin/cookies/${id}`, data),
    deleteCookie:        (id)           => api.delete(`/admin/cookies/${id}`),
    runScanner:          ()             => api.post('/admin/scanner/run', {}),
    getScannerResults:   ()             => api.get('/admin/scanner/results'),
    getConsentLogs:      (page = 1, f)  => api.get(`/admin/consent-logs?page=${page}${f ? '&' + new URLSearchParams(f) : ''}`),
    verifyReceiptChain:  ()             => api.post('/admin/receipts/verify', {}),
    getDsarRequests:     ()             => api.get('/admin/dsar'),
    processDsar:         (id)           => api.post(`/admin/dsar/${id}/process`, {}),
    getSettings:         (group)        => api.get(`/admin/settings/${group}`),
    updateSettings:      (group, data)  => api.put(`/admin/settings/${group}`, data),
};
