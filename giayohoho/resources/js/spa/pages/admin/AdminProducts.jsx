import React, { useEffect, useState } from 'react'
import api from '../../api'

export default function AdminProducts() {
  const [data, setData] = useState({ data: [] })
  const [form, setForm] = useState({ name: '', slug: '', brand: '', gender: 'unisex', base_price: 0 })
  const load = async () => {
    const res = await api.get('/admin/products')
    setData(res.data)
  }
  useEffect(() => { load() }, [])
  const create = async () => {
    await api.post('/admin/products', form)
    setForm({ name: '', slug: '', brand: '', gender: 'unisex', base_price: 0 })
    load()
  }
  return (
    <div>
      <h3>Sản phẩm</h3>
      <div>
        <input placeholder="Tên" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} />
        <input placeholder="Slug" value={form.slug} onChange={e => setForm({ ...form, slug: e.target.value })} />
        <input placeholder="Brand" value={form.brand} onChange={e => setForm({ ...form, brand: e.target.value })} />
        <select value={form.gender} onChange={e => setForm({ ...form, gender: e.target.value })}><option>male</option><option>female</option><option>unisex</option></select>
        <input type="number" placeholder="Giá" value={form.base_price} onChange={e => setForm({ ...form, base_price: Number(e.target.value) })} />
        <button onClick={create}>Tạo</button>
      </div>
      <ul style={{ marginTop: 12 }}>
        {(data.data || []).map(p => (<li key={p.id}>{p.name} • {p.brand} • {p.gender}</li>))}
      </ul>
    </div>
  )
}

