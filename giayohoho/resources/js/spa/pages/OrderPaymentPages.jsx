import { useEffect, useState } from "react"
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom"
import api from "../api"
import ArrowBackIcon from "@mui/icons-material/ArrowBack"
import Alert from "@mui/material/Alert"
import Box from "@mui/material/Box"
import Button from "@mui/material/Button"
import Chip from "@mui/material/Chip"
import CircularProgress from "@mui/material/CircularProgress"
import Divider from "@mui/material/Divider"
import List from "@mui/material/List"
import ListItem from "@mui/material/ListItem"
import ListItemAvatar from "@mui/material/ListItemAvatar"
import ListItemText from "@mui/material/ListItemText"
import Paper from "@mui/material/Paper"
import Stack from "@mui/material/Stack"
import Typography from "@mui/material/Typography"

const money = new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" })

export function OrderDetail() {
  const { id } = useParams()
  const [order, setOrder] = useState(null)
  const [error, setError] = useState("")
  const [canceling, setCanceling] = useState(false)

  const load = () => api.get(`/orders/${id}`)
    .then((response) => setOrder(response.data))
    .catch((requestError) => setError(requestError.response?.data?.message || "Không thể tải đơn hàng."))

  useEffect(() => { load() }, [id])

  const cancel = async () => {
    setCanceling(true)
    try {
      const response = await api.patch(`/orders/${id}/cancel`)
      setOrder(response.data)
    } catch (requestError) {
      setError(requestError.response?.data?.message || "Không thể hủy đơn hàng.")
    } finally {
      setCanceling(false)
    }
  }

  if (error && !order) return <Alert severity="error">{error}</Alert>
  if (!order) return <Box sx={{ py: 8, textAlign: "center" }}><CircularProgress /></Box>

  return (
    <Box sx={{ maxWidth: 980, mx: "auto" }}>
      <Button component={Link} to="/orders" startIcon={<ArrowBackIcon />}>Đơn hàng của tôi</Button>
      <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems={{ sm: "end" }} sx={{ mt: 1, mb: 3 }}>
        <Box><Typography variant="overline" color="primary" fontWeight={900}>Chi tiết đơn hàng</Typography><Typography variant="h3" fontWeight={900}>Đơn #{order.id}</Typography></Box>
        <Stack direction="row" spacing={1} alignItems="center">
          <Chip label={order.status} color={order.status === "CANCELLED" ? "error" : order.status === "DELIVERED" ? "success" : "primary"} />
          {["PENDING", "CONFIRMED"].includes(order.status) && <Button color="error" variant="outlined" disabled={canceling} onClick={cancel}>{canceling ? "Đang hủy..." : "Hủy đơn"}</Button>}
        </Stack>
      </Stack>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      <Paper variant="outlined" sx={{ p: 3, borderRadius: 2 }}>
        <Stack direction={{ xs: "column", md: "row" }} spacing={3} divider={<Divider flexItem orientation="vertical" />}>
          <Box flex={1}><Typography variant="caption" color="text.secondary">Người nhận</Typography><Typography fontWeight={900}>{order.recipientName || order.customerName || "Khách hàng"}</Typography><Typography>{order.recipientPhone || order.phone}</Typography><Typography color="text.secondary">{order.orderAddress || order.address}</Typography></Box>
          <Box flex={1}><Typography variant="caption" color="text.secondary">Thanh toán và vận chuyển</Typography><Typography fontWeight={900}>{order.paymentMethod || "COD"}</Typography><Typography>{order.shippingProvider || "GHN"}</Typography><Typography color="text.secondary">Phí giao hàng: {money.format(Number(order.shippingFee || 0))}</Typography></Box>
        </Stack>
        <Divider sx={{ my: 3 }} />
        <Typography variant="h6" fontWeight={900}>Sản phẩm</Typography>
        <List>
          {(order.items || []).map((item) => (
            <ListItem key={item.id} divider alignItems="flex-start">
              <ListItemAvatar>
                <Box component="img" src={item.imageUrl || item.productImage || `/api/products/media/${item.productSlug || "shoe"}/side.svg`} alt={item.productName} sx={{ width: 64, height: 64, objectFit: "cover", borderRadius: 1, mr: 2 }} />
              </ListItemAvatar>
              <ListItemText primary={<Typography fontWeight={900}>{item.productName || "Sản phẩm"}</Typography>} secondary={`Size ${item.size || item.variantSize || "-"} • ${item.color || item.variantColor || "-"} • SL ${item.quantity}`} />
              <Typography fontWeight={800}>{money.format(Number(item.price) * Number(item.quantity))}</Typography>
            </ListItem>
          ))}
        </List>
        <Stack spacing={1} alignItems="flex-end" sx={{ mt: 2 }}>
          <Typography>Tạm tính: {money.format(Number(order.subTotal || 0))}</Typography>
          <Typography>Giảm giá: -{money.format(Number(order.discountAmount || 0))}</Typography>
          <Typography variant="h5" fontWeight={900}>Tổng: {money.format(Number(order.total))}</Typography>
        </Stack>
      </Paper>
    </Box>
  )
}

export function PayOSReturn() {
  const [params] = useSearchParams()
  const [state, setState] = useState({ loading: true, payment: null, error: "" })
  const navigate = useNavigate()

  useEffect(() => {
    const orderCode = params.get("orderCode") || params.get("order_code")
    if (!orderCode) {
      setState({ loading: false, payment: null, error: "Thiếu mã đơn hàng PayOS." })
      return
    }
    api.post("/payments/payos/sync", { orderCode: Number(orderCode) })
      .then((response) => setState({ loading: false, payment: response.data, error: "" }))
      .catch(() => api.get("/payments/payos/return-status", { params: { orderCode } })
        .then((response) => setState({ loading: false, payment: response.data, error: "" }))
        .catch((error) => setState({ loading: false, payment: null, error: error.response?.data?.message || "Không thể kiểm tra trạng thái thanh toán." })))
  }, [params])

  return (
    <Box sx={{ minHeight: "65vh", display: "grid", placeItems: "center" }}>
      <Paper variant="outlined" sx={{ p: 4, width: "100%", maxWidth: 560, textAlign: "center", borderRadius: 2 }}>
        {state.loading && <CircularProgress />}
        {!state.loading && state.error && <Alert severity="error">{state.error}</Alert>}
        {!state.loading && state.payment && (
          <Stack spacing={2}>
            <Typography variant="h4" fontWeight={900}>Thanh toán {state.payment.status}</Typography>
            <Typography color="text.secondary">Đơn hàng #{state.payment.orderId || state.payment.orderCode}</Typography>
            <Typography variant="h5">{money.format(Number(state.payment.amount || 0))}</Typography>
            <Button variant="contained" onClick={() => navigate(`/orders/${state.payment.orderId || state.payment.orderCode}`)}>Xem đơn hàng</Button>
          </Stack>
        )}
      </Paper>
    </Box>
  )
}
