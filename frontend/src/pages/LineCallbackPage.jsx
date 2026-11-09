import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { linkLine } from '../api/notifications'
import { ApiError } from '../api/client'

export default function LineCallbackPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const started = useRef(false)

  const errorParam = params.get('error')
  const code = params.get('code')
  const state = params.get('state')
  const invalid = Boolean(errorParam || !code || !state)

  const [status, setStatus] = useState(invalid ? 'error' : 'loading')
  const [message, setMessage] = useState(() => {
    if (errorParam === 'access_denied') return 'คุณยกเลิกการเชื่อมต่อ LINE'
    if (invalid) return 'ไม่พบข้อมูลยืนยันจาก LINE'
    return 'กำลังเชื่อมต่อ LINE…'
  })

  useEffect(() => {
    if (started.current || invalid) return
    started.current = true

    linkLine(code, state)
      .then(() => {
        setStatus('success')
        setMessage('เชื่อมต่อ LINE สำเร็จ กำลังกลับไปที่โปรไฟล์…')
        setTimeout(() => navigate('/me', { replace: true }), 1500)
      })
      .catch((err) => {
        setStatus('error')
        setMessage(err instanceof ApiError
          ? err.message
          : 'เชื่อมต่อ LINE ไม่สำเร็จ กรุณาลองใหม่')
      })
  }, [invalid, code, state, navigate])

  return (
    <main className="shell page-shell">
      <h1>เชื่อมต่อ LINE</h1>
      <div className={status === 'error' ? 'alert' : 'alert alert-success'} role="status">
        {message}
      </div>
      {status !== 'loading' && (
        <p><Link to="/me">กลับไปที่โปรไฟล์</Link></p>
      )}
    </main>
  )
}
