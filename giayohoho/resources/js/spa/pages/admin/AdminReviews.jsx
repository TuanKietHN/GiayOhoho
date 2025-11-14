import React, { useEffect, useState } from 'react'
import api from '../../api'

export default function AdminReviews() {
  const [data, setData] = useState({ data: [] })
  const load = async () => { const res = await api.get('/admin/reviews'); setData(res.data) }
  useEffect(() => { load() }, [])
  const remove = async (id) => { await api.delete(`/admin/reviews/${id}`); load() }
  return (
    <div>
      <h3>Reviews</h3>
      <ul>
        {(data.data || []).map(r => (<li key={r.id}>{r.product?.name} • {r.user?.email} • {r.rating}★ {r.comment} <button onClick={() => remove(r.id)} style={{ marginLeft: 8 }}>Xoá</button></li>))}
      </ul>
    </div>
  )
}

