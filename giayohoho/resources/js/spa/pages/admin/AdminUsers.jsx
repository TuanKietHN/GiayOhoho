import React, { useEffect, useState } from "react"
import api from "../../api"
import { useToast } from "../../ui/toast"
import Box from "@mui/material/Box"
import Button from "@mui/material/Button"
import Chip from "@mui/material/Chip"
import Collapse from "@mui/material/Collapse"
import Paper from "@mui/material/Paper"
import Stack from "@mui/material/Stack"
import Table from "@mui/material/Table"
import TableBody from "@mui/material/TableBody"
import TableCell from "@mui/material/TableCell"
import TableContainer from "@mui/material/TableContainer"
import TableHead from "@mui/material/TableHead"
import TableRow from "@mui/material/TableRow"
import TextField from "@mui/material/TextField"
import Typography from "@mui/material/Typography"

function roleNames(user) {
  return (user.roles || []).map((role) => typeof role === "string" ? role : role?.name).filter(Boolean)
}

export default function AdminUsers() {
  const [users, setUsers] = useState([])
  const [q, setQ] = useState("")
  const [rolesInputByUser, setRolesInputByUser] = useState({})
  const [expandedUserId, setExpandedUserId] = useState(null)
  const [sessionsByUser, setSessionsByUser] = useState({})
  const [loading, setLoading] = useState(false)
  const toast = useToast()

  const load = async () => {
    setLoading(true)
    try {
      const response = await api.get("/admin/accounts", { params: q ? { q, page: 0, size: 50 } : { page: 0, size: 50 } })
      setUsers(response.data.content || response.data.data || response.data || [])
    } catch (error) {
      toast.show(error.response?.data?.message || "Không thể tải người dùng", "error")
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const setUserRoles = async (id) => {
    const value = rolesInputByUser[id] || ""
    try {
      await api.post(`/admin/users/${id}/roles`, { roles: value.split(",").map((item) => item.trim()).filter(Boolean) })
      await load()
      toast.show("Đã cập nhật roles", "success")
    } catch (error) {
      toast.show(error.response?.data?.message || "Không thể cập nhật roles", "error")
    }
  }

  const loadSessions = async (userId) => {
    const nextExpanded = expandedUserId === userId ? null : userId
    setExpandedUserId(nextExpanded)
    if (!nextExpanded) return

    try {
      const response = await api.get(`/admin/accounts/${userId}/sessions`)
      setSessionsByUser((current) => ({ ...current, [userId]: response.data || [] }))
    } catch (error) {
      toast.show(error.response?.data?.message || "Không thể tải phiên đăng nhập", "error")
    }
  }

  const revokeSession = async (userId, sessionId) => {
    try {
      await api.delete(`/admin/accounts/${userId}/sessions/${encodeURIComponent(sessionId)}`)
      const response = await api.get(`/admin/accounts/${userId}/sessions`)
      setSessionsByUser((current) => ({ ...current, [userId]: response.data || [] }))
      toast.show("Đã thu hồi phiên", "success")
    } catch (error) {
      toast.show(error.response?.data?.message || "Không thể thu hồi phiên", "error")
    }
  }

  const revokeAllSessions = async (userId) => {
    try {
      await api.delete(`/admin/accounts/${userId}/sessions`)
      setSessionsByUser((current) => ({ ...current, [userId]: [] }))
      toast.show("Đã thu hồi toàn bộ phiên của tài khoản", "success")
    } catch (error) {
      toast.show(error.response?.data?.message || "Không thể thu hồi phiên", "error")
    }
  }

  return (
    <Box>
      <Stack direction={{ xs: "column", sm: "row" }} justifyContent="space-between" alignItems={{ sm: "end" }} spacing={2} sx={{ mb: 3 }}>
        <Box>
          <Typography variant="overline" color="primary" fontWeight={900}>Quản trị</Typography>
          <Typography variant="h4" fontWeight={900}>Người dùng</Typography>
          <Typography color="text.secondary">Quản lý tài khoản, roles và phiên đăng nhập.</Typography>
        </Box>
        <Stack direction="row" spacing={1}>
          <TextField size="small" value={q} onChange={(event) => setQ(event.target.value)} placeholder="Email, username, phone" onKeyDown={(event) => { if (event.key === "Enter") load() }} />
          <Button variant="contained" onClick={load} disabled={loading}>Tìm</Button>
        </Stack>
      </Stack>

      <TableContainer component={Paper} variant="outlined">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Tài khoản</TableCell>
              <TableCell>Roles</TableCell>
              <TableCell>Trạng thái</TableCell>
              <TableCell>Gán roles</TableCell>
              <TableCell align="right">Phiên</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {users.map((user) => (
              <React.Fragment key={user.id}>
                <TableRow>
                  <TableCell>
                    <Typography fontWeight={900}>{user.displayName || `${user.firstName || ""} ${user.lastName || ""}`.trim() || user.username}</Typography>
                    <Typography variant="body2" color="text.secondary">{user.email}</Typography>
                    <Typography variant="caption" color="text.secondary">@{user.username}</Typography>
                  </TableCell>
                  <TableCell>
                    <Stack direction="row" spacing={0.5} flexWrap="wrap">
                      {roleNames(user).map((role) => <Chip key={role} size="small" label={role} />)}
                    </Stack>
                  </TableCell>
                  <TableCell>
                    <Chip size="small" color={user.locked ? "error" : "success"} label={user.status || (user.locked ? "LOCKED" : "ACTIVE")} />
                  </TableCell>
                  <TableCell>
                    <Stack direction="row" spacing={1}>
                      <TextField
                        size="small"
                        value={rolesInputByUser[user.id] ?? roleNames(user).join(",")}
                        onChange={(event) => setRolesInputByUser({ ...rolesInputByUser, [user.id]: event.target.value })}
                        placeholder="ADMIN,CUSTOMER"
                      />
                      <Button variant="outlined" onClick={() => setUserRoles(user.id)}>Lưu</Button>
                    </Stack>
                  </TableCell>
                  <TableCell align="right">
                    <Button size="small" onClick={() => loadSessions(user.id)}>{expandedUserId === user.id ? "Ẩn" : "Xem phiên"}</Button>
                  </TableCell>
                </TableRow>
                <TableRow>
                  <TableCell colSpan={5} sx={{ p: 0, borderBottom: expandedUserId === user.id ? "1px solid" : 0, borderColor: "divider" }}>
                    <Collapse in={expandedUserId === user.id} timeout="auto" unmountOnExit>
                      <Box sx={{ p: 2, bgcolor: "grey.50" }}>
                        <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1 }}>
                          <Typography fontWeight={900}>Phiên đăng nhập đang hoạt động</Typography>
                          <Button size="small" color="error" variant="outlined" onClick={() => revokeAllSessions(user.id)}>Thu hồi tất cả</Button>
                        </Stack>
                        {(sessionsByUser[user.id] || []).length === 0 ? (
                          <Typography color="text.secondary">Không có phiên đang hoạt động.</Typography>
                        ) : (
                          <Stack spacing={1}>
                            {(sessionsByUser[user.id] || []).map((session) => (
                              <Stack key={session.sessionId || session.id} direction={{ xs: "column", sm: "row" }} spacing={1} alignItems={{ sm: "center" }} justifyContent="space-between" component={Paper} variant="outlined" sx={{ p: 1.25 }}>
                                <Box>
                                  <Typography fontWeight={800}>{session.deviceName || "Thiết bị không xác định"}</Typography>
                                  <Typography variant="caption" color="text.secondary">{session.ipAddress || "-"} • {session.authProvider || "PASSWORD"} • {session.lastSeenAt || session.createdAt}</Typography>
                                </Box>
                                <Button size="small" color="error" onClick={() => revokeSession(user.id, session.sessionId || session.id)}>Thu hồi</Button>
                              </Stack>
                            ))}
                          </Stack>
                        )}
                      </Box>
                    </Collapse>
                  </TableCell>
                </TableRow>
              </React.Fragment>
            ))}
            {users.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} align="center">{loading ? "Đang tải..." : "Không có tài khoản phù hợp."}</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </TableContainer>
    </Box>
  )
}
