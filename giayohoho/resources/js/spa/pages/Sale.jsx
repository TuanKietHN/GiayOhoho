import { useEffect, useState } from "react"
import api from "../api"
import ProductCard, { productPrice } from "../components/ProductCard"
import Box from "@mui/material/Box"
import CircularProgress from "@mui/material/CircularProgress"
import Grid from "@mui/material/Grid"
import Typography from "@mui/material/Typography"

export default function Sale() {
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get("/products", { params: { sale: true, sort: "newest", perPage: 100 } })
      .then((response) => {
        const rows = response.data?.content || response.data?.data || response.data || []
        setProducts(rows.filter((item) => Number(item.originalPrice ?? item.original_price ?? 0) > productPrice(item)))
      })
      .finally(() => setLoading(false))
  }, [])

  return (
    <Box>
      <Typography variant="overline" color="error" fontWeight={900}>Khuyến mãi</Typography>
      <Typography variant="h3" component="h1" fontWeight={900}>Sản phẩm đang giảm giá</Typography>
      <Typography color="text.secondary" sx={{ mb: 4 }}>Các mẫu có giá gốc cao hơn giá bán hiện tại.</Typography>
      {loading ? <Box sx={{ py: 8, textAlign: "center" }}><CircularProgress /></Box> : (
        <Grid container spacing={2}>
          {products.map((product) => <Grid item xs={12} sm={6} md={3} key={product.id}><ProductCard product={product} /></Grid>)}
          {!products.length && <Grid item xs={12}><Typography color="text.secondary">Chưa có sản phẩm khuyến mãi.</Typography></Grid>}
        </Grid>
      )}
    </Box>
  )
}
