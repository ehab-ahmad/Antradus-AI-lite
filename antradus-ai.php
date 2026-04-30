<?php
/**
 * Plugin Name: Antradus AI Lite
 * Plugin URI:  https://webops.ae/antradus-ai/
 * Description: AI-powered article and image generator. Write SEO-optimized content from a keyword or URL using OpenAI, Claude, Gemini, or OpenRouter. Works in both Classic and Block editors.
 * Version:     1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author:      ehabahmad
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: antradus-ai-lite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// All constants use the _LITE_ prefix — guaranteed unique, never collide with Premium.
define( 'ANTRADUS_AI_LITE',         true );
define( 'ANTRADUS_AI_LITE_FILE',    __FILE__ );
define( 'ANTRADUS_AI_LITE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ANTRADUS_AI_LITE_URL',     plugin_dir_url( __FILE__ ) );
define( 'ANTRADUS_AI_LITE_VERSION', '1.0.0' );
define( 'ANTRADUS_AI_LITE_PRO_URL', 'https://webops.ae/antradus-ai/' );

// ── Conflict detection ────────────────────────────────────────────────────────
// Runs before shared constants and includes are defined, so that if Premium is
// active neither constant redefinition warnings nor function-name fatals occur.

function antradus_lite_is_premium_active() {
    if ( defined( 'ANTRADUS_AI_PREMIUM' ) ) return true;
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    return function_exists( 'is_plugin_active' ) && is_plugin_active( 'antradus-ai/antradus-ai.php' );
}

if ( antradus_lite_is_premium_active() ) {
    add_action( 'admin_init', function () {
        deactivate_plugins( plugin_basename( ANTRADUS_AI_LITE_FILE ) );
        set_transient( 'antradus_lite_deactivated', 1, 60 );
    } );
    add_action( 'admin_notices', function () {
        if ( ! get_transient( 'antradus_lite_deactivated' ) ) return;
        delete_transient( 'antradus_lite_deactivated' );
        echo '<div class="notice notice-warning is-dismissible"><p>' .
            '<strong>Antradus AI Lite</strong> was automatically deactivated because <strong>Antradus AI (Premium)</strong> is already active on this site. ' .
            'You already have access to all Pro features! ' .
            '<a href="' . esc_url( ANTRADUS_AI_LITE_PRO_URL ) . '" target="_blank" rel="noopener">Manage your license →</a>' .
            '</p></div>';
    } );
    return; // Bail — do not define shared constants or load includes.
}

// ─────────────────────────────────────────────────────────────────────────────

require_once ANTRADUS_AI_LITE_DIR . 'includes/settings.php';
require_once ANTRADUS_AI_LITE_DIR . 'includes/meta-box.php';
require_once ANTRADUS_AI_LITE_DIR . 'includes/ajax-handlers.php';

register_activation_hook( ANTRADUS_AI_LITE_FILE, function () {
    set_transient( 'antradus_ai_activated', 1, 30 );
} );

add_action( 'admin_notices', function () {
    if ( ! get_transient( 'antradus_ai_activated' ) ) return;
    delete_transient( 'antradus_ai_activated' );
    echo '<div class="notice notice-success is-dismissible"><p>' .
        '<strong>Antradus AI Lite</strong> is active. Go to ' .
        '<a href="' . esc_url( admin_url( 'options-general.php?page=antradus-ai' ) ) . '">Settings → Antradus AI</a> ' .
        'to add your OpenAI API key.</p></div>';
} );

add_filter( 'plugin_action_links_' . plugin_basename( ANTRADUS_AI_LITE_FILE ), function ( $links ) {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=antradus-ai' ) ) . '">Settings</a>' );
    $links[] = '<a href="' . esc_url( ANTRADUS_AI_LITE_PRO_URL ) . '" target="_blank" rel="noopener" style="color:#8e44ad;font-weight:700;">⭐ Upgrade to Pro</a>';
    return $links;
} );
