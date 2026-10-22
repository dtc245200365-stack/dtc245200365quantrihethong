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

(Hệ thống log tập trung được thêm ở phần sau.)

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
