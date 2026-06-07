import axios from 'axios'

const TOKEN_KEY = 'token'
const REFRESH_TOKEN_KEY = 'refresh_token'
const REFRESH_CSRF_KEY = 'csrf_refresh_token'

const api = axios.create({
  baseURL: '/api',
  headers: { 'X-Requested-With': 'XMLHttpRequest' }
})

export function persistAuthPayload(payload) {
  if (payload?.token) localStorage.setItem(TOKEN_KEY, payload.token)
  if (payload?.accessToken) localStorage.setItem(TOKEN_KEY, payload.accessToken)
  if (payload?.refreshToken) localStorage.setItem(REFRESH_TOKEN_KEY, payload.refreshToken)
  if (payload?.csrfToken) localStorage.setItem(REFRESH_CSRF_KEY, payload.csrfToken)
}

export function clearAuthPayload() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(REFRESH_TOKEN_KEY)
  localStorage.removeItem(REFRESH_CSRF_KEY)
}

function unwrap(payload) {
  if (payload && payload.success === true && Object.prototype.hasOwnProperty.call(payload, 'data')) {
    return payload.data
  }
  return payload
}

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  const csrf = localStorage.getItem(REFRESH_CSRF_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  if (csrf) config.headers['X-CSRF-Refresh-Token'] = csrf
  return config
})

let refreshPromise = null

api.interceptors.response.use((response) => {
  response.data = unwrap(response.data)
  return response
}, async (error) => {
  const original = error.config
  const refreshToken = localStorage.getItem(REFRESH_TOKEN_KEY)

  if (error.response?.status !== 401 || original?._retry || !refreshToken || original?.url?.includes('/auth/refresh')) {
    return Promise.reject(error)
  }

  original._retry = true
  refreshPromise ||= axios
    .post('/api/auth/refresh', {
      refreshToken,
      csrfToken: localStorage.getItem(REFRESH_CSRF_KEY)
    }, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Refresh-Token': localStorage.getItem(REFRESH_CSRF_KEY) || ''
      }
    })
    .then((res) => {
      const data = unwrap(res.data)
      persistAuthPayload(data)
      return data?.token || data?.accessToken
    })
    .finally(() => {
      refreshPromise = null
    })

  const token = await refreshPromise
  original.headers ||= {}
  if (token) original.headers.Authorization = `Bearer ${token}`
  return api(original)
})

export default api
