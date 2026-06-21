import { useEffect, useState } from "react"
import { Link } from "react-router-dom"
import api from "../api"
import ProductCard from "../components/ProductCard"
import ArrowForwardIcon from "@mui/icons-material/ArrowForward"
import LocalShippingOutlinedIcon from "@mui/icons-material/LocalShippingOutlined"
import ReplayOutlinedIcon from "@mui/icons-material/ReplayOutlined"
import SecurityOutlinedIcon from "@mui/icons-material/SecurityOutlined"
import SupportAgentOutlinedIcon from "@mui/icons-material/SupportAgentOutlined"
import Box from "@mui/material/Box"
import Button from "@mui/material/Button"
import CircularProgress from "@mui/material/CircularProgress"
import Grid from "@mui/material/Grid"
import Paper from "@mui/material/Paper"
import Stack from "@mui/material/Stack"
import Typography from "@mui/material/Typography"

const heroImage = "https://images.unsplash.com/photo-1543508282-6319a3e2621f?q=80&w=1800&auto=format&fit=crop"

const categories = [
  ["Sneaker đi học", "Êm, dễ phối và bền cho lịch di chuyển hằng ngày", "https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?q=80&w=1200&auto=format&fit=crop", "sneaker"],
  ["Giày chạy bộ", "Đệm tốt, nhẹ chân và hỗ trợ tập luyện", "https://images.unsplash.com/photo-1552346154-21d32810aba3?q=80&w=1200&auto=format&fit=crop", "running"],
  ["Giày công sở", "Gọn gàng cho thuyết trình và đi làm", "https://images.unsplash.com/photo-1533867617858-e7b97e060509?q=80&w=1200&auto=format&fit=crop", "office"],
]

export default function Home() {
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get("/products", { params: { sort: "newest", perPage: 12, page: 0 } })
      .then((response) => setProducts(response.data?.content || response.data?.data || response.data || []))
      .finally(() => setLoading(false))
  }, [])

  return (
    <Box>
      <Box
        sx={{
          minHeight: { xs: 520, md: 620 },
          mx: { xs: -2, sm: -3 },
          mt: -4,
          px: { xs: 2, sm: 5, md: 8 },
          py: { xs: 8, md: 12 },
          color: "common.white",
          display: "flex",
          alignItems: "center",
          backgroundImage: `linear-gradient(90deg, rgba(17,24,39,.9), rgba(17,24,39,.35)), url(${heroImage})`,
          backgroundSize: "cover",
          backgroundPosition: "center",
        }}
      >
        <Box sx={{ maxWidth: 680 }}>
          <Typography variant="overline" sx={{ color: "warning.light", fontWeight: 900 }}>GiayOhoho</Typography>
          <Typography variant="h2" component="h1" fontWeight={900} sx={{ fontSize: { xs: "2.5rem", md: "4rem" }, lineHeight: 1.05 }}>
            Giày chính hãng cho đi học, đi làm và luyện tập
          </Typography>
          <Typography sx={{ mt: 2, fontSize: "1.08rem", color: "grey.200" }}>
            Chọn sneaker, running và giày hằng ngày theo đúng kích thước, tồn kho và ngân sách.
          </Typography>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mt: 3 }}>
            <Button component={Link} to="/products" variant="contained" color="warning" endIcon={<ArrowForwardIcon />}>Mua sắm ngay</Button>
            <Button component={Link} to="/sale" variant="outlined" sx={{ color: "white", borderColor: "white" }}>Xem khuyến mãi</Button>
          </Stack>
        </Box>
      </Box>

      <Stack direction={{ xs: "column", md: "row" }} spacing={2} sx={{ mt: 3 }}>
        {[
          ["Giảm đến 40%", "Bộ sưu tập chọn lọc", "/sale"],
          ["Giao hàng GHN", "Phí tính theo địa chỉ nhận", "/policies"],
          ["Hướng dẫn kích thước", "Xem riêng theo sản phẩm", "/products"],
        ].map(([title, text, to]) => (
          <Paper key={title} component={Link} to={to} variant="outlined" sx={{ p: 2, flex: 1, color: "inherit", textDecoration: "none", borderRadius: 2 }}>
            <Typography fontWeight={900}>{title}</Typography>
            <Typography variant="body2" color="text.secondary">{text}</Typography>
          </Paper>
        ))}
      </Stack>

      <Stack direction="row" justifyContent="space-between" alignItems="end" sx={{ mt: 6, mb: 2 }}>
        <Box>
          <Typography variant="overline" color="primary" fontWeight={900}>Sản phẩm</Typography>
          <Typography variant="h4" fontWeight={900}>Nổi bật trong cửa hàng</Typography>
        </Box>
        <Button component={Link} to="/products">Xem tất cả</Button>
      </Stack>
      {loading ? (
        <Box sx={{ py: 8, textAlign: "center" }}><CircularProgress /></Box>
      ) : (
        <Grid container spacing={2}>
          {products.slice(0, 8).map((product) => <Grid item xs={12} sm={6} md={3} key={product.id}><ProductCard product={product} /></Grid>)}
        </Grid>
      )}

      <Typography variant="h4" fontWeight={900} sx={{ mt: 7, mb: 2 }}>Mua theo nhu cầu</Typography>
      <Grid container spacing={2}>
        {categories.map(([title, text, image, q]) => (
          <Grid item xs={12} md={4} key={title}>
            <Paper
              component={Link}
              to={`/products?q=${q}`}
              sx={{ minHeight: 330, p: 3, display: "flex", alignItems: "end", color: "white", textDecoration: "none", borderRadius: 2, backgroundImage: `linear-gradient(transparent, rgba(17,24,39,.88)), url(${image})`, backgroundSize: "cover", backgroundPosition: "center" }}
            >
              <Box><Typography variant="h5" fontWeight={900}>{title}</Typography><Typography>{text}</Typography></Box>
            </Paper>
          </Grid>
        ))}
      </Grid>

      <Grid container spacing={2} sx={{ mt: 5 }}>
        {[
          [LocalShippingOutlinedIcon, "Giao nhanh", "Theo dõi trạng thái đơn rõ ràng."],
          [ReplayOutlinedIcon, "Đổi kích thước", "Hỗ trợ theo điều kiện chính sách."],
          [SecurityOutlinedIcon, "Hàng chính hãng", "Minh bạch sản phẩm và tồn kho."],
          [SupportAgentOutlinedIcon, "Tư vấn phù hợp", "Hỗ trợ chọn giày theo nhu cầu."],
        ].map(([Icon, title, text]) => (
          <Grid item xs={12} sm={6} md={3} key={title}>
            <Paper variant="outlined" sx={{ p: 2.5, height: "100%", borderRadius: 2 }}>
              <Icon color="primary" />
              <Typography fontWeight={900} sx={{ mt: 1 }}>{title}</Typography>
              <Typography variant="body2" color="text.secondary">{text}</Typography>
            </Paper>
          </Grid>
        ))}
      </Grid>
    </Box>
  )
}
