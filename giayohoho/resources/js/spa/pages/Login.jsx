"use client"

import { useState } from "react"
import { useNavigate, Link } from "react-router-dom"
import api from "../api"
import TextField from "@mui/material/TextField"
import Button from "@mui/material/Button"
import Checkbox from "@mui/material/Checkbox"
import FormControlLabel from "@mui/material/FormControlLabel"

export default function Login() {
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [error, setError] = useState("")
  const [loading, setLoading] = useState(false)
  const [show, setShow] = useState(false)
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
      const isAdmin = (me.data.roles || []).some(r => r.name === "admin")
      window.location.href = isAdmin ? "/admin" : "/"
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
          <TextField fullWidth label="Email" type="email" value={email} onChange={e => setEmail(e.target.value)} sx={{ mb: 2 }} required />

          <TextField fullWidth label="Mật khẩu" type={show ? 'text' : 'password'} value={password} onChange={e => setPassword(e.target.value)} sx={{ mb: 1 }} required />
          <FormControlLabel control={<Checkbox checked={show} onChange={e => setShow(e.target.checked)} />} label="Hiển thị mật khẩu" />

          <Button type="submit" variant="contained" disabled={loading} fullWidth>
            {loading ? <span className="spinner" /> : "Đăng nhập"}
          </Button>
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
