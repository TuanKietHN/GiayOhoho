import React, { useEffect, useState } from 'react'
import api from '../../api'

export default function AdminVariants() {
  const [low, setLow] = useState([])
  const [threshold, setThreshold] = useState(5)
  const load = async () => {
    const res = await api.get('/admin/variants/low-stock', { params: { threshold } })
    setLow(res.data)
  }
  useEffect(() => { load() }, [])
  const adjust = async (id, delta) => { await api.post(`/admin/variants/${id}/adjust-stock`, { delta }); load() }
  return (
    <div>
      <h3>Cảnh báo sắp hết hàng</h3>
      <input type="number" value={threshold} onChange={e => setThreshold(Number(e.target.value))} />
      <button onClick={load} style={{ marginLeft: 8 }}>Lọc</button>
      <ul style={{ marginTop: 12 }}>
        {low.map(v => (<li key={v.id}>{v.product?.name} • {v.size} • {v.color} • stock {v.stock}
          <button onClick={() => adjust(v.id, 10)} style={{ marginLeft: 8 }}>+10</button>
          <button onClick={() => adjust(v.id, -10)} style={{ marginLeft: 4 }}>-10</button>
        </li>))}
      </ul>
    </div>
  )
}

