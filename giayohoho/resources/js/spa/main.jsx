"use client"

import React, { useState, useEffect } from "react"
import { createRoot } from "react-dom/client"
import { BrowserRouter, Routes, Route, Link, Navigate } from "react-router-dom"
import Products from "./pages/Products"
import ProductDetail from "./pages/ProductDetail"
import Login from "./pages/Login"
import Register from "./pages/Register"
import Cart from "./pages/Cart"
import Checkout from "./pages/Checkout"
import Orders from "./pages/Orders"
import Wishlist from "./pages/Wishlist"
import Home from "./pages/Home"
import Sale from "./pages/Sale"
import { ForgotPassword, Profile, ResetPassword, SetupPassword, VerifyEmail } from "./pages/AccountPages"
import { Chatbot, Contact, Policies } from "./pages/SupportPages"
import { OrderDetail, PayOSReturn } from "./pages/OrderPaymentPages"
import AdminHome from "./pages/admin/AdminHome"
import AdminUsers from "./pages/admin/AdminUsers"
import AdminOrders from "./pages/admin/AdminOrders"
import AdminProducts from "./pages/admin/AdminProducts"
import AdminCategories from "./pages/admin/AdminCategories"
import AdminVariants from "./pages/admin/AdminVariants"
import AdminCoupons from "./pages/admin/AdminCoupons"
import AdminReviews from "./pages/admin/AdminReviews"
import { AdminMonitoring, AdminOrderDetail, AdminSalePrices, AdminSizeGuides } from "./pages/admin/AdminAdditionalPages"
import HeaderSearch from "./components/HeaderSearch.jsx"
import { ToastProvider } from "./ui/toast.jsx"
import api, { clearAuthPayload } from "./api"
import "../../css/styles.css"
import AppTheme from "./ui/theme.jsx"
import AppBar from "@mui/material/AppBar"
import Toolbar from "@mui/material/Toolbar"
import Button from "@mui/material/Button"
import Container from "@mui/material/Container"

function hasRole(me, roleName) {
  return (me?.roles || []).some((role) => {
    const name = typeof role === "string" ? role : role?.name
    return String(name || "").toUpperCase() === roleName.toUpperCase()
  })
}

function Nav({ me }) {
  const logout = async () => {
    try {
      await api.post("/auth/logout")
    } catch {}
    clearAuthPayload()
    window.location.href = "/"
  }

  return (
    <AppBar position="static">
      <Toolbar sx={{ gap: 0.5, overflowX: "auto" }}>
        <Button color="inherit" component={Link} to="/">GiàyOhoho</Button>
        <HeaderSearch />
        <div style={{ flex: 1 }} />
        <Button color="inherit" component={Link} to="/products">Sản phẩm</Button>
        <Button color="inherit" component={Link} to="/sale">Khuyến mãi</Button>
        <Button color="inherit" component={Link} to="/policies">Chính sách</Button>
        <Button color="inherit" component={Link} to="/contact">Liên hệ</Button>
        <Button color="inherit" component={Link} to="/ai-chatbot">Tư vấn</Button>
        <Button color="inherit" component={Link} to="/cart">Giỏ hàng</Button>
        <Button color="inherit" component={Link} to="/orders">Đơn hàng</Button>
        <Button color="inherit" component={Link} to="/wishlist">Yêu thích</Button>
        {me && <Button color="inherit" component={Link} to="/profile">Hồ sơ</Button>}
        {hasRole(me, "ADMIN") && (
          <Button color="inherit" component={Link} to="/admin">Admin</Button>
        )}
        {me ? (
          <Button color="inherit" onClick={logout}>Đăng xuất</Button>
        ) : (
          <>
            <Button color="inherit" component={Link} to="/login">Đăng nhập</Button>
            <Button color="inherit" component={Link} to="/register">Đăng ký</Button>
          </>
        )}
      </Toolbar>
    </AppBar>
  )
}

function AuthRoute({ me, loading, children }) {
  const token = localStorage.getItem("token")
  if (loading && token) {
    return <div style={{ textAlign: "center", padding: "var(--spacing-2xl)" }}><div className="spinner" /></div>
  }
  if (!token || !me) return <Navigate to="/login" replace />
  if (me.requiresPasswordSetup && window.location.pathname !== "/setup-password") {
    return <Navigate to="/setup-password" replace />
  }
  return children
}

function AdminRoute({ me, loading, children }) {
  const token = localStorage.getItem("token")
  if (loading && token) {
    return <div style={{ textAlign: "center", padding: "var(--spacing-2xl)" }}><div className="spinner" /></div>
  }
  if (!hasRole(me, "ADMIN")) return <Navigate to="/" replace />
  if (me?.requiresPasswordSetup) return <Navigate to="/setup-password" replace />
  return children
}

function App() {
  const [me, setMe] = useState(null)
  const [meLoading, setMeLoading] = useState(true)
  useEffect(() => {
    api
      .get("/auth/me")
      .then((res) => setMe(res.data))
      .catch(() => setMe(null))
      .finally(() => setMeLoading(false))
  }, [])
  return (
    <AppTheme>
    <ToastProvider>
      <BrowserRouter>
        <Nav me={me} />
        <main style={{ minHeight: "100vh" }}>
          <Container maxWidth="lg" sx={{ py: 4 }}>
            <Routes>
              <Route path="/" element={<Home />} />
              <Route path="/products" element={<Products />} />
              <Route path="/products/:id" element={<ProductDetail />} />
              <Route path="/san-pham" element={<Navigate to="/products" replace />} />
              <Route path="/sale" element={<Sale />} />
              <Route path="/khuyen-mai" element={<Navigate to="/sale" replace />} />
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />
              <Route path="/forgot-password" element={<ForgotPassword />} />
              <Route path="/reset-password" element={<ResetPassword />} />
              <Route path="/verify-email" element={<VerifyEmail />} />
              <Route path="/contact" element={<Contact />} />
              <Route path="/lien-he" element={<Navigate to="/contact" replace />} />
              <Route path="/policies" element={<Policies />} />
              <Route path="/chinh-sach" element={<Navigate to="/policies" replace />} />
              <Route path="/ai-chatbot" element={<Chatbot />} />
              <Route path="/payment/payos-return" element={<PayOSReturn />} />
              <Route path="/payment/payos-cancel" element={<PayOSReturn />} />
              <Route path="/setup-password" element={<AuthRoute me={me} loading={meLoading}><SetupPassword /></AuthRoute>} />
              <Route path="/profile" element={<AuthRoute me={me} loading={meLoading}><Profile /></AuthRoute>} />
              <Route path="/ho-so" element={<Navigate to="/profile" replace />} />
              <Route path="/cart" element={<AuthRoute me={me} loading={meLoading}><Cart /></AuthRoute>} />
              <Route path="/checkout" element={<AuthRoute me={me} loading={meLoading}><Checkout /></AuthRoute>} />
              <Route path="/orders" element={<AuthRoute me={me} loading={meLoading}><Orders /></AuthRoute>} />
              <Route path="/orders/:id" element={<AuthRoute me={me} loading={meLoading}><OrderDetail /></AuthRoute>} />
              <Route path="/wishlist" element={<AuthRoute me={me} loading={meLoading}><Wishlist /></AuthRoute>} />
              <Route path="/admin" element={<AdminRoute me={me} loading={meLoading}><AdminHome /></AdminRoute>} />
              <Route path="/admin/users" element={<AdminRoute me={me} loading={meLoading}><AdminUsers /></AdminRoute>} />
              <Route path="/admin/orders" element={<AdminRoute me={me} loading={meLoading}><AdminOrders /></AdminRoute>} />
              <Route path="/admin/products" element={<AdminRoute me={me} loading={meLoading}><AdminProducts /></AdminRoute>} />
              <Route path="/admin/categories" element={<AdminRoute me={me} loading={meLoading}><AdminCategories /></AdminRoute>} />
              <Route path="/admin/variants" element={<AdminRoute me={me} loading={meLoading}><AdminVariants /></AdminRoute>} />
              <Route path="/admin/coupons" element={<AdminRoute me={me} loading={meLoading}><AdminCoupons /></AdminRoute>} />
              <Route path="/admin/reviews" element={<AdminRoute me={me} loading={meLoading}><AdminReviews /></AdminRoute>} />
              <Route path="/admin/size-guides" element={<AdminRoute me={me} loading={meLoading}><AdminSizeGuides /></AdminRoute>} />
              <Route path="/admin/huong-dan-kich-thuoc" element={<Navigate to="/admin/size-guides" replace />} />
              <Route path="/admin/gia-khuyen-mai" element={<AdminRoute me={me} loading={meLoading}><AdminSalePrices /></AdminRoute>} />
              <Route path="/admin/sale-prices" element={<Navigate to="/admin/gia-khuyen-mai" replace />} />
              <Route path="/admin/orders/:id" element={<AdminRoute me={me} loading={meLoading}><AdminOrderDetail /></AdminRoute>} />
              <Route path="/admin/monitoring" element={<AdminRoute me={me} loading={meLoading}><AdminMonitoring /></AdminRoute>} />
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </Container>
        </main>
        <footer style={{ padding: 16, textAlign: 'center' }}>© 2025 GiàyOhoho</footer>
      </BrowserRouter>
    </ToastProvider>
    </AppTheme>
  )
}

const root = createRoot(document.getElementById("app"))
root.render(<App />)
