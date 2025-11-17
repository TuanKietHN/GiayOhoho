import React, { useEffect, useState } from 'react'
import api from '../../api'
import Typography from '@mui/material/Typography'
import Grid from '@mui/material/Grid'
import Paper from '@mui/material/Paper'
import { useI18n } from '../../ui/i18n.jsx'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts'

export default function StaffHome() {
  const { t } = useI18n()
  const [stats, setStats] = useState({})
  const [orders, setOrders] = useState([])

  useEffect(() => {
    api.get('/admin/stats').then(r => setStats(r.data))
    api.get('/admin/orders', { params: { limit: 5 } }).then(r => setOrders(r.data.data))
  }, [])

  const statusData = [
    { name: t('pending'), value: stats.pending_orders || 0 },
    { name: t('paid'), value: stats.paid_orders || 0 },
    { name: t('shipping'), value: stats.shipping_orders || 0 },
    { name: t('done'), value: stats.done_orders || 0 }
  ]
  const COLORS = ['#ff9800', '#2196f3', '#ffeb3b', '#4caf50']

  return (
    <div>
      <Typography variant="h5" sx={{ mb: 3 }}>{t('dashboard')}</Typography>
      
      <Grid container spacing={3}>
        <Grid item xs={12} md={3}>
          <Paper sx={{ p: 3, textAlign: 'center' }}>
            <Typography variant="h4" color="primary">{stats.total_products || 0}</Typography>
            <Typography variant="body2">{t('products')}</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={3}>
          <Paper sx={{ p: 3, textAlign: 'center' }}>
            <Typography variant="h4" color="secondary">{stats.total_orders || 0}</Typography>
            <Typography variant="body2">{t('orders')}</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={3}>
          <Paper sx={{ p: 3, textAlign: 'center' }}>
            <Typography variant="h4" color="success.main">{stats.total_users || 0}</Typography>
            <Typography variant="body2">{t('users')}</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={3}>
          <Paper sx={{ p: 3, textAlign: 'center' }}>
            <Typography variant="h4" color="warning.main">{stats.low_stock_variants || 0}</Typography>
            <Typography variant="body2">{t('low_stock_warning')}</Typography>
          </Paper>
        </Grid>

        <Grid item xs={12} md={6}>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>{t('orders_by_status')}</Typography>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={statusData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip />
                <Bar dataKey="value" fill="#8884d8" />
              </BarChart>
            </ResponsiveContainer>
          </Paper>
        </Grid>

        <Grid item xs={12} md={6}>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>{t('orders_status_distribution')}</Typography>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie data={statusData} cx="50%" cy="50%" outerRadius={100} fill="#8884d8" dataKey="value" label>
                  {statusData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </Paper>
        </Grid>

        <Grid item xs={12}>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>{t('recent_orders')}</Typography>
            {orders.map(o => (
              <Paper key={o.id} sx={{ p: 2, mb: 1 }}>
                <Typography variant="subtitle2">#{o.id} • {t(o.status)} • {new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(o.total)}</Typography>
              </Paper>
            ))}
          </Paper>
        </Grid>
      </Grid>
    </div>
  )
}