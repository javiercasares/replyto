# Propuesta de Funcionalidad - Múltiples Reply-To por Contexto

**Versión:** 1.3.0 (propuesta)
**Fecha:** 2026-01-20
**Estado:** 📋 Documentación / Diseño
**Autor:** Análisis técnico previo a implementación

---

## 📋 Resumen Ejecutivo

Esta propuesta detalla la implementación de **múltiples configuraciones de Reply-To por contexto**, permitiendo a los administradores configurar diferentes direcciones de Reply-To según el tipo de email que WordPress envía.

**Problema a resolver:**
Actualmente el plugin usa una única configuración de Reply-To para todos los emails. Esto no es ideal porque:
- Emails de recuperación de contraseña deberían ir a soporte técnico
- Comentarios deberían ir al equipo de moderación
- Notificaciones de nuevos usuarios deberían ir a administración
- Emails del sistema no deberían tener Reply-To (son automáticos)

**Solución propuesta:**
Permitir configurar Reply-To diferentes según el contexto del email.

---

## 🎯 Contextos Propuestos

### 1. **Por Defecto** (Global)
**Prioridad:** Obligatorio

- Se aplica cuando ningún otro contexto específico está configurado
- Es el comportamiento actual del plugin
- Siempre debe existir como fallback

### 2. **Autenticación y Seguridad**
**Emails incluidos:**
- Recuperación de contraseña (`retrieve_password_*`)
- Cambio de contraseña (`password_change_email`)
- Cambio de dirección de email (`email_change_email`)
- Confirmación de login en nuevo dispositivo

**Hooks de WordPress:**
```php
retrieve_password_title
retrieve_password_message
password_change_email
email_change_email
```

**Caso de uso:**
```
Email: security@example.com
Name: Security Team
```

### 3. **Comentarios y Moderación**
**Emails incluidos:**
- Notificación de nuevo comentario al autor del post
- Notificación de comentario pendiente de moderación
- Respuestas a comentarios

**Hooks de WordPress:**
```php
comment_notification_recipients
comment_notification_subject
comment_notification_text
comment_moderation_recipients
comment_moderation_subject
comment_moderation_text
```

**Funciones de WordPress que envían estos emails:**
- `wp_notify_postauthor()` - Notifica al autor del post
- `wp_notify_moderator()` - Notifica a moderadores

**Caso de uso:**
```
Email: moderation@example.com
Name: Moderation Team
```

### 4. **Usuarios y Registros**
**Emails incluidos:**
- Nuevo registro de usuario
- Notificación al admin sobre nuevo usuario
- Cambio de rol de usuario
- Activación de cuenta

**Hooks de WordPress:**
```php
wp_new_user_notification_email        // Email al nuevo usuario
wp_new_user_notification_email_admin  // Email al admin
wp_send_new_user_notifications        // Determina qué notificaciones enviar
```

**Funciones de WordPress:**
- `wp_new_user_notification()`
- `wp_send_new_user_notifications()`

**Caso de uso:**
```
Email: users@example.com
Name: User Management
```

### 5. **Administración y Sistema**
**Emails incluidos:**
- Actualizaciones automáticas de WordPress
- Actualizaciones de plugins
- Actualizaciones de temas
- Errores críticos del sitio
- Avisos de mantenimiento

**Hooks de WordPress:**
```php
auto_core_update_email          // WordPress core updates
auto_plugin_update_email        // Plugin updates
auto_theme_update_email         // Theme updates
recovery_mode_email             // Recovery mode
site_status_tests               // Site health emails
```

**Caso de uso:**
```
Email: admin@example.com
Name: Site Administrator
```

### 6. **WooCommerce** (Opcional - si está instalado)
**Emails incluidos:**
- Nuevos pedidos
- Pedidos completados
- Facturas
- Notas al cliente

**Detección:**
- Verificar si WooCommerce está activo: `class_exists( 'WooCommerce' )`

**Hooks de WooCommerce:**
```php
woocommerce_email_headers       // Modificar headers de email
woocommerce_email_from_address  // DirecciónFrom
woocommerce_email_from_name     // Nombre From
```

**Caso de uso:**
```
Email: shop@example.com
Name: Shop Support
```

### 7. **bbPress/BuddyPress** (Opcional - si están instalados)
**Emails incluidos:**
- Notificaciones de foro
- Mensajes privados
- Actividad social

**Detección:**
- bbPress: `class_exists( 'bbPress' )`
- BuddyPress: `function_exists( 'bp_is_active' )`

---

## 🏗️ Arquitectura Técnica

### Detección de Contexto

**Método 1: Análisis de Backtrace (Recomendado)**

```php
function wp_mail_replyto_detect_context() {
    $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );

    foreach ( $backtrace as $trace ) {
        if ( ! isset( $trace['function'] ) ) {
            continue;
        }

        // Autenticación
        if ( in_array( $trace['function'], array(
            'retrieve_password',
            'reset_password',
            'wp_password_change_notification'
        ) ) ) {
            return 'authentication';
        }

        // Comentarios
        if ( in_array( $trace['function'], array(
            'wp_notify_postauthor',
            'wp_notify_moderator'
        ) ) ) {
            return 'comments';
        }

        // Usuarios
        if ( in_array( $trace['function'], array(
            'wp_new_user_notification',
            'wp_send_new_user_notifications'
        ) ) ) {
            return 'users';
        }

        // Sistema
        if ( in_array( $trace['function'], array(
            'wp_maybe_auto_update',
            'WP_Automatic_Updater'
        ) ) ) {
            return 'system';
        }
    }

    return 'default';
}
```

**Método 2: Análisis de Subject/To (Alternativo)**

```php
function wp_mail_replyto_detect_context_by_subject( $subject, $to ) {
    // Password reset
    if ( stripos( $subject, 'password reset' ) !== false ||
         stripos( $subject, 'reset your password' ) !== false ) {
        return 'authentication';
    }

    // Comments
    if ( stripos( $subject, 'comment' ) !== false ||
         stripos( $subject, 'moderation' ) !== false ) {
        return 'comments';
    }

    // New user
    if ( stripos( $subject, 'new user' ) !== false ||
         stripos( $subject, 'registration' ) !== false ) {
        return 'users';
    }

    // System
    if ( stripos( $subject, 'updated' ) !== false ||
         stripos( $subject, 'automatic' ) !== false ) {
        return 'system';
    }

    return 'default';
}
```

**Método 3: Hooks Específicos (Más Preciso)**

```php
// En diferentes hooks
add_filter( 'retrieve_password_message', 'wp_mail_replyto_set_context_auth', 1 );
add_filter( 'comment_notification_text', 'wp_mail_replyto_set_context_comments', 1 );
add_filter( 'wp_new_user_notification_email', 'wp_mail_replyto_set_context_users', 1 );

function wp_mail_replyto_set_context_auth( $message ) {
    global $wp_mail_replyto_current_context;
    $wp_mail_replyto_current_context = 'authentication';
    return $message;
}

// Luego en wp_mail filter
function wp_mail_replyto( $args ) {
    global $wp_mail_replyto_current_context;
    $context = $wp_mail_replyto_current_context ?? 'default';

    // Usar configuración según contexto
    $email = get_option( 'wp_mail_replyto_email_' . $context );
    $name = get_option( 'wp_mail_replyto_name_' . $context );

    // ...
}
```

### Estructura de Base de Datos

**Opción 1: Múltiples opciones independientes**
```
wp_mail_replyto_email_default
wp_mail_replyto_name_default
wp_mail_replyto_email_authentication
wp_mail_replyto_name_authentication
wp_mail_replyto_email_comments
wp_mail_replyto_name_comments
wp_mail_replyto_email_users
wp_mail_replyto_name_users
wp_mail_replyto_email_system
wp_mail_replyto_name_system
```

**Opción 2: Array serializado (Recomendado)**
```php
wp_mail_replyto_contexts = array(
    'default' => array(
        'email' => 'support@example.com',
        'name'  => 'Support Team',
        'enabled' => true
    ),
    'authentication' => array(
        'email' => 'security@example.com',
        'name'  => 'Security Team',
        'enabled' => true
    ),
    'comments' => array(
        'email' => 'moderation@example.com',
        'name'  => 'Moderation',
        'enabled' => false  // Usará default
    ),
    // ...
);
```

**Ventajas de Opción 2:**
- ✅ Más fácil de gestionar
- ✅ Menos queries a la DB
- ✅ Más fácil de migrar/exportar
- ✅ Estructura clara

---

## 🎨 Diseño de Interfaz de Usuario

### Opción A: Pestañas (Tabs)

```
┌─────────────────────────────────────────────────┐
│ Reply-To Configuration                          │
├─────────────────────────────────────────────────┤
│                                                 │
│ [Default] [Authentication] [Comments] [Users]  │
│ [System]                                        │
│                                                 │
│ ┌─ Default Configuration ──────────────────┐   │
│ │                                          │   │
│ │ Reply-To Email:                          │   │
│ │ ┌──────────────────────────────────────┐ │   │
│ │ │ support@example.com                  │ │   │
│ │ └──────────────────────────────────────┘ │   │
│ │                                          │   │
│ │ Reply-To Name:                           │   │
│ │ ┌──────────────────────────────────────┐ │   │
│ │ │ Support Team                         │ │   │
│ │ └──────────────────────────────────────┘ │   │
│ │                                          │   │
│ │ ☑ Use as fallback for all emails       │   │
│ │                                          │   │
│ └──────────────────────────────────────────┘   │
│                                                 │
│ [Save Settings]                                 │
└─────────────────────────────────────────────────┘
```

**Ventajas:**
- ✅ Organizado y limpio
- ✅ No abruma al usuario
- ✅ Fácil de navegar

**Desventajas:**
- ❌ Requiere JavaScript
- ❌ Más complejo de implementar

### Opción B: Secciones Colapsables (Accordions)

```
┌─────────────────────────────────────────────────┐
│ Reply-To Configuration                          │
├─────────────────────────────────────────────────┤
│                                                 │
│ ▼ Default (All Emails)                         │
│   Email: [support@example.com            ]     │
│   Name:  [Support Team                   ]     │
│                                                 │
│ ▶ Authentication & Security                     │
│                                                 │
│ ▶ Comments & Moderation                         │
│                                                 │
│ ▼ Users & Registration                          │
│   ☑ Enable custom Reply-To for user emails     │
│   Email: [users@example.com              ]     │
│   Name:  [User Management                ]     │
│                                                 │
│ ▶ System & Administration                       │
│                                                 │
│ [Save All Settings]                             │
└─────────────────────────────────────────────────┘
```

**Ventajas:**
- ✅ Todo en una página
- ✅ Vista general rápida
- ✅ Puede funcionar sin JS

**Desventajas:**
- ❌ Puede ser largo
- ❌ Más scroll necesario

### Opción C: Lista Simple con Checkboxes (Recomendado - Más Simple)

```
┌─────────────────────────────────────────────────┐
│ Reply-To Configuration                          │
├─────────────────────────────────────────────────┤
│                                                 │
│ Configure different Reply-To addresses for      │
│ different types of emails sent by WordPress.    │
│                                                 │
│ ┌─ Default (Global Fallback) ─────────────┐    │
│ │ Used when no specific context is set    │    │
│ │                                          │    │
│ │ Email: [support@example.com         ]   │    │
│ │ Name:  [Support Team                ]   │    │
│ └──────────────────────────────────────────┘    │
│                                                 │
│ ┌─ Context-Specific Settings ─────────────┐    │
│ │                                          │    │
│ │ ☑ Authentication & Password Resets       │    │
│ │   Email: [security@example.com      ]   │    │
│ │   Name:  [Security Team             ]   │    │
│ │   Applies to: Password resets, login     │    │
│ │   confirmations, email changes           │    │
│ │                                          │    │
│ │ ☐ Comments & Moderation                  │    │
│ │   Email: [moderation@example.com    ]   │    │
│ │   Name:  [Moderation Team           ]   │    │
│ │   Applies to: Comment notifications,     │    │
│ │   moderation requests                    │    │
│ │                                          │    │
│ │ ☐ Users & Registration                   │    │
│ │   Email: [                          ]   │    │
│ │   Name:  [                          ]   │    │
│ │   Applies to: New user registrations,    │    │
│ │   role changes                           │    │
│ │                                          │    │
│ │ ☐ System & Updates                       │    │
│ │   Email: [                          ]   │    │
│ │   Name:  [                          ]   │    │
│ │   Applies to: Automatic updates,         │    │
│ │   site health notices                    │    │
│ │                                          │    │
│ └──────────────────────────────────────────┘    │
│                                                 │
│ [Save Settings]                                 │
└─────────────────────────────────────────────────┘
```

**Ventajas:**
- ✅ Simple y claro
- ✅ No requiere JS obligatorio
- ✅ Checkbox habilita/deshabilita contexto
- ✅ Textos explicativos inline
- ✅ Compatible con WordPress UI

---

## 🔧 Implementación Técnica

### Paso 1: Detección de Contexto

```php
/**
 * Detects the email context based on WordPress function backtrace.
 *
 * @since 1.3.0
 * @return string Context identifier.
 */
function wp_mail_replyto_detect_context() {
    static $context = null;

    // Return cached context if already detected
    if ( null !== $context ) {
        return $context;
    }

    $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 );

    foreach ( $backtrace as $trace ) {
        if ( ! isset( $trace['function'] ) ) {
            continue;
        }

        $function = $trace['function'];

        // Authentication context
        if ( in_array( $function, array(
            'retrieve_password',
            'reset_password',
            'wp_password_change_notification',
        ), true ) ) {
            $context = 'authentication';
            return $context;
        }

        // Comments context
        if ( in_array( $function, array(
            'wp_notify_postauthor',
            'wp_notify_moderator',
        ), true ) ) {
            $context = 'comments';
            return $context;
        }

        // Users context
        if ( in_array( $function, array(
            'wp_new_user_notification',
            'wp_send_new_user_notifications',
        ), true ) ) {
            $context = 'users';
            return $context;
        }

        // System context
        if ( in_array( $function, array(
            'wp_maybe_auto_update',
            'send_core_update_notification_email',
        ), true ) ||
        ( isset( $trace['class'] ) && 'WP_Automatic_Updater' === $trace['class'] ) ) {
            $context = 'system';
            return $context;
        }
    }

    // Default context
    $context = 'default';
    return $context;
}
```

### Paso 2: Obtener Configuración por Contexto

```php
/**
 * Gets Reply-To configuration for a specific context.
 *
 * @since 1.3.0
 * @param string $context Context identifier.
 * @return array|false Configuration array or false if disabled.
 */
function wp_mail_replyto_get_context_config( $context = 'default' ) {
    $contexts = get_option( 'wp_mail_replyto_contexts', array() );

    // Get context config
    if ( isset( $contexts[ $context ] ) && ! empty( $contexts[ $context ]['enabled'] ) ) {
        return $contexts[ $context ];
    }

    // Fallback to default if context not enabled
    if ( 'default' !== $context && isset( $contexts['default'] ) ) {
        return $contexts['default'];
    }

    // Legacy fallback to old single config
    return array(
        'email' => get_option( 'wp_mail_replyto_email', '' ),
        'name'  => get_option( 'wp_mail_replyto_name', '' ),
    );
}
```

### Paso 3: Modificar función principal

```php
function wp_mail_replyto( $args ) {
    // Detect context
    $context = wp_mail_replyto_detect_context();

    // Get config for context
    $config = wp_mail_replyto_get_context_config( $context );

    if ( ! $config ) {
        return $args; // Context disabled
    }

    $reply_to_email = $config['email'] ?? '';
    $reply_to_name  = $config['name'] ?? '';

    // ... rest of existing logic
}
```

---

## 📊 Casos de Uso

### Caso 1: Empresa con Departamentos

**Configuración:**
- Default: `info@company.com` - "Company Info"
- Authentication: `security@company.com` - "Security Team"
- Comments: `blog@company.com` - "Blog Team"
- Users: `hr@company.com` - "Human Resources"
- System: *(sin Reply-To - emails automáticos)*

**Resultado:**
- Usuario solicita reset password → Reply-To: Security Team
- Alguien comenta en blog → Reply-To: Blog Team
- Nuevo empleado registrado → Reply-To: HR
- Actualización automática → Sin Reply-To

### Caso 2: Sitio de E-learning

**Configuración:**
- Default: `support@elearning.com` - "Support"
- Authentication: `support@elearning.com` - "Support"
- Comments: `instructors@elearning.com` - "Instructors"
- Users: `admissions@elearning.com` - "Admissions"

**Resultado:**
- Estudiante comenta en curso → Reply-To: Instructors
- Nuevo estudiante → Reply-To: Admissions
- Todo lo demás → Reply-To: Support

### Caso 3: Blog Personal (Simple)

**Configuración:**
- Default: `hello@myblog.com` - "Jane Doe"
- Comments: `comments@myblog.com` - "Jane - Comments"
- Resto deshabilitado

**Resultado:**
- Comentarios → Reply-To específico para gestionar mejor
- Todo lo demás → Reply-To personal

---

## 🔐 Consideraciones de Seguridad

### Validación

Cada contexto debe validar:
- ✅ Email válido con `is_email()`
- ✅ Sanitización con `sanitize_email()`
- ✅ Nombre sanitizado con `sanitize_text_field()`
- ✅ Protección contra header injection en todos los campos
- ✅ Longitud máxima (255 caracteres)

### Permisos

- Solo usuarios con capacidad `manage_options` pueden configurar
- Verificación de nonce en guardado
- Escapado correcto en salida HTML

### Logging

Si WP_DEBUG_LOG está activo, registrar:
- Contexto detectado para cada email
- Configuración usada
- Cambios en configuración de contextos

---

## 📈 Migración desde v1.2.0

### Automática al activar

```php
function wp_mail_replyto_migrate_to_contexts() {
    // Check if already migrated
    if ( get_option( 'wp_mail_replyto_contexts_migrated' ) ) {
        return;
    }

    // Get old config
    $old_email = get_option( 'wp_mail_replyto_email', '' );
    $old_name  = get_option( 'wp_mail_replyto_name', '' );

    if ( empty( $old_email ) ) {
        update_option( 'wp_mail_replyto_contexts_migrated', true );
        return;
    }

    // Create default context with old config
    $contexts = array(
        'default' => array(
            'email'   => $old_email,
            'name'    => $old_name,
            'enabled' => true,
        ),
        'authentication' => array( 'enabled' => false ),
        'comments'       => array( 'enabled' => false ),
        'users'          => array( 'enabled' => false ),
        'system'         => array( 'enabled' => false ),
    );

    update_option( 'wp_mail_replyto_contexts', $contexts );
    update_option( 'wp_mail_replyto_contexts_migrated', true );

    // Keep old options for backward compatibility
    // Don't delete them in case of rollback
}
add_action( 'admin_init', 'wp_mail_replyto_migrate_to_contexts' );
```

---

## 🧪 Testing

### Tests Necesarios

1. **Detección de Contexto:**
   - ✅ Password reset → authentication
   - ✅ Comment notification → comments
   - ✅ New user → users
   - ✅ Auto update → system
   - ✅ Unknown → default

2. **Fallback:**
   - ✅ Contexto deshabilitado usa default
   - ✅ Contexto sin configurar usa default
   - ✅ Default siempre funciona

3. **Migración:**
   - ✅ Config antigua se migra a default
   - ✅ Migración solo ocurre una vez
   - ✅ Config antigua no se borra

4. **Seguridad:**
   - ✅ Header injection bloqueado en todos los campos
   - ✅ Solo admins pueden configurar
   - ✅ Nonce verificado

---

## 💾 Impacto en Rendimiento

### Queries Adicionales

- +1 `get_option()` para obtener array de contextos (cached)
- `debug_backtrace()` - ~0.1-0.2ms overhead

### Memoria

- Array de contextos: ~2-5 KB en memoria
- Backtrace: ~10-20 KB temporalmente

**Conclusión:** Impacto mínimo y aceptable.

---

## 🎯 Prioridad de Implementación

### Fase 1 (v1.3.0 - Core)
1. ✅ Detección de contexto via backtrace
2. ✅ Estructura de datos (array de contextos)
3. ✅ UI simple con checkboxes
4. ✅ Migración automática
5. ✅ 4 contextos básicos: default, authentication, comments, users

### Fase 2 (v1.4.0 - Extensiones)
1. ⏳ Contexto system
2. ⏳ Detección de WooCommerce
3. ⏳ Detección de bbPress/BuddyPress
4. ⏳ UI mejorada con tabs/accordions

---

## 🤔 Preguntas Abiertas

### 1. ¿Método de detección de contexto?

**Opción A: Backtrace** (Recomendado)
- ✅ Más preciso
- ✅ No depende de subject/contenido
- ❌ Pequeño overhead de rendimiento

**Opción B: Hooks específicos**
- ✅ Más eficiente
- ❌ Requiere muchos hooks
- ❌ Más código

**Opción C: Subject/Headers**
- ✅ Muy eficiente
- ❌ Menos preciso
- ❌ Puede fallar con plugins que modifiquen subjects

**Decisión:** Usar **Backtrace** para v1.3.0 por precisión.

### 2. ¿UI preferida?

**Opción A: Tabs** - Más moderna
**Opción B: Accordions** - Más compacta
**Opción C: Lista simple** - Más compatible (Recomendado)

**Decisión:** Empezar con **Lista simple** para v1.3.0.

### 3. ¿Cuántos contextos en v1.3.0?

**Mínimo viable:**
- Default (obligatorio)
- Authentication
- Comments
- Users

**Total:** 4 contextos (más system como opcional)

---

## ✅ Checklist de Implementación

Antes de codificar:
- [ ] Revisar esta documentación con el equipo/usuario
- [ ] Decidir método de detección definitivo
- [ ] Decidir UI definitiva
- [ ] Confirmar lista de contextos para v1.3.0
- [ ] Planificar migración desde v1.2.0

Durante implementación:
- [ ] Crear estructura de datos
- [ ] Implementar detección de contexto
- [ ] Modificar función wp_mail_replyto()
- [ ] Crear UI de configuración
- [ ] Implementar migración
- [ ] Añadir validación y sanitización
- [ ] Escribir tests
- [ ] Actualizar documentación
- [ ] Actualizar uninstall.php

Después de implementación:
- [ ] Testing exhaustivo
- [ ] Verificar PHPCS
- [ ] Verificar compatibilidad PHP
- [ ] Actualizar changelog
- [ ] Crear docs/CHANGELOG-1.3.0.md

---

## 📚 Referencias

**WordPress Codex:**
- https://developer.wordpress.org/reference/functions/wp_mail/
- https://developer.wordpress.org/reference/hooks/wp_mail/
- https://developer.wordpress.org/reference/functions/wp_new_user_notification/
- https://developer.wordpress.org/reference/functions/wp_notify_postauthor/

**RFC 5322 (Email Format):**
- https://tools.ietf.org/html/rfc5322

---

**Preparado por:** Análisis técnico pre-implementación
**Fecha:** 2026-01-20
**Estado:** ✅ Listo para revisión y aprobación
