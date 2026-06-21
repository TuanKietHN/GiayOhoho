# GiayOhoho System Diagrams

Bộ sơ đồ được dựng từ mã nguồn Laravel/React hiện tại và phần đối chiếu với dự án Spring Boot/Vue trong workspace. Ngày rà soát: 2026-06-19.

## File nguồn Draw.io

| File | Số trang | Nội dung |
| --- | ---: | --- |
| `01_USE_CASE_DIAGRAMS.drawio` | 11 | Use case tổng quát và phân rã theo module |
| `02_ARCHITECTURE_DIAGRAMS.drawio` | 4 | System Context, Container, Docker Deployment, Security/Integrations |
| `03_UML_DIAGRAMS.drawio` | 6 | Domain classes, Auth, Commerce và sequence diagrams |
| `04_DATA_ERD_DIAGRAMS.drawio` | 4 | Data domains và ERD Auth, Catalog, Commerce |
| `05_DATA_FLOW_DIAGRAMS.drawio` | 6 | DFD Context, Level 0 và Level 1 theo nhóm chức năng |

## Danh sách trang

### Use Case

1. `UC-00 Tổng quát`
2. `UC-01 Auth & Account`
3. `UC-02 Catalog`
4. `UC-03 Cart & Order`
5. `UC-04 Payment`
6. `UC-05 Shipping`
7. `UC-06 Engagement`
8. `UC-07 Admin Catalog`
9. `UC-08 Admin Operations`
10. `UC-09 Reporting`
11. `UC-10 Support`

### Architecture

1. `ARC-01 System Context`
2. `ARC-02 Containers`
3. `ARC-03 Deployment`
4. `ARC-04 Security & Integrations`

### UML

1. `UML-01 Domain Classes`
2. `UML-02 Auth Classes`
3. `UML-03 Commerce Classes`
4. `UML-04 Auth Sequence`
5. `UML-05 Checkout Sequence`
6. `UML-06 Shipping Sequence`

### Data / ERD

1. `DATA-00 Overview`
2. `DATA-01 Auth ERD`
3. `DATA-02 Catalog ERD`
4. `DATA-03 Commerce ERD`

### Data Flow

1. `DFD-00 Context`
2. `DFD-01 Level 0`
3. `DFD-02 Auth`
4. `DFD-03 Shopping`
5. `DFD-04 Providers`
6. `DFD-05 Admin`

## Bản xuất

- `exports/pdf`: 5 PDF nhiều trang để đọc hoặc in.
- `exports/png`: 31 PNG độ phân giải 2x có nhúng XML Draw.io, có thể mở lại bằng draw.io.
- `preview`: ảnh kiểm tra trực quan của các trang đại diện.

## Sinh lại

Chạy từ thư mục `GiayOhoho`:

```powershell
node tools\generate_drawio_diagrams.mjs
powershell -ExecutionPolicy Bypass -File tools\export_drawio_diagrams.ps1
```

Kiểm định một file:

```powershell
python C:\Users\Kiet\.codex\skills\drawio-skill\scripts\validate.py docs\diagrams\drawio\01_USE_CASE_DIAGRAMS.drawio
```

Mở file nguồn:

```powershell
& "C:\Program Files\draw.io\draw.io.exe" docs\diagrams\drawio\01_USE_CASE_DIAGRAMS.drawio
```
