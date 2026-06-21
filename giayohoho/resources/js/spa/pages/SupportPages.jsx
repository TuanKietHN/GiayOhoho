import { useEffect, useState } from "react"
import { Link } from "react-router-dom"
import api from "../api"
import ProductCard from "../components/ProductCard"
import SendIcon from "@mui/icons-material/Send"
import Alert from "@mui/material/Alert"
import Box from "@mui/material/Box"
import Button from "@mui/material/Button"
import Chip from "@mui/material/Chip"
import Grid from "@mui/material/Grid"
import List from "@mui/material/List"
import ListItem from "@mui/material/ListItem"
import ListItemText from "@mui/material/ListItemText"
import Paper from "@mui/material/Paper"
import Stack from "@mui/material/Stack"
import Tab from "@mui/material/Tab"
import Tabs from "@mui/material/Tabs"
import TextField from "@mui/material/TextField"
import Typography from "@mui/material/Typography"

export function Contact() {
  const [form, setForm] = useState({ name: "", email: "", topic: "Tư vấn sản phẩm", message: "", companyWebsite: "", startedAt: Date.now() })
  const [state, setState] = useState({ loading: false, message: "", error: "" })

  const submit = async (event) => {
    event.preventDefault()
    setState({ loading: true, message: "", error: "" })
    try {
      await api.post("/contact/requests", form)
      setState({ loading: false, message: "Đã ghi nhận nội dung. Bộ phận hỗ trợ sẽ liên hệ lại sớm.", error: "" })
      setForm({ ...form, message: "", companyWebsite: "" })
    } catch (error) {
      setState({ loading: false, message: "", error: error.response?.data?.message || "Không thể gửi yêu cầu." })
    }
  }

  return (
    <Box sx={{ maxWidth: 760, mx: "auto" }}>
      <Typography variant="overline" color="primary" fontWeight={900}>Hỗ trợ</Typography>
      <Typography variant="h3" fontWeight={900}>Liên hệ GiayOhoho</Typography>
      <Typography color="text.secondary" sx={{ mb: 3 }}>Gửi câu hỏi về sản phẩm, đơn hàng hoặc chính sách.</Typography>
      <Paper component="form" variant="outlined" onSubmit={submit} sx={{ p: { xs: 2, sm: 4 }, borderRadius: 2 }}>
        <Stack spacing={2}>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
            <TextField fullWidth label="Họ tên" required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
            <TextField fullWidth label="Email" type="email" required value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
          </Stack>
          <TextField label="Chủ đề" required value={form.topic} onChange={(event) => setForm({ ...form, topic: event.target.value })} />
          <TextField label="Nội dung" required multiline minRows={5} value={form.message} onChange={(event) => setForm({ ...form, message: event.target.value })} />
          <TextField sx={{ display: "none" }} tabIndex={-1} value={form.companyWebsite} onChange={(event) => setForm({ ...form, companyWebsite: event.target.value })} />
          {state.error && <Alert severity="error">{state.error}</Alert>}
          {state.message && <Alert severity="success">{state.message}</Alert>}
          <Button type="submit" variant="contained" disabled={state.loading}>{state.loading ? "Đang gửi..." : "Gửi yêu cầu"}</Button>
        </Stack>
      </Paper>
    </Box>
  )
}

const fallbackPolicies = [
  { id: "shipping", kicker: "Giao hàng", title: "Chính sách giao hàng", summary: "Đơn hàng được giao qua GHN theo địa chỉ nhận hàng.", lines: ["Phí vận chuyển hiển thị tại bước thanh toán.", "Thời gian dự kiến phụ thuộc khu vực nhận hàng.", "Khách hàng có thể theo dõi trạng thái trong trang đơn hàng."] },
  { id: "returns", kicker: "Đổi trả", title: "Chính sách đổi trả", summary: "Liên hệ bộ phận hỗ trợ kèm mã đơn để được kiểm tra.", lines: ["Sản phẩm cần còn nguyên tem, hộp và chưa qua sử dụng.", "Hỗ trợ đổi kích thước theo tồn kho thực tế.", "Không áp dụng với hư hỏng do sử dụng sai."] },
  { id: "payment", kicker: "Thanh toán", title: "Chính sách thanh toán", summary: "Hỗ trợ COD và PayOS.", lines: ["Đơn online chỉ được ghi nhận thanh toán khi backend xác nhận PAID.", "Không chia sẻ mã xác thực thanh toán.", "Giao dịch hủy được giữ lịch sử để đối soát."] },
]

export function Policies() {
  const [policies, setPolicies] = useState(fallbackPolicies)
  const [selected, setSelected] = useState(0)
  useEffect(() => {
    api.get("/policies").then((response) => {
      if (Array.isArray(response.data) && response.data.length) setPolicies(response.data)
    }).catch(() => {})
  }, [])
  const policy = policies[selected] || policies[0]

  return (
    <Box>
      <Typography variant="overline" color="primary" fontWeight={900}>Thông tin mua hàng</Typography>
      <Typography variant="h3" fontWeight={900}>Chính sách cửa hàng</Typography>
      <Typography color="text.secondary" sx={{ mb: 3 }}>Nắm rõ điều kiện giao hàng, đổi trả và thanh toán trước khi đặt đơn.</Typography>
      <Grid container spacing={2}>
        <Grid item xs={12} md={3}>
          <Paper variant="outlined" sx={{ borderRadius: 2 }}>
            <Tabs orientation="vertical" value={selected} onChange={(_, value) => setSelected(value)}>
              {policies.map((item) => <Tab key={item.id} label={item.title} sx={{ alignItems: "flex-start", textAlign: "left" }} />)}
            </Tabs>
          </Paper>
        </Grid>
        <Grid item xs={12} md={9}>
          <Paper variant="outlined" sx={{ p: 3, borderRadius: 2 }}>
            <Typography variant="overline" color="warning.main" fontWeight={900}>{policy.kicker}</Typography>
            <Typography variant="h4" fontWeight={900}>{policy.title}</Typography>
            <Typography color="text.secondary" sx={{ mt: 1 }}>{policy.summary}</Typography>
            <List>{(policy.lines || []).map((line) => <ListItem key={line} divider><ListItemText primary={line} /></ListItem>)}</List>
          </Paper>
        </Grid>
      </Grid>
    </Box>
  )
}

export function Chatbot() {
  const [messages, setMessages] = useState([{ role: "assistant", text: "Xin chào, bạn cần tìm giày, tư vấn size hay hỏi về giao hàng?" }])
  const [input, setInput] = useState("")
  const [conversationId, setConversationId] = useState("")
  const [quickReplies, setQuickReplies] = useState([])
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(false)

  const send = async (message = input) => {
    const text = message.trim()
    if (!text || loading) return
    setMessages((current) => [...current, { role: "user", text }])
    setInput(""); setLoading(true)
    try {
      const response = await api.post("/chatbot/messages", { message: text, conversationId: conversationId || undefined })
      setConversationId(response.data.conversationId)
      setMessages((current) => [...current, { role: "assistant", text: response.data.reply }])
      setQuickReplies(response.data.quickReplies || [])
      setProducts(response.data.products || [])
    } catch (error) {
      setMessages((current) => [...current, { role: "assistant", text: error.response?.data?.message || "Hiện chưa thể trả lời, vui lòng thử lại." }])
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box sx={{ maxWidth: 900, mx: "auto" }}>
      <Typography variant="overline" color="primary" fontWeight={900}>Trợ lý mua sắm</Typography>
      <Typography variant="h3" fontWeight={900}>Chatbot GiayOhoho</Typography>
      <Paper variant="outlined" sx={{ mt: 3, p: 2, minHeight: 420, borderRadius: 2 }}>
        <Stack spacing={1.5}>
          {messages.map((message, index) => (
            <Box key={`${message.role}-${index}`} sx={{ alignSelf: message.role === "user" ? "flex-end" : "flex-start", maxWidth: "78%", bgcolor: message.role === "user" ? "primary.main" : "grey.100", color: message.role === "user" ? "primary.contrastText" : "text.primary", px: 2, py: 1.25, borderRadius: 2 }}>
              {message.text}
            </Box>
          ))}
          {loading && <Typography color="text.secondary">Đang tìm câu trả lời...</Typography>}
        </Stack>
      </Paper>
      <Stack direction="row" spacing={1} sx={{ mt: 2 }}>
        <TextField fullWidth placeholder="Nhập câu hỏi..." value={input} onChange={(event) => setInput(event.target.value)} onKeyDown={(event) => event.key === "Enter" && send()} />
        <Button variant="contained" onClick={() => send()} disabled={loading}><SendIcon /></Button>
      </Stack>
      <Stack direction="row" spacing={1} flexWrap="wrap" sx={{ mt: 1.5 }}>
        {quickReplies.map((reply) => <Chip key={reply.label} label={reply.label} onClick={() => send(reply.message)} sx={{ mb: 1 }} />)}
      </Stack>
      {!!products.length && (
        <Box sx={{ mt: 4 }}>
          <Stack direction="row" justifyContent="space-between"><Typography variant="h5" fontWeight={900}>Sản phẩm gợi ý</Typography><Button component={Link} to="/products">Xem tất cả</Button></Stack>
          <Grid container spacing={2} sx={{ mt: 0.5 }}>{products.map((product) => <Grid item xs={12} sm={6} md={3} key={product.id}><ProductCard product={product} /></Grid>)}</Grid>
        </Box>
      )}
    </Box>
  )
}
