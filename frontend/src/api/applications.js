import { apiFetch } from './client'

export function applyToPost(postId, payload) {
  return apiFetch(`/posts/${postId}/applications`, { method: 'POST', body: payload, auth: true })
}

export function fetchApplications(postId) {
  return apiFetch(`/posts/${postId}/applications`, { auth: true })
}

export function updateApplication(applicationId, status) {
  return apiFetch(`/applications/${applicationId}`, { method: 'PATCH', body: { status }, auth: true })
}
