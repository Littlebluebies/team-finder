import { useState } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { clearToken, getToken, saveToken } from './api/client'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import FeedPage from './pages/FeedPage'
import CreatePostPage from './pages/CreatePostPage'
import PostDetailPage from './pages/PostDetailPage'

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
          <span className="brand">TEAM FINDER</span>
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
        <Route path="/login" element={token ? <Navigate to="/" replace /> : <LoginPage onAuth={handleAuth} />} />
        <Route path="/register" element={token ? <Navigate to="/" replace /> : <RegisterPage onAuth={handleAuth} />} />
        <Route path="*" element={<Navigate to={token ? '/' : '/login'} replace />} />
      </Routes>
    </>
  )
}
