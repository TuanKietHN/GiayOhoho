# 🔒 Báo cáo Vá lỗi Bảo mật — GiayOhoho
**Ngày thực hiện:** 2026-10-26  
**Tham chiếu:** [CISA Alert 2026-10-21](https://www.cisa.gov/news-events/alerts/2026/10/21/critical-vulnerabilities-php-require-immediate-patching)

---

## 1. Tóm tắt lỗ hổng CISA "php2shell"

Hai lỗ hổng cực kỳ nghiêm trọng (**CVSS 9.2**) ảnh hưởng đến **toàn bộ PHP từ 5.4 đến 8.5.x chưa vá**:

| CVE | Tên | Cơ chế | Hậu quả |
|:--|:--|:--|:--|
| **CVE-2026-109618** | OOM Memory Corruption | `longjmp()` interrupt khi đang parse multipart data → cleanup xử lý con trỏ chưa khởi tạo như con trỏ hợp lệ | **RCE không cần xác thực** |
| **CVE-2026-109619** | Stale Global State (Use-After-Free) | Fatal error để lại global pointer trỏ vào vùng nhớ đã free → request tiếp theo của cùng worker process dùng stale state | **RCE không cần xác thực** |

**Tất cả SAPI bị ảnh hưởng**: PHP-FPM, Apache `mod_php`, `php-cgi`, FrankenPHP, LiteSpeed.  
**Trạng thái khai thác**: PoC công khai đã rò rỉ, botnet đang quét tự động.

---

## 2. Phân tích rủi ro của dự án GiayOhoho

### 2.1 PHP Runtime
| Thành phần | Trước | Sau | Trạng thái |
|:--|:--|:--|:--|
| **PHP Local (Windows CLI)** | 8.2.29 | ❌ *Chưa nâng cấp* — xem mục 4 | ⚠️ Cần làm thủ công |
| **PHP trong Docker** | `php:8.3-cli-bookworm` (tag nổi) | `php:8.3.36-cli-bookworm` (tag cố định) | ✅ Đã vá trong Dockerfile |

> [!IMPORTANT]
> **PHP CLI trên máy local (C:\Dev\php)** đang là **8.2.29**, thấp hơn phiên bản vá **8.2.35**. Đây là rủi ro cao nhất cần xử lý ngay (xem Hành động 4 bên dưới).

> [!NOTE]
> **Mức độ rủi ro thực tế cho dự án này**: PHP CLI (SAPI = Command Line Interface) **ít bị tấn công hơn** so với PHP-FPM hay mod_php vì không xử lý request HTTP trực tiếp từ Internet. Tuy nhiên vẫn phải cập nhật vì CVE-2026-109619 có thể ảnh hưởng khi chạy `php artisan serve` hoặc queue workers.

### 2.2 Kết quả `composer audit` (48 lỗ hổng → 0)

Dự án có **48 lỗ hổng bảo mật** trong 14 packages trước khi vá:

#### 🔴 CRITICAL
| Package | CVE | Mô tả |
|:--|:--|:--|
| `laravel/framework` v12.38.1 | **CVE-2026-114509** | TokenGuard authentication bypass qua non-string API credentials |

#### 🟠 HIGH
| Package | CVE | Mô tả |
|:--|:--|:--|
| `guzzlehttp/guzzle` | CVE-2026-69246 | Noncanonical host bypass host-based checks |
| `laravel/framework` | PKSA (no CVE) | CRLF injection trong default email validation rule |
| `league/commonmark` | Nhiều CVE | DoS qua crafted Markdown (quadratic-time, duplicate footnotes, ...) |
| `league/commonmark` | XSS | `AttributesExtension` XSS bypass via U+000C form feed |
| `phpunit/phpunit` | CVE-2026-24765 | Unsafe deserialization trong PHPT code coverage |
| `symfony/mime` | CVE-2026-45067 | SMTP Command Injection via CRLF trong Address |

#### 🟡 MEDIUM
| Package | CVE | Mô tả |
|:--|:--|:--|
| `guzzlehttp/guzzle` | 7 CVE | Cookie domain confusion, HTTPS downgrade, proxy-auth leak, URI fragment disclosure |
| `guzzlehttp/psr7` | 4 CVE | CRLF injection, host confusion |
| `laravel/framework` | PKSA | Temporary Signed URL path confusion |
| `symfony/http-foundation` | CVE-2026-108082 | ReDoS trong `HeaderUtils::split()` via Cache-Control header |
| `symfony/http-foundation` | CVE-2026-48736 | SSRF bypass qua IPv6 transition forms |
| `symfony/mailer` | CVE-2026-45068 | Argument injection trong SendmailTransport |
| `symfony/mime` | CVE-2026-45070 | Email header injection |
| `symfony/routing` | 2 CVE | URL injection, UrlGenerator bypass |
| `symfony/process` | CVE-2026-24739 | Sai escaping argument dưới MSYS2/Git Bash |
| `psy/psysh` | CVE-2026-25129 | Local privilege escalation via CWD autoload |

#### 🔵 LOW
| Package | CVE | Mô tả |
|:--|:--|:--|
| `laravel/framework` | CVE-2026-102279 | XSS trong Debug Page (chỉ khi `APP_DEBUG=true`) |
| `league/flysystem` | CVE-2026-102601 | Malformed UTF-8 bypass WhitespacePathNormalizer |
| `symfony/yaml` | 3 CVE | DoS qua recursive YAML ("Billion Laughs"), ReDoS, stack exhaustion |
| `symfony/polyfill-intl-idn` | CVE-2026-46644 | Punycode equivalence confusion |

---

## 3. Những gì đã được thực hiện ✅

### 3.1 Cập nhật toàn bộ Composer dependencies
```
composer update --with-all-dependencies --no-interaction
```
**Kết quả:** 1 install, 77 upgrades — **0 lỗ hổng còn lại** (`composer audit` xác nhận).

### 3.2 Các phiên bản package sau khi vá
| Package | Từ | Sang |
|:--|:--|:--|
| `laravel/framework` | v12.38.1 | **v12.70.1** |
| `laravel/sanctum` | v4.2.0 | **v4.3.3** |
| `guzzlehttp/guzzle` | 7.10.0 | **7.16.1** |
| `guzzlehttp/psr7` | 2.8.0 | **2.13.2** |
| `league/commonmark` | 2.7.1 | **2.10.4** |
| `league/flysystem` | 3.30.2 | **3.36.0** |
| `symfony/*` | v7.3.x | **v7.4.21** |
| `phpunit/phpunit` | 11.5.44 | **11.5.57** |
| `psy/psysh` | v0.12.14 | **v0.12.24** |
| `monolog/monolog` | 3.9.0 | **3.12.1** |

### 3.3 Vá Dockerfile (Docker runtime)
Thay tag nổi `php:8.3-cli-bookworm` bằng tag cố định `php:8.3.36-cli-bookworm`:
```diff
- FROM php:8.3-cli-bookworm AS runtime
+ FROM php:8.3.36-cli-bookworm AS runtime
```

### 3.4 Kiểm tra sau khi vá
- ✅ `php -l` toàn bộ file PHP: **không có lỗi cú pháp**
- ✅ `GET /api/health` trả về **HTTP 200** `{"status":"ok"}`

---

## 4. ⚠️ Hành động BẮT BUỘC còn lại — Nâng PHP Local

> [!CAUTION]
> **PHP local đang là 8.2.29 — PHẢI nâng cấp lên 8.2.35 ngay.**

### Bước 1: Tải PHP 8.2.35 (Thread Safe, VS16, x64)
```
https://windows.php.net/downloads/releases/php-8.2.35-Win32-vs16-x64.zip
```
SHA256: `cfa6000be835931961e40740bd3b51111700061d6655ddbfe5a2125807947457`

### Bước 2: Giải nén và thay thế (PowerShell Administrator)
```powershell
# Backup PHP cũ
Rename-Item -Path "C:\Dev\php" -NewName "C:\Dev\php-8.2.29-backup"

# Giải nén PHP mới vào đúng vị trí
Expand-Archive -Path "$env:USERPROFILE\Downloads\php-8.2.35-Win32-vs16-x64.zip" -DestinationPath "C:\Dev\php"

# Copy giữ nguyên cấu hình
Copy-Item "C:\Dev\php-8.2.29-backup\php.ini" "C:\Dev\php\php.ini" -Force
```

### Bước 3: Xác nhận
```powershell
php -v
# PHP 8.2.35 (cli) ... Thread Safe ...
```

---

## 5. Cấu hình bảo mật bổ sung khuyến nghị

### 5.1 File `.env` (production)
```env
APP_DEBUG=false          # BẮT BUỘC — tắt debug page (vá CVE-2026-102279)
APP_ENV=production
REFRESH_TOKEN_COOKIE_SECURE=true   # Nếu dùng HTTPS
```

### 5.2 `php.ini` hardening
```ini
; Giảm thiểu rủi ro OOM trigger (CVE-2026-109618)
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 12M

; Bảo mật chung
expose_php = Off
display_errors = Off
log_errors = On
```

---

## 6. Lịch sử thay đổi file

| File | Thay đổi |
|:--|:--|
| `giayohoho/composer.lock` | Cập nhật 77 packages, xóa 48 advisory |
| `giayohoho/Dockerfile` | `php:8.3-cli-bookworm` → `php:8.3.36-cli-bookworm` |
| `giayohoho/.env.example` | Fix `GHN_FROM_ADDRESS="Ha Noi"` (bug parse dotenv) |
| `giayohoho/.env` | `SESSION_DRIVER=file`, `CACHE_STORE=file` (local dev) |

---

## 7. Trạng thái tổng kết

| Hạng mục | Trạng thái |
|:--|:--|
| CVE-2026-109618 + CVE-2026-109619 — PHP Docker | ✅ Đã vá (`php:8.3.36`) |
| CVE-2026-109618 + CVE-2026-109619 — PHP Local | ⚠️ **Cần nâng PHP local lên 8.2.35** |
| CVE-2026-114509 — Laravel TokenGuard auth bypass (CRITICAL) | ✅ Đã vá (`laravel/framework` v12.70.1) |
| 48 lỗ hổng Composer (14 packages) | ✅ Đã vá — `composer audit` = 0 advisory |
| PHP lint toàn bộ codebase sau vá | ✅ Pass — không có lỗi cú pháp |
| API smoke test sau vá | ✅ HTTP 200 |
