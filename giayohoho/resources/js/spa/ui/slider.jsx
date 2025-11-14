import React, { useEffect, useState } from 'react'

const slides = [
  { id: 1, title: 'Siêu phẩm chạy bộ', subtitle: 'Khuyến mãi tới 20%', color: '#1f2937' },
  { id: 2, title: 'Trail bền bỉ', subtitle: 'Chinh phục mọi địa hình', color: '#111827' },
  { id: 3, title: 'Lifestyle năng động', subtitle: 'Phong cách mỗi ngày', color: '#0f172a' },
]

export default function Slider() {
  const [i, setI] = useState(0)
  useEffect(() => {
    const t = setInterval(() => setI((prev) => (prev + 1) % slides.length), 4000)
    return () => clearInterval(t)
  }, [])
  const s = slides[i]
  return (
    <div style={{ background: s.color, color: 'white', padding: '40px', borderRadius: 12, boxShadow: 'var(--shadow-md)' }}>
      <h2 style={{ fontSize: '2rem', marginBottom: 8 }}>{s.title}</h2>
      <p style={{ color: '#e5e7eb' }}>{s.subtitle}</p>
    </div>
  )
}

