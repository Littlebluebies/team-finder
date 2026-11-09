import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchMyPosts } from '../api/me'
import { categoryLabel } from '../api/categories'

export default function MyPostsPage() {
  const [posts, setPosts] = useState(null)
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    fetchMyPosts()
      .then((items) => { if (active) setPosts(items) })
      .catch(() => { if (active) setMessage('โหลดโพสต์ของฉันไม่สำเร็จ กรุณารีเฟรชหน้า') })
    return () => { active = false }
  }, [])

  return (
    <main className="shell page-shell">
      <div className="page-head">
        <h1>โพสต์ของฉัน</h1>
        <Link className="button" to="/posts/new">สร้างโพสต์หาทีม</Link>
      </div>
      {message && <div className="alert" role="alert">{message}</div>}
      {posts === null && !message && <p>กำลังโหลดโพสต์…</p>}
      {posts !== null && posts.length === 0 && (
        <section className="card"><h2>ยังไม่มีโพสต์</h2><p>สร้างโพสต์แรกของคุณได้เลย</p></section>
      )}
      {posts !== null && posts.length > 0 && (
        <ul className="post-list">
          {posts.map((post) => (
            <li key={post.id}>
              <Link className="card post-card" to={`/posts/${post.id}`}>
                <div className="post-meta">
                  <span className={`badge badge-${post.category}`}>{categoryLabel(post.category)}</span>
                  <span className={`badge badge-${post.status}`}>{post.status === 'open' ? 'เปิดรับ' : 'ปิดรับ'}</span>
                  {post.pending_count > 0 && <span className="badge badge-pending">รอตอบ {post.pending_count}</span>}
                </div>
                <h2>{post.title}</h2>
                <p className="post-info">
                  รับแล้ว {post.accepted_count}/{post.slots} คน · ปิดรับ {post.deadline}
                </p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}
