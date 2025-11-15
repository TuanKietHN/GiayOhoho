import React, { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api from '../api'
import { useToast } from '../ui/toast.jsx'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Typography from '@mui/material/Typography'
import Button from '@mui/material/Button'
import Grid from '@mui/material/Grid'

export default function Cart() {
  const [cart, setCart] = useState(null)
  const [code, setCode] = useState('')
  const nav = useNavigate()

  const toast = useToast()
  const load = async () => {
    const res = await api.get('/auth/cart')
    setCart(res.data)
  }

  useEffect(() => { load() }, [])

  const updateQty = async (id, quantity) => {
    await api.put(`/auth/cart/items/${id}`, { quantity })
    load()
    toast?.show('Đã cập nhật số lượng', 'success')
  }

  const removeItem = async (id) => {
    await api.delete(`/auth/cart/items/${id}`)
    load()
    toast?.show('Đã xoá sản phẩm khỏi giỏ', 'success')
  }

  const apply = async () => {
    try {
      await api.post('/auth/cart/apply-coupon', { code })
      load()
      toast?.show('Đã áp dụng mã giảm giá', 'success')
    } catch (e) {
      toast?.show('Mã giảm giá không hợp lệ', 'error')
    }
  }

  if (!cart) return <p>Đang tải...</p>

  return (
    <div>
      <Typography variant="h5" sx={{ mb: 2 }}>Giỏ hàng</Typography>
      <Grid container spacing={2}>
        {(cart.items || []).map(it => (
          <Grid item xs={12} key={it.id}>
            <Card>
              <CardContent>
                <Typography variant="subtitle1"><Link to={`/products/${it.variant.product.id}`}>{it.variant.product.name}</Link></Typography>
                <Typography variant="body2">{it.variant.size} • {it.variant.color}</Typography>
                <div style={{ marginTop: 8 }}>
                  <Button onClick={() => updateQty(it.id, Math.max(1, it.quantity - 1))} variant="outlined">-</Button>
                  <span style={{ margin: '0 8px' }}>{it.quantity}</span>
                  <Button onClick={() => updateQty(it.id, it.quantity + 1)} variant="outlined">+</Button>
                  <Button onClick={() => removeItem(it.id)} variant="text" sx={{ ml: 1 }}>Xoá</Button>
                </div>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>
      <div style={{ marginTop: 12 }}>
        <input placeholder="Mã giảm giá" value={code} onChange={e => setCode(e.target.value)} />
        <Button onClick={apply} sx={{ ml: 1 }} variant="contained">Áp dụng</Button>
      </div>
      <Typography sx={{ mt: 2 }}>Trước giảm: {cart.sub_total} • Giảm: {cart.discount_amount} • Tổng: {cart.total}</Typography>
      <Button onClick={() => nav('/checkout')} sx={{ mt: 1 }} variant="contained">Đặt hàng</Button>
    </div>
  )
}
