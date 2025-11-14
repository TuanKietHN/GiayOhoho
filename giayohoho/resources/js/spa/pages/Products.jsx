"use client"

import { useEffect, useState } from "react"
import { Link } from "react-router-dom"
import api from "../api"

export default function Products() {
  const [items, setItems] = useState([])
  const [q, setQ] = useState("")
  const [loading, setLoading] = useState(false)

  const load = async () => {
    setLoading(true)
    try {
      const res = await api.get("/products", { params: q ? { q } : {} })
      setItems(res.data.data || res.data)
    } catch (e) {
      console.error("Error loading products:", e)
    }
    setLoading(false)
  }

  useEffect(() => {
    load()
  }, [])

  return (
    <div>
      <div style={{ marginBottom: "var(--spacing-2xl)" }}>
        <h1 style={{ textAlign: "center", marginBottom: "var(--spacing-lg)" }}>Danh Sách Sản Phẩm</h1>
        <p style={{ textAlign: "center", color: "var(--neutral-medium)", marginBottom: "var(--spacing-lg)" }}>
          Khám phá bộ sưu tập giày chất lượng cao của chúng tôi
        </p>

        {/* Search Bar */}
        <div style={{ display: "flex", gap: "var(--spacing-md)", maxWidth: "400px", margin: "0 auto" }}>
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Tìm kiếm sản phẩm..."
            onKeyPress={(e) => e.key === "Enter" && load()}
            style={{ flex: 1 }}
          />
          <button onClick={load} disabled={loading} className="btn-primary">
            {loading ? <span className="spinner" /> : "Tìm"}
          </button>
        </div>
      </div>

      {/* Products Grid */}
      {loading && q ? (
        <div style={{ textAlign: "center", padding: "var(--spacing-2xl)" }}>
          <div className="spinner" style={{ margin: "0 auto" }} />
          <p style={{ marginTop: "var(--spacing-md)" }}>Đang tải sản phẩm...</p>
        </div>
      ) : items.length === 0 ? (
        <div style={{ textAlign: "center", padding: "var(--spacing-2xl)" }}>
          <p style={{ fontSize: "1.1rem" }}>Không tìm thấy sản phẩm</p>
        </div>
      ) : (
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))",
            gap: "var(--spacing-lg)",
          }}
        >
          {items.map((p) => (
            <Link key={p.id} to={`/products/${p.id}`} style={{ textDecoration: "none" }}>
              <div
                style={{
                  backgroundColor: "var(--neutral-white)",
                  borderRadius: "var(--radius-lg)",
                  overflow: "hidden",
                  boxShadow: "var(--shadow-sm)",
                  transition: "box-shadow 0.3s ease, transform 0.3s ease",
                  cursor: "pointer",
                  height: "100%",
                  display: "flex",
                  flexDirection: "column",
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.boxShadow = "var(--shadow-lg)"
                  e.currentTarget.style.transform = "translateY(-4px)"
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.boxShadow = "var(--shadow-sm)"
                  e.currentTarget.style.transform = "translateY(0)"
                }}
              >
                {/* Image Placeholder */}
                <div
                  style={{
                    backgroundColor: "var(--neutral-gray)",
                    height: "250px",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    color: "var(--neutral-medium)",
                    fontSize: "3rem",
                  }}
                >
                  👟
                </div>

                {/* Content */}
                <div style={{ padding: "var(--spacing-lg)", flex: 1, display: "flex", flexDirection: "column" }}>
                  <h3 style={{ marginBottom: "var(--spacing-sm)", color: "var(--primary-dark)" }}>{p.name}</h3>
                  <p style={{ color: "var(--neutral-medium)", fontSize: "0.9rem", marginBottom: "var(--spacing-sm)" }}>
                    {p.brand} • {p.gender}
                  </p>
                  <p
                    style={{
                      marginTop: "auto",
                      fontSize: "1.25rem",
                      fontWeight: 700,
                      color: "var(--primary-accent)",
                    }}
                  >
                    {new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(p.base_price)}
                  </p>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
