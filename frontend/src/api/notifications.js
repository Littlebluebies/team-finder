import { apiFetch } from './client'

export function fetchNotifications() {
  return apiFetch('/notifications', { auth: true })
}

export function markNotificationsRead(ids) {
  return apiFetch('/notifications', {
    method: 'PATCH',
    auth: true,
    body: ids ? { ids } : {},
  })
}

export function getLineAuthorizeUrl() {
  return apiFetch('/me/line/authorize-url', { auth: true })
}

export function linkLine(code, state) {
  return apiFetch('/me/line', {
    method: 'POST',
    auth: true,
    body: { code, state },
  })
}

export function unlinkLine() {
  return apiFetch('/me/line', { method: 'DELETE', auth: true })
}
