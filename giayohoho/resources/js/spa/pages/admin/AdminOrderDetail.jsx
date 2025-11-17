import React, { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import api from '../../api'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Typography from '@mui/material/Typography'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemText from '@mui/material/ListItemText'
import Divider from '@mui/material/Divider'
import Box from '@mui/material/Box'
import { useI18n } from '../../ui/i18n.jsx'

export default function AdminOrderDetail() {
  const { id } = useParams()
  const { t } = useI18n()
  const [order, setOrder] = useState(null)
  useEffect(() => { api.get(`/admin/orders/${id}`).then(res => setOrder(res.data)) }, [id])
  if (!order) return <div style={{ textAlign:'center', padding:24 }}><div className='spinner' /></div>
  const fmt = (v) => new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(v)
  const lineTotal = (it) => (Number(it.quantity) * Number(it.price))
  const computedTotal = order.items?.reduce((s,it)=> s + lineTotal(it), 0) || 0
  return (
    <div>
      <Typography variant='h5' sx={{ mb:2 }}>{t('order_details')} #{order.id}</Typography>
      <Card sx={{ mb:2 }}>
        <CardContent>
          <Typography>{t('customer')}: {order.user?.email}</Typography>
          <Typography>{t('status')}: {t(order.status)}</Typography>
          <Typography>{t('total')}: {fmt(order.total ?? computedTotal)}</Typography>
        </CardContent>
      </Card>
      <Card>
        <CardContent>
          <Typography sx={{ mb:1 }}>{t('items')}</Typography>
          <Divider />
          <List>
            {order.items?.map((it)=> (
              <ListItem key={it.id} disableGutters>
                <ListItemText
                  primary={`${t('name')}: ${it.variant?.product?.name || ''}`}
                  secondary={
                    <Box>
                      <Typography variant='body2'>{t('size')}: {it.variant?.size} • {t('color')}: {it.variant?.color}</Typography>
                      <Typography variant='body2'>{t('quantity')}: {it.quantity} • {t('total')}: {fmt(lineTotal(it))}</Typography>
                    </Box>
                  }
                />
              </ListItem>
            ))}
          </List>
        </CardContent>
      </Card>
    </div>
  )
}
