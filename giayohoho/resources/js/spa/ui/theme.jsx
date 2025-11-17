import React, { createContext, useMemo, useState, useContext } from 'react'
import { createTheme, ThemeProvider } from '@mui/material/styles'
import CssBaseline from '@mui/material/CssBaseline'
import IconButton from '@mui/material/IconButton'
import DarkModeIcon from '@mui/icons-material/DarkMode'
import LightModeIcon from '@mui/icons-material/LightMode'

const ModeCtx = createContext({ mode: 'light', toggle: () => {} })

export default function AppTheme({ children }) {
  const [mode, setMode] = useState('light')
  const theme = useMemo(() => createTheme({ palette: { mode, primary: { main: '#2563eb' }, secondary: { main: '#111827' } } }), [mode])
  const toggle = () => setMode((m) => (m === 'light' ? 'dark' : 'light'))
  return (
    <ModeCtx.Provider value={{ mode, toggle }}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        {children}
      </ThemeProvider>
    </ModeCtx.Provider>
  )
}

export function useMode() { return useContext(ModeCtx) }