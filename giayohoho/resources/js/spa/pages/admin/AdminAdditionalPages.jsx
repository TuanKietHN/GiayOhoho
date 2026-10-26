import { useEffect, useState } from "react"
import { Link, useParams } from "react-router-dom"
import api from "../../api"
import { useToast } from "../../ui/toast"
import AddIcon from "@mui/icons-material/Add"
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline"
import EditOutlinedIcon from "@mui/icons-material/EditOutlined"
import OpenInNewIcon from "@mui/icons-material/OpenInNew"
import Alert from "@mui/material/Alert"
import Box from "@mui/material/Box"
import Button from "@mui/material/Button"
import Chip from "@mui/material/Chip"
import Dialog from "@mui/material/Dialog"
import DialogActions from "@mui/material/DialogActions"
import DialogContent from "@mui/material/DialogContent"
import DialogTitle from "@mui/material/DialogTitle"
import Grid from "@mui/material/Grid"
import MenuItem from "@mui/material/MenuItem"
import Paper from "@mui/material/Paper"
import Stack from "@mui/material/Stack"
import Table from "@mui/material/Table"
import TableBody from "@mui/material/TableBody"
import TableCell from "@mui/material/TableCell"
import TableContainer from "@mui/material/TableContainer"
import TableHead from "@mui/material/TableHead"
import TableRow from "@mui/material/TableRow"
import TextField from "@mui/material/TextField"
import Typography from "@mui/material/Typography"

const money = new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" })

function AdminHeading({ title, subtitle, action }) {
  return <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems={{ sm: "end" }} spacing={2} sx={{ mb: 3 }}><Box><Typography variant="overline" color="primary" fontWeight={900}>Quản trị</Typography><Typography variant="h4" fontWeight={900}>{title}</Typography><Typography color="text.secondary">{subtitle}</Typography></Box>{action}</Stack>
}

export function AdminSizeGuides() {
  const [guides, setGuides] = useState([])
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({ brand: "", title: "", measurementUnit: "EU", fitNotes: "", sizeChart: "" })
  const toast = useToast()
  const load = () => api.get("/admin/size-guides", { params: { page: 0, size: 100 } }).then((response) => setGuides(response.data.content || response.data || []))
  useEffect(() => { load() }, [])

  const create = async () => {
    try {
      await api.post("/admin/size-guides", form)
      setOpen(false); setForm({ brand: "", title: "", measurementUnit: "EU", fitNotes: "", sizeChart: "" }); load()
      toast.show("Đã tạo hướng dẫn kích thước", "success")
    } catch (error) { toast.show(error.response?.data?.message || "Không thể tạo size guide", "error") }
  }

  const remove = async (id) => {
    await api.delete(`/admin/size-guides/${id}`)
    load()
  }

  return (
    <Box>
      <AdminHeading title="Hướng dẫn kích thước" subtitle="Quản lý size guide theo sản phẩm hoặc thương hiệu." action={<Button variant="contained" startIcon={<AddIcon />} onClick={() => setOpen(true)}>Tạo mới</Button>} />
      <TableContainer component={Paper} variant="outlined"><Table><TableHead><TableRow><TableCell>Tiêu đề</TableCell><TableCell>Thương hiệu</TableCell><TableCell>Đơn vị</TableCell><TableCell>Trạng thái</TableCell><TableCell /></TableRow></TableHead><TableBody>
        {guides.map((guide) => <TableRow key={guide.id}><TableCell><Typography fontWeight={800}>{guide.title}</Typography><Typography variant="caption">{guide.productName || guide.productType}</Typography></TableCell><TableCell>{guide.brand || "-"}</TableCell><TableCell>{guide.measurementUnit}</TableCell><TableCell><Chip size="small" color={guide.active ? "success" : "default"} label={guide.active ? "Đang dùng" : "Tắt"} /></TableCell><TableCell align="right"><Button color="error" onClick={() => remove(guide.id)}><DeleteOutlineIcon /></Button></TableCell></TableRow>)}
      </TableBody></Table></TableContainer>
      <Dialog open={open} onClose={() => setOpen(false)} fullWidth maxWidth="sm"><DialogTitle>Tạo hướng dẫn kích thước</DialogTitle><DialogContent><Stack spacing={2} sx={{ mt: 1 }}><TextField label="Tiêu đề" required value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} /><TextField label="Thương hiệu" required value={form.brand} onChange={(event) => setForm({ ...form, brand: event.target.value })} /><TextField label="Đơn vị" value={form.measurementUnit} onChange={(event) => setForm({ ...form, measurementUnit: event.target.value })} /><TextField label="Ghi chú độ vừa" multiline minRows={2} value={form.fitNotes} onChange={(event) => setForm({ ...form, fitNotes: event.target.value })} /><TextField label="Bảng size (JSON/text)" multiline minRows={4} value={form.sizeChart} onChange={(event) => setForm({ ...form, sizeChart: event.target.value })} /></Stack></DialogContent><DialogActions><Button onClick={() => setOpen(false)}>Hủy</Button><Button variant="contained" onClick={create}>Tạo</Button></DialogActions></Dialog>
    </Box>
  )
}

export function AdminSalePrices() {
  const [products, setProducts] = useState([])
  const [editing, setEditing] = useState(null)
  const [prices, setPrices] = useState({ currentPrice: 0, originalPrice: "" })
  const toast = useToast()
  const load = () => api.get("/admin/products", { params: { page: 0, size: 500 } }).then((response) => setProducts(response.data.content || response.data.data || response.data || []))
  useEffect(() => { load() }, [])
  const edit = (product) => { setEditing(product); setPrices({ currentPrice: Number(product.basePrice ?? product.base_price ?? 0), originalPrice: product.originalPrice ?? product.original_price ?? "" }) }
  const save = async () => {
    try {
      await api.patch(`/admin/products/${editing.id}/sale-price`, { currentPrice: Number(prices.currentPrice), originalPrice: prices.originalPrice === "" ? null : Number(prices.originalPrice) })
      setEditing(null); load(); toast.show("Đã cập nhật giá khuyến mãi", "success")
    } catch (error) { toast.show(error.response?.data?.message || "Không thể cập nhật giá", "error") }
  }
  return (
    <Box><AdminHeading title="Giá khuyến mãi" subtitle="Thiết lập giá bán và giá gốc hiển thị." />
      <TableContainer component={Paper} variant="outlined"><Table><TableHead><TableRow><TableCell>Sản phẩm</TableCell><TableCell>Thương hiệu</TableCell><TableCell>Giá bán</TableCell><TableCell>Giá gốc</TableCell><TableCell /></TableRow></TableHead><TableBody>{products.map((product) => <TableRow key={product.id}><TableCell><Typography fontWeight={800}>{product.name}</Typography></TableCell><TableCell>{product.brand}</TableCell><TableCell>{money.format(Number(product.basePrice ?? product.base_price ?? 0))}</TableCell><TableCell>{product.originalPrice || product.original_price ? money.format(Number(product.originalPrice ?? product.original_price)) : "-"}</TableCell><TableCell align="right"><Button onClick={() => edit(product)}><EditOutlinedIcon /></Button></TableCell></TableRow>)}</TableBody></Table></TableContainer>
      <Dialog open={!!editing} onClose={() => setEditing(null)} fullWidth maxWidth="xs"><DialogTitle>Thiết lập giá</DialogTitle><DialogContent><Stack spacing={2} sx={{ mt: 1 }}><Typography fontWeight={900}>{editing?.name}</Typography><TextField label="Giá hiện tại" type="number" value={prices.currentPrice} onChange={(event) => setPrices({ ...prices, currentPrice: event.target.value })} /><TextField label="Giá bị gạch" type="number" value={prices.originalPrice} onChange={(event) => setPrices({ ...prices, originalPrice: event.target.value })} /></Stack></DialogContent><DialogActions><Button onClick={() => setEditing(null)}>Hủy</Button><Button variant="contained" onClick={save}>Lưu</Button></DialogActions></Dialog>
    </Box>
  )
}

export function AdminOrderDetail() {
  const { id } = useParams()
  const [order, setOrder] = useState(null)
  const [error, setError] = useState("")
  const load = () => api.get(`/admin/orders/${id}`).then((response) => setOrder(response.data)).catch((requestError) => setError(requestError.response?.data?.message || "Không thể tải đơn hàng"))
  useEffect(() => { load() }, [id])
  const updateStatus = async (status) => { const response = await api.patch(`/admin/orders/${id}/status`, { status }); setOrder(response.data) }
  if (error) return <Alert severity="error">{error}</Alert>
  if (!order) return <Typography>Đang tải...</Typography>
  return (
    <Box><AdminHeading title={`Đơn hàng #${order.id}`} subtitle={`${order.customerName} • ${order.customerEmail || ""}`} action={<Button component={Link} to="/admin/orders">Danh sách đơn</Button>} />
      <Paper variant="outlined" sx={{ p: 3, borderRadius: 2 }}>
        <Stack direction={{ xs: "column", md: "row" }} spacing={2} justifyContent="space-between"><Box><Typography color="text.secondary">Người nhận</Typography><Typography fontWeight={900}>{order.recipientName || order.customerName}</Typography><Typography>{order.recipientPhone}</Typography><Typography>{order.orderAddress}</Typography></Box><Box sx={{ minWidth: 220 }}><TextField select fullWidth label="Trạng thái" value={order.status} onChange={(event) => updateStatus(event.target.value)}>{["PENDING", "PAID", "SHIPPING", "DONE", "CANCELLED"].map((status) => <MenuItem key={status} value={status}>{status}</MenuItem>)}</TextField><Typography variant="h5" fontWeight={900} sx={{ mt: 2 }}>{money.format(Number(order.total))}</Typography></Box></Stack>
        <TableContainer sx={{ mt: 3 }}><Table size="small"><TableHead><TableRow><TableCell>Sản phẩm</TableCell><TableCell>Biến thể</TableCell><TableCell>SL</TableCell><TableCell>Thành tiền</TableCell></TableRow></TableHead><TableBody>{(order.items || []).map((item, index) => <TableRow key={index}><TableCell>{item.productName}</TableCell><TableCell>{item.variantSize} / {item.variantColor}</TableCell><TableCell>{item.quantity}</TableCell><TableCell>{money.format(Number(item.subTotal))}</TableCell></TableRow>)}</TableBody></Table></TableContainer>
      </Paper>
    </Box>
  )
}

export function AdminMonitoring() {
  const grafana = (import.meta.env.VITE_GRAFANA_URL || "http://localhost:3001").replace(/\/$/, "")
  const [summary, setSummary] = useState(null)
  const [logQuery, setLogQuery] = useState('{app="giayohoho"}')
  const [logResults, setLogResults] = useState([])
  const [error, setError] = useState("")
  const links = [
    ["Application logs", `${grafana}/d/ohgiay-loki-logs/ohgiay-loki-logs`, 'app="giayohoho"'],
    ["System overview", `${grafana}/d/ohgiay-system-overview/ohgiay-system-overview`, "CPU, RAM, disk"],
    ["Loki Explore", `${grafana}/explore`, '{app="giayohoho"}'],
    ["Alert rules", `${grafana}/alerting/list`, "Grafana alerting"],
  ]

  useEffect(() => {
    api.get("/admin/monitoring/summary")
      .then((response) => setSummary(response.data))
      .catch((requestError) => setError(requestError.response?.data?.message || "Không thể tải monitoring summary"))
  }, [])

  const runLogQuery = async () => {
    try {
      const response = await api.post("/admin/monitoring/logql", { query: logQuery })
      setLogResults(response.data.results || [])
    } catch (requestError) {
      setError(requestError.response?.data?.message || "Không thể chạy LogQL")
    }
  }

  return (
    <Box><AdminHeading title="Monitoring" subtitle="Theo dõi log, mail outbox, PayOS và GHN." />
      {error && <Alert severity="warning" sx={{ mb: 2 }}>{error}</Alert>}
      {summary && (
        <Grid container spacing={2} sx={{ mb: 2 }}>
          {(summary.servicesStatus || []).map((service) => <Grid item xs={12} sm={4} key={service.name}><Paper variant="outlined" sx={{ p: 2, borderRadius: 2 }}><Typography fontWeight={900}>{service.name}</Typography><Chip size="small" color={service.status === "operational" ? "success" : service.status === "degraded" ? "warning" : "default"} label={service.status} sx={{ mt: 1 }} /><Typography variant="caption" display="block" color="text.secondary" sx={{ mt: 1 }}>Uptime score: {service.uptime}%</Typography></Paper></Grid>)}
          {(summary.warnings || []).length > 0 && <Grid item xs={12}><Alert severity="info">{summary.warnings.join(" | ")}</Alert></Grid>}
        </Grid>
      )}
      <Grid container spacing={2}>{links.map(([title, href, text]) => <Grid item xs={12} sm={6} key={title}><Paper variant="outlined" sx={{ p: 3, height: "100%", borderRadius: 2 }}><Typography variant="h6" fontWeight={900}>{title}</Typography><Typography color="text.secondary" sx={{ my: 1 }}>{text}</Typography><Button href={href} target="_blank" endIcon={<OpenInNewIcon />}>Mở Grafana</Button></Paper></Grid>)}</Grid>
      <Paper variant="outlined" sx={{ p: 3, mt: 3, borderRadius: 2 }}><Typography variant="h6" fontWeight={900}>LogQL</Typography><Stack spacing={2} sx={{ mt: 2 }}><TextField label="Truy vấn LogQL" value={logQuery} onChange={(event) => setLogQuery(event.target.value)} fullWidth /><Button variant="contained" onClick={runLogQuery}>Chạy truy vấn</Button>{['{app="giayohoho"}', '{app="giayohoho"} |~ "(?i)(payment|payos)"', '{app="giayohoho"} |~ "(?i)(ghn|shipping)"', '{app="giayohoho"} |~ "(?i)(mail_outbox|smtp)"'].map((query) => <Box component="code" key={query} onClick={() => setLogQuery(query)} sx={{ bgcolor: "grey.100", p: 1.5, borderRadius: 1, overflowWrap: "anywhere", cursor: "pointer" }}>{query}</Box>)}{logResults.map((item, index) => <Paper key={`${item.timestamp}-${index}`} variant="outlined" sx={{ p: 1.5 }}><Typography variant="caption" color="text.secondary">{item.timestamp} • {item.service}</Typography><Typography sx={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>{item.message}</Typography></Paper>)}</Stack></Paper>
    </Box>
  )
}
