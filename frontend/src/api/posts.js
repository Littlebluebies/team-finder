import { apiFetch } from './client'

export function fetchPosts() {
  return apiFetch('/posts', { auth: true })
}

export function fetchPost(id) {
  return apiFetch(`/posts/${id}`, { auth: true })
}

export function createPost(payload) {
  return apiFetch('/posts', { method: 'POST', body: payload, auth: true })
}
