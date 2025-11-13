import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../api'

export default function Register() {
  const [first_name, setFirst] = useState('')
  const [username, setUsername] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [password_confirmation, setConfirm] = useState('')
  const [error, setError] = useState('')
  const nav = useNavigate()

  const submit = async () => {
    setError('')
    try {
      const res = await api.post('/auth/register', { first_name, username, email, password, password_confirmation })
      localStorage.setItem('token', res.data.token)
      const me = await api.get('/auth/me')
      localStorage.setItem('user_email', me.data.email || '')
      nav('/')
    } catch (e) {
      setError('Đăng ký thất bại')
    }
  }

  return (
    <div>
      <h2>Đăng ký</h2>
      {error && <p style={{ color: 'red' }}>{error}</p>}
      <input placeholder="Họ" value={first_name} onChange={e => setFirst(e.target.value)} />
      <input placeholder="Username" value={username} onChange={e => setUsername(e.target.value)} style={{ display: 'block', marginTop: 8 }} />
      <input placeholder="Email" value={email} onChange={e => setEmail(e.target.value)} style={{ display: 'block', marginTop: 8 }} />
      <input type="password" placeholder="Mật khẩu" value={password} onChange={e => setPassword(e.target.value)} style={{ display: 'block', marginTop: 8 }} />
      <input type="password" placeholder="Xác nhận" value={password_confirmation} onChange={e => setConfirm(e.target.value)} style={{ display: 'block', marginTop: 8 }} />
      <button onClick={submit} style={{ marginTop: 8 }}>Đăng ký</button>
    </div>
  )
}
