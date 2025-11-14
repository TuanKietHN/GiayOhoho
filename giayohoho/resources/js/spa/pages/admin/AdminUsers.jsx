import React, { useEffect, useState } from 'react'
import api from '../../api'

export default function AdminUsers() {
  const [data, setData] = useState({ data: [] })
  const [q, setQ] = useState('')
  const [roles, setRoles] = useState('customer')
  const load = async () => {
    const res = await api.get('/admin/users', { params: q ? { q } : {} })
    setData(res.data)
  }
  useEffect(() => { load() }, [])
  const setUserRoles = async (id) => {
    await api.post(`/admin/users/${id}/roles`, { roles: roles.split(',').map(s => s.trim()).filter(Boolean) })
    load()
  }
  return (
    <div>
      <h3>Người dùng</h3>
      <input value={q} onChange={e => setQ(e.target.value)} placeholder="Tìm email/username/phone" />
      <button onClick={load} style={{ marginLeft: 8 }}>Tìm</button>
      <table style={{ width: '100%', marginTop: 12 }}>
        <thead><tr><th>Email</th><th>Username</th><th>Roles</th><th>Gán roles</th></tr></thead>
        <tbody>
          {(data.data || []).map(u => (
            <tr key={u.id}><td>{u.email}</td><td>{u.username}</td><td>{(u.roles || []).map(r => r.name).join(', ')}</td><td><input value={roles} onChange={e => setRoles(e.target.value)} /><button onClick={() => setUserRoles(u.id)}>Gán</button></td></tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

