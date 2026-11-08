const API_URL = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')
const TOKEN_KEY = 'team_finder_token'

export class ApiError extends Error {
  constructor(status, data) {
    const message = Array.isArray(data)
      ? 'กรุณาตรวจสอบข้อมูลในฟอร์ม'
      : data?.message || (status === 429
        ? 'มีการเรียกใช้งานถี่เกินไป กรุณารอสักครู่'
        : 'ไม่สามารถทำรายการได้ กรุณาลองใหม่')
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.data = data
  }
}

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function saveToken(token) {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY)
}

export function getFieldErrors(error) {
  if (!(error instanceof ApiError) || !Array.isArray(error.data)) return {}
  return Object.fromEntries(error.data.map(({ field, message }) => [field, message]))
}

export async function apiFetch(path, { method = 'GET', body, auth = false } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (auth) {
    const token = getToken()
    if (token) headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${API_URL}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const data = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401 && auth) {
      clearToken()
      window.location.replace('/login')
    }
    throw new ApiError(response.status, data)
  }
  return data
}
