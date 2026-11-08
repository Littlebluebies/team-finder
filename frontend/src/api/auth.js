import { apiFetch } from './client'

export function register(payload) {
  return apiFetch('/auth/register', { method: 'POST', body: payload })
}

export function login(payload) {
  return apiFetch('/auth/login', { method: 'POST', body: payload })
}
