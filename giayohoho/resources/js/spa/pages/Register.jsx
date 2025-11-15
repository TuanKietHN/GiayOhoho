"use client"

import { useState } from "react"
import { useNavigate, Link } from "react-router-dom"
import api from "../api"
import TextField from "@mui/material/TextField"
import Button from "@mui/material/Button"
import Checkbox from "@mui/material/Checkbox"
import FormControlLabel from "@mui/material/FormControlLabel"

export default function Register() {
  const [first_name, setFirst] = useState("")
  const [username, setUsername] = useState("")
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [password_confirmation, setConfirm] = useState("")
  const [error, setError] = useState("")
  const [loading, setLoading] = useState(false)
  const [showPw, setShowPw] = useState(false)
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
          <TextField fullWidth label="Họ và tên" value={first_name} onChange={e => setFirst(e.target.value)} sx={{ mb: 2 }} required />

          <TextField fullWidth label="Tên tài khoản" value={username} onChange={e => setUsername(e.target.value)} sx={{ mb: 2 }} required />

          <TextField fullWidth label="Email" type="email" value={email} onChange={e => setEmail(e.target.value)} sx={{ mb: 2 }} required />

          <TextField fullWidth label="Mật khẩu" type={showPw ? 'text' : 'password'} value={password} onChange={e => setPassword(e.target.value)} sx={{ mb: 1 }} required />
          <FormControlLabel control={<Checkbox checked={showPw} onChange={e => setShowPw(e.target.checked)} />} label="Hiển thị mật khẩu" />

          <div style={{ marginBottom: "var(--spacing-lg)" }}>
            <label htmlFor="password_confirmation">
              <strong>Xác nhận mật khẩu</strong>
            </label>
            <TextField fullWidth label="Xác nhận mật khẩu" type={showPw ? 'text' : 'password'} value={password_confirmation} onChange={e => setConfirm(e.target.value)} sx={{ mb: 1 }} required error={Boolean(password_confirmation) && password_confirmation !== password} helperText={Boolean(password_confirmation) && password_confirmation !== password ? 'Mật khẩu không khớp' : ''} />
          </div>

          <Button type="submit" variant="contained" disabled={loading} fullWidth>
            {loading ? <span className="spinner" /> : "Đăng ký"}
          </Button>
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
