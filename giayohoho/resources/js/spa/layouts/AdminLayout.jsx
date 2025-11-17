import React from 'react'
import Drawer from '@mui/material/Drawer'
import List from '@mui/material/List'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import Box from '@mui/material/Box'
import AppBar from '@mui/material/AppBar'
import Toolbar from '@mui/material/Toolbar'
import Typography from '@mui/material/Typography'
import Button from '@mui/material/Button'
import { Link } from 'react-router-dom'
import { useI18n } from '../ui/i18n.jsx'

export default function AdminLayout({ children }) {
  const { t } = useI18n()
  return (
    <Box sx={{ display: 'flex' }}>
      <Drawer variant='permanent' anchor='left' sx={{ '& .MuiDrawer-paper': { width: 240 } }}>
        <List>
          <ListItemButton component={Link} to='/admin'><ListItemText primary={t('dashboard')} /></ListItemButton>
          <ListItemButton component={Link} to='/admin/users'><ListItemText primary={t('users')} /></ListItemButton>
          <ListItemButton component={Link} to='/admin/orders'><ListItemText primary={t('orders')} /></ListItemButton>
          <ListItemButton component={Link} to='/admin/products'><ListItemText primary={t('products')} /></ListItemButton>
          <ListItemButton component={Link} to='/admin/categories'><ListItemText primary={t('categories')} /></ListItemButton>
          <ListItemButton component={Link} to='/admin/variants'><ListItemText primary={t('variants')} /></ListItemButton>
          <ListItemButton component={Link} to='/admin/coupons'><ListItemText primary={t('coupons')} /></ListItemButton>
          <ListItemButton component={Link} to='/admin/reviews'><ListItemText primary={t('reviews')} /></ListItemButton>
        </List>
      </Drawer>
      <Box sx={{ flexGrow: 1 }}>
        <AppBar position='static'>
          <Toolbar>
            <Typography variant='h6' sx={{ flex: 1 }}>{t('admin')}</Typography>
            <Button color='inherit' component={Link} to='/'>{t('back_home')}</Button>
          </Toolbar>
        </AppBar>
        <Box component='main' sx={{ p: 3 }}>{children}</Box>
        <Box component='footer' sx={{ p: 2, textAlign: 'center' }}>© 2025 GiàyOhoho • {t('admin')}</Box>
      </Box>
    </Box>
  )
}
