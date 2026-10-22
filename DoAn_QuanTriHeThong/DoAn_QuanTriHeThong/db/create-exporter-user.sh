#!/bin/sh
# Tạo (hoặc cập nhật) tài khoản MySQL riêng cho mysqld-exporter với quyền tối thiểu.
# Chạy lặp lại an toàn (idempotent) và không phụ thuộc volume đã tạo từ trước.
set -eu
export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
mysql -h "$MYSQL_HOST" -uroot <<SQL
CREATE USER IF NOT EXISTS 'exporter'@'%' IDENTIFIED BY '${EXPORTER_DB_PASSWORD}' WITH MAX_USER_CONNECTIONS 3;
ALTER USER 'exporter'@'%' IDENTIFIED BY '${EXPORTER_DB_PASSWORD}' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'exporter'@'%';
GRANT SELECT ON performance_schema.* TO 'exporter'@'%';
FLUSH PRIVILEGES;
SQL
echo "Đã cấu hình tài khoản exporter (chỉ quyền PROCESS, REPLICATION CLIENT, SELECT trên performance_schema)."
