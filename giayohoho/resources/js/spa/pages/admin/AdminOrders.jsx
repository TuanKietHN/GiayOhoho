import React, { useEffect, useState } from 'react'
import api from '../../api'

export default function AdminOrders() {
  const [data, setData] = useState({ data: [] })
  const [status, setStatus] = useState('')
  const [pendingStatus, setPendingStatus] = useState({})
  const load = async () => {
    const res = await api.get('/admin/orders', { params: status ? { status } : {} })
    setData(res.data)
  }
  useEffect(() => { load() }, [])
  const saveStatus = async (id) => {
    const s = pendingStatus[id]
    if (!s) return
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
          <li key={o.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>#{o.id} • {o.status} • {o.total}</div>
            <div>
              <select value={pendingStatus[o.id] ?? o.status} onChange={e => setPendingStatus({ ...pendingStatus, [o.id]: e.target.value })}>
                <option>pending</option><option>paid</option><option>shipping</option><option>done</option><option>cancel</option>
              </select>
              <button onClick={() => saveStatus(o.id)} className="btn-secondary" style={{ marginLeft: 8 }}>Lưu</button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}

