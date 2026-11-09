import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { fetchNotifications } from '../api/notifications'

export default function NotificationBadge() {
  const location = useLocation()
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    let active = true
    fetchNotifications()
      .then((data) => {
        if (active) setUnread(data.unread)
      })
      .catch(() => {
        if (active) setUnread(0)
      })
    return () => { active = false }
  }, [location.pathname])

  return (
    <Link
      className="notification-badge"
      to="/me/notifications"
      aria-label={`การแจ้งเตือน ${unread} รายการที่ยังไม่อ่าน`}
    >
      การแจ้งเตือน
      {unread > 0 && (
        <span className="notification-count">{unread > 9 ? '9+' : unread}</span>
      )}
    </Link>
  )
}
