import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { login } from '../api/auth'
import { ApiError, getFieldErrors } from '../api/client'
import AuthLinks from '../components/AuthLinks'

export default function LoginPage({ onAuth }) {
  const [form, setForm] = useState({ email: '', password: '' })
  const [fieldErrors, setFieldErrors] = useState({})
  const [message, setMessage] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const navigate = useNavigate()

  function update(event) {
    setForm((current) => ({ ...current, [event.target.name]: event.target.value }))
  }

  async function submit(event) {
    event.preventDefault()
    setMessage('')
    setFieldErrors({})
    setSubmitting(true)
    try {
      const result = await login(form)
      onAuth(result)
      navigate('/')
    } catch (error) {
      if (error instanceof ApiError) {
        setFieldErrors(getFieldErrors(error))
        setMessage(error.status === 401 ? 'อีเมลหรือรหัสผ่านไม่ถูกต้อง' : error.message)
      } else {
        setMessage('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาตรวจว่า backend กำลังรันอยู่')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="shell">
      <section className="card auth-card">
        <div className="doc-chrome">
          <span className="doc-mark">TEAM FINDER</span>
        </div>
        <h1>เข้าสู่ระบบ</h1>
        <p>กลับไปหาทีมที่ใช่สำหรับโปรเจกต์ของคุณ</p>
        {message && <div className="alert" role="alert">{message}</div>}
        <form onSubmit={submit}>
          <label className="field">
            <span>อีเมล</span>
            <input
              id="email"
              name="email"
              type="email"
              value={form.email}
              onChange={update}
              autoComplete="email"
              required
              aria-invalid={Boolean(fieldErrors.email)}
              aria-describedby={fieldErrors.email ? 'email-error' : undefined}
            />
            {fieldErrors.email && <small id="email-error" className="field-error">{fieldErrors.email}</small>}
          </label>
          <label className="field">
            <span>รหัสผ่าน</span>
            <input
              id="password"
              name="password"
              type="password"
              value={form.password}
              onChange={update}
              autoComplete="current-password"
              required
              aria-invalid={Boolean(fieldErrors.password)}
              aria-describedby={fieldErrors.password ? 'password-error' : undefined}
            />
            {fieldErrors.password && <small id="password-error" className="field-error">{fieldErrors.password}</small>}
          </label>
          <button className="button" type="submit" disabled={submitting}>
            {submitting ? 'กำลังเข้าสู่ระบบ…' : 'เข้าสู่ระบบ'}
          </button>
        </form>
        <AuthLinks />
      </section>
    </main>
  )
}
