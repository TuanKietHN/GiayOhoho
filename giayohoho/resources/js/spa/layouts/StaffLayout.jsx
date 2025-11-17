import React from 'react'
import { Outlet, useNavigate, useLocation } from 'react-router-dom'
// import { useAuth } from '../hooks/useAuth.jsx'
import { useI18n } from '../ui/i18n.jsx'
import Drawer from '@mui/material/Drawer'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import AppBar from '@mui/material/AppBar'
import Toolbar from '@mui/material/Toolbar'
import Typography from '@mui/material/Typography'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'

export default function StaffLayout() {
  const { t } = useI18n()
  const nav = useNavigate()
  const { pathname } = useLocation()
  // const { user, logout } = useAuth()

  const items = [
    { label: t('dashboard'), path: '/staff' },
    { label: t('orders'), path: '/staff/orders' },
    { label: t('products'), path: '/staff/products' },
    { label: t('categories'), path: '/staff/categories' },
    { label: t('variants'), path: '/staff/variants' },
    { label: t('coupons'), path: '/staff/coupons' },
    { label: t('reviews'), path: '/staff/reviews' }
  ]

  return (
    <Box sx={{ display: 'flex', height: '100vh' }}>
      <AppBar position="fixed" sx={{ zIndex: theme => theme.zIndex.drawer + 1 }}>
        <Toolbar>
          <Typography variant="h6" sx={{ flexGrow: 1 }}>
            {t('staff')} • {user?.email}
          </Typography>
          <Button color="inherit" onClick={() => nav('/')}>
            {t('back_home')}
          </Button>
          <Button color="inherit" onClick={logout}>
            {t('logout')}
          </Button>
        </Toolbar>
      </AppBar>

      <Drawer variant='permanent' anchor='left' sx={{ '& .MuiDrawer-paper': { width: 240 } }}>
        <Toolbar />
        <List>
          {items.map(it => (
            <ListItem key={it.path} disablePadding>
              <ListItemButton selected={pathname === it.path} onClick={() => nav(it.path)}>
                <ListItemText primary={it.label} />
              </ListItemButton>
            </ListItem>
          ))}
        </List>
      </Drawer>

      <Box component="main" sx={{ flexGrow: 1, p: 3, overflow: 'auto' }}>
        <Toolbar />
        <Outlet />
      </Box>
    </Box>
  )
}