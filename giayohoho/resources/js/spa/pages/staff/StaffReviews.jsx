import React, { useEffect, useState } from 'react'
import api from '../../api'
import Typography from '@mui/material/Typography'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Grid from '@mui/material/Grid'
import Rating from '@mui/material/Rating'
import { useI18n } from '../../ui/i18n.jsx'

export default function StaffReviews() {
  const { t } = useI18n()
  const [data, setData] = useState({ data: [] })
  
  const load = async () => { 
    const res = await api.get('/admin/reviews') 
    setData(res.data) 
  }
  
  useEffect(() => { load() }, [])
  
  const remove = async (id) => { 
    await api.delete(`/admin/reviews/${id}`) 
    load() 
  }
  
  return (
    <div>
      <Typography variant="h5" sx={{ mb: 2 }}>{t('reviews')}</Typography>
      
      <Grid container spacing={2}>
        {(data.data || []).map(r => (
          <Grid item xs={12} md={6} lg={4} key={r.id}>
            <Card>
              <CardContent>
                <Typography variant="subtitle1">{r.product?.name}</Typography>
                <Typography color="text.secondary">{r.user?.email}</Typography>
                <Rating value={r.rating} readOnly size="small" sx={{ my: 1 }} />
                <Typography variant="body2">{r.comment}</Typography>
                <div style={{ marginTop: 8 }}>
                  <Button size="small" color="error" onClick={() => remove(r.id)}>{t('delete')}</Button>
                </div>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>
    </div>
  )
}