# CLAUDE.md — mod_mathdoku

Guía de mantenimiento para Claude Code. Stack: PHP 8.1+, Moodle 4.3+, AMD JS (define/require).

---

## Paths críticos del servidor

| Recurso | Ruta |
|---------|------|
| Plugin instalado | `/var/www/moodle/public/mod/mathdoku/` |
| CLI purge_caches | `/var/www/moodle/admin/cli/purge_caches.php` (sin `/public/`) |
| SSH aula | `alvaretto@192.168.0.19` |
| SSH casa | `alvaretto@192.168.1.19` |
| Plugin en moodle.org | https://moodle.org/plugins/mod_mathdoku |
| GitHub | https://github.com/alvaretto/mathdoku |

---

## Flujo de release completo

1. **Editar `version.php`**
   - `$plugin->release` → nuevo string (ej. `'1.0.2-beta'`)
   - `$plugin->version` → formato `YYYYMMDDXX` (ej. `2026041701`)
   - XX empieza en `00`; incrementar si hay más de un release el mismo día

2. **Reconstruir JS minificado** (si hubo cambios en `amd/src/mathdoku.js`)
   ```bash
   npx terser amd/src/mathdoku.js \
     --compress --mangle \
     --source-map "content=inline,url=mathdoku.min.js.map" \
     -o amd/build/mathdoku.min.js
   ```

3. **Commit y tag git**
   ```bash
   git add -A
   git commit -m "Release vX.Y.Z-beta"
   git tag vX.Y.Z-beta
   git push origin main
   git push origin vX.Y.Z-beta
   ```
   El tag debe existir en GitHub ANTES de actualizar el formulario en moodle.org.

4. **Construir ZIP**
   ```bash
   # Desde el directorio PADRE del repo (no dentro de mod_mathdoku)
   cp -r mod_mathdoku mathdoku
   zip -r mathdoku-X.Y.Z.zip mathdoku/ \
     --exclude "mathdoku/.git/*" \
     --exclude "mathdoku/tests/*" \
     --exclude "mathdoku/.gitignore" \
     --exclude "mathdoku/CLAUDE.md"
   rm -rf mathdoku
   # README.md SÍ se incluye (release notes en moodle.org)
   # tests/ SÍ se excluye (evita PHPCS sobre tests)
   ```

5. **Publicar en moodle.org**
   - Ir a https://moodle.org/plugins/mod_mathdoku → pestaña **Developer zone**
   - Sección "Releasing a new version" → botón **"Add a new version"**
   - Subir el ZIP construido en el paso anterior
   - **NUNCA usar "Edit version"** para publicar código nuevo: el checker automático NO se re-ejecuta al editar, solo al subir un ZIP nuevo via "Add a new version"
   - La pestaña "Versions" solo tiene "Edit details" y "Hide" — no permite crear versiones

   **Notas críticas:**
   - El formulario pre-selecciona Moodle 4.2 y no permite deseleccionarla → mantener `$plugin->requires = 2023041800` en `version.php`
   - Si un build number ya fue usado (aunque sea editando una versión existente), usar el siguiente XX disponible
   - El tag git debe existir en GitHub ANTES de completar el formulario

---

## Flujo de deploy al servidor

### Deploy completo (múltiples archivos)
```bash
# 1. Transferir al /tmp del servidor con rsync
rsync -av --delete --exclude='.git' --exclude='tests/' \
  /mnt/datos/Documentos/Proyectos/Moodle/mod_mathdoku/ \
  alvaretto@192.168.0.19:/tmp/mathdoku/

# 2. Mover al directorio real con sudo
ssh alvaretto@192.168.0.19 "sudo rsync -av --delete /tmp/mathdoku/ /var/www/moodle/public/mod/mathdoku/"

# 3. Purgar caché
ssh alvaretto@192.168.0.19 "sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php"
```

### Deploy de archivo único
```bash
scp archivo alvaretto@192.168.0.19:/tmp/archivo
ssh alvaretto@192.168.0.19 "sudo cp /tmp/archivo /var/www/moodle/public/mod/mathdoku/ruta/archivo"
ssh alvaretto@192.168.0.19 "sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php"
```

### Crear directorio nuevo en el servidor
```bash
ssh alvaretto@192.168.0.19 "sudo mkdir -p /var/www/moodle/public/mod/mathdoku/nuevo/directorio"
```

**Nota:** scp directo al directorio del plugin falla con "Permission denied" (dueño www-data). Siempre pasar por `/tmp`.

---

## Errores conocidos y correcciones

| Error | Causa | Corrección |
|-------|-------|-----------|
| `YYYYMMDDXX` rechazado en moodle.org | Formato incorrecto del build number | Usar `YYYYMMDDXX` exacto, ej. `2026041700` |
| "Could not open input file" al purgar caché | Ruta `/public/admin/cli/...` incorrecta | Usar `/var/www/moodle/admin/cli/purge_caches.php` |
| ZIP rechazado en moodle.org | Carpeta raíz se llama `mod_mathdoku/` | La carpeta raíz del ZIP debe llamarse `mathdoku/` |
| Source control URL rechazada | URL termina en `.git` | Usar `https://github.com/alvaretto/mathdoku` sin `.git` |
| Cambios JS sin efecto en producción | `amd/build/mathdoku.min.js` no fue reconstruido | Ejecutar terser tras cada cambio en `amd/src/` |
| PHPCS: variable con guión bajo | `$attempt_number` no cumple estándar Moodle | Renombrar a `$attemptnumber` (todo minúsculas) |
| PHPCS: MOODLE_INTERNAL en clase autoloaded | La comprobación no corresponde en `classes/` | Eliminar `defined('MOODLE_INTERNAL') \|\| die();` de clases PSR-4 |
| Test falla con rutas relativas | Test copiado a `/tmp`, `__DIR__` resuelve a `/tmp` | Copiar test a `/var/www/moodle/public/mod/mathdoku/tests/` |
| `php` no encontrado en local | PHP no instalado en Manjaro (máquina de desarrollo) | Ejecutar tests vía SSH en el servidor |
| Checker muestra errores del ZIP anterior | Se usó "Edit version" en lugar de crear versión nueva | Usar SIEMPRE "Add a new version" en Developer zone para publicar código corregido |
| "Add a new version" no aparece en pestaña Versions | La opción está en Developer zone, no en Versions | Plugins → MathDoku → Developer zone → "Releasing a new version" → "Add a new version" |
| "There is already a plugin version with this version number" | Build number ya registrado por edición previa | Incrementar XX del build: `2026041701 → 2026041702 → 2026041703` |
| "will not install on Moodle 4.2" al crear versión | `$plugin->requires = 2023100900` (Moodle 4.3) pero formulario fuerza 4.2 | Usar `$plugin->requires = 2023041800` (Moodle 4.2) en version.php |
| Warning "no README file found" en moodle.org | README.md fue excluido del ZIP | Incluir README.md en el ZIP (no excluirlo) |

---

## Historial de versiones publicadas

| Versión | Build number | Fecha | Notas |
|---------|-------------|-------|-------|
| v1.0.0-beta | 2026041600 | 2026-04-17 | Release inicial. ZIP con errores PHPCS. Ticket CONTRIB-10468. |
| v1.0.1-beta | 2026041701 | 2026-04-17 | Solo metadatos editados (Edit version). Checker no re-corrió — no sirve. |
| v1.0.2-beta | 2026041703 | 2026-04-17 | ZIP nuevo subido via "Add a new version". Código PHPCS corregido. |

---

## Coding standards Moodle

### PHP — Variables locales
Todo minúsculas, sin guiones bajos, sin camelCase:
- Correcto: `$attemptnumber`, `$griddata`, `$userid`
- Incorrecto: `$attempt_number`, `$attemptNumber`, `$grid_Data`

### PHP — MOODLE_INTERNAL
La línea `defined('MOODLE_INTERNAL') || die();` solo va en archivos de inclusión directa (`lib.php`, `locallib.php`, `version.php`, etc.).
**No usar** en clases de `classes/` (autoloaded por Moodle vía PSR-4) ni en tests PHPUnit.

### JS — Build AMD obligatorio
Tras cada cambio en `amd/src/mathdoku.js`, reconstruir:
```bash
npx terser amd/src/mathdoku.js \
  --compress --mangle \
  --source-map "content=inline,url=mathdoku.min.js.map" \
  -o amd/build/mathdoku.min.js
```
Moodle sirve `amd/build/mathdoku.min.js` en producción, no el fuente.

### Escala de calificación
La escala es 0–5 (colombiana). El plugin almacena internamente 0 o 100, pero muestra al estudiante en escala 0–5:
```php
number_format($grade * 5 / 100, 1)  // → "0.0" o "5.0"
```
Los strings de idioma deben decir `/ 5`, nunca `/ 100`.

---

## Estructura del repositorio

```
mod_mathdoku/
├── amd/
│   ├── src/mathdoku.js      # Fuente JS (editar aquí)
│   └── build/mathdoku.min.js # Minificado (generar con terser)
├── classes/                  # Clases PHP autoloaded (sin MOODLE_INTERNAL)
├── db/                       # install.xml, upgrade.php, access.php
├── lang/                     # Strings de idioma
├── tests/                    # Tests PHPUnit (ejecutar en servidor)
├── lib.php                   # Hooks Moodle (con MOODLE_INTERNAL)
├── locallib.php              # Lógica interna (con MOODLE_INTERNAL)
├── mod_form.php              # Formulario de creación/edición
├── version.php               # Versión del plugin
└── view.php                  # Vista principal del estudiante
```

---

## Persistencia del grid del estudiante

Dos capas independientes:
- **localStorage** (inmediato, por tecla): rescate offline, clave `mathdoku_grid_{attemptId}`
- **Servidor/DB** (2s debounce + sendBeacon en beforeunload): fuente autoritativa

Reglas:
- Al entregar (`submit`): limpiar localStorage con `removeItem` para no restaurar un intento ya terminado
- En page load: si el servidor no tiene datos → usar localStorage → enviar al servidor
