import React, { useEffect, useState } from 'react'
import api from '../../api'
import Modal from '../../ui/modal.jsx'

export default function AdminProducts() {
  const [data, setData] = useState({ data: [] })
  const [form, setForm] = useState({ name: '', slug: '', brand: '', gender: 'unisex', base_price: 0 })
  const [editOpen, setEditOpen] = useState(false)
  const [editItem, setEditItem] = useState(null)
  const [bulkOpen, setBulkOpen] = useState(false)
  const [bulk, setBulk] = useState({ category_id: '', filters: { brand: '', gender: '', min_price: '', max_price: '' } })
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
  const openEdit = (p) => { setEditItem(p); setEditOpen(true) }
  const saveEdit = async () => {
    if (!editItem) return
    const { id, name, slug, brand, gender, base_price, description } = editItem
    await api.put(`/admin/products/${id}`, { name, slug, brand, gender, base_price, description })
    setEditOpen(false)
    setEditItem(null)
    load()
  }
  const remove = async (id) => { await api.delete(`/admin/products/${id}`); load() }
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
        <button onClick={() => setBulkOpen(true)} className="btn-secondary" style={{ marginLeft: 8 }}>Gán danh mục theo bộ lọc</button>
      </div>
      <ul style={{ marginTop: 12 }}>
        {(data.data || []).map(p => (
          <li key={p.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>{p.name} • {p.brand} • {p.gender}</div>
            <div>
              <button onClick={() => openEdit(p)} className="btn-secondary">Sửa</button>
              <button onClick={() => remove(p.id)} className="btn-outline" style={{ marginLeft: 8 }}>Xoá</button>
            </div>
          </li>
        ))}
      </ul>

      <Modal open={editOpen} title="Sửa sản phẩm" onClose={() => setEditOpen(false)}>
        {editItem && (
          <div>
            <input value={editItem.name || ''} onChange={e => setEditItem({ ...editItem, name: e.target.value })} placeholder="Tên" />
            <input value={editItem.slug || ''} onChange={e => setEditItem({ ...editItem, slug: e.target.value })} placeholder="Slug" />
            <input value={editItem.brand || ''} onChange={e => setEditItem({ ...editItem, brand: e.target.value })} placeholder="Brand" />
            <select value={editItem.gender || 'unisex'} onChange={e => setEditItem({ ...editItem, gender: e.target.value })}><option>male</option><option>female</option><option>unisex</option></select>
            <input type="number" value={editItem.base_price || 0} onChange={e => setEditItem({ ...editItem, base_price: Number(e.target.value) })} placeholder="Giá" />
            <textarea value={editItem.description || ''} onChange={e => setEditItem({ ...editItem, description: e.target.value })} placeholder="Mô tả" />
            <button onClick={saveEdit} className="btn-primary" style={{ marginTop: 8 }}>Lưu</button>
          </div>
        )}
      </Modal>

      <Modal open={bulkOpen} title="Gán danh mục theo bộ lọc" onClose={() => setBulkOpen(false)}>
        <div>
          <input placeholder="Category ID" value={bulk.category_id} onChange={e => setBulk({ ...bulk, category_id: e.target.value })} />
          <input placeholder="Brand" value={bulk.filters.brand} onChange={e => setBulk({ ...bulk, filters: { ...bulk.filters, brand: e.target.value } })} />
          <select value={bulk.filters.gender} onChange={e => setBulk({ ...bulk, filters: { ...bulk.filters, gender: e.target.value } })}>
            <option value="">Giới tính</option><option>male</option><option>female</option><option>unisex</option>
          </select>
          <input type="number" placeholder="Giá từ" value={bulk.filters.min_price} onChange={e => setBulk({ ...bulk, filters: { ...bulk.filters, min_price: e.target.value } })} />
          <input type="number" placeholder="Giá đến" value={bulk.filters.max_price} onChange={e => setBulk({ ...bulk, filters: { ...bulk.filters, max_price: e.target.value } })} />
          <button onClick={async () => { await api.post('/admin/products/bulk-assign-category', { category_id: Number(bulk.category_id), filters: { ...bulk.filters, brand: bulk.filters.brand ? [bulk.filters.brand] : undefined } }); setBulkOpen(false); load(); }} className="btn-primary" style={{ marginTop: 8 }}>Thực hiện</button>
        </div>
      </Modal>
    </div>
  )
}

