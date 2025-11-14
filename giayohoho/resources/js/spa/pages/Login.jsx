"use client"

import { useState } from "react"
import { useNavigate, Link } from "react-router-dom"
import api from "../api"

export default function Login() {
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [error, setError] = useState("")
  const [loading, setLoading] = useState(false)
  const nav = useNavigate()

  const submit = async (e) => {
    e.preventDefault()
    setError("")
    setLoading(true)

    try {
      const res = await api.post("/auth/login", { email, password })
      localStorage.setItem("token", res.data.token)
      const me = await api.get("/auth/me")
      localStorage.setItem("user_email", me.data.email || "")
      nav("/")
    } catch (e) {
      setError("Email hoặc mật khẩu không chính xác. Vui lòng thử lại.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <div
      style={{
        maxWidth: "400px",
        margin: "0 auto",
        display: "flex",
        flexDirection: "column",
        minHeight: "calc(100vh - 200px)",
        justifyContent: "center",
      }}
    >
      <div
        style={{
          backgroundColor: "var(--neutral-white)",
          padding: "var(--spacing-2xl)",
          borderRadius: "var(--radius-lg)",
          boxShadow: "var(--shadow-md)",
        }}
      >
        <h1 style={{ textAlign: "center", marginBottom: "var(--spacing-md)" }}>Đăng Nhập</h1>
        <p style={{ textAlign: "center", color: "var(--neutral-medium)", marginBottom: "var(--spacing-2xl)" }}>
          Chào mừng bạn quay lại
        </p>

        {error && <div className="alert alert-error">{error}</div>}

        <form onSubmit={submit}>
          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label htmlFor="email">
              <strong>Email</strong>
            </label>
            <input
              id="email"
              type="email"
              placeholder="your@email.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </div>

          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label htmlFor="password">
              <strong>Mật khẩu</strong>
            </label>
            <input
              id="password"
              type="password"
              placeholder="••••••••"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>

          <button type="submit" className="btn-primary" disabled={loading} style={{ width: "100%" }}>
            {loading ? <span className="spinner" /> : "Đăng nhập"}
          </button>
        </form>

        <hr style={{ margin: "var(--spacing-lg) 0", borderColor: "var(--neutral-gray)" }} />

        <p style={{ textAlign: "center", color: "var(--neutral-dark)" }}>
          Chưa có tài khoản?{" "}
          <Link to="/register" style={{ fontWeight: 600 }}>
            Đăng ký ngay
          </Link>
        </p>
      </div>
    </div>
  )
}
