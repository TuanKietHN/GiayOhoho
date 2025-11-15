import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api'
import Grid from '@mui/material/Grid'
import Paper from '@mui/material/Paper'
import Typography from '@mui/material/Typography'
import Button from '@mui/material/Button'

export default function AdminHome() {
  const [stats, setStats] = useState({ totalOrders: 0, totalRevenue: 0, pending: 0, paid: 0, shipping: 0, done: 0, cancel: 0 })
  useEffect(() => {
    api.get('/admin/orders').then(res => {
      const items = res.data.data || []
      const totalOrders = items.length
      const totalRevenue = items.reduce((s, o) => s + (Number(o.total) || 0), 0)
      const byStatus = items.reduce((acc, o) => { acc[o.status] = (acc[o.status] || 0) + 1; return acc }, {})
      setStats({ totalOrders, totalRevenue, pending: byStatus.pending || 0, paid: byStatus.paid || 0, shipping: byStatus.shipping || 0, done: byStatus.done || 0, cancel: byStatus.cancel || 0 })
    })
  }, [])
  return (
    <Grid container spacing={2}>
      <Grid item xs={12}>
        <Typography variant="h5">Bảng điều khiển</Typography>
      </Grid>
      <Grid item xs={12} sm={6} md={3}><Paper sx={{ p:2 }}><Typography>Tổng đơn</Typography><Typography variant="h6">{stats.totalOrders}</Typography></Paper></Grid>
      <Grid item xs={12} sm={6} md={3}><Paper sx={{ p:2 }}><Typography>Doanh thu</Typography><Typography variant="h6">{new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND' }).format(stats.totalRevenue)}</Typography></Paper></Grid>
      <Grid item xs={12} sm={6} md={3}><Paper sx={{ p:2 }}><Typography>Đã thanh toán</Typography><Typography variant="h6">{stats.paid}</Typography></Paper></Grid>
      <Grid item xs={12} sm={6} md={3}><Paper sx={{ p:2 }}><Typography>Hoàn tất</Typography><Typography variant="h6">{stats.done}</Typography></Paper></Grid>
      <Grid item xs={12}>
        <Paper sx={{ p:2 }}>
          <Typography variant="subtitle1" sx={{ mb:1 }}>Danh mục quản lý</Typography>
          <div style={{ display:'flex', gap:8, flexWrap:'wrap' }}>
            <Link to="/admin/users"><Button variant="contained">Người dùng</Button></Link>
            <Link to="/admin/orders"><Button variant="contained">Đơn hàng</Button></Link>
            <Link to="/admin/products"><Button variant="contained">Sản phẩm</Button></Link>
            <Link to="/admin/categories"><Button variant="contained">Danh mục</Button></Link>
            <Link to="/admin/variants"><Button variant="contained">Biến thể</Button></Link>
            <Link to="/admin/coupons"><Button variant="contained">Coupons</Button></Link>
            <Link to="/admin/reviews"><Button variant="contained">Reviews</Button></Link>
          </div>
        </Paper>
      </Grid>
    </Grid>
  )
}

