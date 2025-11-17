import React, { useEffect, useState } from 'react'
import api from '../../api'
import { useToast } from '../../ui/toast.jsx'
export default function AdminOrders() {
  const [data, setData] = useState({ data: [] })
  const [status, setStatus] = useState('')
  const [pendingStatus, setPendingStatus] = useState({})
  const toast = useToast()
  const load = async () => {
    const res = await api.get('/admin/orders', { params: status ? { status } : {} })
    setData(res.data)
  }
  useEffect(() => { load() }, [])
  const saveStatus = async (id) => {
    const s = pendingStatus[id]
    if (!s) return
    try {
      await api.post(`/admin/orders/${id}/status`, { status: s })
      toast?.show('Đã lưu trạng thái đơn hàng', 'success')
      load()
    } catch (e) {
      toast?.show('Không thể cập nhật đơn hàng đã hoàn tất', 'error')
    }
  }
  return (
    <div>
      <h3>Đơn hàng</h3>
      <select value={status} onChange={e => setStatus(e.target.value)}><option value="">Tất cả</option><option>pending</option><option>paid</option><option>shipping</option><option>done</option><option>cancel</option></select>
      <button onClick={load} style={{ marginLeft: 8 }}>Lọc</button>
      <ul style={{ marginTop: 12 }}>
        {(data.data || []).map(o => (
          <li key={o.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>#{o.id} • {(o.status === 'pending' ? 'đang chờ giao hàng' : o.status)} • {new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(o.total)}</div>
            <div>
              <select value={pendingStatus[o.id] ?? o.status} onChange={e => setPendingStatus({ ...pendingStatus, [o.id]: e.target.value })} disabled={o.status === 'done'}>
                <option>pending</option><option>paid</option><option>shipping</option><option>done</option><option>cancel</option>
              </select>
              <button onClick={() => saveStatus(o.id)} className="btn-secondary" style={{ marginLeft: 8 }} disabled={o.status === 'done'}>Lưu</button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}

