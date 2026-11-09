import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { fetchNotifications, markNotificationsRead } from '../api/notifications'
import { ApiError } from '../api/client'

const LABELS = {
  application_received: 'มีผู้สมัครใหม่',
  application_accepted: 'ใบสมัครได้รับการตอบรับ',
  application_rejected: 'ใบสมัครไม่ผ่าน',
}

function relativeTime(value) {
  const timestamp = new Date(value.replace(' ', 'T')).getTime()
  const minutes = Math.floor((Date.now() - timestamp) / 60000)
  if (minutes < 1) return 'เมื่อสักครู่'
  if (minutes < 60) return `${minutes} นาทีที่แล้ว`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours} ชั่วโมงที่แล้ว`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days} วันที่แล้ว`
  return new Date(value.replace(' ', 'T')).toLocaleDateString('th-TH')
}

export default function NotificationsPage() {
  const navigate = useNavigate()
  const [items, setItems] = useState([])
  const [unread, setUnread] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    fetchNotifications()
      .then((data) => {
        if (!active) return
        setItems(data.items)
        setUnread(data.unread)
      })
      .catch((error) => {
        if (!active) return
        setMessage(error instanceof ApiError
          ? error.message
          : 'โหลดการแจ้งเตือนไม่สำเร็จ กรุณาลองใหม่')
      })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [])

  async function openNotification(item) {
    if (!item.is_read) {
      try {
        const data = await markNotificationsRead([item.id])
        setUnread(data.unread)
        setItems((current) => current.map((entry) => (
          entry.id === item.id ? { ...entry, is_read: true } : entry
        )))
      } catch {
        // ponytail: เปิดประกาศต่อได้แม้ mark read ล้มเหลว
      }
    }
    navigate(`/posts/${item.post.id}`)
  }

  async function markAll() {
    setBusy(true)
    try {
      const data = await markNotificationsRead()
      setUnread(data.unread)
      setItems((current) => current.map((entry) => ({ ...entry, is_read: true })))
    } catch (error) {
      setMessage(error instanceof ApiError ? error.message : 'ทำรายการไม่สำเร็จ')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="shell page-shell">
      <div className="page-head">
        <h1>การแจ้งเตือน</h1>
        <button
          className="button secondary"
          type="button"
          onClick={markAll}
          disabled={busy || unread === 0}
        >
          {busy ? 'กำลังบันทึก…' : 'อ่านทั้งหมด'}
        </button>
      </div>

      {message && <div className="alert" role="alert">{message}</div>}

      {loading ? (
        <p>กำลังโหลดการแจ้งเตือน…</p>
      ) : items.length === 0 ? (
        <div className="notification-empty">
          <p>ยังไม่มีการแจ้งเตือน</p>
          <p><Link to="/">ไปที่กระดานประกาศ</Link></p>
        </div>
      ) : (
        <ul className="notification-list">
          {items.map((item) => (
            <li
              key={item.id}
              className={item.is_read ? 'notification-item' : 'notification-item unread'}
            >
              <button className="notification-link" type="button" onClick={() => openNotification(item)}>
                <span className="notification-title">{LABELS[item.type] || item.type}</span>
                <span className="notification-meta">
                  {item.post.title} · {item.actor.display_name} · {relativeTime(item.created_at)}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}
