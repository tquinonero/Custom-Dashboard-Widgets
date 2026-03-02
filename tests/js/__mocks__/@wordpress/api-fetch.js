const apiFetch = jest.fn();
apiFetch.use = jest.fn();
apiFetch.createNonceMiddleware = jest.fn(() => jest.fn());
module.exports = apiFetch;
