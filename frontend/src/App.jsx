import { useState } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { clearToken, getToken, saveToken } from './api/client'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'

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
    <Routes>
      <Route
        path="/"
        element={token ? (
          <main className="shell">
            <section className="card home-card">
              <p className="eyebrow">TEAM FINDER</p>
              <h1>{user ? `ยินดีต้อนรับ, ${user.display_name}` : 'คุณเข้าสู่ระบบแล้ว'}</h1>
              <p>ระบบสมัครสมาชิกและเข้าสู่ระบบพร้อมใช้งาน ฟีเจอร์กระดานหาทีมจะอยู่ในงานถัดไป</p>
              <button className="button secondary" type="button" onClick={logout}>ออกจากระบบ</button>
            </section>
          </main>
        ) : <Navigate to="/login" replace />}
      />
      <Route path="/login" element={token ? <Navigate to="/" replace /> : <LoginPage onAuth={handleAuth} />} />
      <Route path="/register" element={token ? <Navigate to="/" replace /> : <RegisterPage onAuth={handleAuth} />} />
      <Route path="*" element={<Navigate to={token ? '/' : '/login'} replace />} />
    </Routes>
  )
}
