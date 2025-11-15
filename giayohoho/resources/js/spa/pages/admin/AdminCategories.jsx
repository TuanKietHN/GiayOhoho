import React, { useEffect, useState } from 'react'
import api from '../../api'
import Modal from '../../ui/modal.jsx'

export default function AdminCategories() {
  const [items, setItems] = useState([])
  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const [editOpen, setEditOpen] = useState(false)
  const [editItem, setEditItem] = useState(null)
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
      <p style={{ color: 'var(--neutral-medium)', marginTop: 8 }}>Slug là định danh URL, ví dụ: running-shoes</p>
      <ul style={{ marginTop: 12 }}>
        {items.map(c => (
          <li key={c.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>{c.name} • {c.slug}</div>
            <div>
              <button onClick={() => { setEditItem(c); setEditOpen(true) }} className="btn-secondary">Sửa</button>
              <button onClick={() => del(c.id)} style={{ marginLeft: 8 }} className="btn-outline">Xoá</button>
            </div>
          </li>
        ))}
      </ul>

      <Modal open={editOpen} title="Sửa danh mục" onClose={() => setEditOpen(false)}>
        {editItem && (
          <div>
            <input value={editItem.name || ''} onChange={e => setEditItem({ ...editItem, name: e.target.value })} placeholder="Tên" />
            <input value={editItem.slug || ''} onChange={e => setEditItem({ ...editItem, slug: e.target.value })} placeholder="Slug" />
            <button onClick={async () => { await api.put(`/admin/categories/${editItem.id}`, { name: editItem.name, slug: editItem.slug }); setEditOpen(false); setEditItem(null); load(); }} className="btn-primary" style={{ marginTop: 8 }}>Lưu</button>
          </div>
        )}
      </Modal>
    </div>
  )
}

