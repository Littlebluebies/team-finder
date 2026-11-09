import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchMyApplications } from '../api/me'

const STATUS_LABELS = {
  pending: 'รอการตอบรับ',
  accepted: 'ตอบรับแล้ว',
  rejected: 'ไม่ได้รับเลือก',
}

export default function MyApplicationsPage() {
  const [applications, setApplications] = useState(null)
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    fetchMyApplications()
      .then((items) => { if (active) setApplications(items) })
      .catch(() => { if (active) setMessage('โหลดใบสมัครของฉันไม่สำเร็จ กรุณารีเฟรชหน้า') })
    return () => { active = false }
  }, [])

  return (
    <main className="shell page-shell">
      <div className="page-head">
        <h1>ใบสมัครของฉัน</h1>
      </div>
      {message && <div className="alert" role="alert">{message}</div>}
      {applications === null && !message && <p>กำลังโหลดใบสมัคร…</p>}
      {applications !== null && applications.length === 0 && (
        <section className="card"><h2>ยังไม่มีใบสมัคร</h2><p>เลือกโพสต์จากกระดานหาทีมเพื่อสมัครเข้าทีม</p></section>
      )}
      {applications !== null && applications.length > 0 && (
        <ul className="post-list">
          {applications.map((application) => (
            <li key={application.id} className="card">
              <div className="post-meta">
                <span className={`badge badge-${application.status}`}>{STATUS_LABELS[application.status]}</span>
                <span className={`badge badge-${application.post.status}`}>{application.post.status === 'open' ? 'เปิดรับ' : 'ปิดรับ'}</span>
              </div>
              <h2><Link to={`/posts/${application.post.id}`}>{application.post.title}</Link></h2>
              <p className="post-info">เจ้าของโพสต์: {application.post.owner.display_name}</p>
              {application.post.owner.line_id && (
                <p className="post-info">LINE: <strong>{application.post.owner.line_id}</strong></p>
              )}
              <p className="application-message">{application.message}</p>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}
