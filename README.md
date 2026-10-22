# Đề 3 – Website quảng bá sản phẩm (WordPress) trên Docker Compose

Môn: Quản trị hệ thống. Toàn bộ dịch vụ chạy bằng **Docker Compose**: WordPress + MySQL + phpMyAdmin,
Nginx reverse proxy (HTTPS tự ký + security headers), Prometheus + Grafana, Loki + Promtail.

## 1. Kiến trúc

```
                 Internet / trình duyệt
                         │ 443 (HTTPS)   8080 (→ chuyển sang HTTPS)
                    ┌────▼─────┐
   mạng web         │  Nginx   │  non-root, TLS1.2/1.3, security headers, rate limit
 (ra ngoài được)    └────┬─────┘
                         │ http (nội bộ)
                    ┌────▼─────┐          ┌──────────────┐
                    │WordPress │          │ phpMyAdmin   │ ← chỉ 127.0.0.1:8081
                    └────┬─────┘          └──────┬───────┘
   mạng backend ─────────┼───────────────────────┘
   (internal, không      │
    có đường ra ngoài)┌──▼───┐
                      │MySQL │  chỉ nằm trong mạng backend, không publish cổng
                      └──────┘
```

| Dịch vụ | Vai trò | Truy cập |
|---|---|---|
| nginx | Reverse proxy, HTTPS, security headers | https://localhost (HTTP :8080 tự chuyển sang HTTPS) |
| wordpress | Website giới thiệu sản phẩm | chỉ qua Nginx |
| mysql | Cơ sở dữ liệu | không mở ra ngoài |
| phpmyadmin | Quản trị CSDL | http://127.0.0.1:8081 |
| prometheus | Thu thập metrics | http://127.0.0.1:9090 |
| grafana | Dashboard giám sát | http://127.0.0.1:3000 |
| cadvisor, nginx-exporter, mysqld-exporter | Lấy metrics container / web server / database | chỉ trong mạng `monitoring` |
| loki + promtail | Log tập trung, truy vấn bằng LogQL | qua Grafana → Explore / dashboard *Log tập trung* |

## 2. Yêu cầu

- Docker Desktop (hoặc Docker Engine) có Docker Compose v2.
- Cổng trống: 443 và 8080 (đổi trong `.env` nếu bị chiếm).

## 3. Chạy hệ thống

1. Tạo file `.env` từ mẫu rồi **đổi mọi giá trị `CHANGE_ME`** thành mật khẩu mạnh (>= 16 ký tự):
   ```powershell
   copy .env.example .env
   notepad .env
   ```
   Tạo nhanh một mật khẩu ngẫu nhiên (PowerShell):
   ```powershell
   -join ((48..57)+(65..90)+(97..122) | Get-Random -Count 24 | ForEach-Object {[char]$_})
   ```
2. Khởi động:
   ```powershell
   docker compose up -d --remove-orphans
   docker compose ps
   ```
   Chứng chỉ HTTPS tự ký được dịch vụ `cert-init` tạo tự động ở lần chạy đầu (khóa riêng nằm trong volume, không nằm trong repo).
3. Mở https://localhost (trình duyệt cảnh báo chứng chỉ tự ký: chọn *Nâng cao → Tiếp tục*).

### Dùng lại dữ liệu WordPress đã có

Nếu máy đã có volume `doan_quantrihethong_mysql_data` và `doan_quantrihethong_wordpress_data` thì dữ liệu được giữ nguyên.
Vì site cũ chạy ở `http://127.0.0.1:8080` nên cần đổi địa chỉ trong nội dung bài viết, menu:

```powershell
docker compose run --rm wpcli search-replace "http://127.0.0.1:8080" "https://localhost" --all-tables
docker compose run --rm wpcli cache flush
```

Máy mới (chưa có dữ liệu): mở https://localhost, cài WordPress, cài WooCommerce nếu cần, kích hoạt plugin **Summit Gear Home**.
Khôi phục từ bản sao lưu SQL (nếu có):

```powershell
Get-Content backups\wordpress.sql | docker compose exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" wordpress'
```

### Đổi mật khẩu MySQL trên volume đã có

Biến trong `.env` chỉ có tác dụng khi volume MySQL được tạo lần đầu. Với volume cũ, đổi mật khẩu trong CSDL **trước**, rồi mới sửa `.env`:

```powershell
docker compose exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "ALTER USER ''root''@''localhost'' IDENTIFIED BY ''MAT_KHAU_MOI''; ALTER USER ''root''@''%'' IDENTIFIED BY ''MAT_KHAU_MOI''; ALTER USER ''wp_user''@''%'' IDENTIFIED BY ''MAT_KHAU_MOI_WP'';"'
```
(thay `wp_user` đúng bằng `MYSQL_USER` của bạn), sau đó cập nhật `.env` rồi `docker compose up -d`.

## 4. Nginx reverse proxy (commit 1)

Cấu hình nằm ở `nginx/templates/default.conf.template` và `nginx/proxy_wp.conf` (có trong repo):

- Nginx **non-root** (`nginx-unprivileged`), là điểm vào duy nhất của website; WordPress không mở cổng ra máy chủ.
- HTTP → HTTPS (301), TLS 1.2/1.3, chứng chỉ tự ký.
- Security headers: `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy`.
- Ẩn phiên bản Nginx, chặn `xmlrpc.php`, chặn thực thi PHP trong `uploads`, giới hạn tốc độ request (nhất là `wp-login.php`).

Kiểm tra:
```powershell
curl.exe -k -I https://localhost          # xem các security header
curl.exe -I http://localhost:8080         # phải trả 301 sang https
```

## 5. Giám sát bằng Prometheus + Grafana (commit 2)

| Đối tượng | Nguồn metrics | Ví dụ |
|---|---|---|
| Container | cAdvisor | CPU, RAM, mạng theo từng container |
| Web server | nginx-exporter (đọc `stub_status` của Nginx) | request/giây, số kết nối |
| Database | mysqld-exporter | số kết nối, truy vấn/giây, `mysql_up` |

- Cấu hình Prometheus: `monitoring/prometheus/prometheus.yml` (4 job: prometheus, cadvisor, nginx, mysql).
- Grafana được **provisioning** từ repo nên clone về là có sẵn datasource và dashboard
  **Summit Gear – Tổng quan hệ thống** (thư mục *Summit Gear*): `monitoring/grafana/`.
- `mysqld-exporter` dùng tài khoản MySQL riêng `exporter` chỉ có quyền `PROCESS`, `REPLICATION CLIENT`
  và `SELECT` trên `performance_schema` (dịch vụ `mysql-exporter-init` tự tạo, chạy lặp lại an toàn), **không dùng root**.
- Prometheus và Grafana chỉ nghe trên `127.0.0.1`; mạng `monitoring` là mạng internal.

Kiểm tra:
1. http://127.0.0.1:9090/targets → cả 4 target ở trạng thái **UP**.
2. http://127.0.0.1:3000 (tài khoản `GRAFANA_ADMIN_USER` / `GRAFANA_ADMIN_PASSWORD` trong `.env`) → mở dashboard *Summit Gear – Tổng quan hệ thống*.
3. Một vài truy vấn PromQL để demo: `up`, `rate(nginx_http_requests_total[1m])`, `mysql_global_status_threads_connected`,
   `sum by (name) (rate(container_cpu_usage_seconds_total{name=~".+"}[1m]))`.

Lưu ý:
- Mật khẩu Grafana trong `.env` chỉ áp dụng khi volume Grafana tạo lần đầu. Với volume cũ (đang là `admin/admin`), đặt lại bằng:
  ```powershell
  docker compose exec grafana grafana cli admin reset-admin-password "MAT_KHAU_MOI"
  ```
- Nếu cAdvisor không hiện tên container trên Docker Desktop (WSL2), xem lại mục *Xử lý sự cố* ở cuối file này.

## 6. Log tập trung Loki + Promtail, truy vấn LogQL (commit 3)

- **Promtail** đọc log các container `product_*` qua Docker API (socket gắn chỉ đọc) và gắn nhãn `container`, `service`, `stream`.
  Vị trí đọc lưu trong volume nên khởi động lại không đọc trùng. Cấu hình: `logging/promtail/promtail-config.yml`.
- **Loki** lưu log trong volume `loki_data`, giữ 7 ngày, chỉ nằm trong mạng `monitoring` (không publish cổng): `logging/loki/loki-config.yml`.
- Grafana tự có datasource **Loki** và dashboard **Summit Gear – Log tập trung (Loki)**.
- Log Nginx có thêm `X-Forwarded-For` và thời gian xử lý `rt=` để phân tích.

Truy vấn mẫu: Grafana → **Explore** → chọn datasource *Loki* → dán từng truy vấn (chọn chế độ *Code*):

| # | Mục đích | LogQL |
|---|---|---|
| 1 | Xem log truy cập Nginx | `{service="nginx"}` |
| 2 | Số request Nginx mỗi giây | `sum(rate({service="nginx"}[1m]))` |
| 3 | Request theo mã HTTP | ``sum by (status) (count_over_time({service="nginx"} \| pattern `<ip> - <user> [<_>] "<method> <uri> <_>" <status> <size> "<_>" "<_>" "<_>" rt=<rt>` [1m]))`` |
| 4 | Chỉ các request lỗi 4xx/5xx | ``{service="nginx"} \| pattern `<ip> - <user> [<_>] "<method> <uri> <_>" <status> <size> "<_>" "<_>" "<_>" rt=<rt>` \| status >= 400`` |
| 5 | Số dòng lỗi theo dịch vụ trong 5 phút | `sum by (service) (count_over_time({container=~"product_.*"} \|~ "(?i)(error\|fatal\|critical)" [5m]))` |
| 6 | Log MySQL có cảnh báo/lỗi | `{service="mysql"} \|~ "(?i)(warn\|error)"` |

Tạo traffic để có log khi demo:
```powershell
1..20 | ForEach-Object { curl.exe -k -s -o NUL https://localhost/ }
curl.exe -k -s -o NUL https://localhost/khong-ton-tai      # tạo log 404
curl.exe -k -s -o NUL https://localhost/xmlrpc.php          # bị chặn 403
```

## 7. Hardening đã áp dụng

| Biện pháp | Cách làm trong repo |
|---|---|
| Container non-root | Nginx (`nginx-unprivileged`, uid 101), Prometheus, Grafana, Loki, exporter chạy user không phải root; WordPress/Apache hạ quyền xuống `www-data` |
| Hạn chế quyền container | `cap_drop: ALL` cho mọi dịch vụ, chỉ `cap_add` đúng capability cần thiết; `no-new-privileges`; cAdvisor không còn `privileged` |
| Cô lập mạng | `web` (ra ngoài), `backend` **internal** (MySQL, không có đường ra Internet), `monitoring` **internal**; MySQL, Loki, exporter không publish cổng |
| Giảm bề mặt tấn công | WordPress không mở cổng; phpMyAdmin, Prometheus, Grafana chỉ nghe `127.0.0.1`; bỏ Nginx Proxy Manager (cổng quản trị 81) |
| Mật khẩu mạnh, không nằm trong repo | `.env` bị gitignore, `.env.example` chỉ có `CHANGE_ME`; Grafana/exporter bắt buộc đặt mật khẩu |
| Hạn chế quyền DB | Tài khoản WordPress chỉ có quyền trên một database; exporter chỉ `PROCESS`, `REPLICATION CLIENT`, `SELECT performance_schema`; `--local-infile=0` |
| Security headers + TLS (Nginx) | HSTS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `CSP`; TLS 1.2/1.3; ẩn phiên bản |
| Chống lạm dụng | Giới hạn request theo IP, chặt hơn ở `wp-login.php`; chặn `xmlrpc.php` và PHP trong `uploads`; `DISALLOW_FILE_EDIT` |
| Giới hạn tài nguyên & log | `mem_limit`, `pids_limit`, xoay vòng log `json-file` 10 MB × 3 |

Kiểm tra nhanh:
```powershell
docker compose exec nginx id                      # uid=101(nginx) – không phải root
docker inspect product_mysql --format "{{.HostConfig.CapDrop}} {{.HostConfig.SecurityOpt}}"
docker network inspect doan_quantrihethong_backend --format "internal={{.Internal}}"
docker compose ps --format "table {{.Name}}\t{{.Ports}}"   # MySQL/Loki không có cổng publish
```

Hạn chế đã biết: Promtail cần đọc `docker.sock` (gắn chỉ đọc) và cAdvisor cần đọc thư mục hệ thống của Docker,
nên hai dịch vụ này không áp dụng `cap_drop: ALL`.

## 8. Danh sách kiểm tra khi demo

1. `docker compose ps` → tất cả dịch vụ `Up` (các job `cert-init`, `mysql-exporter-init` đã `Exited (0)`).
2. https://localhost → website; `curl.exe -k -I https://localhost` → thấy security headers; http://localhost:8080 → 301 sang HTTPS.
3. http://127.0.0.1:8081 → phpMyAdmin đăng nhập, mở database `wordpress`.
4. http://127.0.0.1:9090/targets → 4 target UP.
5. Grafana → dashboard *Tổng quan hệ thống* (container, Nginx, MySQL).
6. Grafana → Explore → Loki → chạy các truy vấn LogQL ở mục 6, hoặc mở dashboard *Log tập trung*.
7. Các lệnh kiểm tra hardening ở mục 7.
8. GitHub: `git log --oneline` thấy 3 commit: Nginx → Prometheus + Grafana → Loki.

## 9. Xử lý sự cố

- **cAdvisor không hiện tên container / thiếu số liệu (Docker Desktop, WSL2):** dashboard dùng nhãn `name`. Nếu trống, kiểm tra
  http://127.0.0.1:9090/graph với truy vấn `container_cpu_usage_seconds_total`. Cần đã bật WSL2 backend; thử khởi động lại Docker Desktop.
- **Grafana báo lỗi nâng cấp CSDL khi khởi động:** volume `grafana_data` cũ được tạo bởi bản mới hơn. Đặt `GRAFANA_VERSION` trong `.env`
  đúng bản bạn từng chạy (xem bằng `docker image ls grafana/grafana`). Tương tự với `PROMETHEUS_VERSION`.
- **Datasource báo trùng tên:** vào Grafana → Connections → Data sources, xóa datasource Prometheus/Loki tạo tay rồi `docker compose restart grafana`.
- **Không có log trong Loki:** `docker compose logs promtail --tail 30`; kiểm tra Promtail đã nối được `loki:3100` và container có tên bắt đầu bằng `product_`.
- **Cổng 443 hoặc 8080 bị chiếm:** đổi `HTTPS_PORT`/`HTTP_PORT` trong `.env` và đặt `PUBLIC_URL` kèm cổng (ví dụ `https://localhost:8443`).
