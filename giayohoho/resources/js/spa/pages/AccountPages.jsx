import { useEffect, useMemo, useState } from "react"
import { Link, useNavigate, useSearchParams } from "react-router-dom"
import api, { clearAuthPayload } from "../api"
import { useToast } from "../ui/toast"
import Avatar from "@mui/material/Avatar"
import Alert from "@mui/material/Alert"
import Box from "@mui/material/Box"
import Button from "@mui/material/Button"
import CircularProgress from "@mui/material/CircularProgress"
import Divider from "@mui/material/Divider"
import Paper from "@mui/material/Paper"
import Stack from "@mui/material/Stack"
import TextField from "@mui/material/TextField"
import Typography from "@mui/material/Typography"

function AuthPanel({ title, subtitle, children }) {
  return (
    <Box sx={{ minHeight: "70vh", display: "grid", placeItems: "center" }}>
      <Paper variant="outlined" sx={{ width: "100%", maxWidth: 480, p: { xs: 3, sm: 4 }, borderRadius: 2 }}>
        <Typography variant="h4" fontWeight={900}>{title}</Typography>
        <Typography color="text.secondary" sx={{ mt: 1, mb: 3 }}>{subtitle}</Typography>
        {children}
      </Paper>
    </Box>
  )
}

export function ForgotPassword() {
  const [email, setEmail] = useState("")
  const [loading, setLoading] = useState(false)
  const [message, setMessage] = useState("")
  const [error, setError] = useState("")

  const submit = async (event) => {
    event.preventDefault()
    setLoading(true); setError(""); setMessage("")
    try {
      await api.post("/auth/forgot-password", { email })
      setMessage("Nếu email tồn tại, liên kết đặt lại mật khẩu đã được gửi.")
    } catch (requestError) {
      setError(requestError.response?.data?.message || "Không thể gửi yêu cầu.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthPanel title="Quên mật khẩu" subtitle="Nhập email tài khoản để nhận liên kết đặt lại mật khẩu.">
      <Stack component="form" spacing={2} onSubmit={submit}>
        <TextField label="Email" type="email" required value={email} onChange={(event) => setEmail(event.target.value)} />
        {error && <Alert severity="error">{error}</Alert>}
        {message && <Alert severity="success">{message}</Alert>}
        <Button type="submit" variant="contained" disabled={loading}>{loading ? "Đang gửi..." : "Gửi liên kết"}</Button>
        <Button component={Link} to="/login">Quay lại đăng nhập</Button>
      </Stack>
    </AuthPanel>
  )
}

export function ResetPassword() {
  const [params] = useSearchParams()
  const [password, setPassword] = useState("")
  const [confirm, setConfirm] = useState("")
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState("")
  const navigate = useNavigate()

  const submit = async (event) => {
    event.preventDefault()
    if (password.length < 6 || password !== confirm) {
      setError("Mật khẩu tối thiểu 6 ký tự và hai ô phải trùng nhau.")
      return
    }
    setLoading(true); setError("")
    try {
      await api.post("/auth/reset-password", { token: params.get("token"), newPassword: password })
      navigate("/login?reason=password_reset", { replace: true })
    } catch (requestError) {
      setError(requestError.response?.data?.message || "Token không hợp lệ hoặc đã hết hạn.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthPanel title="Đặt lại mật khẩu" subtitle="Tạo mật khẩu mới cho tài khoản của bạn.">
      <Stack component="form" spacing={2} onSubmit={submit}>
        <TextField label="Mật khẩu mới" type="password" required value={password} onChange={(event) => setPassword(event.target.value)} />
        <TextField label="Xác nhận mật khẩu" type="password" required value={confirm} onChange={(event) => setConfirm(event.target.value)} />
        {error && <Alert severity="error">{error}</Alert>}
        <Button type="submit" variant="contained" disabled={loading || !params.get("token")}>{loading ? "Đang cập nhật..." : "Cập nhật mật khẩu"}</Button>
      </Stack>
    </AuthPanel>
  )
}

export function SetupPassword() {
  const [password, setPassword] = useState("")
  const [confirm, setConfirm] = useState("")
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState("")
  const navigate = useNavigate()

  const submit = async (event) => {
    event.preventDefault()
    if (password.length < 6 || password !== confirm) {
      setError("Mật khẩu tối thiểu 6 ký tự và hai ô phải trùng nhau.")
      return
    }
    setLoading(true); setError("")
    try {
      await api.post("/auth/setup-password", { newPassword: password })
      navigate("/", { replace: true })
    } catch (requestError) {
      setError(requestError.response?.data?.message || "Không thể thiết lập mật khẩu.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthPanel title="Thiết lập mật khẩu" subtitle="Hoàn tất bảo mật cho tài khoản đăng nhập Google.">
      <Stack component="form" spacing={2} onSubmit={submit}>
        <TextField label="Mật khẩu mới" type="password" required value={password} onChange={(event) => setPassword(event.target.value)} />
        <TextField label="Xác nhận mật khẩu" type="password" required value={confirm} onChange={(event) => setConfirm(event.target.value)} />
        {error && <Alert severity="error">{error}</Alert>}
        <Button type="submit" variant="contained" disabled={loading}>{loading ? "Đang lưu..." : "Lưu mật khẩu"}</Button>
      </Stack>
    </AuthPanel>
  )
}

export function VerifyEmail() {
  const [params] = useSearchParams()
  const [state, setState] = useState({ loading: true, success: false, error: "" })

  useEffect(() => {
    const token = params.get("token")
    if (!token) {
      setState({ loading: false, success: false, error: "Thiếu token xác thực email." })
      return
    }
    api.post("/auth/verify-email", { token })
      .then(() => setState({ loading: false, success: true, error: "" }))
      .catch((error) => setState({ loading: false, success: false, error: error.response?.data?.message || "Token không hợp lệ hoặc đã hết hạn." }))
  }, [params])

  return (
    <AuthPanel title="Xác thực email" subtitle="Kiểm tra liên kết xác thực tài khoản.">
      {state.loading && <Box sx={{ textAlign: "center" }}><CircularProgress /></Box>}
      {!state.loading && state.success && <Alert severity="success">Email đã được xác thực. Bạn có thể đăng nhập.</Alert>}
      {!state.loading && state.error && <Alert severity="error">{state.error}</Alert>}
      <Button component={Link} to="/login" fullWidth sx={{ mt: 2 }}>Đến trang đăng nhập</Button>
    </AuthPanel>
  )
}

export function Profile() {
  const [profile, setProfile] = useState(null)
  const [form, setForm] = useState({ firstName: "", lastName: "", phoneNumber: "", birthOfDate: "" })
  const [passwords, setPasswords] = useState({ currentPassword: "", newPassword: "" })
  const [saving, setSaving] = useState(false)
  const toast = useToast()
  const navigate = useNavigate()

  const initials = useMemo(() => {
    if (!profile) return "U"
    return `${profile.firstName?.[0] || ""}${profile.lastName?.[0] || ""}`.toUpperCase() || profile.username?.[0]?.toUpperCase()
  }, [profile])

  const load = () => api.get("/profile").then((response) => {
    setProfile(response.data)
    setForm({
      firstName: response.data.firstName || "",
      lastName: response.data.lastName || "",
      phoneNumber: response.data.phoneNumber || "",
      birthOfDate: response.data.birthOfDate || "",
    })
  })

  useEffect(() => { load() }, [])

  const save = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      const response = await api.put("/profile", form)
      setProfile(response.data)
      toast.show("Đã cập nhật hồ sơ", "success")
    } catch (error) {
      toast.show(error.response?.data?.message || "Không thể cập nhật hồ sơ", "error")
    } finally {
      setSaving(false)
    }
  }

  const uploadAvatar = async (event) => {
    const file = event.target.files?.[0]
    if (!file) return
    const data = new FormData()
    data.append("file", file)
    try {
      const response = await api.post("/profile/avatar", data)
      setProfile(response.data)
      toast.show("Đã cập nhật ảnh đại diện", "success")
    } catch (error) {
      toast.show(error.response?.data?.message || "Không thể tải ảnh", "error")
    }
  }

  const changePassword = async (event) => {
    event.preventDefault()
    try {
      await api.post("/auth/change-password", passwords)
      clearAuthPayload()
      navigate("/login?reason=password_changed", { replace: true })
    } catch (error) {
      toast.show(error.response?.data?.message || "Không thể đổi mật khẩu", "error")
    }
  }

  if (!profile) return <Box sx={{ py: 8, textAlign: "center" }}><CircularProgress /></Box>

  return (
    <Box sx={{ maxWidth: 900, mx: "auto" }}>
      <Typography variant="overline" color="primary" fontWeight={900}>Tài khoản</Typography>
      <Typography variant="h3" fontWeight={900}>Hồ sơ của tôi</Typography>
      <Paper variant="outlined" sx={{ mt: 3, p: { xs: 2, sm: 4 }, borderRadius: 2 }}>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={2} alignItems="center">
          <Avatar src={profile.avatar || ""} sx={{ width: 88, height: 88, fontSize: 28 }}>{initials}</Avatar>
          <Box sx={{ flex: 1 }}>
            <Typography variant="h5" fontWeight={900}>{profile.firstName} {profile.lastName}</Typography>
            <Typography color="text.secondary">{profile.email}</Typography>
          </Box>
          <Button component="label" variant="outlined">Đổi ảnh<input hidden type="file" accept="image/png,image/jpeg,image/webp" onChange={uploadAvatar} /></Button>
        </Stack>
        <Divider sx={{ my: 3 }} />
        <Stack component="form" spacing={2} onSubmit={save}>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
            <TextField fullWidth label="Họ" value={form.firstName} onChange={(event) => setForm({ ...form, firstName: event.target.value })} />
            <TextField fullWidth label="Tên" value={form.lastName} onChange={(event) => setForm({ ...form, lastName: event.target.value })} />
          </Stack>
          <TextField label="Số điện thoại" value={form.phoneNumber} onChange={(event) => setForm({ ...form, phoneNumber: event.target.value })} />
          <TextField label="Ngày sinh" type="date" InputLabelProps={{ shrink: true }} value={form.birthOfDate || ""} onChange={(event) => setForm({ ...form, birthOfDate: event.target.value })} />
          <Button type="submit" variant="contained" disabled={saving}>{saving ? "Đang lưu..." : "Lưu hồ sơ"}</Button>
        </Stack>
        <Divider sx={{ my: 3 }} />
        <Typography variant="h6" fontWeight={900} sx={{ mb: 2 }}>Đổi mật khẩu</Typography>
        <Stack component="form" spacing={2} onSubmit={changePassword}>
          <TextField label="Mật khẩu hiện tại" type="password" required value={passwords.currentPassword} onChange={(event) => setPasswords({ ...passwords, currentPassword: event.target.value })} />
          <TextField label="Mật khẩu mới" type="password" required value={passwords.newPassword} onChange={(event) => setPasswords({ ...passwords, newPassword: event.target.value })} />
          <Button type="submit" variant="outlined">Cập nhật mật khẩu</Button>
        </Stack>
      </Paper>
    </Box>
  )
}
