"use client"

import { useEffect, useState } from "react"
import { useParams, useNavigate } from "react-router-dom"
import api from "../api"
import { useToast } from "../ui/toast.jsx"

export default function ProductDetail() {
  const { id } = useParams()
  const nav = useNavigate()
  const [p, setP] = useState(null)
  const [variantId, setVariantId] = useState("")
  const [qty, setQty] = useState(1)
  const [similar, setSimilar] = useState([])
  const [reviews, setReviews] = useState([])
  const [rating, setRating] = useState(5)
  const [comment, setComment] = useState("")
  const [me, setMe] = useState(null)
  const toast = useToast()

  useEffect(() => {
    api.get(`/products/${id}`).then((res) => setP(res.data))
    api.get(`/products/${id}/similar`).then((res) => setSimilar(res.data))
    api.get(`/products/${id}/reviews`).then((res) => setReviews(res.data))
    api
      .get("/auth/me")
      .then((res) => setMe(res.data))
      .catch(() => {})
  }, [id])

  const addToCart = async () => {
    if (!variantId) {
      toast?.show("Vui lòng chọn biến thể", "error")
      return
    }
    try {
      await api.post("/auth/cart/items", { product_variant_id: Number(variantId), quantity: Number(qty) })
      toast?.show("Đã thêm vào giỏ", "success")
    } catch {
      toast?.show("Vui lòng đăng nhập trước", "error")
    }
  }

  const addToWishlist = async () => {
    try {
      await api.post("/auth/wishlist", { product_id: Number(id) })
      toast?.show("Đã thêm vào yêu thích", "success")
    } catch {
      toast?.show("Vui lòng đăng nhập trước", "error")
    }
  }

  const submitReview = async () => {
    try {
      await api.post("/auth/reviews", { product_id: Number(id), rating: Number(rating), comment })
      const res = await api.get(`/products/${id}/reviews`)
      setReviews(res.data)
      setComment("")
      setRating(5)
      toast?.show("Cảm ơn bạn đã đánh giá", "success")
    } catch (e) {
      toast?.show("Vui lòng đăng nhập trước", "error")
    }
  }

  const deleteReview = async (rid) => {
    try {
      await api.delete(`/auth/reviews/${rid}`)
      const res = await api.get(`/products/${id}/reviews`)
      setReviews(res.data)
      toast?.show("Đã xoá review", "success")
    } catch {
      toast?.show("Xoá thất bại", "error")
    }
  }

  if (!p)
    return (
      <div style={{ textAlign: "center", padding: "var(--spacing-2xl)" }}>
        <div className="spinner" style={{ margin: "0 auto" }} />
      </div>
    )

  return (
    <div>
      {/* Product Header */}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(350px, 1fr))",
          gap: "var(--spacing-2xl)",
          marginBottom: "var(--spacing-2xl)",
        }}
      >
        {/* Image */}
        <div
          style={{
            backgroundColor: "var(--neutral-gray)",
            borderRadius: "var(--radius-lg)",
            height: "400px",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            fontSize: "5rem",
          }}
        >
          👟
        </div>

        {/* Details */}
        <div>
          <h1 style={{ marginBottom: "var(--spacing-sm)" }}>{p.name}</h1>
          <p style={{ color: "var(--neutral-medium)", marginBottom: "var(--spacing-md)" }}>
            {p.brand} • {p.gender}
          </p>

          <div
            style={{
              fontSize: "1.75rem",
              fontWeight: 700,
              color: "var(--primary-accent)",
              marginBottom: "var(--spacing-lg)",
            }}
          >
            {new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(p.base_price)}
          </div>

          {/* Description */}
          <p style={{ marginBottom: "var(--spacing-lg)", lineHeight: 1.8 }}>
            {p.description || "Sản phẩm chất lượng cao, thiết kế hiện đại"}
          </p>

          {/* Variants Selection */}
          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label style={{ display: "block", marginBottom: "var(--spacing-md)" }}>
              <strong>Chọn biến thể</strong>
            </label>
            <select
              value={variantId}
              onChange={(e) => setVariantId(e.target.value)}
              style={{ marginBottom: "var(--spacing-md)" }}
            >
              <option value="">-- Chọn kích thước & màu --</option>
              {(p.variants || []).map((v) => (
                <option key={v.id} value={v.id}>
                  Size {v.size} • {v.color} • ({v.stock > 0 ? `${v.stock} còn` : "Hết hàng"})
                </option>
              ))}
            </select>
          </div>

          {/* Quantity */}
          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label style={{ display: "block", marginBottom: "var(--spacing-md)" }}>
              <strong>Số lượng</strong>
            </label>
            <input
              type="number"
              min="1"
              max="99"
              value={qty}
              onChange={(e) => setQty(Math.max(1, Number.parseInt(e.target.value) || 1))}
              style={{ width: "100px" }}
            />
          </div>

          {/* Action Buttons */}
          <div style={{ display: "flex", gap: "var(--spacing-md)" }}>
            <button onClick={addToCart} className="btn-primary" style={{ flex: 1 }}>
              Thêm vào giỏ
            </button>
            <button onClick={addToWishlist} className="btn-outline">
              ❤️ Yêu thích
            </button>
          </div>
        </div>
      </div>

      {/* Divider */}
      <hr style={{ margin: "var(--spacing-2xl) 0", borderColor: "var(--neutral-gray)" }} />

      {/* Similar Products */}
      {similar.length > 0 && (
        <div style={{ marginBottom: "var(--spacing-2xl)" }}>
          <h2 style={{ marginBottom: "var(--spacing-lg)" }}>Có thể bạn sẽ thích</h2>
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fill, minmax(250px, 1fr))",
              gap: "var(--spacing-lg)",
            }}
          >
            {similar.map((s) => (
              <div
                key={s.id}
                style={{
                  backgroundColor: "var(--neutral-white)",
                  borderRadius: "var(--radius-lg)",
                  padding: "var(--spacing-lg)",
                  textAlign: "center",
                }}
              >
                <p style={{ fontSize: "3rem", marginBottom: "var(--spacing-md)" }}>👟</p>
                <h4>{s.name}</h4>
                <p style={{ color: "var(--neutral-medium)", fontSize: "0.9rem" }}>{s.brand}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      <hr style={{ margin: "var(--spacing-2xl) 0", borderColor: "var(--neutral-gray)" }} />

      {/* Reviews Section */}
      <div>
        <h2 style={{ marginBottom: "var(--spacing-lg)" }}>Đánh giá từ khách hàng</h2>

        {/* Existing Reviews */}
        {reviews.length > 0 ? (
          <div style={{ marginBottom: "var(--spacing-2xl)" }}>
            {reviews.map((r) => (
              <div
                key={r.id}
                style={{
                  backgroundColor: "var(--neutral-white)",
                  padding: "var(--spacing-lg)",
                  borderRadius: "var(--radius-md)",
                  marginBottom: "var(--spacing-md)",
                  borderLeft: `4px solid var(--primary-accent)`,
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "start" }}>
                  <div style={{ flex: 1 }}>
                    <p style={{ marginBottom: "var(--spacing-sm)" }}>
                      <strong>⭐ {r.rating}/5</strong> • {r.user?.email || "Ẩn danh"}
                    </p>
                    <p style={{ color: "var(--neutral-dark)" }}>{r.comment}</p>
                  </div>
                  {me && r.user && me.id === r.user.id && (
                    <button
                      onClick={() => deleteReview(r.id)}
                      className="btn-secondary"
                      style={{ padding: "var(--spacing-sm)" }}
                    >
                      Xoá
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p style={{ color: "var(--neutral-medium)", marginBottom: "var(--spacing-lg)" }}>
            Chưa có đánh giá nào. Hãy là người đầu tiên đánh giá!
          </p>
        )}

        {/* Add Review Form */}
        <div
          style={{
            backgroundColor: "var(--neutral-white)",
            padding: "var(--spacing-lg)",
            borderRadius: "var(--radius-lg)",
            border: `1px solid var(--neutral-gray)`,
          }}
        >
          <h3 style={{ marginBottom: "var(--spacing-lg)" }}>Viết đánh giá của bạn</h3>

          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label>
              <strong>Xếp hạng</strong>
            </label>
            <select
              value={rating}
              onChange={(e) => setRating(e.target.value)}
              style={{ marginTop: "var(--spacing-sm)" }}
            >
              {[1, 2, 3, 4, 5].map((n) => (
                <option key={n} value={n}>
                  ⭐ {n} sao
                </option>
              ))}
            </select>
          </div>

          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label>
              <strong>Nhận xét</strong>
            </label>
            <textarea
              placeholder="Chia sẻ trải nghiệm của bạn..."
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              style={{ marginTop: "var(--spacing-sm)", minHeight: "120px", resize: "vertical" }}
            />
          </div>

          <button onClick={submitReview} className="btn-primary">
            Gửi đánh giá
          </button>
        </div>
      </div>
    </div>
  )
}
