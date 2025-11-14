import React, { useEffect, useState } from 'react'
import api from '../../api'

export default function AdminCategories() {
  const [items, setItems] = useState([])
  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const load = async () => {
    const res = await api.get('/admin/categories')
    setItems(res.data)
  }
  useEffect(() => { load() }, [])
  const create = async () => {
    await api.post('/admin/categories', { name, slug })
    setName(''); setSlug('')
    load()
  }
  const del = async (id) => { await api.delete(`/admin/categories/${id}`); load() }
  return (
    <div>
      <h3>Danh mục</h3>
      <input placeholder="Tên" value={name} onChange={e => setName(e.target.value)} />
      <input placeholder="Slug" value={slug} onChange={e => setSlug(e.target.value)} />
      <button onClick={create}>Tạo</button>
      <ul style={{ marginTop: 12 }}>
        {items.map(c => (<li key={c.id}>{c.name} • {c.slug} <button onClick={() => del(c.id)} style={{ marginLeft: 8 }}>Xoá</button></li>))}
      </ul>
    </div>
  )
}

