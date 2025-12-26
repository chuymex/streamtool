# Streamtool Panel (custom fork)

Panel de administración para streams IPTV, basado en el panel original de **Streamtool**, con varias mejoras y personalizaciones:

- Soporte probado en **Ubuntu 20.04** y **Ubuntu 24.04**.
- **PHP-FPM** y **nginx** embebidos dentro de `/opt/streamtool/app` (no dependen de PHP/nginx del sistema).
- Sistema de servicios `systemd` para:
  - Webserver
  - PHP-FPM
  - Watcher (cron)
  - Stats

Este fork incluye mejoras específicas:

- **MASS EDIT** funcional (cambio masivo de categoría y perfil de transcode).
- **Corrección del checkbox global** (selección masiva de streams).
- **Cálculo de uptime mejorado**.
- **Reproductor interno HLS** embebido en el panel (modal estético y responsivo).
- Ajustes de JS (DataTables, iCheck) para que todo funcione de forma consistente.

---

## Características principales

### 1. Gestión de Streams

- Listado de streams con:
  - Nombre.
  - Estado (RUNNING / STOPPED / etc.).
  - Categoría.
  - Codecs de entrada (video/audio).
  - Codecs de salida (perfil de transcode).
  - Uptime y FPS.
- Acciones individuales:
  - **Start**, **Stop**, **Restart**, **Edit**, **Delete**.

### 2. Acciones masivas (MASS ACTIONS)

En la vista de Streams:

- **MASS START**
- **MASS STOP**
- **MASS DELETE**
- **MASS EDIT** (panel desplegable):

  Permite aplicar a varios streams seleccionados:

  - Nueva **categoría**.
  - Nuevo **perfil de transcode**.

El checkbox global en el encabezado vuelve a funcionar correctamente, marcando y desmarcando todos los streams gracias a la integración con **iCheck**.

### 3. Uptime mejorado

Se ha revisado y mejorado el cálculo de uptime:

- Se combina la duración reportada por el watcher (`duration`) con un campo `uptime_started_at` para obtener una visualización más coherente del tiempo que lleva un stream en **RUNNING**.
- Gestión de estados:
  - `RUNNING` → muestra uptime y FPS.
  - Otros estados → muestra información de error / stop.

### 4. Reproductor interno HLS

Cada stream tiene ahora un botón de **preview** con icono de TV:

- Abre un **modal** Bootstrap más pequeño y estético.
- El modal incluye un `<video>` HTML5 con soporte para **HLS** mediante `hls.js`.
- Ejemplo de URL de reproducción (ajustable):

  ```text
  /hls/{id}_.m3u8
  ```

- Soporta:
  - HLS nativo en navegadores compatibles.
  - `hls.js` en navegadores que no permiten HLS nativamente (Chrome, Firefox, etc.).

El modal:

- Está centrado.
- Usa un contenedor 16:9 para mantener proporción.
- Detiene y limpia el video al cerrar.

---

## Requisitos y compatibilidad

### Sistemas operativos probados

- **Ubuntu 20.04** (plataforma original recomendada).
- **Ubuntu 24.04** (validado con esta guía y setup de servicios embebidos).

### Dependencias externas mínimas

En la máquina donde se despliega el panel:

- `git`
- `curl`
- `sudo`
- (Opcional) `ufw` para gestión de firewall.

El panel NO requiere:

- `php-fpm` del sistema.
- `nginx` del sistema.

porque utiliza sus propios binarios embebidos en:

- `/opt/streamtool/app/php`
- `/opt/streamtool/app/nginx`

---

## Instalación en Ubuntu 24.04

Para instrucciones detalladas de despliegue en Ubuntu 24.04, consulta:

- [`INSTALL_UBUNTU24.md`](./INSTALL_UBUNTU24.md)

Ese documento explica:

- Creación del usuario/grupo `streamtool`.
- Clonado del repo en `/opt/streamtool`.
- Configuración de permisos (`logs`, `hls`, etc.).
- Creación y activación de los servicios systemd:
  - `streamtool.service`
  - `streamtool-fpm.service`
  - `streamtool-webserver.service`
  - `streamtool-stats.service`
  - `streamtool-watcher.service`
- Apertura de puertos en el firewall.
- Pruebas básicas del panel.

---

## Estructura relevante del proyecto

Fragmento simplificado de la estructura bajo `/opt/streamtool`:

```text
/opt/streamtool
├── app
│   ├── nginx
│   │   ├── sbin/nginx_streamtool      # nginx embebido
│   │   └── conf/                      # configuración de nginx (puerto, vhosts, etc.)
│   ├── php
│   │   ├── bin/php                    # PHP CLI embebido
│   │   ├── sbin/php-fpm               # PHP-FPM embebido
│   │   └── etc/php-fpm.conf           # Config de FPM
│   └── www
│       ├── index.php
│       ├── streams.php                # Lógica de listado/control de streams
│       ├── functions.php              # Funciones comunes
│       ├── views/
│       │   └── streams.blade.php      # Vista principal de Streams (MASS EDIT, reproductor interno, etc.)
│       ├── hls/                       # Segmentos HLS y playlists (.ts, .m3u8)
│       └── logs/                      # Logs del panel
├── INSTALL_UBUNTU24.md
└── README.md
```

---

## Personalizaciones clave de este fork

### 1. `views/streams.blade.php`

- Se añadió el panel de **MASS EDIT** con campos:
  - `mass_edit_category`
  - `mass_edit_transcode`
- Se corrigió la integración de:
  - `iCheck` (eventos `ifChecked` / `ifUnchecked`).
  - Checkbox global con `id="check-all"` y clase `tableflat` en el header.
  - Checkboxes de fila con `name="mselect[]"` y clase `tableflat check`.
- Se integró el **reproductor interno HLS**:
  - Botón azul `btn-open-player` con icono TV (`fas fa-tv`) en la columna Control.
  - Modal `#playerModal` con `<video id="streamPlayer">`.
  - Uso de `hls.js` para cargar `/hls/{id}_.m3u8`.

### 2. JS asociado

En la sección `@section('js')`:

- Inicialización de DataTables.
- Inicialización explícita de `iCheck`:

  ```js
  if ($.fn.iCheck) {
      $('input.tableflat').iCheck({
          checkboxClass: 'icheckbox_flat-green',
          radioClass: 'iradio_flat-green'
      });
  }
  ```

- Lógica del checkbox global:

  ```js
  $('.bulk_action input#check-all').on('ifChecked', function() {
      check_state = 'check_all';
      countChecked();
  });
  $('.bulk_action input#check-all').on('ifUnchecked', function() {
      check_state = 'uncheck_all';
      countChecked();
  });
  ```

- Función `countChecked()` que hace:

  ```js
  if (check_state == 'check_all') {
      $(".bulk_action input[name='mselect[]']").iCheck('check');
  }
  if (check_state == 'uncheck_all') {
      $(".bulk_action input[name='mselect[]']").iCheck('uncheck');
  }
  ```

- Integración del reproductor HLS con `hls.js`, carga dinámica de URL:

  ```js
  var streamUrl = '/hls/' + streamId + '_.m3u8';
  ```

  (Ruta ajustable según tu configuración real de HLS).

---

## Desarrollo y contribuciones

Este repositorio refleja el estado **en producción** del servidor del usuario, incluyendo:

- Fixes de bugs del panel original.
- Mejoras de UX en Streams (MASS EDIT, reproductor, uptime).
- Integración con systemd en `/etc/systemd/system/`.

Si quieres contribuir o adaptar este fork:

1. Crea una rama nueva:

   ```bash
   git checkout -b feature/nueva-mejora
   ```

2. Haz tus cambios, commits y push:

   ```bash
   git add .
   git commit -m "Describe la mejora"
   git push -u origin feature/nueva-mejora
   ```

3. Abre un Pull Request en GitHub.

---

## Notas de seguridad

- No versiones archivos con credenciales reales (por ejemplo, `.env` o `config.php` con passwords).
- Usa `.gitignore` (ya incluido) para excluir:
  - `app/www/hls/`
  - `app/www/logs/`
  - `app/www/cache/`
  - `vendor/`
  - `.env`

Si en algún momento se subieron credenciales, es recomendable rotar esos datos (cambiar contraseñas en DB, usuarios de panel, etc.).

---

## Soporte / Contacto

Este es un fork personalizado. Para dudas generales sobre:

- Instalación en Ubuntu 24.
- Problemas con systemd (`streamtool-fpm`, `streamtool-webserver`, etc.).
- Ajustes del reproductor HLS interno.

puedes abrir un **Issue** en este repositorio describiendo:

- Sistema operativo (ej. Ubuntu 24.04).
- Versión del repo (commit, rama).
- Logs relevantes (`php_dashboard_error.log`, `journalctl -u streamtool-webserver`, etc.).
