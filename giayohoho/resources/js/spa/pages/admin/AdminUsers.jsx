import React, { useEffect, useState } from 'react'
import api from '../../api'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import TextField from '@mui/material/TextField'
import Button from '@mui/material/Button'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import { useI18n } from '../../ui/i18n.jsx'

export default function AdminUsers() {
  const { t } = useI18n()
  const [data, setData] = useState({ data: [] })
  const [q, setQ] = useState('')
  const [rolesInputByUser, setRolesInputByUser] = useState({})
  const load = async () => {
    const res = await api.get('/admin/users', { params: q ? { q } : {} })
    setData(res.data)
  }
  useEffect(() => { load() }, [])
  const setUserRoles = async (id) => {
    const value = rolesInputByUser[id] || ''
    await api.post(`/admin/users/${id}/roles`, { roles: value.split(',').map(s => s.trim()).filter(Boolean) })
    load()
  }
  return (
    <Card>
      <CardContent>
        <Typography variant="h6" sx={{ mb: 2 }}>{t('users')}</Typography>
        <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          <TextField size="small" value={q} onChange={e => setQ(e.target.value)} placeholder={t('search_placeholder')} onKeyDown={e => { if (e.key === 'Enter') load() }} />
          <Button variant="contained" onClick={load}>{t('search')}</Button>
        </div>
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>{t('email')}</TableCell>
                <TableCell>{t('username')}</TableCell>
                <TableCell>{t('roles')}</TableCell>
                <TableCell>{t('assign_roles')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {(data.data || []).map(u => (
                <TableRow key={u.id}>
                  <TableCell>{u.email}</TableCell>
                  <TableCell>{u.username}</TableCell>
                  <TableCell>{(u.roles || []).map(r => r.name).join(', ')}</TableCell>
                  <TableCell>
                    <TextField size="small" value={rolesInputByUser[u.id] || ''} onChange={e => setRolesInputByUser({ ...rolesInputByUser, [u.id]: e.target.value })} placeholder={t('roles_placeholder')} />
                    <Button variant="outlined" size="small" sx={{ ml: 1 }} onClick={() => setUserRoles(u.id)}>{t('assign')}</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      </CardContent>
    </Card>
  )
}

