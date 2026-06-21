import React, { useEffect, useState } from 'react'
import api from '../api'
import { Link } from 'react-router-dom'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Typography from '@mui/material/Typography'
import Grid from '@mui/material/Grid'
import CardActionArea from '@mui/material/CardActionArea'

export default function Orders() {
  const [orders, setOrders] = useState([])

  useEffect(() => {
    api.get('/auth/orders').then(res => setOrders(res.data))
  }, [])

  return (
    <div>
      <Typography variant="h5" sx={{ mb: 2 }}>Đơn hàng của bạn</Typography>
      <Grid container spacing={2}>
        {orders.map(o => (
          <Grid item xs={12} md={6} key={o.id}>
            <Card variant="outlined" sx={{ borderRadius: 2 }}>
              <CardActionArea component={Link} to={`/orders/${o.id}`}>
                <CardContent>
                  <Typography variant="subtitle1" fontWeight={900}>#{o.id} • {o.status}</Typography>
                  <Typography>Tổng: {new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(o.total)}</Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>{o.createdAt ? new Date(o.createdAt).toLocaleString('vi-VN') : ''}</Typography>
                </CardContent>
              </CardActionArea>
            </Card>
          </Grid>
        ))}
      </Grid>
    </div>
  )
}
