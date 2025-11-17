import React, { useEffect, useState } from "react"
import { useParams, useNavigate } from "react-router-dom"
import api from "../api"
import { useToast } from "../ui/toast.jsx"
import Grid from "@mui/material/Grid"
import Card from "@mui/material/Card"
import CardContent from "@mui/material/CardContent"
import Typography from "@mui/material/Typography"
import IconButton from "@mui/material/IconButton"
import ArrowBackIosNewIcon from "@mui/icons-material/ArrowBackIosNew"
import ArrowForwardIosIcon from "@mui/icons-material/ArrowForwardIos"

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
  const [simPage, setSimPage] = useState(0)
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

  useEffect(() => {
    if (variantId) {
      api.get(`/variants/${variantId}`).then((res) => setP((prev) => ({ ...prev, _selectedVariant: res.data })))
    }
  }, [variantId])

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
      <Grid container spacing={4} sx={{ mb: 4 }}>
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              {p.images && p.images.length > 0 ? (
                <img src={p.images[0].image_url} alt={p.images[0].alt_text || p.name} style={{ width: '100%', borderRadius: 8 }} />
              ) : (
                <div style={{ backgroundColor: '#e5e7eb', height: 300, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: 8 }}>👟</div>
              )}
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={6}>
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
          <Typography sx={{ mb: 2 }}>{p.description || "Thông tin sản phẩm"}</Typography>

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
        </Grid>
      </Grid>

      {/* Divider */}
      <hr style={{ margin: "var(--spacing-2xl) 0", borderColor: "var(--neutral-gray)" }} />

      {/* Similar Products */}
      {similar.length > 0 && (
        <div style={{ marginBottom: "var(--spacing-2xl)" }}>
          <h2 style={{ marginBottom: "var(--spacing-lg)" }}>Có thể bạn sẽ thích</h2>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <IconButton onClick={() => setSimPage(Math.max(0, simPage - 1))}><ArrowBackIosNewIcon /></IconButton>
            <Grid container spacing={2}>
              {similar.slice(simPage * 4, simPage * 4 + 4).map((s) => (
                <Grid item xs={12} sm={6} md={3} key={s.id}>
                  <Card onClick={() => nav(`/products/${s.id}`)} style={{ cursor: 'pointer' }}>
                    <CardContent style={{ textAlign: 'center' }}>
                      {s.images && s.images.length > 0 ? (
                        <img src={s.images[0].image_url} alt={s.images[0].alt_text || s.name} style={{ width: '100%', borderRadius: 8 }} />
                      ) : (
                        <div style={{ backgroundColor: '#e5e7eb', height: 150, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: 8 }}>👟</div>
                      )}
                      <Typography variant="subtitle1" sx={{ mt: 1 }}>{s.name}</Typography>
                      <Typography variant="caption" color="text.secondary">{s.brand}</Typography>
                    </CardContent>
                  </Card>
                </Grid>
              ))}
            </Grid>
            <IconButton onClick={() => setSimPage(Math.min(Math.floor((similar.length - 1) / 4), simPage + 1))}><ArrowForwardIosIcon /></IconButton>
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

