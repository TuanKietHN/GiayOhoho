import React, { useEffect, useState } from 'react'
import api from '../../api'
import Modal from '../../ui/modal.jsx'

export default function AdminVariants() {
  const [low, setLow] = useState([])
  const [threshold, setThreshold] = useState(5)
  const [editOpen, setEditOpen] = useState(false)
  const [editItem, setEditItem] = useState(null)
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
        {low.map(v => (<li key={v.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>{v.product?.name} • {v.size} • {v.color} • stock {v.stock}</div>
          <div>
            <button onClick={() => adjust(v.id, 10)} style={{ marginLeft: 8 }}>+10</button>
            <button onClick={() => adjust(v.id, -10)} style={{ marginLeft: 4 }}>-10</button>
            <button onClick={() => { setEditItem(v); setEditOpen(true) }} className="btn-secondary" style={{ marginLeft: 8 }}>Sửa</button>
          </div>
        </li>))}
      </ul>

      <Modal open={editOpen} title="Sửa biến thể" onClose={() => setEditOpen(false)}>
        {editItem && (
          <div>
            <input value={editItem.size || ''} onChange={e => setEditItem({ ...editItem, size: e.target.value })} placeholder="Size" />
            <input value={editItem.color || ''} onChange={e => setEditItem({ ...editItem, color: e.target.value })} placeholder="Color" />
            <input value={editItem.sku || ''} onChange={e => setEditItem({ ...editItem, sku: e.target.value })} placeholder="SKU" />
            <input type="number" value={editItem.stock || 0} onChange={e => setEditItem({ ...editItem, stock: Number(e.target.value) })} placeholder="Stock" />
            <input type="number" value={editItem.extra_price || 0} onChange={e => setEditItem({ ...editItem, extra_price: Number(e.target.value) })} placeholder="Extra Price" />
            <button onClick={async () => { await api.put(`/admin/variants/${editItem.id}`, { size: editItem.size, color: editItem.color, sku: editItem.sku, stock: editItem.stock, extra_price: editItem.extra_price }); setEditOpen(false); setEditItem(null); load(); }} className="btn-primary" style={{ marginTop: 8 }}>Lưu</button>
          </div>
        )}
      </Modal>
    </div>
  )
}

