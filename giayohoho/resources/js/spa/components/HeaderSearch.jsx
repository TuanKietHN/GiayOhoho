import { useEffect, useMemo, useRef, useState } from "react"
import { useNavigate } from "react-router-dom"
import api from "../api"
import Box from "@mui/material/Box"
import CircularProgress from "@mui/material/CircularProgress"
import IconButton from "@mui/material/IconButton"
import InputAdornment from "@mui/material/InputAdornment"
import Paper from "@mui/material/Paper"
import Stack from "@mui/material/Stack"
import TextField from "@mui/material/TextField"
import Typography from "@mui/material/Typography"
import SearchIcon from "@mui/icons-material/Search"

const money = new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" })

function itemImage(item) {
  return item.thumbnailUrl
    || item.thumbnail_url
    || `/api/products/media/${encodeURIComponent(item.slug || "shoe")}/side.svg`
}

export default function HeaderSearch() {
  const [query, setQuery] = useState("")
  const [items, setItems] = useState([])
  const [total, setTotal] = useState(0)
  const [loading, setLoading] = useState(false)
  const [focused, setFocused] = useState(false)
  const [error, setError] = useState("")
  const navigate = useNavigate()
  const blurTimer = useRef(null)
  const normalizedQuery = useMemo(() => query.trim(), [query])

  useEffect(() => {
    if (normalizedQuery.length < 2) {
      setItems([])
      setTotal(0)
      setError("")
      return
    }

    const controller = new AbortController()
    const timeout = window.setTimeout(async () => {
      setLoading(true)
      setError("")
      try {
        const response = await api.get("/products/search-preview", {
          params: { q: normalizedQuery, limit: 6 },
          signal: controller.signal,
        })
        setItems(response.data.items || [])
        setTotal(Number(response.data.total || 0))
      } catch (requestError) {
        if (requestError.name !== "CanceledError") {
          setError("Không tải được gợi ý")
        }
      } finally {
        setLoading(false)
      }
    }, 250)

    return () => {
      controller.abort()
      window.clearTimeout(timeout)
    }
  }, [normalizedQuery])

  const submit = () => {
    if (!normalizedQuery) return
    setFocused(false)
    navigate(`/products?q=${encodeURIComponent(normalizedQuery)}`)
  }

  const openProduct = (item) => {
    setFocused(false)
    setQuery("")
    navigate(`/products/${item.slug || item.id}`)
  }

  return (
    <Box sx={{ position: "relative", width: { xs: 220, md: 360 }, flexShrink: 0 }}>
      <TextField
        size="small"
        fullWidth
        value={query}
        placeholder="Tìm giày, SKU, màu..."
        onFocus={() => setFocused(true)}
        onBlur={() => {
          blurTimer.current = window.setTimeout(() => setFocused(false), 160)
        }}
        onChange={(event) => setQuery(event.target.value)}
        onKeyDown={(event) => {
          if (event.key === "Enter") submit()
        }}
        InputProps={{
          startAdornment: (
            <InputAdornment position="start">
              {loading ? <CircularProgress size={16} /> : <SearchIcon fontSize="small" />}
            </InputAdornment>
          ),
          endAdornment: (
            <InputAdornment position="end">
              <IconButton size="small" onMouseDown={(event) => event.preventDefault()} onClick={submit} aria-label="Tìm kiếm">
                <SearchIcon fontSize="small" />
              </IconButton>
            </InputAdornment>
          ),
        }}
        sx={{
          bgcolor: "background.paper",
          borderRadius: 1,
          "& .MuiOutlinedInput-root": { borderRadius: 1 },
        }}
      />

      {focused && normalizedQuery.length >= 2 && (
        <Paper
          elevation={8}
          sx={{
            position: "absolute",
            top: "calc(100% + 8px)",
            left: 0,
            right: 0,
            zIndex: 20,
            overflow: "hidden",
            borderRadius: 1,
          }}
          onMouseDown={(event) => {
            event.preventDefault()
            if (blurTimer.current) window.clearTimeout(blurTimer.current)
          }}
        >
          {error ? (
            <Typography color="error" sx={{ p: 2 }}>{error}</Typography>
          ) : items.length === 0 && !loading ? (
            <Typography color="text.secondary" sx={{ p: 2 }}>Không có gợi ý phù hợp.</Typography>
          ) : (
            <Stack>
              {items.map((item) => (
                <Stack
                  key={`${item.id}-${item.variantId || "product"}`}
                  direction="row"
                  spacing={1.5}
                  onClick={() => openProduct(item)}
                  sx={{
                    p: 1.25,
                    cursor: "pointer",
                    alignItems: "center",
                    borderBottom: "1px solid",
                    borderColor: "divider",
                    "&:hover": { bgcolor: "action.hover" },
                  }}
                >
                  <Box
                    component="img"
                    src={itemImage(item)}
                    alt={item.name}
                    sx={{ width: 48, height: 48, objectFit: "cover", borderRadius: 1, bgcolor: "grey.100" }}
                  />
                  <Box sx={{ minWidth: 0, flex: 1 }}>
                    <Typography fontWeight={800} noWrap>{item.name}</Typography>
                    <Typography variant="caption" color="text.secondary" noWrap>
                      {[item.brand, item.sku, item.size, item.color].filter(Boolean).join(" • ")}
                    </Typography>
                    <Typography variant="body2" color="primary" fontWeight={800}>
                      {money.format(Number(item.finalPrice ?? item.final_price ?? item.basePrice ?? item.base_price ?? 0))}
                    </Typography>
                  </Box>
                </Stack>
              ))}
              {total > items.length && (
                <Box onClick={submit} sx={{ p: 1.25, cursor: "pointer", bgcolor: "grey.50", "&:hover": { bgcolor: "action.hover" } }}>
                  <Typography variant="body2" fontWeight={800}>Xem tất cả {total} kết quả</Typography>
                </Box>
              )}
            </Stack>
          )}
        </Paper>
      )}
    </Box>
  )
}
