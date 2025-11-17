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

export default function StaffProducts() {
  const { t } = useI18n()
  const [data, setData] = useState({ data: [] })
  const [form, setForm] = useState({ name: '', slug: '', brand: '', gender: 'unisex', base_price: 0 })
  const [editOpen, setEditOpen] = useState(false)
  const [editItem, setEditItem] = useState(null)
  const [bulkOpen, setBulkOpen] = useState(false)
  const [bulk, setBulk] = useState({ category_id: '', filters: { brand: '', gender: '', min_price: '', max_price: '' } })
  
  const load = async () => {
    const res = await api.get('/admin/products')
    setData(res.data)
  }
  
  useEffect(() => { load() }, [])
  
  const create = async () => {
    await api.post('/admin/products', form)
    setForm({ name: '', slug: '', brand: '', gender: 'unisex', base_price: 0 })
    load()
  }
  
  const openEdit = (p) => { setEditItem(p); setEditOpen(true) }
  
  const saveEdit = async () => {
    if (!editItem) return
    const { id, name, slug, brand, gender, base_price, description } = editItem
    await api.put(`/admin/products/${id}`, { name, slug, brand, gender, base_price, description })
    setEditOpen(false)
    setEditItem(null)
    load()
  }
  
  const remove = async (id) => { await api.delete(`/admin/products/${id}`); load() }
  
  return (
    <div>
      <Typography variant="h5" sx={{ mb: 2 }}>{t('products')}</Typography>
      <Grid container spacing={2} sx={{ mb: 2 }}>
        <Grid item xs={12} md={2}>
          <TextField size="small" fullWidth placeholder={t('name')} value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} />
        </Grid>
        <Grid item xs={12} md={2}>
          <TextField size="small" fullWidth placeholder="Slug" value={form.slug} onChange={e => setForm({ ...form, slug: e.target.value })} />
        </Grid>
        <Grid item xs={12} md={2}>
          <TextField size="small" fullWidth placeholder="Brand" value={form.brand} onChange={e => setForm({ ...form, brand: e.target.value })} />
        </Grid>
        <Grid item xs={12} md={2}>
          <Select size="small" fullWidth value={form.gender} onChange={e => setForm({ ...form, gender: e.target.value })}>
            <MenuItem value="male">{t('male')}</MenuItem>
            <MenuItem value="female">{t('female')}</MenuItem>
            <MenuItem value="unisex">{t('unisex')}</MenuItem>
          </Select>
        </Grid>
        <Grid item xs={12} md={2}>
          <TextField size="small" fullWidth type="number" placeholder={t('price')} value={form.base_price} onChange={e => setForm({ ...form, base_price: Number(e.target.value) })} />
        </Grid>
        <Grid item xs={12} md={1}>
          <Button size="small" variant="contained" onClick={create}>{t('create')}</Button>
        </Grid>
        <Grid item xs={12} md={1}>
          <Button size="small" variant="outlined" onClick={() => setBulkOpen(true)}>{t('bulk_assign_category')}</Button>
        </Grid>
      </Grid>
      <Grid container spacing={2}>
        {(data.data || []).map(p => (
          <Grid item xs={12} md={6} lg={4} key={p.id}>
            <Card>
              <CardContent>
                <Typography variant="subtitle1">{p.name}</Typography>
                <Typography color="text.secondary">{p.brand} • {t(p.gender)}</Typography>
                <div style={{ marginTop: 8 }}>
                  <Button size="small" onClick={() => openEdit(p)}>{t('edit')}</Button>
                  <Button size="small" color="error" onClick={() => remove(p.id)} sx={{ ml: 1 }}>{t('delete')}</Button>
                </div>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      <Modal open={editOpen} title={t('edit_product')} onClose={() => setEditOpen(false)}>
        {editItem && (
          <div>
            <TextField size="small" fullWidth sx={{ mb: 1 }} value={editItem.name || ''} onChange={e => setEditItem({ ...editItem, name: e.target.value })} placeholder={t('name')} />
            <TextField size="small" fullWidth sx={{ mb: 1 }} value={editItem.slug || ''} onChange={e => setEditItem({ ...editItem, slug: e.target.value })} placeholder="Slug" />
            <TextField size="small" fullWidth sx={{ mb: 1 }} value={editItem.brand || ''} onChange={e => setEditItem({ ...editItem, brand: e.target.value })} placeholder="Brand" />
            <Select size="small" fullWidth sx={{ mb: 1 }} value={editItem.gender || 'unisex'} onChange={e => setEditItem({ ...editItem, gender: e.target.value })}>
              <MenuItem value="male">{t('male')}</MenuItem>
              <MenuItem value="female">{t('female')}</MenuItem>
              <MenuItem value="unisex">{t('unisex')}</MenuItem>
            </Select>
            <TextField size="small" fullWidth sx={{ mb: 1 }} type="number" value={editItem.base_price || 0} onChange={e => setEditItem({ ...editItem, base_price: Number(e.target.value) })} placeholder={t('price')} />
            <TextField size="small" fullWidth multiline rows={3} sx={{ mb: 1 }} value={editItem.description || ''} onChange={e => setEditItem({ ...editItem, description: e.target.value })} placeholder={t('description')} />
            <Button size="small" variant="contained" onClick={saveEdit}>{t('save')}</Button>
          </div>
        )}
      </Modal>

      <Modal open={bulkOpen} title={t('bulk_assign_category')} onClose={() => setBulkOpen(false)}>
        <div>
          <TextField size="small" fullWidth sx={{ mb: 1 }} placeholder={t('category_id')} value={bulk.category_id} onChange={e => setBulk({ ...bulk, category_id: e.target.value })} />
          <TextField size="small" fullWidth sx={{ mb: 1 }} placeholder="Brand" value={bulk.filters.brand} onChange={e => setBulk({ ...bulk, filters: { ...bulk.filters, brand: e.target.value } })} />
          <Select size="small" fullWidth sx={{ mb: 1 }} value={bulk.filters.gender} onChange={e => setBulk({ ...bulk, filters: { ...bulk.filters, gender: e.target.value } })}>
            <MenuItem value="">{t('gender')}</MenuItem>
            <MenuItem value="male">{t('male')}</MenuItem>
            <MenuItem value="female">{t('female')}</MenuItem>
            <MenuItem value="unisex">{t('unisex')}</MenuItem>
          </Select>
          <TextField size="small" fullWidth sx={{ mb: 1 }} type="number" placeholder={t('price_from')} value={bulk.filters.min_price} onChange={e => setBulk({ ...bulk, filters: { ...bulk.filters, min_price: e.target.value } })} />
          <TextField size="small" fullWidth sx={{ mb: 1 }} type="number" placeholder={t('price_to')} value={bulk.filters.max_price} onChange={e => setBulk({ ...bulk, filters: { ...bulk.filters, max_price: e.target.value } })} />
          <Button size="small" variant="contained" onClick={async () => { await api.post('/admin/products/bulk-assign-category', { category_id: Number(bulk.category_id), filters: { ...bulk.filters, brand: bulk.filters.brand ? [bulk.filters.brand] : undefined } }); setBulkOpen(false); load(); }}>{t('execute')}</Button>
        </div>
      </Modal>
    </div>
  )
}