<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Fetch external URL (with SSRF protection) ─────────────────────────────────

add_action( 'wp_ajax_antradus_fetch_url', function () {
    check_ajax_referer( 'antradus_fetch_url', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
    if ( ! $url ) wp_send_json_error( 'No URL provided' );

    $parsed = parse_url( $url );
    if ( ! in_array( strtolower( $parsed['scheme'] ?? '' ), [ 'http', 'https' ], true ) ) {
        wp_send_json_error( 'URL not allowed' );
    }

    $host = strtolower( $parsed['host'] ?? '' );
    if ( ! $host || in_array( $host, [ 'localhost', 'ip6-localhost', 'ip6-loopback' ], true ) ) {
        wp_send_json_error( 'URL not allowed' );
    }

    $ip = gethostbyname( $host );
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
        wp_send_json_error( 'URL not allowed' );
    }

    $response = wp_remote_get( $url, [
        'timeout'    => 30,
        'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . ')',
        'sslverify'  => false,
    ] );

    if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );

    $body = wp_remote_retrieve_body( $response );
    if ( ! $body ) wp_send_json_error( 'Empty response' );

    $body = preg_replace( '#<(script|style|nav|header|footer|aside|form|noscript)[^>]*>.*?</\1>#si', '', $body );

    $text = '';
    if ( preg_match( '#<article[^>]*>(.*?)</article>#si', $body, $m ) )  $text = $m[1];
    elseif ( preg_match( '#<main[^>]*>(.*?)</main>#si', $body, $m ) )    $text = $m[1];
    else $text = $body;

    $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
    if ( mb_strlen( $text ) > 6000 ) $text = mb_substr( $text, 0, 6000 ) . '...';
    if ( ! $text ) wp_send_json_error( 'Could not extract text' );

    wp_send_json_success( $text );
} );

// ── Generate content ──────────────────────────────────────────────────────────

add_action( 'wp_ajax_antradus_generate', function () {
    @set_time_limit( 180 );

    check_ajax_referer( 'antradus_generate', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $keyword   = sanitize_text_field( wp_unslash( $_POST['keyword']   ?? '' ) );
    $source    = sanitize_textarea_field( wp_unslash( $_POST['source']  ?? '' ) );
    $style     = sanitize_text_field( wp_unslash( $_POST['style']     ?? 'Blog' ) );
    $tone      = sanitize_text_field( wp_unslash( $_POST['tone']      ?? 'Formal' ) );
    $lang      = sanitize_text_field( wp_unslash( $_POST['lang']      ?? 'English' ) );
    $niche     = sanitize_text_field( wp_unslash( $_POST['niche']     ?? '' ) );
    $incl_faq  = ( $_POST['incl_faq']  ?? '0' ) === '1';
    $incl_meta = ( $_POST['incl_meta'] ?? '0' ) === '1';

    if ( ! $keyword && ! $source ) wp_send_json_error( 'No keyword or source content provided' );

    $api_key = get_option( 'antradus_openai_api_key', '' );
    if ( ! $api_key ) wp_send_json_error( 'API key not set. Go to Settings — Antradus AI.' );
    $model = get_option( 'antradus_openai_model', 'gpt-4o' );

    $default_system  = "You are a professional content writer. Write engaging, SEO-friendly articles for a general audience.\n\n";
    $default_system .= "If a source article is provided, use it only as a fact source — do not follow its structure. ";
    $default_system .= "Write a completely original article from scratch with a different angle, opening, and narrative flow.\n\n";
    $default_system .= "Rules:\n- Open with a compelling hook. Never a definition.\n";
    $default_system .= "- Use H1, H2, H3 in clean HTML only — no other tags\n- Short paragraphs, 2-3 lines max\n";
    $default_system .= "- Conversational and confident tone\n- Target length: 1000-1400 words";

    $system  = get_option( 'antradus_system_prompt', $default_system ) . "\n\n";
    $system .= "For this article:\n- Language: write entirely in {$lang}\n- Style: {$style}\n- Tone: {$tone}\n";
    if ( $niche )    $system .= "- Topic niche: {$niche}\n";
    if ( $keyword )  $system .= "- Primary keyword: {$keyword} — use naturally\n";
    if ( $incl_faq ) $system .= "- Add an FAQ section at the end with 4-5 questions and answers\n";
    $system .= "\nReturn a valid JSON object with these keys:\n  \"article\": the full HTML article\n";
    if ( $incl_meta ) $system .= "  \"meta_title\": SEO meta title (55-60 chars)\n  \"meta_desc\": SEO meta description — strictly max 155 chars, active voice, ends with a call to action, naturally includes the focus keyphrase, unique and matches the article\n";
    $system .= "Return ONLY raw JSON. No markdown fences, no explanation.";

    $user_msg = $keyword ? "Primary keyword: {$keyword}\n" : '';
    if ( $niche )  $user_msg .= "Niche: {$niche}\n";
    if ( $source ) $user_msg .= "\nSource article:\n{$source}";

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 150,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'                 => $model,
            'messages'              => [
                [ 'role' => 'system', 'content' => $system ],
                [ 'role' => 'user',   'content' => $user_msg ],
            ],
            'max_completion_tokens' => 4096,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) wp_send_json_error( 'Request failed: ' . $response->get_error_message() );

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) wp_send_json_error( $body['error']['message'] ?? 'HTTP ' . $code );

    $raw = $body['choices'][0]['message']['content'] ?? '';
    if ( ! $raw ) wp_send_json_error( 'Empty response from OpenAI' );

    $raw    = trim( preg_replace( [ '/^```json\s*/i', '/^```\s*/i', '/\s*```$/' ], '', $raw ) );
    $parsed = json_decode( $raw, true );

    if ( json_last_error() !== JSON_ERROR_NONE || empty( $parsed['article'] ) ) {
        wp_send_json_success( [ 'article' => $raw, 'meta_title' => '', 'meta_desc' => '' ] );
    }

    wp_send_json_success( [
        'article'    => $parsed['article']    ?? '',
        'meta_title' => $parsed['meta_title'] ?? '',
        'meta_desc'  => $parsed['meta_desc']  ?? '',
    ] );
} );

// ── Generate image ─────────────────────────────────────────────────────────────

add_action( 'wp_ajax_antradus_generate_image', function () {
    @set_time_limit( 180 );

    check_ajax_referer( 'antradus_generate_image', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $article_text       = sanitize_textarea_field( wp_unslash( $_POST['article_text']       ?? '' ) );
    $extra_instructions = sanitize_textarea_field( wp_unslash( $_POST['extra_instructions'] ?? '' ) );
    $instructions_only  = ( $_POST['instructions_only'] ?? '0' ) === '1';
    $post_id            = absint( $_POST['post_id'] ?? 0 );

    if ( ! $article_text && ! $extra_instructions ) wp_send_json_error( 'No article text or instructions provided' );

    $api_key = get_option( 'antradus_openai_api_key', '' );
    if ( ! $api_key ) wp_send_json_error( 'API key not set. Go to Settings — Antradus AI.' );

    $image_model  = get_option( 'antradus_image_model', 'gpt-image-1' );
    $style_prompt = get_option( 'antradus_image_prompt',
        'A high-end lifestyle marketing banner in a clean editorial thumbnail style, professional commercial photography, ' .
        'soft cinematic lighting, shallow depth of field, warm color grading, visually pleasing composition, ' .
        'premium brand feel, social media thumbnail layout, ultra realistic, sharp focus, high detail, 4K, advertising style'
    );

    if ( $instructions_only && $extra_instructions ) {
        $image_prompt = $extra_instructions;
    } else {
        $image_prompt  = $article_text ? "Create an image for an article about:\n\n" . mb_substr( $article_text, 0, 600 ) . "\n\n" : '';
        $image_prompt .= "Visual style: " . $style_prompt;
        if ( $extra_instructions ) $image_prompt .= "\n\nAdditional instructions: " . $extra_instructions;
    }

    $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
        'timeout' => 120,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'  => $image_model,
            'prompt' => $image_prompt,
            'n'      => 1,
            'size'   => '1024x1024',
        ] ),
    ] );

    if ( is_wp_error( $response ) ) wp_send_json_error( 'Image request failed: ' . $response->get_error_message() );

    $code       = wp_remote_retrieve_response_code( $response );
    $body       = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) wp_send_json_error( $body['error']['message'] ?? 'HTTP ' . $code );

    $image_data = $body['data'][0] ?? [];

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    if ( ! empty( $image_data['b64_json'] ) ) {
        $img_data = base64_decode( $image_data['b64_json'] );
        if ( ! $img_data ) wp_send_json_error( 'Could not decode image data' );

        $filename = 'antradus-ai-' . time() . '.png';
        $upload   = wp_upload_bits( $filename, null, $img_data );
        if ( $upload['error'] ) wp_send_json_error( 'Upload error: ' . $upload['error'] );

        $attachment_id = wp_insert_attachment( [
            'post_mime_type' => 'image/png',
            'post_title'     => $filename,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $upload['file'], $post_id );

        if ( is_wp_error( $attachment_id ) ) wp_send_json_error( 'Attachment error: ' . $attachment_id->get_error_message() );

        wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

        wp_send_json_success( [ 'attachment_id' => $attachment_id, 'url' => $upload['url'] ] );

    } elseif ( ! empty( $image_data['url'] ) ) {
        $attachment_id = media_sideload_image( $image_data['url'], $post_id, null, 'id' );
        if ( is_wp_error( $attachment_id ) ) wp_send_json_error( 'Sideload error: ' . $attachment_id->get_error_message() );

        wp_send_json_success( [ 'attachment_id' => $attachment_id, 'url' => wp_get_attachment_url( $attachment_id ) ] );

    } else {
        wp_send_json_error( 'No image data in response' );
    }
} );

// ── Set featured image ────────────────────────────────────────────────────────

add_action( 'wp_ajax_antradus_set_featured', function () {
    check_ajax_referer( 'antradus_set_featured', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $attachment_id = absint( $_POST['attachment_id'] ?? 0 );
    $post_id       = absint( $_POST['post_id']       ?? 0 );

    if ( ! $attachment_id || ! $post_id )  wp_send_json_error( 'Missing data' );
    if ( ! get_post( $attachment_id ) )    wp_send_json_error( 'Invalid attachment' );
    if ( ! set_post_thumbnail( $post_id, $attachment_id ) ) wp_send_json_error( 'Could not set featured image' );

    wp_send_json_success( [ 'attachment_id' => $attachment_id ] );
} );
