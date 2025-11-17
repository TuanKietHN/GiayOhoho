"use client"

import React, { useState, useEffect } from "react"
import { createRoot } from "react-dom/client"
import { BrowserRouter, Routes, Route, Link, Navigate, useLocation } from "react-router-dom"
import Products from "./pages/Products"
import ProductDetail from "./pages/ProductDetail"
import Login from "./pages/Login"
import Register from "./pages/Register"
import Cart from "./pages/Cart"
import Checkout from "./pages/Checkout"
import Orders from "./pages/Orders"
import OrderDetail from "./pages/OrderDetail"
import Wishlist from "./pages/Wishlist"
import AdminHome from "./pages/admin/AdminHome"
import AdminUsers from "./pages/admin/AdminUsers"
import AdminOrders from "./pages/admin/AdminOrders"
import AdminOrderDetail from "./pages/admin/AdminOrderDetail"
import AdminProducts from "./pages/admin/AdminProducts"
import AdminCategories from "./pages/admin/AdminCategories"
import AdminVariants from "./pages/admin/AdminVariants"
import AdminCoupons from "./pages/admin/AdminCoupons"
import AdminReviews from "./pages/admin/AdminReviews"
import StaffHome from "./pages/staff/StaffHome"
import StaffOrders from "./pages/staff/StaffOrders"
import StaffProducts from "./pages/staff/StaffProducts"
import StaffCategories from "./pages/staff/StaffCategories"
import StaffVariants from "./pages/staff/StaffVariants"
import StaffCoupons from "./pages/staff/StaffCoupons"
import StaffReviews from "./pages/staff/StaffReviews"
import AdminLayout from "./layouts/AdminLayout.jsx"
import StaffLayout from "./layouts/StaffLayout.jsx"
import { ToastProvider } from "./ui/toast.jsx"
import api from "./api"
import "../../css/styles.css"
import AppTheme from "./ui/theme.jsx"
import AppBar from "@mui/material/AppBar"
import Toolbar from "@mui/material/Toolbar"
import Button from "@mui/material/Button"
import Container from "@mui/material/Container"
import Menu from "@mui/material/Menu"
import MenuItem from "@mui/material/MenuItem"
import { useI18n, I18nProvider } from "./ui/i18n.jsx"
import { useMode } from "./ui/theme.jsx"

function Nav({ me }) {
  const token = localStorage.getItem("token")
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const { t, setLang, lang } = useI18n()
  const { toggle } = useMode()
  const [anchorCat, setAnchorCat] = useState(null)
  const [cats, setCats] = useState([])
  useEffect(() => { fetch('/api/categories').then(r => r.json()).then(setCats).catch(()=>{}) }, [])

  const logout = async () => {
    try {
      await (await import("./api")).default.post("/auth/logout")
    } catch {}
    localStorage.removeItem("token")
    window.location.href = "/"
  }

  return (
    <AppBar position="static">
      <Toolbar>
        <Button color="inherit" component={Link} to="/">GiàyOhoho</Button>
        <div style={{ flex: 1 }} />
        <Button color="inherit" component={Link} to="/">{t('products')}</Button>
        <Button color="inherit" onClick={(e)=>setAnchorCat(e.currentTarget)}>{t('categories')}</Button>
        <Menu anchorEl={anchorCat} open={Boolean(anchorCat)} onClose={()=>setAnchorCat(null)}>
          {cats.map(c => (<MenuItem key={c.id} onClick={()=>{ setAnchorCat(null); window.location.href = `/?category_id=${c.id}` }}>{c.name}</MenuItem>))}
        </Menu>
        <Button color="inherit" component={Link} to="/cart">{t('cart')}</Button>
        <Button color="inherit" component={Link} to="/orders">{t('orders')}</Button>
        <Button color="inherit" component={Link} to="/wishlist">{t('wishlist')}</Button>
        {me?.roles?.some((r) => r.name === "admin") && (
          <Button color="inherit" component={Link} to="/admin">{t('admin')}</Button>
        )}
        {me?.roles?.some((r) => r.name === "staff") && (
          <Button color="inherit" component={Link} to="/staff">{t('staff')}</Button>
        )}
        <Button color="inherit" onClick={()=>setLang(lang==='vi'?'en':'vi')}>{lang.toUpperCase()}</Button>
        <Button color="inherit" onClick={toggle}>Theme</Button>
        {me ? (<Button color="inherit" onClick={logout}>{t('logout')}</Button>) : (<><Button color="inherit" component={Link} to="/login">{t('login')}</Button><Button color="inherit" component={Link} to="/register">{t('register')}</Button></>)}
      </Toolbar>
    </AppBar>
  )
}

function AdminRoute({ me, loading, children }) {
  const token = localStorage.getItem("token")
  if (loading && token) {
    return <div style={{ textAlign: "center", padding: "var(--spacing-2xl)" }}><div className="spinner" /></div>
  }
  if (!me?.roles?.some((r) => r.name === "admin")) return <Navigate to="/" replace />
  return children
}

function StaffRoute({ me, loading, children }) {
  const token = localStorage.getItem("token")
  if (loading && token) {
    return <div style={{ textAlign: "center", padding: "var(--spacing-2xl)" }}><div className="spinner" /></div>
  }
  if (!me?.roles?.some((r) => r.name === "staff" || r.name === "admin")) return <Navigate to="/" replace />
  return children
}

function App() {
  const [me, setMe] = useState(null)
  const [meLoading, setMeLoading] = useState(true)
  const location = useLocation()
  useEffect(() => {
    api
      .get("/auth/me")
      .then((res) => setMe(res.data))
      .catch(() => setMe(null))
      .finally(() => setMeLoading(false))
  }, [])
  return (
    <AppTheme>
    <I18nProvider>
    <ToastProvider>
        {!(location.pathname.startsWith('/admin') || location.pathname.startsWith('/staff')) && <Nav me={me} />}
        <main style={{ minHeight: "100vh" }}>
          <Container maxWidth="lg" sx={{ py: 4 }}>
            <Routes>
              <Route path="/" element={<Products />} />
              <Route path="/products/:id" element={<ProductDetail />} />
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />
              <Route path="/cart" element={<Cart />} />
              <Route path="/checkout" element={<Checkout />} />
              <Route path="/orders" element={<Orders />} />
              <Route path="/orders/:id" element={<OrderDetail />} />
              <Route path="/wishlist" element={<Wishlist />} />
              <Route path="/admin" element={<AdminRoute me={me} loading={meLoading}><AdminLayout /></AdminRoute>}>
                <Route index element={<AdminHome />} />
                <Route path="users" element={<AdminUsers />} />
                <Route path="orders" element={<AdminOrders />} />
                <Route path="orders/:id" element={<AdminOrderDetail />} />
                <Route path="products" element={<AdminProducts />} />
                <Route path="categories" element={<AdminCategories />} />
                <Route path="variants" element={<AdminVariants />} />
                <Route path="coupons" element={<AdminCoupons />} />
                <Route path="reviews" element={<AdminReviews />} />
              </Route>
              <Route path="/staff" element={<StaffRoute me={me} loading={meLoading}><StaffLayout /></StaffRoute>}>
                <Route index element={<StaffHome />} />
                <Route path="orders" element={<StaffOrders />} />
                <Route path="products" element={<StaffProducts />} />
                <Route path="categories" element={<StaffCategories />} />
                <Route path="variants" element={<StaffVariants />} />
                <Route path="coupons" element={<StaffCoupons />} />
                <Route path="reviews" element={<StaffReviews />} />
              </Route>
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </Container>
        </main>
        {!(location.pathname.startsWith('/admin') || location.pathname.startsWith('/staff')) && (
          <footer style={{ padding: 16, textAlign: 'center' }}>© 2025 GiàyOhoho</footer>
        )}
    </ToastProvider>
    </I18nProvider>
    </AppTheme>
  )
}

const root = createRoot(document.getElementById("app"))
root.render(<BrowserRouter><App /></BrowserRouter>)
