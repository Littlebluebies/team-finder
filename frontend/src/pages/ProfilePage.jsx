import { useEffect, useState } from 'react'
import { fetchMe, updateMe } from '../api/me'
import { getSkills } from '../api/skills'
import { ApiError, getFieldErrors } from '../api/client'

const initialForm = {
  display_name: '', faculty: '', major: '', year: '1', line_id: '', bio: '',
}

export default function ProfilePage() {
  const [form, setForm] = useState(initialForm)
  const [email, setEmail] = useState('')
  const [skills, setSkills] = useState([])
  const [selectedSkills, setSelectedSkills] = useState([])
  const [fieldErrors, setFieldErrors] = useState({})
  const [message, setMessage] = useState('')
  const [success, setSuccess] = useState('')
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    let active = true
    Promise.all([fetchMe(), getSkills()])
      .then(([profile, skillItems]) => {
        if (!active) return
        setEmail(profile.email)
        setForm({
          display_name: profile.display_name,
          faculty: profile.faculty,
          major: profile.major,
          year: String(profile.year),
          line_id: profile.line_id,
          bio: profile.bio || '',
        })
        setSelectedSkills(profile.skills.map((skill) => skill.id))
        setSkills(skillItems)
      })
      .catch(() => { if (active) setMessage('โหลดโปรไฟล์ไม่สำเร็จ กรุณารีเฟรชหน้า') })
      .finally(() => { if (active) setLoading(false) })
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
    setSuccess('')
    if (selectedSkills.length === 0) {
      setFieldErrors({ skill_ids: 'เลือกอย่างน้อย 1 ทักษะ' })
      return
    }

    setSubmitting(true)
    try {
      const profile = await updateMe({ ...form, year: Number(form.year), skill_ids: selectedSkills })
      setForm({
        display_name: profile.display_name,
        faculty: profile.faculty,
        major: profile.major,
        year: String(profile.year),
        line_id: profile.line_id,
        bio: profile.bio || '',
      })
      setSelectedSkills(profile.skills.map((skill) => skill.id))
      setSuccess('บันทึกโปรไฟล์แล้ว')
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

  if (loading) {
    return <main className="shell page-shell"><p>กำลังโหลดโปรไฟล์…</p></main>
  }

  return (
    <main className="shell page-shell">
      <section className="card form-card">
        <h1>โปรไฟล์ของฉัน</h1>
        <p className="post-info">อีเมล: {email}</p>
        {message && <div className="alert" role="alert">{message}</div>}
        {success && <div className="alert alert-success" role="status">{success}</div>}
        <form onSubmit={submit}>
          <label className="field">
            <span>ชื่อที่แสดง</span>
            <input name="display_name" value={form.display_name} onChange={update} required maxLength={100} />
            {fieldErrors.display_name && <small className="field-error">{fieldErrors.display_name}</small>}
          </label>
          <label className="field">
            <span>คณะ</span>
            <input name="faculty" value={form.faculty} onChange={update} required maxLength={150} />
            {fieldErrors.faculty && <small className="field-error">{fieldErrors.faculty}</small>}
          </label>
          <label className="field">
            <span>สาขา</span>
            <input name="major" value={form.major} onChange={update} required maxLength={150} />
            {fieldErrors.major && <small className="field-error">{fieldErrors.major}</small>}
          </label>
          <label className="field">
            <span>ชั้นปี</span>
            <select name="year" value={form.year} onChange={update} required>
              {[1, 2, 3, 4, 5, 6, 7, 8].map((year) => <option key={year} value={year}>ปี {year}</option>)}
            </select>
            {fieldErrors.year && <small className="field-error">{fieldErrors.year}</small>}
          </label>
          <label className="field">
            <span>LINE ID</span>
            <input name="line_id" value={form.line_id} onChange={update} required maxLength={50} />
            {fieldErrors.line_id && <small className="field-error">{fieldErrors.line_id}</small>}
          </label>
          <label className="field">
            <span>แนะนำตัว</span>
            <textarea name="bio" rows={4} value={form.bio} onChange={update} maxLength={2000} />
            {fieldErrors.bio && <small className="field-error">{fieldErrors.bio}</small>}
          </label>
          <p className="privacy-note">LINE ID จะแสดงให้บุคคลที่เกี่ยวข้องเมื่อใบสมัครได้รับการตอบรับเท่านั้น</p>
          <fieldset className="skills-fieldset" disabled={submitting}>
            <legend>ทักษะของคุณ</legend>
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
            {fieldErrors.skill_ids && <small className="field-error" role="alert">{fieldErrors.skill_ids}</small>}
          </fieldset>
          <div className="form-actions">
            <button className="button" type="submit" disabled={submitting}>
              {submitting ? 'กำลังบันทึก…' : 'บันทึกโปรไฟล์'}
            </button>
          </div>
        </form>
      </section>
    </main>
  )
}
