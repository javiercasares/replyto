# Scripts de Deployment - Reply-To for WP_Mail

Esta carpeta contiene scripts de utilidad para el deployment y empaquetado del plugin.

---

## 📦 deploy.sh

Script de deployment para crear paquetes ZIP listos para producción.

### Uso

```bash
./bin/deploy.sh <version>
```

### Ejemplo

```bash
./bin/deploy.sh 1.1.0
```

### Qué hace

1. **Valida la versión**
   - Verifica formato de versión (x.x.x)
   - Comprueba que la versión coincida en `replyto.php` y `readme.txt`

2. **Crea directorio temporal**
   - Genera un directorio de build limpio
   - Copia solo archivos de producción

3. **Excluye archivos de desarrollo**
   - `.git/`, `.github/`, `.claude/`
   - `vendor/`, `bin/`, `docs/`
   - `composer.json`, `composer.lock`
   - `CLAUDE.md` y archivos de desarrollo

4. **Incluye archivos de producción**
   - `replyto.php` - Plugin principal
   - `uninstall.php` - Script de desinstalación
   - `readme.txt` - Descripción WordPress.org
   - `LICENSE` - Licencia GPL
   - `assets/` - Assets del plugin
   - `languages/` - Archivos de traducción

5. **Crea ZIP**
   - Genera `replyto-x.x.x.zip` en el directorio padre
   - Muestra tamaño y número de archivos
   - Limpia archivos temporales

### Salida

El script crea el archivo ZIP en la carpeta padre del plugin:

```
wp-content/plugins/
├── replyto/              # Carpeta del plugin
│   ├── bin/
│   │   └── deploy.sh
│   ├── replyto.php
│   └── ...
└── replyto-1.1.0.zip    # ← ZIP generado aquí
```

### Requisitos

- **Bash** 4.0 o superior
- **zip** instalado en el sistema
- Permisos de ejecución (`chmod +x bin/deploy.sh`)

### Verificación de Versión

Antes de crear el ZIP, el script verifica que la versión especificada coincida en:

1. **replyto.php** - Header `Version: x.x.x`
2. **readme.txt** - Campo `Stable tag: x.x.x`

Si las versiones no coinciden, el script falla con un error.

### Mensajes del Script

El script usa colores para facilitar la lectura:

- 🔵 **Azul**: Información
- 🟢 **Verde**: Éxito
- 🟡 **Amarillo**: Advertencia
- 🔴 **Rojo**: Error

### Ejemplo de Ejecución

```bash
$ ./bin/deploy.sh 1.1.0

==================================
  Deploy Script - replyto
==================================

ℹ️  Step 1/6: Validating...
✅ Plugin directory verified
✅ Version 1.1.0 verified in all files

ℹ️  Step 2/6: Preparing build...
✅ Temporary directory created: /path/to/plugins/replyto-build-1.1.0

ℹ️  Step 3/6: Copying production files...
✅ Copied assets/
✅ Copied languages/
✅ All production files copied

ℹ️  Step 4/6: Excluding development files...
ℹ️  Excluded from deployment:
  🚫 .git/
  🚫 .github/
  🚫 .claude/
  🚫 vendor/
  🚫 bin/
  🚫 docs/
  🚫 composer.json
  🚫 composer.lock
  🚫 CLAUDE.md
  🚫 Development files

ℹ️  Step 5/6: Building package...
✅ ZIP file created: /path/to/plugins/replyto-1.1.0.zip

ℹ️  Step 6/6: Finalizing...
✅ Temporary files removed
ℹ️  Package information:
  📦 File: replyto-1.1.0.zip
  📏 Size: 12K
  📄 Files: 8

==================================
  ✅ Deployment Complete!
==================================

✅ Package ready: replyto-1.1.0.zip
✅ Location: /path/to/plugins/replyto-1.1.0.zip

ℹ️  Next steps:
  1. Test the ZIP file in a clean WordPress installation
  2. Verify all functionality works correctly
  3. Upload to WordPress.org (if applicable)
```

### Solución de Problemas

#### Error: "Version not found in replyto.php"

**Causa:** La versión en el header del plugin no coincide con la especificada.

**Solución:** Actualizar la versión en `replyto.php`:
```php
* Version: 1.1.0
```

#### Error: "Version not found in readme.txt"

**Causa:** La versión en readme.txt no coincide.

**Solución:** Actualizar `readme.txt`:
```
Stable tag: 1.1.0
```

#### Error: "Plugin main file not found"

**Causa:** El script no se está ejecutando desde el directorio correcto.

**Solución:** Ejecutar desde la raíz del plugin:
```bash
cd /path/to/replyto
./bin/deploy.sh 1.1.0
```

#### Error: "Permission denied"

**Causa:** El script no tiene permisos de ejecución.

**Solución:**
```bash
chmod +x bin/deploy.sh
```

---

## 🔧 Desarrollo de Nuevos Scripts

Si necesitas crear scripts adicionales, añádelos a esta carpeta con el formato:

```bash
#!/bin/bash
# Descripción del script
# Usage: ./bin/script-name.sh
```

### Convenciones

- Usar **bash** como shell
- Incluir `set -e` para fallar en errores
- Añadir mensajes informativos coloreados
- Documentar uso en comentarios del header
- Hacer scripts ejecutables: `chmod +x`
- Actualizar este README con la documentación

---

## 📋 Checklist Pre-Deployment

Antes de ejecutar el script de deployment:

- [ ] Todas las pruebas pasan
- [ ] Versión actualizada en `replyto.php`
- [ ] Versión actualizada en `readme.txt`
- [ ] Changelog actualizado en `readme.txt`
- [ ] Código cumple WordPress Coding Standards
- [ ] Compatibilidad PHP verificada
- [ ] Git commit realizado (si aplica)
- [ ] Git tag creado con la versión (si aplica)

---

## 🚀 Workflow Recomendado

### 1. Preparación

```bash
# Actualizar versión en archivos
vim replyto.php     # Cambiar Version:
vim readme.txt      # Cambiar Stable tag:

# Verificar con git
git diff
```

### 2. Commit y Tag

```bash
# Commit de cambios
git add .
git commit -m "Release version 1.1.0"

# Crear tag
git tag -a v1.1.0 -m "Version 1.1.0 - Security enhancements"
git push origin main --tags
```

### 3. Build

```bash
# Crear ZIP de deployment
./bin/deploy.sh 1.1.0
```

### 4. Verificación

```bash
# Verificar contenido del ZIP
unzip -l ../replyto-1.1.0.zip

# Probar en WordPress limpio
# (Copiar ZIP a instalación de prueba y activar)
```

### 5. Publicación

```bash
# Subir a WordPress.org SVN (si aplica)
# O distribuir ZIP según proceso interno
```

---

## 📝 Notas

- Los scripts en `bin/` **nunca** se incluyen en el ZIP final
- La carpeta `docs/` se excluye para mantener el paquete ligero
- Los archivos de desarrollo (composer, vendor) no se incluyen
- El ZIP resultante contiene solo lo necesario para producción

---

## 🔗 Enlaces Relacionados

- [CLAUDE.md](../CLAUDE.md) - Guía de desarrollo completa
- [docs/](../docs/) - Documentación técnica
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)

---

**Última actualización:** 2026-01-20
