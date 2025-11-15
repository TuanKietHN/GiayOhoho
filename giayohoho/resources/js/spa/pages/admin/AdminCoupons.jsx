import React, { useEffect, useState } from 'react'
import api from '../../api'
import Modal from '../../ui/modal.jsx'

export default function AdminCoupons() {
  const [data, setData] = useState({ data: [] })
  const [form, setForm] = useState({ code: '', discount_type: 'PERCENTAGE', discount_value: 0, start_date: new Date().toISOString(), end_date: new Date(Date.now()+86400000).toISOString() })
  const [editOpen, setEditOpen] = useState(false)
  const [editItem, setEditItem] = useState(null)
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
        {(data.data || []).map(c => (
          <li key={c.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>{c.code} • {(c.discount_type === 'PERCENTAGE' ? 'Phần trăm' : c.discount_type === 'FIXED_AMOUNT' ? 'Cố định' : c.discount_type)} • {c.discount_value}</div>
            <div>
              <button className="btn-secondary" onClick={() => { setEditItem(c); setEditOpen(true) }}>Sửa</button>
            </div>
          </li>
        ))}
      </ul>

      <Modal open={editOpen} title="Sửa coupon" onClose={() => setEditOpen(false)}>
        {editItem && (
          <div>
            <input value={editItem.code || ''} onChange={e => setEditItem({ ...editItem, code: e.target.value })} placeholder="Code" />
            <select value={editItem.discount_type} onChange={e => setEditItem({ ...editItem, discount_type: e.target.value })}><option>PERCENTAGE</option><option>FIXED_AMOUNT</option></select>
            <input type="number" value={editItem.discount_value || 0} onChange={e => setEditItem({ ...editItem, discount_value: Number(e.target.value) })} placeholder="Value" />
            <input type="datetime-local" value={editItem.start_date?.slice(0,16) || ''} onChange={e => setEditItem({ ...editItem, start_date: e.target.value })} />
            <input type="datetime-local" value={editItem.end_date?.slice(0,16) || ''} onChange={e => setEditItem({ ...editItem, end_date: e.target.value })} />
            <button onClick={async () => { await api.put(`/admin/coupons/${editItem.id}`, { code: editItem.code, discount_type: editItem.discount_type, discount_value: editItem.discount_value, start_date: editItem.start_date, end_date: editItem.end_date }); setEditOpen(false); setEditItem(null); load(); }} className="btn-primary" style={{ marginTop: 8 }}>Lưu</button>
          </div>
        )}
      </Modal>
    </div>
  )
}

