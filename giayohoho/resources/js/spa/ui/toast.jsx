import React, { createContext, useContext, useState } from 'react'

const Ctx = createContext(null)

export function ToastProvider({ children }) {
  const [items, setItems] = useState([])
  const show = (text, type = 'info') => {
    const id = Date.now() + Math.random()
    setItems(prev => [...prev, { id, text, type }])
    setTimeout(() => setItems(prev => prev.filter(i => i.id !== id)), 3000)
  }
  return (
    <Ctx.Provider value={{ show }}>
      {children}
      <div style={{ position: 'fixed', top: 16, right: 16, display: 'flex', flexDirection: 'column', gap: 8, zIndex: 9999 }}>
        {items.map(i => (
          <div key={i.id} style={{ background: i.type === 'error' ? '#fee2e2' : i.type === 'success' ? '#dcfce7' : '#e5e7eb', color: '#111827', padding: '8px 12px', borderRadius: 8, boxShadow: '0 2px 8px rgba(0,0,0,.15)' }}>
            {i.text}
          </div>
        ))}
      </div>
    </Ctx.Provider>
  )
}

export function useToast() {
  return useContext(Ctx)
}
