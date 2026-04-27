<?php
/**
 * Antradus AI — uninstall cleanup
 * Runs when the user deletes the plugin from the WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$options = [
    'antradus_openai_api_key',
    'antradus_openai_model',
    'antradus_topics',
    'antradus_system_prompt',
    'antradus_image_model',
    'antradus_image_prompt',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

if ( is_multisite() ) {
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        foreach ( $options as $option ) {
            delete_option( $option );
        }
        restore_current_blog();
    }
}

// Per-post meta (_antradus_keyword, _antradus_url, _antradus_url2) is intentionally kept
// so users don't lose data if they reinstall later.
