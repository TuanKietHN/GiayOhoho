import React from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Routes, Route, Link, Navigate } from 'react-router-dom'
import Products from './pages/Products'
import ProductDetail from './pages/ProductDetail'
import Login from './pages/Login'
import Register from './pages/Register'
import Cart from './pages/Cart'
import Checkout from './pages/Checkout'
import Orders from './pages/Orders'
import Wishlist from './pages/Wishlist'

function Nav() {
  const token = localStorage.getItem('token')
  const logout = async () => {
    try { await (await import('./api')).default.post('/auth/logout') } catch {}
    localStorage.removeItem('token')
    window.location.href = '/'
  }
  return (
    <nav style={{ padding: 12, borderBottom: '1px solid #ddd' }}>
      <Link to="/" style={{ marginRight: 12 }}>Sản phẩm</Link>
      <Link to="/cart" style={{ marginRight: 12 }}>Giỏ hàng</Link>
      <Link to="/orders" style={{ marginRight: 12 }}>Đơn hàng</Link>
      <Link to="/wishlist" style={{ marginRight: 12 }}>Yêu thích</Link>
      {token ? (
        <button onClick={logout}>Đăng xuất</button>
      ) : (
        <>
          <Link to="/login" style={{ marginRight: 12 }}>Đăng nhập</Link>
          <Link to="/register">Đăng ký</Link>
        </>
      )}
    </nav>
  )
}

function App() {
  return (
    <BrowserRouter>
      <Nav />
      <div style={{ padding: 16 }}>
        <Routes>
          <Route path="/" element={<Products />} />
          <Route path="/products/:id" element={<ProductDetail />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/cart" element={<Cart />} />
          <Route path="/checkout" element={<Checkout />} />
          <Route path="/orders" element={<Orders />} />
          <Route path="/wishlist" element={<Wishlist />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </div>
    </BrowserRouter>
  )}

const root = createRoot(document.getElementById('app'))
root.render(<App />)
