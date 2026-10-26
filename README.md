# GiayOhoho Laravel/React Migration

Thu muc chay ung dung hien tai:

```powershell
cd F:\WebBanGiayTLU\GiayOhoho\giayohoho
```

Stack hien tai:

- Backend: Laravel 12, PHP 8.2, Laravel Sanctum.
- Frontend: React 18, Vite, Material UI.
- Database: PostgreSQL 16, schema tao bang Laravel migration canonical.
- Tich hop: PayOS, GHN, Google OAuth, DB-backed refresh token/session management, monitoring proxy tuy chon.

Chay nhanh bang Docker:

```powershell
docker compose up --build
```

Sau khi len stack:

- App: `http://localhost:8000`
- API health: `http://localhost:8000/api/health`
- Mailpit: `http://localhost:8025`

Tai khoan seed mac dinh:

- `admin@ohgiay.vn / 123456`
- `tuankiethn@ohgiay.vn / 123456`
- `kiet@ohgiay.vn / 123456`

Tai lieu chi tiet nam tai:

- [README app](giayohoho/README.md)
- [Migration plan](docs/LARAVEL_REACT_MIGRATION_PLAN_2026-06-06.md)
- [Migration process](docs/LARAVEL_REACT_MIGRATION_PROCESS_2026-06-07.md)
- [Draw.io diagrams](docs/diagrams/drawio/README.md)

Trang Login/Register React cu cua GiayOhoho duoc giu giao dien, cac module con lai dang theo contract migrate tu Spring Boot + Vue source tai `F:\WebBanGiayTLU`.
