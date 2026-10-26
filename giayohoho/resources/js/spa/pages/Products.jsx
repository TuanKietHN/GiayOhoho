"use client"

import { useEffect, useState } from "react"
import { useSearchParams } from "react-router-dom"
import api from "../api"
import Slider from "../ui/slider.jsx"
import ProductCard from "../components/ProductCard.jsx"

export default function Products() {
  const [items, setItems] = useState([])
  const [q, setQ] = useState("")
  const [loading, setLoading] = useState(false)
  const [brand, setBrand] = useState("")
  const [gender, setGender] = useState("")
  const [minPrice, setMinPrice] = useState("")
  const [maxPrice, setMaxPrice] = useState("")
  const [size, setSize] = useState("")
  const [color, setColor] = useState("")
  const [surface, setSurface] = useState("")
  const [cushioning, setCushioning] = useState("")
  const [pronation, setPronation] = useState("")
  const [waterproof, setWaterproof] = useState("")
  const [sort, setSort] = useState("")
  const [searchParams] = useSearchParams()

  const load = async (overrides = {}) => {
    setLoading(true)
    try {
      const params = {}
      const effectiveQ = overrides.q ?? q
      if (effectiveQ) params.q = effectiveQ
      if (brand) params.brand = [brand]
      if (gender) params.gender = [gender]
      if (minPrice) params.min_price = Number(minPrice)
      if (maxPrice) params.max_price = Number(maxPrice)
      if (size) params.size = [size]
      if (color) params.color = [color]
      if (surface) params.surface = [surface]
      if (cushioning) params.cushioning_level = [cushioning]
      if (pronation) params.pronation_type = [pronation]
      if (waterproof) params.is_waterproof = waterproof === "true"
      if (sort) params.sort = sort
      const res = await api.get("/products", { params })
      setItems(res.data.content || res.data.data || res.data)
    } catch (e) {
      console.error("Error loading products:", e)
    }
    setLoading(false)
  }

  useEffect(() => {
    const urlQuery = searchParams.get("q") || ""
    setQ(urlQuery)
    load({ q: urlQuery })
  }, [searchParams])

  return (
    <div>
      <div style={{ marginBottom: "var(--spacing-2xl)" }}>
        <h1 style={{ textAlign: "center", marginBottom: "var(--spacing-lg)" }}>Danh Sách Sản Phẩm</h1>
        <p style={{ textAlign: "center", color: "var(--neutral-medium)", marginBottom: "var(--spacing-lg)" }}>
          Khám phá bộ sưu tập giày chất lượng cao của chúng tôi
        </p>

        {/* Search Bar */}
        <div style={{ display: "flex", gap: "var(--spacing-md)", maxWidth: "800px", margin: "0 auto" }}>
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

        {/* Filters */}
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: "var(--spacing-md)", marginTop: "var(--spacing-lg)" }}>
          <select value={brand} onChange={(e) => setBrand(e.target.value)}>
            <option value="">Brand</option>
            {['Nike','Adidas','Asics','Saucony','New Balance','Hoka','Brooks'].map(b => <option key={b} value={b}>{b}</option>)}
          </select>
          <select value={gender} onChange={(e) => setGender(e.target.value)}>
            <option value="">Giới tính</option>
            {['male','female','unisex'].map(g => <option key={g} value={g}>{g}</option>)}
          </select>
          <input type="number" placeholder="Giá từ" value={minPrice} onChange={(e) => setMinPrice(e.target.value)} />
          <input type="number" placeholder="Giá đến" value={maxPrice} onChange={(e) => setMaxPrice(e.target.value)} />
          <input placeholder="Size" value={size} onChange={(e) => setSize(e.target.value)} />
          <input placeholder="Màu" value={color} onChange={(e) => setColor(e.target.value)} />
          <select value={surface} onChange={(e) => setSurface(e.target.value)}>
            <option value="">Surface</option>
            {['road','trail','treadmill','walking','hiking'].map(s => <option key={s} value={s}>{s}</option>)}
          </select>
          <select value={cushioning} onChange={(e) => setCushioning(e.target.value)}>
            <option value="">Cushioning</option>
            {['low','medium','high','maximum'].map(c => <option key={c} value={c}>{c}</option>)}
          </select>
          <select value={pronation} onChange={(e) => setPronation(e.target.value)}>
            <option value="">Pronation</option>
            {['neutral','stability','motion_control'].map(p => <option key={p} value={p}>{p}</option>)}
          </select>
          <select value={waterproof} onChange={(e) => setWaterproof(e.target.value)}>
            <option value="">Waterproof</option>
            <option value="true">Có</option>
            <option value="false">Không</option>
          </select>
          <select value={sort} onChange={(e) => setSort(e.target.value)}>
            <option value="">Sắp xếp</option>
            <option value="newest">Mới nhất</option>
            <option value="price_asc">Giá tăng</option>
            <option value="price_desc">Giá giảm</option>
            <option value="rating_desc">Rating cao</option>
          </select>
          <button onClick={load} className="btn-secondary">Áp dụng bộ lọc</button>
        </div>
      </div>

      {/* Banner Sections */}
      <div style={{ marginBottom: "var(--spacing-2xl)" }}>
        <Slider />
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
            <ProductCard key={p.id} product={p} />
          ))}
        </div>
      )}
    </div>
  )
}
