# Instalación de Streamtool en Ubuntu 24.04

Este documento describe cómo desplegar **Streamtool** en una máquina nueva con **Ubuntu 24.04**, utilizando el código de este repositorio.

El panel es prácticamente *auto-contenido*:

- Usa su **propio PHP-FPM** (`/opt/streamtool/app/php/...`).
- Usa su **propio nginx embebido** (`/opt/streamtool/app/nginx/...`).
- Se gestiona mediante varios servicios **systemd**:
  - `streamtool.service`
  - `streamtool-fpm.service`
  - `streamtool-webserver.service`
  - `streamtool-watcher.service`
  - `streamtool-stats.service`

Por ello, la compatibilidad con Ubuntu 24.04 es muy alta y no depende de las versiones de PHP / nginx del sistema.

---

## 1. Requisitos mínimos

En una Ubuntu 24.04 limpia, instala los paquetes base:

```bash
sudo apt update
sudo apt install -y git curl sudo
```

No es necesario instalar `nginx` ni `php` del sistema para que el panel funcione (ya vienen embebidos).  
Solo podrían ser útiles si quieres poner un proxy inverso delante.

---

## 2. Crear usuario y grupo `streamtool`

Algunos servicios se ejecutan como el usuario `streamtool`:

```ini
User=streamtool
Group=streamtool
```

Crea el usuario y grupo del sistema:

```bash
sudo groupadd --system streamtool || true
sudo useradd  --system --no-create-home --ingroup streamtool streamtool || true
```

---

## 3. Clonar el repositorio en `/opt/streamtool`

Por convención (y porque los servicios lo esperan así), el proyecto vive en `/opt/streamtool`.

```bash
sudo mkdir -p /opt
cd /opt

sudo git clone https://github.com/chuymex/streamtool.git
sudo chown -R root:root /opt/streamtool
```

> Si usas SSH:
>
> ```bash
> sudo git clone git@github.com:chuymex/streamtool.git
> ```

### 3.1. Permisos de escritura

El panel necesita escribir en ciertas carpetas (logs, HLS, etc.).  
Ajusta los permisos para el usuario `streamtool`:

```bash
sudo chown -R streamtool:streamtool /opt/streamtool/app/www/logs /opt/streamtool/app/www/hls
```

Si usas otras carpetas de escritura (por ejemplo, `cache/`), ajusta también:

```bash
sudo chown -R streamtool:streamtool /opt/streamtool/app/www/cache
```

---

## 4. Servicios systemd

En la instalación original se usan los siguientes archivos en `/etc/systemd/system/`:

- `streamtool.service`
- `streamtool-fpm.service`
- `streamtool-webserver.service`
- `streamtool-stats.service`
- `streamtool-watcher.service`

Debes crearlos en la nueva máquina con **el mismo contenido**.

### 4.1. `/etc/systemd/system/streamtool.service`

```ini
[Unit]
Description=Streamtool
After=syslog.target network.target remote-fs.target nss-lookup.target mariadb.service
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/bin/true
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
```

> Nota: si no usas `mariadb.service`, puedes dejarlo tal cual (no es crítico) o adaptarlo a tu servicio de base de datos real.

---

### 4.2. `/etc/systemd/system/streamtool-fpm.service`

```ini
[Unit]
Description=Streamtool FPM
After=streamtool.service

[Service]
Type=forking
PIDFile=/opt/streamtool/app/php/var/run/php-fpm.pid
ExecStart=/opt/streamtool/app/php/sbin/php-fpm --fpm-config /opt/streamtool/app/php/etc/php-fpm.conf --pid /opt/streamtool/app/php/var/run/php-fpm.pid
ExecReload=/bin/kill -s HUP $MAINPID
ExecStop=/bin/kill -s QUIT $MAINPID
Restart=always
KillMode=control-group

[Install]
WantedBy=streamtool.service
```

Este servicio arranca el **PHP-FPM embebido** del panel.

---

### 4.3. `/etc/systemd/system/streamtool-webserver.service`

```ini
[Unit]
Description=Streamtool Webserver
PartOf=streamtool.service
After=streamtool-fpm.service

[Service]
Restart=always
Type=forking
PIDFile=/opt/streamtool/app/nginx/pid/nginx.pid
ExecStart=/opt/streamtool/app/nginx/sbin/nginx_streamtool
ExecStartPre=/opt/streamtool/app/nginx/sbin/nginx_streamtool -t
ExecReload=/opt/streamtool/app/nginx/sbin/nginx_streamtool -s reload
PrivateTmp=true
KillMode=control-group

[Install]
WantedBy=streamtool.service
```

Este servicio arranca el **nginx embebido** (`nginx_streamtool`) que sirve el panel y los HLS.

---

### 4.4. `/etc/systemd/system/streamtool-stats.service`

```ini
[Unit]
Description=Streamtool Stats Service
After=streamtool-fpm.service
Requires=streamtool-fpm.service

[Service]
Restart=always
Type=simple
ExecStart=/bin/sh -c '/opt/streamtool/app/php/bin/php -f /opt/streamtool/app/www/servicestat.php  2>&1 > /opt/streamtool/app/logs/statservice.log'
User=streamtool
Group=streamtool
KillMode=control-group

[Install]
WantedBy=default.target
```

Este servicio ejecuta periódicamente `servicestat.php` usando el **PHP embebido**.

---

### 4.5. `/etc/systemd/system/streamtool-watcher.service`

```ini
[Unit]
Description=Streamtool Watcher Service
After=streamtool-fpm.service
Requires=streamtool-fpm.service

[Service]
Restart=always
Type=simple
ExecStart=/bin/sh -c '/opt/streamtool/app/php/bin/php /opt/streamtool/app/www/cron.php  2>&1 > /opt/streamtool/app/logs/watcher.log'
User=streamtool
Group=streamtool
KillMode=control-group

[Install]
WantedBy=default.target
```

Este servicio ejecuta el watcher (`cron.php`), que se encarga de monitorizar streams, duraciones, etc.

---

### 4.6. Crear los servicios en la nueva máquina

Crea los archivos anteriores en `/etc/systemd/system`:

```bash
sudo nano /etc/systemd/system/streamtool.service
# pega el contenido del bloque 4.1

sudo nano /etc/systemd/system/streamtool-fpm.service
# pega el contenido del bloque 4.2

sudo nano /etc/systemd/system/streamtool-webserver.service
# pega el contenido del bloque 4.3

sudo nano /etc/systemd/system/streamtool-stats.service
# pega el contenido del bloque 4.4

sudo nano /etc/systemd/system/streamtool-watcher.service
# pega el contenido del bloque 4.5
```

Recarga systemd y habilita los servicios:

```bash
sudo systemctl daemon-reload
sudo systemctl enable streamtool streamtool-fpm streamtool-webserver streamtool-stats streamtool-watcher
sudo systemctl start  streamtool streamtool-fpm streamtool-webserver streamtool-stats streamtool-watcher
```

Comprueba que están activos:

```bash
systemctl status streamtool streamtool-fpm streamtool-webserver streamtool-stats streamtool-watcher
```

---

## 5. Puertos y firewall

El nginx embebido (`nginx_streamtool`) escucha en el puerto definido en sus configs (dentro de `app/nginx/conf/`).

Puedes localizar el puerto así:

```bash
grep -R "listen" -n /opt/streamtool/app/nginx/conf
```

Ejemplo (no literal):

```nginx
server {
    listen 9001;
    server_name _;
    root /opt/streamtool/app/www;
    ...
}
```

Asegúrate de que el firewall lo permita:

```bash
sudo ufw allow 9001/tcp     # ajusta 9001 al puerto real
sudo ufw reload
```

---

## 6. Acceso al panel

Una vez arrancados los servicios y abierto el puerto:

- Desde tu navegador:

  ```text
  http://IP_DE_TU_UBUNTU24:PUERTO
  ```

- Deberías ver la página de login y luego el panel de Streams.

Funciones que deben estar operativas:

- Listado de streams.
- Acciones masivas (MASS START, MASS STOP, MASS DELETE).
- MASS EDIT (cambio masivo de categoría y perfil de transcode).
- Uptime mejorado.
- Reproductor interno HLS en un modal (botón con icono de TV).

---

## 7. Logs y resolución de problemas

Si algo falla en la instalación:

### 7.1. Logs del panel PHP

```bash
tail -n 80 /opt/streamtool/app/www/logs/php_dashboard_error.log
tail -n 80 /opt/streamtool/app/www/logs/php_backend_error.log  # si existe
```

### 7.2. Logs de servicios systemd

```bash
journalctl -u streamtool-webserver.service -n 80 --no-pager
journalctl -u streamtool-fpm.service      -n 80 --no-pager
journalctl -u streamtool-watcher.service  -n 80 --no-pager
journalctl -u streamtool-stats.service    -n 80 --no-pager
```

Busca errores relacionados con:

- Permisos de carpetas (`logs`, `hls`, `cache`).
- Puertos ocupados.
- Rutas incorrectas (si cambiaste `/opt/streamtool` por otra).

---

## 8. Notas sobre compatibilidad Ubuntu 20 → 24

Aunque el autor original indique “Ubuntu 20” como versión soportada:

- El panel **no depende** del PHP ni del nginx del sistema.
- Usa sus propios binarios en `/opt/streamtool/app/php` y `/opt/streamtool/app/nginx`.
- `systemd` se comporta igual en Ubuntu 20 y 24 para estos servicios simples.

Por tanto, la compatibilidad con Ubuntu 24.04 es plena siempre que:

1. El árbol `/opt/streamtool` se haya desplegado correctamente (por ejemplo, clonando este repositorio).
2. Los servicios systemd estén configurados con rutas correctas.
3. El usuario `streamtool` tenga permisos de escritura donde se necesitan.

---