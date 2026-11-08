import { Link } from 'react-router-dom'

export default function AuthLinks({ register }) {
  return (
    <p className="auth-link">
      {register ? 'มีบัญชีอยู่แล้ว?' : 'ยังไม่มีบัญชี?'}{' '}
      <Link to={register ? '/login' : '/register'}>{register ? 'เข้าสู่ระบบ' : 'สมัครสมาชิก'}</Link>
    </p>
  )
}
