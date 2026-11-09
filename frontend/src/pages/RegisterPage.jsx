import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { register } from '../api/auth'
import { ApiError, getFieldErrors } from '../api/client'
import { getSkills } from '../api/skills'
import AuthLinks from '../components/AuthLinks'

const initialForm = {
  email: '', password: '', display_name: '', faculty: '', major: '', year: '1', line_id: '',
}

export default function RegisterPage({ onAuth }) {
  const [form, setForm] = useState(initialForm)
  const [skills, setSkills] = useState([])
  const [selectedSkills, setSelectedSkills] = useState([])
  const [fieldErrors, setFieldErrors] = useState({})
  const [message, setMessage] = useState('')
  const [loadingSkills, setLoadingSkills] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const navigate = useNavigate()

  useEffect(() => {
    let active = true
    getSkills()
      .then((items) => { if (active) setSkills(items) })
      .catch(() => { if (active) setMessage('โหลดรายการทักษะไม่สำเร็จ กรุณารีเฟรชหน้า') })
      .finally(() => { if (active) setLoadingSkills(false) })
    return () => { active = false }
  }, [])

  function update(event) {
    setForm((current) => ({ ...current, [event.target.name]: event.target.value }))
  }

  function toggleSkill(id) {
    setSelectedSkills((current) => current.includes(id)
      ? current.filter((item) => item !== id)
      : [...current, id])
  }

  async function submit(event) {
    event.preventDefault()
    setFieldErrors({})
    setMessage('')
    if (selectedSkills.length === 0) {
      setFieldErrors({ skill_ids: 'เลือกอย่างน้อย 1 ทักษะ' })
      return
    }

    setSubmitting(true)
    try {
      const result = await register({ ...form, year: Number(form.year), skill_ids: selectedSkills })
      onAuth(result)
      navigate('/')
    } catch (error) {
      if (error instanceof ApiError) {
        setFieldErrors(getFieldErrors(error))
        setMessage(error.message)
      } else {
        setMessage('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาตรวจว่า backend กำลังรันอยู่')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const fields = [
    ['display_name', 'ชื่อที่แสดง', 'text', 'name'],
    ['email', 'อีเมล', 'email', 'email'],
    ['password', 'รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)', 'password', 'new-password'],
    ['faculty', 'คณะ', 'text', 'organization'],
    ['major', 'สาขา', 'text', 'organization-title'],
    ['line_id', 'LINE ID', 'text', 'off'],
  ]

  return (
    <main className="shell">
      <section className="card auth-card">
        <div className="doc-chrome">
          <span className="doc-mark">TEAM FINDER</span>
        </div>
        <h1>สมัครสมาชิก</h1>
        <p>สร้างบัญชีเพื่อเริ่มหาทีมและสมาชิกร่วมโปรเจกต์</p>
        {message && <div className="alert" role="alert">{message}</div>}
        <form onSubmit={submit}>
          {fields.map(([name, label, type, autoComplete]) => (
            <label className="field" key={name}>
              <span>{label}</span>
              <input
                id={name}
                name={name}
                type={type}
                value={form[name]}
                onChange={update}
                autoComplete={autoComplete}
                required
                minLength={name === 'password' ? 8 : undefined}
                maxLength={name === 'password' ? 72 : undefined}
                aria-invalid={Boolean(fieldErrors[name])}
                aria-describedby={fieldErrors[name] ? `${name}-error` : undefined}
              />
              {fieldErrors[name] && <small id={`${name}-error`} className="field-error">{fieldErrors[name]}</small>}
            </label>
          ))}
          <label className="field">
            <span>ชั้นปี</span>
            <select
              id="year"
              name="year"
              value={form.year}
              onChange={update}
              required
              aria-invalid={Boolean(fieldErrors.year)}
              aria-describedby={fieldErrors.year ? 'year-error' : undefined}
            >
              {[1, 2, 3, 4, 5, 6, 7, 8].map((year) => <option key={year} value={year}>ปี {year}</option>)}
            </select>
            {fieldErrors.year && <small id="year-error" className="field-error">{fieldErrors.year}</small>}
          </label>
          <p className="privacy-note">LINE ID จะแสดงให้บุคคลที่เกี่ยวข้องเมื่อใบสมัครได้รับการตอบรับเท่านั้น</p>
          <fieldset
            className="skills-fieldset"
            disabled={loadingSkills || submitting}
            aria-invalid={Boolean(fieldErrors.skill_ids)}
            aria-describedby={fieldErrors.skill_ids ? 'skill-ids-error' : undefined}
          >
            <legend>ทักษะของคุณ</legend>
            {loadingSkills ? <p>กำลังโหลดทักษะ…</p> : (
              <div className="skill-grid">
                {skills.map((skill) => (
                  <label className="skill-option" key={skill.id}>
                    <input
                      type="checkbox"
                      checked={selectedSkills.includes(skill.id)}
                      onChange={() => toggleSkill(skill.id)}
                    />
                    <span>{skill.name}</span>
                  </label>
                ))}
              </div>
            )}
            {fieldErrors.skill_ids && <small id="skill-ids-error" className="field-error" role="alert">{fieldErrors.skill_ids}</small>}
          </fieldset>
          <button className="button" type="submit" disabled={submitting || loadingSkills}>
            {submitting ? 'กำลังสมัคร…' : 'สมัครสมาชิก'}
          </button>
        </form>
        <AuthLinks register />
      </section>
    </main>
  )
}
