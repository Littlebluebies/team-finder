import { apiFetch } from './client'

export function fetchMe() {
  return apiFetch('/me', { auth: true })
}

export function updateMe(payload) {
  return apiFetch('/me', { method: 'PUT', body: payload, auth: true })
}

export function fetchMyPosts() {
  return apiFetch('/me/posts', { auth: true })
}

export function fetchMyApplications() {
  return apiFetch('/me/applications', { auth: true })
}
