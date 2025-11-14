import React, { useEffect, useState } from 'react'
import api from '../../api'

export default function AdminOrders() {
  const [data, setData] = useState({ data: [] })
  const [status, setStatus] = useState('')
  const load = async () => {
    const res = await api.get('/admin/orders', { params: status ? { status } : {} })
    setData(res.data)
  }
  useEffect(() => { load() }, [])
  const updateStatus = async (id, s) => {
    await api.post(`/admin/orders/${id}/status`, { status: s })
    load()
  }
  return (
    <div>
      <h3>Đơn hàng</h3>
      <select value={status} onChange={e => setStatus(e.target.value)}><option value="">Tất cả</option><option>pending</option><option>paid</option><option>shipping</option><option>done</option><option>cancel</option></select>
      <button onClick={load} style={{ marginLeft: 8 }}>Lọc</button>
      <ul style={{ marginTop: 12 }}>
        {(data.data || []).map(o => (
          <li key={o.id}>#{o.id} • {o.status} • {o.total}
            <select onChange={e => updateStatus(o.id, e.target.value)} defaultValue={o.status} style={{ marginLeft: 8 }}>
              <option>pending</option><option>paid</option><option>shipping</option><option>done</option><option>cancel</option>
            </select>
          </li>
        ))}
      </ul>
    </div>
  )
}

