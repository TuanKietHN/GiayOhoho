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

export default function StaffCategories() {
  const { t } = useI18n()
  const [items, setItems] = useState([])
  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const [editOpen, setEditOpen] = useState(false)
  const [editItem, setEditItem] = useState(null)
  
  const load = async () => {
    const res = await api.get('/admin/categories')
    setItems(res.data)
  }
  
  useEffect(() => { load() }, [])
  
  const create = async () => {
    await api.post('/admin/categories', { name, slug })
    setName(''); setSlug('')
    load()
  }
  
  const del = async (id) => { await api.delete(`/admin/categories/${id}`); load() }
  
  return (
    <div>
      <Typography variant="h5" sx={{ mb: 2 }}>{t('categories')}</Typography>
      <Grid container spacing={2} sx={{ mb: 2 }}>
        <Grid item xs={12} md={4}>
          <TextField size="small" fullWidth placeholder={t('name')} value={name} onChange={e => setName(e.target.value)} />
        </Grid>
        <Grid item xs={12} md={4}>
          <TextField size="small" fullWidth placeholder="Slug" value={slug} onChange={e => setSlug(e.target.value)} />
        </Grid>
        <Grid item xs={12} md={2}>
          <Button size="small" variant="contained" onClick={create}>{t('create')}</Button>
        </Grid>
      </Grid>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>{t('slug_description')}</Typography>
      
      <Grid container spacing={2}>
        {items.map(c => (
          <Grid item xs={12} md={6} lg={4} key={c.id}>
            <Card>
              <CardContent>
                <Typography variant="subtitle1">{c.name}</Typography>
                <Typography color="text.secondary">{c.slug}</Typography>
                <div style={{ marginTop: 8 }}>
                  <Button size="small" onClick={() => { setEditItem(c); setEditOpen(true) }}>{t('edit')}</Button>
                  <Button size="small" color="error" onClick={() => del(c.id)} sx={{ ml: 1 }}>{t('delete')}</Button>
                </div>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      <Modal open={editOpen} title={t('edit_category')} onClose={() => setEditOpen(false)}>
        {editItem && (
          <div>
            <TextField size="small" fullWidth sx={{ mb: 1 }} value={editItem.name || ''} onChange={e => setEditItem({ ...editItem, name: e.target.value })} placeholder={t('name')} />
            <TextField size="small" fullWidth sx={{ mb: 1 }} value={editItem.slug || ''} onChange={e => setEditItem({ ...editItem, slug: e.target.value })} placeholder="Slug" />
            <Button size="small" variant="contained" onClick={async () => { await api.put(`/admin/categories/${editItem.id}`, { name: editItem.name, slug: editItem.slug }); setEditOpen(false); setEditItem(null); load(); }}>{t('save')}</Button>
          </div>
        )}
      </Modal>
    </div>
  )
}