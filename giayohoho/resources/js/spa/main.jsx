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
import AdminHome from "./pages/admin/AdminHome"
import AdminUsers from "./pages/admin/AdminUsers"
import AdminOrders from "./pages/admin/AdminOrders"
import AdminProducts from "./pages/admin/AdminProducts"
import AdminCategories from "./pages/admin/AdminCategories"
import AdminVariants from "./pages/admin/AdminVariants"
import AdminCoupons from "./pages/admin/AdminCoupons"
import AdminReviews from "./pages/admin/AdminReviews"
import { ToastProvider } from "./ui/toast.jsx"
import api from "./api"
import "../../css/styles.css"
import AppTheme from "./ui/theme.jsx"
import AppBar from "@mui/material/AppBar"
import Toolbar from "@mui/material/Toolbar"
import Button from "@mui/material/Button"
import Container from "@mui/material/Container"

function Nav({ me }) {
  const token = localStorage.getItem("token")
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

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
        <Button color="inherit" component={Link} to="/">Sản phẩm</Button>
        <Button color="inherit" component={Link} to="/cart">Giỏ hàng</Button>
        <Button color="inherit" component={Link} to="/orders">Đơn hàng</Button>
        <Button color="inherit" component={Link} to="/wishlist">Yêu thích</Button>
        {me?.roles?.some((r) => r.name === "admin") && (
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

function AdminRoute({ me, loading, children }) {
  const token = localStorage.getItem("token")
  if (loading && token) {
    return <div style={{ textAlign: "center", padding: "var(--spacing-2xl)" }}><div className="spinner" /></div>
  }
  if (!me?.roles?.some((r) => r.name === "admin")) return <Navigate to="/" replace />
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
              <Route path="/" element={<Products />} />
              <Route path="/products/:id" element={<ProductDetail />} />
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />
              <Route path="/cart" element={<Cart />} />
              <Route path="/checkout" element={<Checkout />} />
              <Route path="/orders" element={<Orders />} />
              <Route path="/wishlist" element={<Wishlist />} />
              <Route path="/admin" element={<AdminRoute me={me} loading={meLoading}><AdminHome /></AdminRoute>} />
              <Route path="/admin/users" element={<AdminRoute me={me} loading={meLoading}><AdminUsers /></AdminRoute>} />
              <Route path="/admin/orders" element={<AdminRoute me={me} loading={meLoading}><AdminOrders /></AdminRoute>} />
              <Route path="/admin/products" element={<AdminRoute me={me} loading={meLoading}><AdminProducts /></AdminRoute>} />
              <Route path="/admin/categories" element={<AdminRoute me={me} loading={meLoading}><AdminCategories /></AdminRoute>} />
              <Route path="/admin/variants" element={<AdminRoute me={me} loading={meLoading}><AdminVariants /></AdminRoute>} />
              <Route path="/admin/coupons" element={<AdminRoute me={me} loading={meLoading}><AdminCoupons /></AdminRoute>} />
              <Route path="/admin/reviews" element={<AdminRoute me={me} loading={meLoading}><AdminReviews /></AdminRoute>} />
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
