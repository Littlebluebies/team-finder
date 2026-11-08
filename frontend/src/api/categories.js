export const CATEGORIES = [
  ['course', 'รายวิชา'],
  ['contest', 'การแข่งขัน'],
  ['personal', 'โปรเจกต์ส่วนตัว'],
]

export function categoryLabel(category) {
  const found = CATEGORIES.find(([value]) => value === category)
  return found ? found[1] : category
}
