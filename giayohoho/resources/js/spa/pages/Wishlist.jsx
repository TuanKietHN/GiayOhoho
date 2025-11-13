import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api'

export default function Wishlist() {
  const [items, setItems] = useState([])
  const load = async () => {
    const res = await api.get('/auth/wishlist')
    setItems(res.data)
  }
  useEffect(() => { load() }, [])
  const removeItem = async (id) => {
    await api.delete(`/auth/wishlist/${id}`)
    load()
  }
  return (
    <div>
      <h2>Yêu thích</h2>
      <ul>
        {items.map(w => (
          <li key={w.id}>
            <Link to={`/products/${w.product.id}`}>{w.product.name}</Link>
            <button onClick={() => removeItem(w.id)} style={{ marginLeft: 8 }}>Xoá</button>
          </li>
        ))}
      </ul>
    </div>
  )
}

