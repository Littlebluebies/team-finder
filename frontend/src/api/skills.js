import { apiFetch } from './client'

export function getSkills() {
  return apiFetch('/skills')
}
