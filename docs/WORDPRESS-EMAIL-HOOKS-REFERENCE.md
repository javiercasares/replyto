# WordPress Email Hooks - Referencia Rápida

**Versión WordPress:** 4.1 - 6.8
**Propósito:** Mapeo de funciones y hooks de WordPress que envían emails
**Para:** Implementación de Reply-To por contexto

---

## 📧 Tipos de Emails en WordPress Core

### 1️⃣ Autenticación y Seguridad

| Email | Función WordPress | Hook Disponible | Parámetros |
|-------|------------------|-----------------|------------|
| **Password Reset Request** | `retrieve_password()` | `retrieve_password_title` | Email subject |
| | | `retrieve_password_message` | Email message |
| | | `retrieve_password_key` | Reset key |
| **Password Changed** | `wp_password_change_notification()` | `password_change_email` | Array con to, subject, message |
| | | `wp_password_change_notification_email` | (WP 4.9+) |
| **Email Changed** | `wp_update_user()` | `email_change_email` | Array con to, subject, message |
| | | `send_email_change_email` | Boolean, permite cancelar |

**Backtrace signatures:**
```php
retrieve_password
reset_password
wp_password_change_notification
```

---

### 2️⃣ Comentarios y Moderación

| Email | Función WordPress | Hook Disponible | Parámetros |
|-------|------------------|-----------------|------------|
| **Comment Notification** | `wp_notify_postauthor()` | `comment_notification_recipients` | Recipients array |
| (al autor del post) | | `comment_notification_subject` | Email subject |
| | | `comment_notification_text` | Email text |
| | | `comment_notification_headers` | Email headers |
| **Comment Moderation** | `wp_notify_moderator()` | `comment_moderation_recipients` | Recipients array |
| | | `comment_moderation_subject` | Email subject |
| | | `comment_moderation_text` | Email text |
| | | `comment_moderation_headers` | Email headers |

**Backtrace signatures:**
```php
wp_notify_postauthor
wp_notify_moderator
wp_new_comment_notify_postauthor
wp_new_comment_notify_moderator
```

---

### 3️⃣ Usuarios y Registros

| Email | Función WordPress | Hook Disponible | Parámetros |
|-------|------------------|-----------------|------------|
| **New User (to user)** | `wp_new_user_notification()` | `wp_new_user_notification_email` | Array con to, subject, message |
| **New User (to admin)** | `wp_new_user_notification()` | `wp_new_user_notification_email_admin` | Array con to, subject, message |
| **Send notifications?** | | `wp_send_new_user_notifications` | User ID, notify type |

**Backtrace signatures:**
```php
wp_new_user_notification
wp_send_new_user_notifications
register_new_user
wp_create_user
```

---

### 4️⃣ Sistema y Actualizaciones

| Email | Función WordPress | Hook Disponible | Parámetros |
|-------|------------------|-----------------|------------|
| **Core Update** | `WP_Automatic_Updater::send_email()` | `auto_core_update_email` | Email array |
| **Plugin Update** | `WP_Automatic_Updater::send_email()` | `auto_plugin_update_email` | Email array |
| **Theme Update** | `WP_Automatic_Updater::send_email()` | `auto_theme_update_email` | Email array |
| **Recovery Mode** | `WP_Recovery_Mode::send_recovery_mode_email()` | `recovery_mode_email` | Email array |
| **Fatal Error** | `wp_fatal_error_handler()` | `wp_fatal_error_handler_enabled` | Boolean |

**Backtrace signatures:**
```php
WP_Automatic_Updater
send_core_update_notification_email
wp_maybe_auto_update
WP_Recovery_Mode
```

---

## 🔍 Detección por Backtrace - Implementación

### Función de Detección Completa

```php
/**
 * Detects email context based on WordPress backtrace.
 *
 * @return string Context: 'default', 'authentication', 'comments', 'users', 'system'
 */
function wp_mail_replyto_detect_context() {
    static $context = null;

    if ( null !== $context ) {
        return $context;
    }

    $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );

    foreach ( $backtrace as $trace ) {
        if ( empty( $trace['function'] ) ) {
            continue;
        }

        $function = $trace['function'];
        $class = $trace['class'] ?? '';

        // Authentication
        if ( in_array( $function, array(
            'retrieve_password',
            'reset_password',
            'wp_password_change_notification',
        ) ) ) {
            $context = 'authentication';
            return $context;
        }

        // Comments
        if ( in_array( $function, array(
            'wp_notify_postauthor',
            'wp_notify_moderator',
            'wp_new_comment_notify_postauthor',
            'wp_new_comment_notify_moderator',
        ) ) ) {
            $context = 'comments';
            return $context;
        }

        // Users
        if ( in_array( $function, array(
            'wp_new_user_notification',
            'wp_send_new_user_notifications',
            'register_new_user',
        ) ) ) {
            $context = 'users';
            return $context;
        }

        // System
        if ( 'WP_Automatic_Updater' === $class ||
             'WP_Recovery_Mode' === $class ||
             in_array( $function, array(
                'wp_maybe_auto_update',
                'send_core_update_notification_email',
             ) ) ) {
            $context = 'system';
            return $context;
        }
    }

    $context = 'default';
    return $context;
}
```

---

## 🔌 Plugins Populares

### WooCommerce

**Detección:**
```php
if ( class_exists( 'WooCommerce' ) ) {
    // WooCommerce está activo
}
```

**Hooks principales:**
```php
woocommerce_email_headers           // Modificar headers
woocommerce_email_from_address      // Cambiar From address
woocommerce_email_from_name         // Cambiar From name
woocommerce_email_recipient_{type}  // Cambiar destinatario por tipo
```

**Tipos de emails:**
- New order (admin)
- Processing order (customer)
- Completed order (customer)
- Customer invoice
- Customer note
- Reset password
- New account

**Detección por clase:**
```php
$backtrace = debug_backtrace();
foreach ( $backtrace as $trace ) {
    if ( isset( $trace['class'] ) &&
         false !== strpos( $trace['class'], 'WC_Email' ) ) {
        return 'woocommerce';
    }
}
```

---

### bbPress

**Detección:**
```php
if ( class_exists( 'bbPress' ) ) {
    // bbPress está activo
}
```

**Funciones que envían email:**
```php
bbp_notify_topic_subscribers()      // Notificar suscriptores de topic
bbp_notify_forum_subscribers()      // Notificar suscriptores de forum
bbp_subscription_email()            // Email de suscripción
```

**Hooks:**
```php
bbp_subscription_mail_message       // Mensaje del email
bbp_subscription_mail_title         // Título del email
```

---

### BuddyPress

**Detección:**
```php
if ( function_exists( 'bp_is_active' ) ) {
    // BuddyPress está activo
}
```

**Funciones principales:**
```php
bp_send_email()                     // Función principal de envío
messages_notification_new_message() // Nuevo mensaje privado
groups_notification_new_membership_request()
friends_notification_new_request()
```

**Tipos de emails:**
- Activity comment
- Activity at mention
- Private messages
- Friend requests
- Group invitations
- Membership requests

---

## 📊 Tabla de Prioridades

| Contexto | Prioridad | Complejidad | Incluir en v1.3.0 |
|----------|-----------|-------------|-------------------|
| Default | ⭐⭐⭐⭐⭐ | 🟢 Baja | ✅ Sí |
| Authentication | ⭐⭐⭐⭐⭐ | 🟢 Baja | ✅ Sí |
| Comments | ⭐⭐⭐⭐ | 🟢 Baja | ✅ Sí |
| Users | ⭐⭐⭐⭐ | 🟢 Baja | ✅ Sí |
| System | ⭐⭐⭐ | 🟡 Media | 🤔 Opcional |
| WooCommerce | ⭐⭐⭐ | 🟡 Media | ❌ v1.4.0 |
| bbPress | ⭐⭐ | 🟡 Media | ❌ v1.4.0 |
| BuddyPress | ⭐⭐ | 🟡 Media | ❌ v1.4.0 |

---

## 🧪 Testing de Detección

### Scripts de Prueba

```php
// Test Authentication
wp_set_password( 'newpass', 1 );  // Trigger password change notification

// Test Comments
$comment_data = array(
    'comment_post_ID' => 1,
    'comment_author' => 'Test',
    'comment_author_email' => 'test@test.com',
    'comment_content' => 'Test comment',
);
wp_new_comment( $comment_data );

// Test Users
wp_create_user( 'testuser', 'testpass', 'test@test.com' );

// Test Password Reset
$user = get_user_by( 'email', 'admin@test.com' );
retrieve_password( $user->user_login );
```

### Verificación de Contexto

```php
add_filter( 'wp_mail', function( $args ) {
    $context = wp_mail_replyto_detect_context();
    error_log( "Email context detected: $context for subject: {$args['subject']}" );
    return $args;
}, 1 );
```

---

## 📚 Referencias WordPress Core

### Archivos Fuente

```
wp-includes/
├── pluggable.php           # wp_mail(), wp_new_user_notification()
├── user.php                # wp_create_user(), wp_update_user()
├── comment.php             # wp_notify_postauthor(), wp_notify_moderator()
└── class-wp-recovery-mode-email-service.php

wp-admin/includes/
├── user.php                # Funciones de admin de usuarios
└── class-wp-automatic-updater.php
```

### Documentación Oficial

- **wp_mail()**: https://developer.wordpress.org/reference/functions/wp_mail/
- **Pluggable Functions**: https://developer.wordpress.org/reference/files/wp-includes/pluggable.php/
- **Email Hooks**: https://developer.wordpress.org/reference/hooks/

---

## 💡 Notas de Implementación

### Caché de Contexto

```php
// Cachear contexto durante la ejecución del email
static $context = null;

// Resetear después del email (importante)
add_filter( 'wp_mail', function( $args ) {
    global $wp_mail_replyto_context;
    $wp_mail_replyto_context = null;  // Reset para próximo email
    return $args;
}, 999 );
```

### Fallback Chain

```
1. Detectar contexto específico
   ↓ (no habilitado)
2. Usar contexto default
   ↓ (no configurado)
3. Usar config legacy (v1.2.0)
   ↓ (no existe)
4. No modificar Reply-To
```

### Performance

```php
// Backtrace es caro, solo llamar una vez por email
static $context = null;
if ( null !== $context ) {
    return $context;  // Usar cache
}
$context = expensive_detection();
return $context;
```

---

**Última actualización:** 2026-01-20
**Versiones probadas:** WordPress 4.1 - 6.8
