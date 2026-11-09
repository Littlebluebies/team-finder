import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { closePost, fetchPost } from '../api/posts'
import { applyToPost, fetchApplications, updateApplication } from '../api/applications'
import { categoryLabel } from '../api/categories'
import { getFieldErrors } from '../api/client'
import { fetchMyApplications } from '../api/me'

const STATUS_LABELS = {
  pending: 'รอการตอบรับ',
  accepted: 'ตอบรับแล้ว',
  rejected: 'ไม่ได้รับเลือก',
}

export default function PostDetailPage() {
  const { id } = useParams()
  const [post, setPost] = useState(null)
  const [applications, setApplications] = useState(null)
  const [myApplication, setMyApplication] = useState(null)
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
          try {
            const items = await fetchApplications(id)
            if (active) setApplications(items)
          } catch {
            if (active) {
              setApplications(null)
              setActionError('โหลดรายการผู้สมัครไม่สำเร็จ กรุณารีเฟรชหน้า')
            }
          }
        } else if (data.viewer_application) {
          try {
            const items = await fetchMyApplications()
            if (active) {
              setMyApplication(items.find((item) => item.id === data.viewer_application.id) ?? null)
            }
          } catch {
            if (active) setMyApplication(null)
          }
        }
      })
      .catch(() => { if (active) setMessage('โหลดโพสต์ไม่สำเร็จ หรือโพสต์ไม่มีอยู่แล้ว') })
    return () => { active = false }
  }, [id])

  async function refresh() {
    const data = await fetchPost(id)
    setPost(data)
    if (data.is_owner) {
      try {
        setApplications(await fetchApplications(id))
      } catch {
        setActionError('โหลดรายการผู้สมัครไม่สำเร็จ กรุณารีเฟรชหน้า')
      }
    } else {
      setApplications([])
    }
    if (!data.is_owner && data.viewer_application) {
      try {
        const items = await fetchMyApplications()
        setMyApplication(items.find((item) => item.id === data.viewer_application.id) ?? null)
      } catch {
        setMyApplication(null)
      }
    } else {
      setMyApplication(null)
    }
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
          <span className={`badge badge-${post.status}`}>{post.status === 'open' ? 'เปิดรับ' : 'ปิดรับ'}</span>
          {post.is_owner && <span className="badge badge-own">โพสต์ของคุณ</span>}
        </div>
        <h1>{post.title}</h1>
        <p className="post-info">
          <span>รับ {post.slots} คน</span>
          <span>ปิดรับ {post.deadline}</span>
          <span>โพสต์โดย {post.owner.display_name}</span>
          <span>{post.created_at}</span>
        </p>
        <p className="post-desc">{post.description}</p>
        <h2>ทักษะที่ต้องการ</h2>
        <ul className="skill-chips">
          {post.skills.map((skill) => <li key={skill.id}>{skill.name}</li>)}
        </ul>

        <aside className="comment-rail">
          {post.is_owner ? (
            <>
              <div className="rail-head">
                <h2>{applications === null ? 'ผู้สมัคร' : `ผู้สมัคร (${applications.length})`}</h2>
              </div>
              {actionError && <div className="alert" role="alert">{actionError}</div>}
              {applications !== null && applications.length === 0 && (
                <div className="comment-empty">ยังไม่มีผู้สมัคร</div>
              )}
              {applications !== null && applications.length > 0 && (
                <ul className="comment-list">
                  {applications.map((application) => (
                    <li key={application.id} className={`comment comment-${application.status}`}>
                      <div className="comment-head">
                        <strong>{application.applicant.display_name}</strong>
                        <span className="comment-time">{application.created_at}</span>
                      </div>
                      {application.applicant.skills.length > 0 && (
                        <p className="comment-skills">
                          {application.applicant.skills.map((skill) => skill.name).join(' · ')}
                        </p>
                      )}
                      <p className="comment-message">{application.message}</p>
                      <p className="comment-state">สถานะ: {STATUS_LABELS[application.status]}</p>
                      {application.applicant.line_id && (
                        <p className="comment-line">LINE: <strong>{application.applicant.line_id}</strong></p>
                      )}
                      {application.status === 'pending' && (
                        <div className="comment-actions">
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
                <div className="rail-foot">
                  <button className="rail-action" type="button" disabled={acting} onClick={handleClose}>
                    ปิดรับสมัคร
                  </button>
                </div>
              )}
            </>
          ) : (
            <>
              <div className="rail-head">
                <h2>{post.viewer_application ? 'ใบสมัครของฉัน' : post.status === 'open' ? 'สมัครเข้าทีม' : 'สถานะโพสต์'}</h2>
              </div>
              {post.viewer_application ? (
                <div className={`comment comment-${post.viewer_application.status}`}>
                  <div className="comment-head">
                    <strong>ฉัน</strong>
                    {myApplication?.created_at && (
                      <span className="comment-time">{myApplication.created_at}</span>
                    )}
                  </div>
                  {myApplication?.message && (
                    <p className="comment-message">{myApplication.message}</p>
                  )}
                  <p className="comment-state">
                    สถานะใบสมัครของคุณ: <strong>{STATUS_LABELS[post.viewer_application.status]}</strong>
                  </p>
                  {post.viewer_application.status === 'accepted' && post.owner.line_id && (
                    <p className="comment-line">
                      ติดต่อเจ้าของโพสต์ผ่าน LINE: <strong>{post.owner.line_id}</strong>
                    </p>
                  )}
                </div>
              ) : post.status === 'open' ? (
                <form className="comment-composer" onSubmit={handleApply}>
                  <label className="field">
                    <span>ข้อความถึงเจ้าของโพสต์</span>
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
              ) : (
                <div className="comment-empty">ปิดรับสมัคร</div>
              )}
            </>
          )}
        </aside>

        <Link className="button secondary" to="/">กลับไปกระดานหาทีม</Link>
      </section>
    </main>
  )
}
