import React, { useState } from 'react'
import api from '../api'
import { useToast } from '../ui/toast'

export default function Checkout() {
  const [order_address, setAddr] = useState('')
  const [payment_method, setPay] = useState('COD')
  const [msg, setMsg] = useState('')
  const toast = useToast()

  const place = async () => {
    setMsg('')
    try {
      const res = await api.post('/auth/checkout', { order_address, payment_method })
      setMsg('Đã đặt hàng #' + res.data.id)
      toast?.show('Đặt hàng thành công', 'success')
    } catch (e) {
      const m = e?.response?.data?.message || 'Đặt hàng thất bại'
      setMsg(m)
      toast?.show(m, 'error')
    }
  }

  return (
    <div>
      <h2>Checkout</h2>
      {msg && <p>{msg}</p>}
      <input placeholder="Địa chỉ giao hàng" value={order_address} onChange={e => setAddr(e.target.value)} />
      <select value={payment_method} onChange={e => setPay(e.target.value)} style={{ display: 'block', marginTop: 8 }}>
        <option value="COD">COD</option>
        <option value="VNPAY">VNPAY</option>
        <option value="MOMO">Momo</option>
      </select>
      <button onClick={place} style={{ marginTop: 8 }}>Đặt hàng</button>
    </div>
  )
}
