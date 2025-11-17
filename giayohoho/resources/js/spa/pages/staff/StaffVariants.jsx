import React, { useEffect, useState } from 'react'
import api from '../../api'
import Modal from '../../ui/modal.jsx'
import Typography from '@mui/material/Typography'
import TextField from '@mui/material/TextField'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Grid from '@mui/material/Grid'
import { useI18n } from '../../ui/i18n.jsx'

export default function StaffVariants() {
  const { t } = useI18n()
  const [low, setLow] = useState([])
  const [threshold, setThreshold] = useState(5)
  const [editOpen, setEditOpen] = useState(false)
  const [editItem, setEditItem] = useState(null)
  
  const load = async () => {
    const res = await api.get('/admin/variants/low-stock', { params: { threshold } })
    setLow(res.data)
  }
  
  useEffect(() => { load() }, [])
  
  const adjust = async (id, delta) => { 
    await api.post(`/admin/variants/${id}/adjust-stock`, { delta }) 
    load() 
  }
  
  return (
    <div>
      <Typography variant="h5" sx={{ mb: 2 }}>{t('low_stock_warning')}</Typography>
      <Grid container spacing={2} sx={{ mb: 2 }}>
        <Grid item xs={12} md={3}>
          <TextField size="small" fullWidth type="number" value={threshold} onChange={e => setThreshold(Number(e.target.value))} label={t('threshold')} />
        </Grid>
        <Grid item xs={12} md={2}>
          <Button size="small" variant="outlined" onClick={load}>{t('filter')}</Button>
        </Grid>
      </Grid>
      
      <Grid container spacing={2}>
        {low.map(v => (
          <Grid item xs={12} md={6} lg={4} key={v.id}>
            <Card>
              <CardContent>
                <Typography variant="subtitle1">{v.product?.name}</Typography>
                <Typography color="text.secondary">{v.size} • {v.color} • {t('stock')}: {v.stock}</Typography>
                <div style={{ marginTop: 8 }}>
                  <Button size="small" onClick={() => adjust(v.id, 10)}>+10</Button>
                  <Button size="small" onClick={() => adjust(v.id, -10)} sx={{ ml: 1 }}>-10</Button>
                  <Button size="small" onClick={() => { setEditItem(v); setEditOpen(true) }} sx={{ ml: 1 }}>{t('edit')}</Button>
                </div>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      <Modal open={editOpen} title={t('edit_variant')} onClose={() => setEditOpen(false)}>
        {editItem && (
          <div>
            <TextField size="small" fullWidth sx={{ mb: 1 }} value={editItem.size || ''} onChange={e => setEditItem({ ...editItem, size: e.target.value })} placeholder={t('size')} />
            <TextField size="small" fullWidth sx={{ mb: 1 }} value={editItem.color || ''} onChange={e => setEditItem({ ...editItem, color: e.target.value })} placeholder={t('color')} />
            <TextField size="small" fullWidth sx={{ mb: 1 }} value={editItem.sku || ''} onChange={e => setEditItem({ ...editItem, sku: e.target.value })} placeholder="SKU" />
            <TextField size="small" fullWidth sx={{ mb: 1 }} type="number" value={editItem.stock || 0} onChange={e => setEditItem({ ...editItem, stock: Number(e.target.value) })} placeholder={t('stock')} />
            <TextField size="small" fullWidth sx={{ mb: 1 }} type="number" value={editItem.extra_price || 0} onChange={e => setEditItem({ ...editItem, extra_price: Number(e.target.value) })} placeholder={t('extra_price')} />
            <Button size="small" variant="contained" onClick={async () => { await api.put(`/admin/variants/${editItem.id}`, { size: editItem.size, color: editItem.color, sku: editItem.sku, stock: editItem.stock, extra_price: editItem.extra_price }); setEditOpen(false); setEditItem(null); load(); }}>{t('save')}</Button>
          </div>
        )}
      </Modal>
    </div>
  )
}