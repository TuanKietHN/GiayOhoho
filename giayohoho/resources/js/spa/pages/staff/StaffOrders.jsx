import React, { useEffect, useState } from 'react'
import api from '../../api'
import { useToast } from '../../ui/toast.jsx'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Typography from '@mui/material/Typography'
import Grid from '@mui/material/Grid'
import Button from '@mui/material/Button'
import { useNavigate } from 'react-router-dom'
import { useI18n } from '../../ui/i18n.jsx'

export default function StaffOrders() {
  const [data, setData] = useState({ data: [] })
  const [status, setStatus] = useState('')
  const [pendingStatus, setPendingStatus] = useState({})
  const toast = useToast()
  const nav = useNavigate()
  const { t } = useI18n()
  
  const load = async () => {
    const res = await api.get('/admin/orders', { params: status ? { status } : {} })
    setData(res.data)
  }
  
  useEffect(() => { load() }, [])
  
  const saveStatus = async (id) => {
    const s = pendingStatus[id]
    if (!s) return
    try {
      await api.post(`/admin/orders/${id}/status`, { status: s })
      toast?.show(t('order_status_saved'), 'success')
      load()
    } catch (e) {
      toast?.show(t('cannot_update_completed_order'), 'error')
    }
  }
  
  return (
    <div>
      <Typography variant="h5" sx={{ mb: 2 }}>{t('orders')}</Typography>
      <select value={status} onChange={e => setStatus(e.target.value)}>
        <option value="">{t('all')}</option>
        <option value="pending">{t('pending')}</option>
        <option value="paid">{t('paid')}</option>
        <option value="shipping">{t('shipping')}</option>
        <option value="done">{t('done')}</option>
        <option value="cancel">{t('cancel')}</option>
      </select>
      <Button onClick={load} sx={{ ml: 1 }} variant="outlined" size="small">{t('filter')}</Button>
      <Grid container spacing={2} sx={{ mt: 2 }}>
        {(data.data || []).map(o => (
          <Grid item xs={12} key={o.id}>
            <Card>
              <CardContent>
                <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center' }}>
                  <div style={{ cursor:'pointer' }} onClick={()=>nav(`/staff/orders/${o.id}`)}>
                    <Typography variant='subtitle1'>#{o.id} • {t(o.status)}</Typography>
                    <Typography>{t('total')}: {new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(o.total)}</Typography>
                  </div>
                  <div>
                    <select value={pendingStatus[o.id] ?? o.status} onChange={e => setPendingStatus({ ...pendingStatus, [o.id]: e.target.value })} disabled={o.status === 'done'}>
                      <option value="pending">{t('pending')}</option>
                      <option value="paid">{t('paid')}</option>
                      <option value="shipping">{t('shipping')}</option>
                      <option value="done">{t('done')}</option>
                      <option value="cancel">{t('cancel')}</option>
                    </select>
                    <Button onClick={() => saveStatus(o.id)} sx={{ ml:1 }} variant='contained' disabled={o.status === 'done'}>{t('save')}</Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>
    </div>
  )
}