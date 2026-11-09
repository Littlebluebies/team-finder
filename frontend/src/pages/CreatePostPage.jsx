import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { createPost } from '../api/posts'
import { CATEGORIES } from '../api/categories'
import { ApiError, getFieldErrors } from '../api/client'
import { getSkills } from '../api/skills'

const initialForm = {
  title: '', description: '', category: 'course', slots: '1', deadline: '',
}

export default function CreatePostPage() {
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
      const post = await createPost({
        ...form,
        slots: Number(form.slots),
        skill_ids: selectedSkills,
      })
      navigate(`/posts/${post.id}`)
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

  return (
    <main className="shell page-shell">
      <section className="card form-card">
        <h1>สร้างโพสต์หาทีม</h1>
        <p>บอกให้เพื่อน ๆ รู้ว่าคุณกำลังหาสมาชิกทีมสำหรับโปรเจกต์อะไร</p>
        {message && <div className="alert" role="alert">{message}</div>}
        <form onSubmit={submit}>
          <label className="field">
            <span>ชื่อโพสต์</span>
            <input
              id="title"
              name="title"
              type="text"
              value={form.title}
              onChange={update}
              maxLength={200}
              required
              aria-invalid={Boolean(fieldErrors.title)}
              aria-describedby={fieldErrors.title ? 'title-error' : undefined}
            />
            {fieldErrors.title && <small id="title-error" className="field-error">{fieldErrors.title}</small>}
          </label>
          <label className="field">
            <span>รายละเอียด</span>
            <textarea
              id="description"
              name="description"
              rows={4}
              value={form.description}
              onChange={update}
              required
              aria-invalid={Boolean(fieldErrors.description)}
              aria-describedby={fieldErrors.description ? 'description-error' : undefined}
            />
            {fieldErrors.description && <small id="description-error" className="field-error">{fieldErrors.description}</small>}
          </label>
          <div className="field-row">
            <label className="field">
              <span>ประเภท</span>
              <select
                id="category"
                name="category"
                value={form.category}
                onChange={update}
                aria-invalid={Boolean(fieldErrors.category)}
                aria-describedby={fieldErrors.category ? 'category-error' : undefined}
              >
                {CATEGORIES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </select>
              {fieldErrors.category && <small id="category-error" className="field-error">{fieldErrors.category}</small>}
            </label>
            <label className="field">
              <span>จำนวนที่รับ (คน)</span>
              <input
                id="slots"
                name="slots"
                type="number"
                min={1}
                max={99}
                value={form.slots}
                onChange={update}
                required
                aria-invalid={Boolean(fieldErrors.slots)}
                aria-describedby={fieldErrors.slots ? 'slots-error' : undefined}
              />
              {fieldErrors.slots && <small id="slots-error" className="field-error">{fieldErrors.slots}</small>}
            </label>
            <label className="field">
              <span>ปิดรับวันที่</span>
              <input
                id="deadline"
                name="deadline"
                type="date"
                value={form.deadline}
                onChange={update}
                required
                aria-invalid={Boolean(fieldErrors.deadline)}
                aria-describedby={fieldErrors.deadline ? 'deadline-error' : undefined}
              />
              {fieldErrors.deadline && <small id="deadline-error" className="field-error">{fieldErrors.deadline}</small>}
            </label>
          </div>
          <fieldset
            className="skills-fieldset"
            disabled={loadingSkills || submitting}
            aria-invalid={Boolean(fieldErrors.skill_ids)}
            aria-describedby={fieldErrors.skill_ids ? 'skill-ids-error' : undefined}
          >
            <legend>ทักษะที่ต้องการ</legend>
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
          <div className="form-actions">
            <button className="button" type="submit" disabled={submitting || loadingSkills}>
              {submitting ? 'กำลังโพสต์…' : 'โพสต์หาทีม'}
            </button>
            <Link className="button secondary" to="/">ยกเลิก</Link>
          </div>
        </form>
      </section>
    </main>
  )
}
