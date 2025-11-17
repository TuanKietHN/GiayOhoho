import React, { useEffect, useState } from 'react'
import api from '../../api'
import Modal from '../../ui/modal.jsx'
import Typography from '@mui/material/Typography'
import TextField from '@mui/material/TextField'
import Button from '@mui/material/Button'
import Select from '@mui/material/Select'
import MenuItem from '@mui/material/MenuItem'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Grid from '@mui/material/Grid'
import { useI18n } from '../../ui/i18n.jsx'

export default function StaffCoupons() {
  const { t } = useI18n()
  const [data, setData] = useState({ data: [] })
  const [form, setForm] = useState({ code: '', discount_type: 'PERCENTAGE', discount_value: 0, start_date: new Date().toISOString(), end_date: new Date(Date.now()+86400000).toISOString() })
  const [editOpen, setEditOpen] = useState(false)
  const [editItem, setEditItem] = useState(null)
  
  const load = async () => { 
    const res = await api.get('/admin/coupons') 
    setData(res.data) 
  }
  
  useEffect(() => { load() }, [])
  
  const create = async () => { 
    await api.post('/admin/coupons', form) 
    setForm({ ...form, code: '', discount_value: 0 }) 
    load() 
  }
  
  return (
    <div>
      <Typography variant="h5" sx={{ mb: 2 }}>{t('coupons')}</Typography>
      <Grid container spacing={2} sx={{ mb: 2 }}>
        <Grid item xs={12} md={3}>
          <TextField size="small" fullWidth placeholder="Code" value={form.code} onChange={e => setForm({ ...form, code: e.target.value })} />
        </Grid>
        <Grid item xs={12} md={3}>
          <Select size="small" fullWidth value={form.discount_type} onChange={e => setForm({ ...form, discount_type: e.target.value })}>
            <MenuItem value="PERCENTAGE">{t('percentage')}</MenuItem>
            <MenuItem value="FIXED_AMOUNT">{t('fixed_amount')}</MenuItem>
          </Select>
        </Grid>
        <Grid item xs={12} md={3}>
          <TextField size="small" fullWidth type="number" placeholder={t('value')} value={form.discount_value} onChange={e => setForm({ ...form, discount_value: Number(e.target.value) })} />
        </Grid>
        <Grid item xs={12} md={2}>
          <Button size="small" variant="contained" onClick={create}>{t('create')}</Button>
        </Grid>
      </Grid>
      
      <Grid container spacing={2}>
        {(data.data || []).map(c => (
          <Grid item xs={12} md={6} lg={4} key={c.id}>
            <Card>
              <CardContent>
                <Typography variant="subtitle1">{c.code}</Typography>
                <Typography color="text.secondary">{t(c.discount_type.toLowerCase())} • {c.discount_value}</Typography>
                <div style={{ marginTop: 8 }}>
                  <Button size="small" onClick={() => { setEditItem(c); setEditOpen(true) }}>{t('edit')}</Button>
                </div>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      <Modal open={editOpen} title={t('edit_coupon')} onClose={() => setEditOpen(false)}>
        {editItem && (
          <div>
            <TextField size="small" fullWidth sx={{ mb: 1 }} value={editItem.code || ''} onChange={e => setEditItem({ ...editItem, code: e.target.value })} placeholder="Code" />
            <Select size="small" fullWidth sx={{ mb: 1 }} value={editItem.discount_type} onChange={e => setEditItem({ ...editItem, discount_type: e.target.value })}>
              <MenuItem value="PERCENTAGE">{t('percentage')}</MenuItem>
              <MenuItem value="FIXED_AMOUNT">{t('fixed_amount')}</MenuItem>
            </Select>
            <TextField size="small" fullWidth sx={{ mb: 1 }} type="number" value={editItem.discount_value || 0} onChange={e => setEditItem({ ...editItem, discount_value: Number(e.target.value) })} placeholder={t('value')} />
            <TextField size="small" fullWidth sx={{ mb: 1 }} type="datetime-local" value={editItem.start_date?.slice(0,16) || ''} onChange={e => setEditItem({ ...editItem, start_date: e.target.value })} />
            <TextField size="small" fullWidth sx={{ mb: 1 }} type="datetime-local" value={editItem.end_date?.slice(0,16) || ''} onChange={e => setEditItem({ ...editItem, end_date: e.target.value })} />
            <Button size="small" variant="contained" onClick={async () => { await api.put(`/admin/coupons/${editItem.id}`, { code: editItem.code, discount_type: editItem.discount_type, discount_value: editItem.discount_value, start_date: editItem.start_date, end_date: editItem.end_date }); setEditOpen(false); setEditItem(null); load(); }}>{t('save')}</Button>
          </div>
        )}
      </Modal>
    </div>
  )
}