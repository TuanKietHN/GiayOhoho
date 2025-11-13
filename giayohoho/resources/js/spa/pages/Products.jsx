import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api'

export default function Products() {
  const [items, setItems] = useState([])
  const [q, setQ] = useState('')
  const [loading, setLoading] = useState(false)

  const load = async () => {
    setLoading(true)
    const res = await api.get('/products', { params: q ? { q } : {} })
    setItems(res.data.data || res.data)
    setLoading(false)
  }

  useEffect(() => { load() }, [])

  return (
    <div>
      <h2>Danh sách sản phẩm</h2>
      <div style={{ marginBottom: 12 }}>
        <input value={q} onChange={e => setQ(e.target.value)} placeholder="Tìm kiếm" />
        <button onClick={load} disabled={loading} style={{ marginLeft: 8 }}>Tìm</button>
      </div>
      {loading ? <p>Đang tải...</p> : (
        <ul>
          {items.map(p => (
            <li key={p.id} style={{ marginBottom: 8 }}>
              <Link to={`/products/${p.id}`}>{p.name}</Link>
              <span style={{ marginLeft: 8 }}>{p.brand}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

