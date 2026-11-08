import { apiFetch } from './client'

export function fetchPosts(params = {}) {
  const search = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) search.set(key, value)
  })
  const query = search.toString()
  return apiFetch(`/posts${query ? `?${query}` : ''}`, { auth: true })
}

export function fetchPost(id) {
  return apiFetch(`/posts/${id}`, { auth: true })
}

export function createPost(payload) {
  return apiFetch('/posts', { method: 'POST', body: payload, auth: true })
}

export function closePost(id) {
  return apiFetch(`/posts/${id}`, { method: 'PATCH', body: { status: 'closed' }, auth: true })
}
