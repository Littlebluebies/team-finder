import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchPosts } from '../api/posts'
import { categoryLabel } from '../api/categories'

export default function FeedPage() {
  const [posts, setPosts] = useState(null)
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    fetchPosts()
      .then((items) => { if (active) setPosts(items) })
      .catch(() => { if (active) setMessage('โหลดกระดานหาทีมไม่สำเร็จ กรุณารีเฟรชหน้า') })
    return () => { active = false }
  }, [])

  return (
    <main className="shell page-shell">
      <div className="page-head">
        <div>
          <p className="eyebrow">TEAM FINDER</p>
          <h1>กระดานหาทีม</h1>
        </div>
        <Link className="button" to="/posts/new">สร้างโพสต์หาทีม</Link>
      </div>
      {message && <div className="alert" role="alert">{message}</div>}
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
                  <span className="badge">{post.status === 'open' ? 'เปิดรับ' : 'ปิดรับ'}</span>
                </div>
                <h2>{post.title}</h2>
                <p className="post-desc">{post.description}</p>
                <p className="post-info">
                  รับ {post.slots} คน · ปิดรับ {post.deadline} · โพสต์โดย {post.owner.display_name}
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
