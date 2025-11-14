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
    <nav
      style={{
        backgroundColor: "var(--primary-dark)",
        color: "var(--neutral-white)",
        padding: "var(--spacing-md) 0",
        boxShadow: "var(--shadow-md)",
      }}
    >
      <div
        style={{
          maxWidth: "1200px",
          margin: "0 auto",
          padding: "0 var(--spacing-md)",
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
        }}
      >
        <Link
          to="/"
          style={{
            fontSize: "1.5rem",
            fontWeight: 700,
            fontFamily: "var(--font-display)",
            color: "var(--neutral-white)",
            textDecoration: "none",
          }}
        >
          GiàyOhoho
        </Link>

        {/* Desktop Menu */}
        <div
          style={{
            display: "flex",
            gap: "var(--spacing-lg)",
            alignItems: "center",
          }}
        >
          <Link to="/" style={{ color: "var(--neutral-white)" }}>
            Sản phẩm
          </Link>
          <Link to="/cart" style={{ color: "var(--neutral-white)" }}>
            Giỏ hàng
          </Link>
          <Link to="/orders" style={{ color: "var(--neutral-white)" }}>
            Đơn hàng
          </Link>
          <Link to="/wishlist" style={{ color: "var(--neutral-white)" }}>
            Yêu thích
          </Link>
          {me?.roles?.some((r) => r.name === "admin") && (
            <Link to="/admin" style={{ color: "var(--neutral-white)" }}>
              Admin
            </Link>
          )}

          {me ? (
            <button
              onClick={logout}
              className="btn-secondary"
              style={{ padding: "var(--spacing-sm) var(--spacing-md)" }}
            >
              Đăng xuất
            </button>
          ) : (
            <>
              <Link to="/login" style={{ color: "var(--neutral-white)" }}>
                Đăng nhập
              </Link>
              <Link to="/register" className="btn-primary" style={{ padding: "var(--spacing-sm) var(--spacing-md)" }}>
                Đăng ký
              </Link>
            </>
          )}
        </div>
      </div>
    </nav>
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
    <ToastProvider>
      <BrowserRouter>
        <Nav me={me} />
        <main style={{ minHeight: "100vh", backgroundColor: "var(--neutral-light)" }}>
          <div style={{ maxWidth: "1200px", margin: "0 auto", padding: "var(--spacing-2xl) var(--spacing-md)" }}>
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
          </div>
        </main>
        <footer
          style={{
            backgroundColor: "var(--primary-dark)",
            color: "var(--neutral-white)",
            padding: "var(--spacing-2xl) var(--spacing-md)",
            textAlign: "center",
            marginTop: "var(--spacing-2xl)",
            borderTop: `1px solid var(--neutral-gray)`,
          }}
        >
          <div style={{ maxWidth: "1200px", margin: "0 auto" }}>
            <p style={{ color: "var(--neutral-white)" }}>
              © 2025 GiàyOhoho - Giày chất lượng cao. Tous les droits réservés.
            </p>
          </div>
        </footer>
      </BrowserRouter>
    </ToastProvider>
  )
}

const root = createRoot(document.getElementById("app"))
root.render(<App />)
