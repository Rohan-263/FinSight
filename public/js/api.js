/** Thin wrapper around fetch() for talking to the FinSight PHP API. */
const API_BASE = '/api';

async function apiRequest(path, options = {}) {
    const { method = 'GET', body } = options;
    const fetchOptions = { method, credentials: 'same-origin', headers: {} };

    if (body !== undefined) {
        fetchOptions.headers['Content-Type'] = 'application/json';
        fetchOptions.body = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(`${API_BASE}/${path}`, fetchOptions);
    } catch (networkErr) {
        throw new Error('Could not reach the server. Check your connection and try again.');
    }

    let data = null;
    try {
        data = await response.json();
    } catch (parseErr) {
        throw new Error('Unexpected server response.');
    }

    if (!response.ok || data.success === false) {
        throw new Error(data.error || `Request failed (${response.status}).`);
    }
    return data;
}

const api = {
    get:  (path) => apiRequest(path, { method: 'GET' }),
    post: (path, body) => apiRequest(path, { method: 'POST', body }),
    put:  (path, body) => apiRequest(path, { method: 'PUT', body }),
    del:  (path) => apiRequest(path, { method: 'DELETE' }),
};

function formatINR(amount) {
    const n = Number(amount) || 0;
    return '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function formatDateLong(isoDate) {
    const d = new Date(isoDate + 'T00:00:00');
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
