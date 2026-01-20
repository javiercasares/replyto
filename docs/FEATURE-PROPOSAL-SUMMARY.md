# Resumen - Múltiples Reply-To por Contexto

**Documento completo:** [FEATURE-PROPOSAL-MULTIPLE-CONTEXTS.md](FEATURE-PROPOSAL-MULTIPLE-CONTEXTS.md)

---

## 🎯 Propuesta en 30 Segundos

Permitir diferentes Reply-To según el tipo de email:
- **Password resets** → security@example.com
- **Comentarios** → moderation@example.com
- **Nuevos usuarios** → users@example.com
- **Sistema/Updates** → admin@example.com
- **Todo lo demás** → support@example.com (default)

---

## 📋 Contextos Propuestos

| # | Contexto | Emails que incluye | Funciones WordPress |
|---|----------|-------------------|---------------------|
| 1 | **Default** | Todo (fallback) | - |
| 2 | **Authentication** | Password resets, cambios email | `retrieve_password()`, `wp_password_change_notification()` |
| 3 | **Comments** | Notificaciones comentarios | `wp_notify_postauthor()`, `wp_notify_moderator()` |
| 4 | **Users** | Registros, cambios rol | `wp_new_user_notification()` |
| 5 | **System** | Updates, errores críticos | `WP_Automatic_Updater`, `send_core_update_notification_email()` |
| 6 | **WooCommerce** *(opcional)* | Pedidos, facturas | hooks de WooCommerce |

---

## 🔍 Detección de Contexto: 3 Métodos Posibles

### Método 1: Backtrace (⭐ Recomendado)

```php
// Analiza qué función de WordPress envió el email
$backtrace = debug_backtrace();
if ( in_array( 'retrieve_password', $functions ) ) {
    return 'authentication';
}
```

**Pros:** Muy preciso, no depende del contenido del email
**Contras:** Overhead ~0.1ms
**Decisión:** ✅ Usar este para v1.3.0

### Método 2: Subject/Headers

```php
if ( stripos( $subject, 'password reset' ) !== false ) {
    return 'authentication';
}
```

**Pros:** Muy rápido
**Contras:** Menos preciso, puede fallar con traducciones

### Método 3: Hooks Específicos

```php
add_filter( 'retrieve_password_message', 'set_context_auth' );
global $current_context = 'authentication';
```

**Pros:** Preciso y eficiente
**Contras:** Requiere muchos hooks, más código

---

## 🎨 UI Propuesta (Opción Simple)

```
┌─────────────────────────────────────────┐
│ Reply-To Configuration                  │
├─────────────────────────────────────────┤
│                                         │
│ ▼ Default (All Emails) - REQUIRED      │
│   Email: [support@example.com    ]     │
│   Name:  [Support Team           ]     │
│                                         │
│ ☑ Authentication & Password Resets      │
│   Email: [security@example.com   ]     │
│   Name:  [Security Team          ]     │
│   → Password resets, email changes     │
│                                         │
│ ☐ Comments & Moderation                 │
│   Email: [moderation@example.com ]     │
│   Name:  [Moderation Team        ]     │
│   → Comment notifications              │
│                                         │
│ ☐ Users & Registration                  │
│   Email: [                       ]     │
│   Name:  [                       ]     │
│   → New users, role changes            │
│                                         │
│ ☐ System & Updates                      │
│   Email: [                       ]     │
│   Name:  [                       ]     │
│   → Automatic updates, site health     │
│                                         │
│ [Save Settings]                         │
└─────────────────────────────────────────┘
```

**Características:**
- Checkbox habilita/deshabilita contexto
- Texto explicativo de qué emails incluye
- Sin JavaScript obligatorio
- Compatible con WordPress UI

---

## 💾 Base de Datos

### Estructura Recomendada

Una sola opción con array:

```php
wp_mail_replyto_contexts = array(
    'default' => array(
        'email' => 'support@example.com',
        'name'  => 'Support',
        'enabled' => true  // Siempre true
    ),
    'authentication' => array(
        'email' => 'security@example.com',
        'name'  => 'Security',
        'enabled' => true
    ),
    'comments' => array(
        'email' => '',
        'name'  => '',
        'enabled' => false  // Usará default
    ),
    // ...
);
```

**Ventajas:**
- ✅ 1 query en vez de 8-10
- ✅ Fácil de gestionar
- ✅ Fácil de exportar/importar

---

## 🔄 Migración desde v1.2.0

### Automática y Transparente

```php
// Al actualizar a v1.3.0
Config antigua:
- wp_mail_replyto_email = "support@example.com"
- wp_mail_replyto_name = "Support"

Se convierte en:
- contexts['default']['email'] = "support@example.com"
- contexts['default']['name'] = "Support"
- contexts['default']['enabled'] = true
- Otros contextos con enabled = false

Resultado: Funciona exactamente igual que v1.2.0
```

**Sin acción requerida del usuario.**

---

## 📊 Ejemplo de Uso

### Empresa Mediana

```
Default:        info@company.com         - Info
Authentication: security@company.com     - Security
Comments:       blog@company.com         - Blog Team
Users:          hr@company.com           - HR
System:         (disabled - usa default)
```

**Flujo:**
1. Usuario olvida contraseña
2. WordPress llama `retrieve_password()`
3. Plugin detecta contexto = "authentication"
4. Usa Reply-To: Security <security@company.com>
5. Usuario responde al email → va a Security

---

## 🎯 Plan de Implementación

### v1.3.0 - Core (4 semanas)

**Semana 1: Backend**
- Estructura de datos
- Detección de contexto (backtrace)
- Migración automática

**Semana 2: UI**
- Formulario con checkboxes
- Validación y sanitización
- Mensajes de ayuda

**Semana 3: Testing**
- Tests de detección
- Tests de fallback
- Tests de migración
- PHPCS + Compatibilidad

**Semana 4: Docs**
- Actualizar readme.txt
- Crear CHANGELOG-1.3.0.md
- Actualizar CLAUDE.md
- Guía de usuario

### v1.4.0 - Extensiones (futuro)

- Contexto WooCommerce
- Contexto bbPress/BuddyPress
- UI mejorada (tabs/accordions)
- Email de prueba por contexto

---

## ⚡ Impacto

### Rendimiento

| Aspecto | Impacto |
|---------|---------|
| Queries DB | +1 get_option (cached) |
| CPU | +0.1-0.2ms (backtrace) |
| Memoria | +5 KB |
| Tamaño archivo | +~10 KB |

**Conclusión:** ✅ Impacto mínimo

### Compatibilidad

| Aspecto | Estado |
|---------|--------|
| Backward compatibility | ✅ 100% |
| WordPress 4.1+ | ✅ Compatible |
| PHP 5.6+ | ✅ Compatible |
| Multisite | ✅ Compatible |

---

## ✅ Decisiones Aprobadas

### 1. Método de Detección

**Decisión:** ✅ Backtrace
**Razón:** Muy preciso, no depende del contenido del email

### 2. Contextos en v1.3.0

**Decisión:** ✅ Incluir todos: Default, Authentication, Comments, Users, System, WooCommerce (6 contextos)
**Razón:** Funcionalidad completa desde v1.3.0

### 3. UI Inicial

**Decisión:** ✅ Tabs modernos (primer tab = Default)
**Razón:** UI moderna manteniendo compatibilidad con WordPress
**Nota:** Header y botón de guardar mantienen estilo estándar de WordPress

### 4. Contexto System

**Decisión:** ✅ Incluido y opcional
**Razón:** Dar flexibilidad al usuario

### 5. Detección WooCommerce

**Decisión:** ✅ Siempre mostrar
**Razón:** Usuario puede configurarlo aunque no esté instalado todavía

### 6. Campos Obligatorios

**Decisión:** ✅ Ninguno es obligatorio
**Razón:** Máxima flexibilidad, fallback a Default

---

## 🚦 Semáforo de Complejidad

| Aspecto | Complejidad | Estimación |
|---------|-------------|------------|
| Detección contexto | 🟡 Media | 4-6 horas |
| Estructura datos | 🟢 Baja | 2-3 horas |
| UI básica | 🟢 Baja | 4-6 horas |
| Migración | 🟡 Media | 3-4 horas |
| Validación/Seguridad | 🟢 Baja | 2-3 horas |
| Testing | 🟡 Media | 6-8 horas |
| Documentación | 🟢 Baja | 4-6 horas |
| **TOTAL** | **🟡 Media** | **25-36 horas** |

**Complejidad general:** Media-Baja
**Riesgo:** Bajo (backward compatible)

---

## ✅ Siguiente Paso

1. **Revisar** esta propuesta
2. **Decidir** sobre las preguntas abiertas
3. **Aprobar** o solicitar cambios
4. **Implementar** según plan

---

**Estado de la Propuesta**

✅ **APROBADA** - Implementación en progreso para v1.3.0
