import { Link } from "react-router-dom"
import Card from "@mui/material/Card"
import CardActionArea from "@mui/material/CardActionArea"
import CardContent from "@mui/material/CardContent"
import CardMedia from "@mui/material/CardMedia"
import Chip from "@mui/material/Chip"
import Stack from "@mui/material/Stack"
import Typography from "@mui/material/Typography"

const money = new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" })

export function productPrice(product) {
  const variants = product?.variants || []
  const prices = variants
    .filter((variant) => Number(variant.stock) > 0)
    .map((variant) => Number(variant.finalPrice ?? variant.final_price ?? 0))
    .filter((price) => price > 0)
  return prices.length ? Math.min(...prices) : Number(product?.basePrice ?? product?.base_price ?? 0)
}

export function productImage(product) {
  const images = product?.images || []
  const preferred = images.find((image) => image.primary || image.is_primary) || images[0]
  return preferred?.imageUrl
    || preferred?.image_url
    || `/api/products/media/${encodeURIComponent(product?.slug || "shoe")}/side.svg`
}

export default function ProductCard({ product }) {
  const price = productPrice(product)
  const originalPrice = Number(product?.originalPrice ?? product?.original_price ?? 0)
  const sale = originalPrice > price
  const href = `/products/${product?.slug || product?.id}`

  return (
    <Card variant="outlined" sx={{ height: "100%", borderRadius: 2 }}>
      <CardActionArea component={Link} to={href} sx={{ height: "100%", display: "flex", flexDirection: "column", alignItems: "stretch" }}>
        <CardMedia
          component="img"
          image={productImage(product)}
          alt={product?.name || "Sản phẩm giày"}
          sx={{ height: 220, objectFit: "cover", bgcolor: "grey.100" }}
        />
        <CardContent sx={{ display: "flex", flexDirection: "column", flex: 1, gap: 0.75 }}>
          <Stack direction="row" spacing={1} alignItems="center">
            <Typography variant="caption" color="text.secondary">{product?.brand || "GiayOhoho"}</Typography>
            {sale && <Chip size="small" color="error" label="Sale" />}
          </Stack>
          <Typography variant="subtitle1" fontWeight={800}>{product?.name}</Typography>
          <Typography variant="body2" color="text.secondary">{product?.gender || "Unisex"}</Typography>
          <Stack direction="row" spacing={1} alignItems="baseline" sx={{ mt: "auto" }}>
            <Typography color="primary" fontWeight={900}>{money.format(price)}</Typography>
            {sale && (
              <Typography variant="caption" color="text.secondary" sx={{ textDecoration: "line-through" }}>
                {money.format(originalPrice)}
              </Typography>
            )}
          </Stack>
        </CardContent>
      </CardActionArea>
    </Card>
  )
}
