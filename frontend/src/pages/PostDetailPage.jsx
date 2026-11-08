import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { fetchPost } from '../api/posts'
import { categoryLabel } from '../api/categories'

export default function PostDetailPage() {
  const { id } = useParams()
  const [post, setPost] = useState(null)
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    fetchPost(id)
      .then((data) => { if (active) setPost(data) })
      .catch(() => { if (active) setMessage('โหลดโพสต์ไม่สำเร็จ หรือโพสต์ไม่มีอยู่แล้ว') })
    return () => { active = false }
  }, [id])

  if (message) {
    return (
      <main className="shell page-shell">
        <div className="alert" role="alert">{message}</div>
        <p><Link to="/">กลับไปกระดานหาทีม</Link></p>
      </main>
    )
  }

  if (post === null) {
    return <main className="shell page-shell"><p>กำลังโหลดโพสต์…</p></main>
  }

  return (
    <main className="shell page-shell">
      <section className="card post-detail">
        <div className="post-meta">
          <span className={`badge badge-${post.category}`}>{categoryLabel(post.category)}</span>
          <span className="badge">{post.status === 'open' ? 'เปิดรับ' : 'ปิดรับ'}</span>
        </div>
        <h1>{post.title}</h1>
        <p className="post-info">
          รับ {post.slots} คน · ปิดรับ {post.deadline} · โพสต์โดย {post.owner.display_name} · {post.created_at}
        </p>
        <p className="post-desc">{post.description}</p>
        <h2>ทักษะที่ต้องการ</h2>
        <ul className="skill-chips">
          {post.skills.map((skill) => <li key={skill.id}>{skill.name}</li>)}
        </ul>
        <p className="privacy-note">การสมัครเข้าทีมจะเปิดใช้งานในงานถัดไป</p>
        <Link className="button secondary" to="/">กลับไปกระดานหาทีม</Link>
      </section>
    </main>
  )
}
