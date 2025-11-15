import React, { useEffect, useState } from 'react'
import api from '../../api'

export default function AdminUsers() {
  const [data, setData] = useState({ data: [] })
  const [q, setQ] = useState('')
  const [rolesInputByUser, setRolesInputByUser] = useState({})
  const load = async () => {
    const res = await api.get('/admin/users', { params: q ? { q } : {} })
    setData(res.data)
  }
  useEffect(() => { load() }, [])
  const setUserRoles = async (id) => {
    const value = rolesInputByUser[id] || ''
    await api.post(`/admin/users/${id}/roles`, { roles: value.split(',').map(s => s.trim()).filter(Boolean) })
    load()
  }
  return (
    <div>
      <h3>Người dùng</h3>
      <input value={q} onChange={e => setQ(e.target.value)} placeholder="Tìm email/username/phone" onKeyDown={e => { if (e.key === 'Enter') load() }} />
      <button onClick={load} style={{ marginLeft: 8 }}>Tìm</button>
      <table style={{ width: '100%', marginTop: 12 }}>
        <thead><tr><th>Email</th><th>Username</th><th>Roles</th><th>Gán roles</th></tr></thead>
        <tbody>
          {(data.data || []).map(u => (
            <tr key={u.id}>
              <td>{u.email}</td>
              <td>{u.username}</td>
              <td>{(u.roles || []).map(r => r.name).join(', ')}</td>
              <td>
                <input value={rolesInputByUser[u.id] || ''} onChange={e => setRolesInputByUser({ ...rolesInputByUser, [u.id]: e.target.value })} placeholder="vd: admin,customer" />
                <button onClick={() => setUserRoles(u.id)} style={{ marginLeft: 8 }}>Gán</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

