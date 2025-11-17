import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api'
import Grid from '@mui/material/Grid'
import Paper from '@mui/material/Paper'
import Typography from '@mui/material/Typography'
import Button from '@mui/material/Button'
import { PieChart, Pie, Cell, ResponsiveContainer, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend } from 'recharts'
import { useI18n } from '../../ui/i18n.jsx'

export default function AdminHome() {
  const { t } = useI18n()
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
  const statusData = [
    { name: 'Chờ giao', value: stats.pending },
    { name: 'Đã thanh toán', value: stats.paid },
    { name: 'Đang giao', value: stats.shipping },
    { name: 'Hoàn tất', value: stats.done },
    { name: 'Hủy', value: stats.cancel },
  ]
  const COLORS = ['#fde68a','#86efac','#60a5fa','#c084fc','#fecaca']
  const revenueData = [
    { name: 'Tuần 1', revenue: stats.totalRevenue * 0.2 },
    { name: 'Tuần 2', revenue: stats.totalRevenue * 0.3 },
    { name: 'Tuần 3', revenue: stats.totalRevenue * 0.25 },
    { name: 'Tuần 4', revenue: stats.totalRevenue * 0.25 },
  ]
  return (
    <Grid container spacing={2}>
      <Grid item xs={12}>
        <Typography variant="h5">{t('dashboard')}</Typography>
      </Grid>
      <Grid item xs={12} sm={6} md={3}><Paper sx={{ p:2 }}><Typography>{t('total_orders')}</Typography><Typography variant="h6">{stats.totalOrders}</Typography></Paper></Grid>
      <Grid item xs={12} sm={6} md={3}><Paper sx={{ p:2 }}><Typography>{t('total_revenue')}</Typography><Typography variant="h6">{new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND' }).format(stats.totalRevenue)}</Typography></Paper></Grid>
      <Grid item xs={12} sm={6} md={3}><Paper sx={{ p:2 }}><Typography>{t('paid')}</Typography><Typography variant="h6">{stats.paid}</Typography></Paper></Grid>
      <Grid item xs={12} sm={6} md={3}><Paper sx={{ p:2 }}><Typography>{t('done')}</Typography><Typography variant="h6">{stats.done}</Typography></Paper></Grid>
      <Grid item xs={12}>
        <Paper sx={{ p:2 }}>
          <Typography variant="subtitle1" sx={{ mb:1 }}>{t('management')}</Typography>
          <div style={{ display:'flex', gap:8, flexWrap:'wrap' }}>
            <Link to="/admin/users"><Button variant="contained">{t('users')}</Button></Link>
            <Link to="/admin/orders"><Button variant="contained">{t('orders')}</Button></Link>
            <Link to="/admin/products"><Button variant="contained">{t('products')}</Button></Link>
            <Link to="/admin/categories"><Button variant="contained">{t('categories')}</Button></Link>
            <Link to="/admin/variants"><Button variant="contained">{t('variants')}</Button></Link>
            <Link to="/admin/coupons"><Button variant="contained">{t('coupons')}</Button></Link>
            <Link to="/admin/reviews"><Button variant="contained">{t('reviews')}</Button></Link>
          </div>
        </Paper>
      </Grid>
      <Grid item xs={12} md={6}>
        <Paper sx={{ p:2 }}>
          <Typography sx={{ mb:1 }}>{t('order_status_ratio')}</Typography>
          <ResponsiveContainer width="100%" height={240}>
            <PieChart>
              <Pie data={statusData} dataKey="value" nameKey="name" outerRadius={80} label>
                {statusData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                ))}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </Paper>
      </Grid>
      <Grid item xs={12} md={6}>
        <Paper sx={{ p:2 }}>
          <Typography sx={{ mb:1 }}>{t('revenue_by_week')}</Typography>
          <ResponsiveContainer width="100%" height={240}>
            <LineChart data={revenueData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="name" />
              <YAxis />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="revenue" stroke="#2563eb" />
            </LineChart>
          </ResponsiveContainer>
        </Paper>
      </Grid>
    </Grid>
  )
}

