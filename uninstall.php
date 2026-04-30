<?php
/**
 * Antradus AI Lite — uninstall cleanup
 * Runs when the user deletes the plugin from the WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$antradus_options = [
    // Provider selection
    'antradus_provider',
    // OpenAI
    'antradus_openai_api_key',
    'antradus_openai_model',
    'antradus_openai_image_model',
    // Anthropic
    'antradus_anthropic_api_key',
    'antradus_anthropic_model',
    // Google Gemini
    'antradus_gemini_api_key',
    'antradus_gemini_model',
    'antradus_gemini_image_model',
    // OpenRouter
    'antradus_openrouter_api_key',
    'antradus_openrouter_model',
    'antradus_openrouter_image_model',
    // Content settings
    'antradus_topics',
    'antradus_system_prompt',
    // Image settings
    'antradus_image_preset',
    'antradus_image_color',
    'antradus_image_color_enabled',
    // Image style presets
    'antradus_image_prompt',
    'antradus_image_prompt_gaming',
    'antradus_image_prompt_medical',
    'antradus_image_prompt_news',
    'antradus_image_prompt_sports',
    'antradus_image_prompt_finance',
    'antradus_image_prompt_tech',
    'antradus_image_prompt_food',
    'antradus_image_prompt_travel',
    'antradus_image_prompt_entertainment',
    // Editor settings
    'antradus_disable_gutenberg_posts',
    // Legacy (pre-1.0 installs)
    'antradus_image_model',
];

foreach ( $antradus_options as $antradus_option ) {
    delete_option( $antradus_option );
}

if ( is_multisite() ) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $antradus_blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
    foreach ( $antradus_blog_ids as $antradus_blog_id ) {
        switch_to_blog( $antradus_blog_id );
        foreach ( $antradus_options as $antradus_option ) {
            delete_option( $antradus_option );
        }
        restore_current_blog();
    }
}

// Per-post meta (_antradus_keyword, _antradus_url) is intentionally kept
// so users don't lose data if they reinstall later.
