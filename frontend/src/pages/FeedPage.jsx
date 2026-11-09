import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchPosts } from '../api/posts'
import { getSkills } from '../api/skills'
import { CATEGORIES, categoryLabel } from '../api/categories'

const emptyFilters = { skill: '', category: '', q: '' }

export default function FeedPage() {
  const [posts, setPosts] = useState(null)
  const [skills, setSkills] = useState([])
  const [filters, setFilters] = useState(emptyFilters)
  const [query, setQuery] = useState(emptyFilters)
  const [message, setMessage] = useState('')
  const [skillError, setSkillError] = useState('')

  useEffect(() => {
    let active = true
    getSkills()
      .then((items) => { if (active) setSkills(items) })
      .catch(() => {
        if (active) setSkillError('โหลดรายการทักษะไม่สำเร็จ กรองด้วยทักษะไม่ได้ กรุณารีเฟรชหน้า')
      })
    return () => { active = false }
  }, [])

  useEffect(() => {
    let active = true
    fetchPosts(query)
      .then((items) => { if (active) { setPosts(items); setMessage('') } })
      .catch(() => { if (active) setMessage('โหลดกระดานหาทีมไม่สำเร็จ กรุณารีเฟรชหน้า') })
    return () => { active = false }
  }, [query])

  function update(event) {
    setFilters((current) => ({ ...current, [event.target.name]: event.target.value }))
  }

  function submit(event) {
    event.preventDefault()
    setQuery(filters)
  }

  function clear() {
    setFilters(emptyFilters)
    setQuery(emptyFilters)
  }

  return (
    <main className="shell page-shell">
      <div className="page-head">
        <h1>กระดานหาทีม</h1>
        <Link className="button" to="/posts/new">สร้างโพสต์หาทีม</Link>
      </div>

      <form className="filter-bar" onSubmit={submit}>
        <label className="field">
          <span>ทักษะ</span>
          <select name="skill" value={filters.skill} onChange={update} disabled={Boolean(skillError)}>
            <option value="">ทั้งหมด</option>
            {skills.map((skill) => <option key={skill.id} value={skill.id}>{skill.name}</option>)}
          </select>
        </label>
        <label className="field">
          <span>ประเภท</span>
          <select name="category" value={filters.category} onChange={update}>
            <option value="">ทั้งหมด</option>
            {CATEGORIES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
        </label>
        <label className="field">
          <span>ค้นหา</span>
          <input name="q" value={filters.q} onChange={update} placeholder="ชื่อหรือรายละเอียด" />
        </label>
        <div className="form-actions">
          <button className="button" type="submit">กรอง</button>
          <button className="button secondary" type="button" onClick={clear}>ล้าง</button>
        </div>
      </form>

      {message && <div className="alert" role="alert">{message}</div>}
      {skillError && <div className="alert" role="alert">{skillError}</div>}
      {posts === null && !message && <p>กำลังโหลดโพสต์…</p>}
      {posts !== null && posts.length === 0 && (
        <section className="card">
          <h2>ยังไม่มีโพสต์</h2>
          <p>เป็นคนแรกที่เปิดรับสมาชิกทีมได้เลย</p>
        </section>
      )}
      {posts !== null && posts.length > 0 && (
        <ul className="post-list">
          {posts.map((post) => (
            <li key={post.id}>
              <Link className="card post-card" to={`/posts/${post.id}`}>
                <div className="post-meta">
                  <span className={`badge badge-${post.category}`}>{categoryLabel(post.category)}</span>
                  <span className={`badge badge-${post.status}`}>{post.status === 'open' ? 'เปิดรับ' : 'ปิดรับ'}</span>
                </div>
                <h2>{post.title}</h2>
                <p className="post-desc">{post.description}</p>
                <p className="post-info">
                  <span>รับแล้ว {post.accepted_count}/{post.slots} คน</span>
                  <span>ปิดรับ {post.deadline}</span>
                  <span>โพสต์โดย {post.owner.display_name}</span>
                </p>
                <ul className="skill-chips">
                  {post.skills.map((skill) => <li key={skill.id}>{skill.name}</li>)}
                </ul>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}
