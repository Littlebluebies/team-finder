import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { closePost, fetchPost } from '../api/posts'
import { applyToPost, fetchApplications, updateApplication } from '../api/applications'
import { categoryLabel } from '../api/categories'
import { getFieldErrors } from '../api/client'

const STATUS_LABELS = {
  pending: 'รอการตอบรับ',
  accepted: 'ตอบรับแล้ว',
  rejected: 'ไม่ได้รับเลือก',
}

export default function PostDetailPage() {
  const { id } = useParams()
  const [post, setPost] = useState(null)
  const [applications, setApplications] = useState([])
  const [message, setMessage] = useState('')
  const [formMessage, setFormMessage] = useState('')
  const [formError, setFormError] = useState('')
  const [actionError, setActionError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [acting, setActing] = useState(false)

  useEffect(() => {
    let active = true
    fetchPost(id)
      .then(async (data) => {
        if (!active) return
        setPost(data)
        if (data.is_owner) {
          const items = await fetchApplications(id)
          if (active) setApplications(items)
        }
      })
      .catch(() => { if (active) setMessage('โหลดโพสต์ไม่สำเร็จ หรือโพสต์ไม่มีอยู่แล้ว') })
    return () => { active = false }
  }, [id])

  async function refresh() {
    const data = await fetchPost(id)
    setPost(data)
    setApplications(data.is_owner ? await fetchApplications(id) : [])
  }

  async function handleApply(event) {
    event.preventDefault()
    setSubmitting(true)
    setFormError('')
    try {
      await applyToPost(id, { message: formMessage })
      setFormMessage('')
      await refresh()
    } catch (error) {
      setFormError(getFieldErrors(error).message || 'ส่งใบสมัครไม่สำเร็จ กรุณาลองใหม่')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleUpdate(applicationId, status) {
    setActing(true)
    setActionError('')
    try {
      await updateApplication(applicationId, status)
      await refresh()
    } catch {
      setActionError('อัปเดตใบสมัครไม่สำเร็จ กรุณาลองใหม่')
    } finally {
      setActing(false)
    }
  }

  async function handleClose() {
    setActing(true)
    setActionError('')
    try {
      await closePost(id)
      await refresh()
    } catch {
      setActionError('ปิดรับสมัครไม่สำเร็จ กรุณาลองใหม่')
    } finally {
      setActing(false)
    }
  }

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
          {post.is_owner && <span className="badge">โพสต์ของคุณ</span>}
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

        {post.viewer_application && (
          <div className="application-status">
            <p>สถานะใบสมัครของคุณ: <strong>{STATUS_LABELS[post.viewer_application.status]}</strong></p>
            {post.viewer_application.status === 'accepted' && post.owner.line_id && (
              <p>ติดต่อเจ้าของโพสต์ผ่าน LINE: <strong>{post.owner.line_id}</strong></p>
            )}
          </div>
        )}

        {!post.is_owner && post.status === 'open' && !post.viewer_application && (
          <form className="apply-form" onSubmit={handleApply}>
            <h2>สมัครเข้าทีม</h2>
            <label className="field">
              ข้อความถึงเจ้าของโพสต์
              <textarea
                rows={4}
                value={formMessage}
                onChange={(event) => setFormMessage(event.target.value)}
                required
              />
              {formError && <span className="field-error">{formError}</span>}
            </label>
            <button className="button" type="submit" disabled={submitting}>
              {submitting ? 'กำลังส่ง…' : 'ส่งใบสมัคร'}
            </button>
          </form>
        )}

        {post.is_owner && (
          <section className="owner-panel">
            <h2>ผู้สมัคร ({applications.length})</h2>
            {actionError && <div className="alert" role="alert">{actionError}</div>}
            {applications.length === 0 && <p>ยังไม่มีผู้สมัคร</p>}
            {applications.length > 0 && (
              <ul className="application-list">
                {applications.map((application) => (
                  <li key={application.id} className="application-item">
                    <div>
                      <strong>{application.applicant.display_name}</strong>
                      <p className="application-skills">
                        {application.applicant.skills.map((skill) => skill.name).join(' · ')}
                      </p>
                      <p className="application-message">{application.message}</p>
                      <p className="post-info">สถานะ: {STATUS_LABELS[application.status]}</p>
                      {application.applicant.line_id && (
                        <p className="post-info">LINE: {application.applicant.line_id}</p>
                      )}
                    </div>
                    {application.status === 'pending' && (
                      <div className="application-actions">
                        <button
                          className="button"
                          type="button"
                          disabled={acting}
                          onClick={() => handleUpdate(application.id, 'accepted')}
                        >
                          ตอบรับ
                        </button>
                        <button
                          className="button secondary"
                          type="button"
                          disabled={acting}
                          onClick={() => handleUpdate(application.id, 'rejected')}
                        >
                          ปฏิเสธ
                        </button>
                      </div>
                    )}
                  </li>
                ))}
              </ul>
            )}
            {post.status === 'open' && (
              <button className="button secondary" type="button" disabled={acting} onClick={handleClose}>
                ปิดรับสมัคร
              </button>
            )}
          </section>
        )}

        <Link className="button secondary" to="/">กลับไปกระดานหาทีม</Link>
      </section>
    </main>
  )
}
