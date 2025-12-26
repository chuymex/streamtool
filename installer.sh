#!/usr/bin/env bash
set -e

# ============================================================
# Streamtool installer para Ubuntu 24 (modo root)
# - Clona chuymex/streamtool en /opt/streamtool
# - Usa SOLO el stack embebido (php + nginx) del repo
# - Crea estructura hls/logs/cache
# - Crea y habilita servicios:
#     streamtool-fpm.service
#     streamtool-webserver.service
#     streamtool-watcher.service
# - La app usa SQLite: app/www/database.sqlite
#   => normalmente NO necesitas tocar config.php
# ============================================================

REPO_URL="https://github.com/chuymex/streamtool.git"
INSTALL_DIR="/opt/streamtool"
APP_WWW_DIR="$INSTALL_DIR/app/www"
APP_LOG_DIR="$INSTALL_DIR/app/logs"

echo "==> Actualizando paquetes base..."
apt-get update -y
apt-get install -y git ffmpeg curl

# ------------------------------------------------------------
# 1. Clonar el repositorio
# ------------------------------------------------------------
echo "==> Clonando repositorio en $INSTALL_DIR..."
if [ -d "$INSTALL_DIR/.git" ]; then
    echo "   Ya existe un repo git en $INSTALL_DIR, omitiendo clon."
else
    rm -rf "$INSTALL_DIR"
    git clone "$REPO_URL" "$INSTALL_DIR"
fi

# ------------------------------------------------------------
# 2. Crear estructura de carpetas (hls, logs, cache)
# ------------------------------------------------------------
echo "==> Creando carpetas necesarias (hls, logs, cache)..."

mkdir -p "$APP_WWW_DIR/hls"
mkdir -p "$APP_WWW_DIR/logs"
mkdir -p "$APP_WWW_DIR/cache"
mkdir -p "$APP_LOG_DIR"

# ------------------------------------------------------------
# 3. Permisos (root)
# ------------------------------------------------------------
echo "==> Ajustando permisos para root:root ..."
chown -R root:root "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod -R 775 "$APP_WWW_DIR/hls" "$APP_WWW_DIR/logs" "$APP_WWW_DIR/cache" "$APP_LOG_DIR"

# ------------------------------------------------------------
# 4. Crear unidades systemd (fpm, webserver, watcher) como root
#    Basadas en las de tu servidor actual
# ------------------------------------------------------------

echo "==> Creando unidad systemd: streamtool-fpm.service ..."
cat > /etc/systemd/system/streamtool-fpm.service <<EOF
[Unit]
Description=Streamtool FPM
After=network.target

[Service]
Type=forking
PIDFile=$INSTALL_DIR/app/php/var/run/php-fpm.pid
ExecStart=$INSTALL_DIR/app/php/sbin/php-fpm --fpm-config $INSTALL_DIR/app/php/etc/php-fpm.conf --pid $INSTALL_DIR/app/php/var/run/php-fpm.pid
ExecReload=/bin/kill -s HUP \$MAINPID
ExecStop=/bin/kill -s QUIT \$MAINPID
Restart=always
KillMode=control-group
User=root
Group=root

[Install]
WantedBy=multi-user.target
EOF

echo "==> Creando unidad systemd: streamtool-webserver.service ..."
cat > /etc/systemd/system/streamtool-webserver.service <<EOF
[Unit]
Description=Streamtool Webserver
PartOf=streamtool.service
After=streamtool-fpm.service

[Service]
Restart=always
Type=forking
PIDFile=$INSTALL_DIR/app/nginx/pid/nginx.pid
ExecStart=$INSTALL_DIR/app/nginx/sbin/nginx_streamtool
ExecStartPre=$INSTALL_DIR/app/nginx/sbin/nginx_streamtool -t
ExecReload=$INSTALL_DIR/app/nginx/sbin/nginx_streamtool -s reload
PrivateTmp=true
KillMode=control-group
User=root
Group=root

[Install]
WantedBy=multi-user.target
EOF

echo "==> Creando unidad systemd: streamtool-watcher.service ..."
cat > /etc/systemd/system/streamtool-watcher.service <<EOF
[Unit]
Description=Streamtool Watcher Service
After=streamtool-fpm.service
Requires=streamtool-fpm.service

[Service]
Restart=always
Type=simple
ExecStart=/bin/sh -c '$INSTALL_DIR/app/php/bin/php $APP_WWW_DIR/cron.php  2>&1 > $APP_LOG_DIR/watcher.log'
User=root
Group=root
KillMode=control-group

[Install]
WantedBy=multi-user.target
EOF

# ------------------------------------------------------------
# 5. Servicio "umbrella" opcional streamtool.service
# ------------------------------------------------------------
echo "==> Creando servicio umbrella: streamtool.service ..."
cat > /etc/systemd/system/streamtool.service <<EOF
[Unit]
Description=Streamtool stack (php-fpm + nginx + watcher)
After=network.target

[Service]
Type=oneshot
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

# ------------------------------------------------------------
# 6. Recargar systemd y habilitar servicios
# ------------------------------------------------------------
echo "==> Recargando systemd y habilitando servicios..."
systemctl daemon-reload

systemctl enable streamtool-fpm
systemctl enable streamtool-webserver
systemctl enable streamtool-watcher

echo "==> Iniciando servicios streamtool..."
systemctl start streamtool-fpm
systemctl start streamtool-webserver
systemctl start streamtool-watcher

# ------------------------------------------------------------
# 7. Resumen / instrucciones finales
# ------------------------------------------------------------
cat << 'EOF'

============================================================
INSTALACIÓN STREAMTOOL COMPLETADA (MODO ROOT)
============================================================

Se ha clonado el repo en:      /opt/streamtool
Servicios creados y habilitados (corriendo como root):
  - streamtool-fpm.service       (PHP embebido)
  - streamtool-webserver.service (nginx_streamtool en puertos 8000 y 9001)
  - streamtool-watcher.service   (cron.php)

Comandos útiles:
  systemctl status streamtool-fpm
  systemctl status streamtool-webserver
  systemctl status streamtool-watcher

Los logs del watcher se guardan en:
  /opt/streamtool/app/logs/watcher.log

============================================================
BASE DE DATOS / CONFIG.PHP
============================================================
La app usa SQLite, configurada en:

    /opt/streamtool/app/www/config.php

    'driver'   => 'sqlite',
    'database' => __DIR__ . '/database.sqlite',

Normalmente NO necesitas tocar config.php:
- Mientras el archivo database.sqlite esté en app/www,
  se mantendrán tus usuarios (por ejemplo admin/admin) y datos.

Solo tendrías que cambiar algo si:
- Quieres mover database.sqlite a otra ruta, o
- Cambias el tipo de base de datos.

============================================================
PUERTOS (según nginx.conf embebido)
============================================================
  - listen 8000;
  - listen 9001;

Acceso directo:
  - http://IP_DEL_SERVIDOR:8000/
  - http://IP_DEL_SERVIDOR:9001/

============================================================
NOTA DE SEGURIDAD
============================================================
Todos los servicios corren como root. Para un entorno accesible
desde Internet, es MUCHO más seguro usar un usuario sin privilegios.
Este modo está pensado para entornos controlados o de laboratorio.
============================================================
EOF

echo "==> Installer finalizado."