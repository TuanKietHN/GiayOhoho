import React, { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import api from '../api'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Typography from '@mui/material/Typography'

export default function OrderDetail() {
  const { id } = useParams()
  const [order, setOrder] = useState(null)
  useEffect(() => { api.get(`/auth/orders/${id}`).then(res => setOrder(res.data)) }, [id])
  if (!order) return <div style={{ textAlign:'center', padding:24 }}><div className='spinner' /></div>
  return (
    <div>
      <Typography variant='h5' sx={{ mb:2 }}>Chi tiết đơn #{order.id}</Typography>
      <Card>
        <CardContent>
          <Typography>Trạng thái: {({pending:'đang chờ giao hàng',paid:'đã thanh toán',shipping:'đang giao',done:'hoàn tất',cancel:'đã hủy'})[order.status] || order.status}</Typography>
          <Typography>Tổng: {new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(order.total)}</Typography>
        </CardContent>
      </Card>
    </div>
  )
}