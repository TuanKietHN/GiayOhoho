import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../api'

export default function Login() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const nav = useNavigate()

  const submit = async () => {
    setError('')
    try {
      const res = await api.post('/auth/login', { email, password })
      localStorage.setItem('token', res.data.token)
      const me = await api.get('/auth/me')
      localStorage.setItem('user_email', me.data.email || '')
      nav('/')
    } catch (e) {
      setError('Đăng nhập thất bại')
    }
  }

  return (
    <div>
      <h2>Đăng nhập</h2>
      {error && <p style={{ color: 'red' }}>{error}</p>}
      <input placeholder="Email" value={email} onChange={e => setEmail(e.target.value)} />
      <input type="password" placeholder="Mật khẩu" value={password} onChange={e => setPassword(e.target.value)} style={{ display: 'block', marginTop: 8 }} />
      <button onClick={submit} style={{ marginTop: 8 }}>Đăng nhập</button>
    </div>
  )
}
