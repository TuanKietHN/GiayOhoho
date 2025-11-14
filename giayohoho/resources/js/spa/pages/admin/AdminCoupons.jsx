import React, { useEffect, useState } from 'react'
import api from '../../api'

export default function AdminCoupons() {
  const [data, setData] = useState({ data: [] })
  const [form, setForm] = useState({ code: '', discount_type: 'PERCENTAGE', discount_value: 0, start_date: new Date().toISOString(), end_date: new Date(Date.now()+86400000).toISOString() })
  const load = async () => { const res = await api.get('/admin/coupons'); setData(res.data) }
  useEffect(() => { load() }, [])
  const create = async () => { await api.post('/admin/coupons', form); setForm({ ...form, code: '', discount_value: 0 }); load() }
  return (
    <div>
      <h3>Coupons</h3>
      <div>
        <input placeholder="Code" value={form.code} onChange={e => setForm({ ...form, code: e.target.value })} />
        <select value={form.discount_type} onChange={e => setForm({ ...form, discount_type: e.target.value })}><option>PERCENTAGE</option><option>FIXED_AMOUNT</option></select>
        <input type="number" placeholder="Value" value={form.discount_value} onChange={e => setForm({ ...form, discount_value: Number(e.target.value) })} />
        <button onClick={create}>Tạo</button>
      </div>
      <ul style={{ marginTop: 12 }}>
        {(data.data || []).map(c => (<li key={c.id}>{c.code} • {c.discount_type} • {c.discount_value}</li>))}
      </ul>
    </div>
  )
}

