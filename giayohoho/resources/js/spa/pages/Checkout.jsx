import React, { useEffect, useState } from 'react'
import api from '../api'
import { useToast } from '../ui/toast.jsx'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Typography from '@mui/material/Typography'
import Button from '@mui/material/Button'
import TextField from '@mui/material/TextField'
import Select from '@mui/material/Select'
import MenuItem from '@mui/material/MenuItem'

export default function Checkout() {
  const [order_address, setAddr] = useState('')
  const [payment_method, setPay] = useState('COD')
  const [msg, setMsg] = useState('')
  const toast = useToast()
  const [total, setTotal] = useState(0)
  useEffect(() => { api.get('/auth/cart').then(res => setTotal(Number(res.data.total) || 0)).catch(() => {}) }, [])

  const place = async () => {
    setMsg('')
    try {
      const res = await api.post('/auth/checkout', { order_address, payment_method })
      setMsg('Đã đặt hàng #' + res.data.id)
      toast?.show('Đặt hàng thành công', 'success')
      if (payment_method === 'SePay') {
        const amount = Math.max(0, Number(res.data.total) || 0)
        window.location.href = `/sepay/checkout?amount=${amount}&invoice=INV_${res.data.id}&desc=Thanh+toan+don+hang`
      }
    } catch (e) {
      const m = e?.response?.data?.message || 'Đặt hàng thất bại'
      setMsg(m)
      toast?.show(m, 'error')
    }
  }

  return (
    <div>
      <Typography variant="h5" sx={{ mb: 2 }}>Checkout</Typography>
      {msg && <Typography>{msg}</Typography>}
      <Card>
        <CardContent>
          <TextField fullWidth label="Địa chỉ giao hàng" value={order_address} onChange={e => setAddr(e.target.value)} />
          <Select fullWidth value={payment_method} onChange={e => setPay(e.target.value)} sx={{ mt: 2 }}>
            <MenuItem value="COD">COD</MenuItem>
            <MenuItem value="SePay">SePay</MenuItem>
          </Select>
          <Typography sx={{ mt: 2 }}>Tổng thanh toán: {new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND' }).format(total)}</Typography>
          <div style={{ marginTop: 12, display:'flex', gap:8 }}>
            <Button onClick={place} variant="contained" disabled={!order_address?.trim()}>Đặt hàng</Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
