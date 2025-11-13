import React, { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import api from '../api'

export default function ProductDetail() {
  const { id } = useParams()
  const [p, setP] = useState(null)
  const [variantId, setVariantId] = useState('')
  const [qty, setQty] = useState(1)
  const [similar, setSimilar] = useState([])
  const [reviews, setReviews] = useState([])
  const [rating, setRating] = useState(5)
  const [comment, setComment] = useState('')
  const [me, setMe] = useState(null)

  useEffect(() => {
    api.get(`/products/${id}`).then(res => setP(res.data))
    api.get(`/products/${id}/similar`).then(res => setSimilar(res.data))
    api.get(`/products/${id}/reviews`).then(res => setReviews(res.data))
    api.get('/auth/me').then(res => setMe(res.data)).catch(() => {})
  }, [id])

  const addToCart = async () => {
    if (!variantId) return
    await api.post('/auth/cart/items', { product_variant_id: Number(variantId), quantity: Number(qty) })
    alert('Đã thêm vào giỏ')
  }

  const addToWishlist = async () => {
    await api.post('/auth/wishlist', { product_id: Number(id) })
    alert('Đã thêm vào yêu thích')
  }

  const submitReview = async () => {
    try {
      await api.post('/auth/reviews', { product_id: Number(id), rating: Number(rating), comment })
      const res = await api.get(`/products/${id}/reviews`)
      setReviews(res.data)
      setComment('')
    } catch {
      alert('Viết review thất bại')
    }
  }

  const deleteReview = async (rid) => {
    await api.delete(`/auth/reviews/${rid}`)
    const res = await api.get(`/products/${id}/reviews`)
    setReviews(res.data)
  }

  if (!p) return <p>Đang tải...</p>

  return (
    <div>
      <h2>{p.name}</h2>
      <p>{p.brand} • {p.gender}</p>
      <p>Giá: {p.base_price}</p>
      <div>
        <select value={variantId} onChange={e => setVariantId(e.target.value)}>
          <option value="">Chọn biến thể</option>
          {(p.variants || []).map(v => (
            <option key={v.id} value={v.id}>{v.size} • {v.color} • stock {v.stock}</option>
          ))}
        </select>
        <input type="number" min="1" value={qty} onChange={e => setQty(e.target.value)} style={{ marginLeft: 8 }} />
        <button onClick={addToCart} style={{ marginLeft: 8 }}>Thêm vào giỏ</button>
        <button onClick={addToWishlist} style={{ marginLeft: 8 }}>Yêu thích</button>
      </div>
      <h3 style={{ marginTop: 16 }}>Có thể bạn sẽ thích</h3>
      <ul>
        {similar.map(s => (<li key={s.id}>{s.name}</li>))}
      </ul>
      <h3 style={{ marginTop: 16 }}>Đánh giá</h3>
      <ul>
        {reviews.map(r => (
          <li key={r.id}>
            {r.rating}★ {r.comment} • {r.user?.email || ''}
            {me && r.user && me.id === r.user.id && (
              <button onClick={() => deleteReview(r.id)} style={{ marginLeft: 8 }}>Xoá</button>
            )}
          </li>
        ))}
      </ul>
      <div style={{ marginTop: 8 }}>
        <select value={rating} onChange={e => setRating(e.target.value)}>
          {[1,2,3,4,5].map(n => <option key={n} value={n}>{n}</option>)}
        </select>
        <input placeholder="Nhận xét" value={comment} onChange={e => setComment(e.target.value)} style={{ marginLeft: 8 }} />
        <button onClick={submitReview} style={{ marginLeft: 8 }}>Gửi đánh giá</button>
      </div>
    </div>
  )
}
