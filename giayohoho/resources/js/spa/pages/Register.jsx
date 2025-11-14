"use client"

import { useState } from "react"
import { useNavigate, Link } from "react-router-dom"
import api from "../api"

export default function Register() {
  const [first_name, setFirst] = useState("")
  const [username, setUsername] = useState("")
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [password_confirmation, setConfirm] = useState("")
  const [error, setError] = useState("")
  const [loading, setLoading] = useState(false)
  const nav = useNavigate()

  const submit = async (e) => {
    e.preventDefault()
    setError("")

    if (password !== password_confirmation) {
      setError("Mật khẩu không khớp")
      return
    }

    setLoading(true)
    try {
      const res = await api.post("/auth/register", { first_name, username, email, password, password_confirmation })
      localStorage.setItem("token", res.data.token)
      const me = await api.get("/auth/me")
      localStorage.setItem("user_email", me.data.email || "")
      const isAdmin = (me.data.roles || []).some(r => r.name === "admin")
      window.location.href = isAdmin ? "/admin" : "/"
    } catch (e) {
      setError(e.response?.data?.message || "Đăng ký thất bại. Vui lòng thử lại.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <div
      style={{
        maxWidth: "450px",
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
        <h1 style={{ textAlign: "center", marginBottom: "var(--spacing-md)" }}>Tạo Tài Khoản</h1>
        <p style={{ textAlign: "center", color: "var(--neutral-medium)", marginBottom: "var(--spacing-2xl)" }}>
          Tham gia cộng đồng GiàyOhoho ngay
        </p>

        {error && <div className="alert alert-error">{error}</div>}

        <form onSubmit={submit}>
          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label htmlFor="first_name">
              <strong>Họ và tên</strong>
            </label>
            <input
              id="first_name"
              placeholder="Nguyễn Văn A"
              value={first_name}
              onChange={(e) => setFirst(e.target.value)}
              required
            />
          </div>

          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label htmlFor="username">
              <strong>Tên tài khoản</strong>
            </label>
            <input
              id="username"
              placeholder="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              required
            />
          </div>

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

          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label htmlFor="password_confirmation">
              <strong>Xác nhận mật khẩu</strong>
            </label>
            <input
              id="password_confirmation"
              type="password"
              placeholder="••••••••"
              value={password_confirmation}
              onChange={(e) => setConfirm(e.target.value)}
              required
            />
          </div>

          <button type="submit" className="btn-primary" disabled={loading} style={{ width: "100%" }}>
            {loading ? <span className="spinner" /> : "Đăng ký"}
          </button>
        </form>

        <hr style={{ margin: "var(--spacing-lg) 0", borderColor: "var(--neutral-gray)" }} />

        <p style={{ textAlign: "center", color: "var(--neutral-dark)" }}>
          Đã có tài khoản?{" "}
          <Link to="/login" style={{ fontWeight: 600 }}>
            Đăng nhập
          </Link>
        </p>
      </div>
    </div>
  )
}
