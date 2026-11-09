import { useState } from 'react'
import { Link, Navigate, Route, Routes } from 'react-router-dom'
import { clearToken, getToken, saveToken } from './api/client'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import FeedPage from './pages/FeedPage'
import CreatePostPage from './pages/CreatePostPage'
import PostDetailPage from './pages/PostDetailPage'
import ProfilePage from './pages/ProfilePage'
import MyPostsPage from './pages/MyPostsPage'
import MyApplicationsPage from './pages/MyApplicationsPage'
import NotificationBadge from './components/NotificationBadge'
import NotificationsPage from './pages/NotificationsPage'
import LineCallbackPage from './pages/LineCallbackPage'

export default function App() {
  const [token, setToken] = useState(getToken)
  const [user, setUser] = useState(null)

  function handleAuth(result) {
    saveToken(result.token)
    setToken(result.token)
    setUser(result.user)
  }

  function logout() {
    clearToken()
    setToken(null)
    setUser(null)
  }

  return (
    <>
      {token && (
        <header className="topbar">
          <Link className="brand" to="/">TEAM FINDER</Link>
          <nav className="topbar-nav">
            <Link to="/">กระดาน</Link>
            <Link to="/me/posts">โพสต์ของฉัน</Link>
            <Link to="/me/applications">ใบสมัครของฉัน</Link>
            <NotificationBadge />
            <Link to="/me">โปรไฟล์</Link>
          </nav>
          <div className="topbar-actions">
            {user && <span className="topbar-user">{user.display_name}</span>}
            <button className="button secondary" type="button" onClick={logout}>ออกจากระบบ</button>
          </div>
        </header>
      )}
      <Routes>
        <Route path="/" element={token ? <FeedPage /> : <Navigate to="/login" replace />} />
        <Route path="/posts/new" element={token ? <CreatePostPage /> : <Navigate to="/login" replace />} />
        <Route path="/posts/:id" element={token ? <PostDetailPage /> : <Navigate to="/login" replace />} />
        <Route path="/me" element={token ? <ProfilePage /> : <Navigate to="/login" replace />} />
        <Route path="/me/posts" element={token ? <MyPostsPage /> : <Navigate to="/login" replace />} />
        <Route path="/me/applications" element={token ? <MyApplicationsPage /> : <Navigate to="/login" replace />} />
        <Route path="/me/notifications" element={token ? <NotificationsPage /> : <Navigate to="/login" replace />} />
        <Route path="/line/callback" element={token ? <LineCallbackPage /> : <Navigate to="/login" replace />} />
        <Route path="/login" element={token ? <Navigate to="/" replace /> : <LoginPage onAuth={handleAuth} />} />
        <Route path="/register" element={token ? <Navigate to="/" replace /> : <RegisterPage onAuth={handleAuth} />} />
        <Route path="*" element={<Navigate to={token ? '/' : '/login'} replace />} />
      </Routes>
    </>
  )
}
