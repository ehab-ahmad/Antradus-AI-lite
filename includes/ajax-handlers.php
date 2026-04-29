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

    @set_time_limit( 60 );

    $response = wp_remote_get( $url, [
        'timeout'    => 30,
        'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . ')',
        'sslverify'  => false,
    ] );

    if ( is_wp_error( $response ) ) {
        $msg = $response->get_error_message();
        if ( strpos( $msg, 'timed out' ) !== false || strpos( $msg, 'Operation timed out' ) !== false ) {
            wp_send_json_error( "That website is taking too long to respond. It might be down or blocking us. Try a different source, or paste the article text directly into the keyword field." );
        }
        wp_send_json_error( "We couldn't reach that URL. Double-check the address, or try a different source." );
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code === 401 ) {
        wp_send_json_error( "This article is behind a login wall — you'd need an account to read it. Try finding the same story on an open news site, or paste the text you want to rewrite directly." );
    }
    if ( $code === 403 ) {
        wp_send_json_error( "This website is blocking outside access to its content (common on academic journals, paywalled news, and some blogs). Try an open-access version — Google Scholar, the author's personal site, or a preprint on arXiv/bioRxiv often have free copies." );
    }
    if ( $code === 404 ) {
        wp_send_json_error( "That page doesn't exist anymore. The URL may be outdated — try searching for the article title to find a working link." );
    }
    if ( $code === 429 ) {
        wp_send_json_error( "That website is rate-limiting us right now. Wait a minute and try again, or use a different source." );
    }
    if ( $code >= 500 ) {
        wp_send_json_error( "That website is having server problems right now. Try again later or use a different source." );
    }
    if ( $code >= 400 ) {
        wp_send_json_error( "That website returned an error and wouldn't share the page. Try a different source URL." );
    }

    $body = wp_remote_retrieve_body( $response );
    if ( ! $body ) wp_send_json_error( "The website responded but sent back an empty page. Try a different source URL." );

    // Detect Cloudflare challenge / bot-protection pages
    if ( preg_match( '/<title[^>]*>\s*(Just a moment|Attention Required|Access denied|Security check|DDoS protection)[^<]*<\/title>/i', $body )
        || strpos( $body, 'cf-browser-verification' ) !== false
        || strpos( $body, 'cf_chl_opt' ) !== false
        || strpos( $body, '__cf_chl_f_tk' ) !== false
        || strpos( $body, 'challenge-platform' ) !== false ) {
        wp_send_json_error( "This site is protected by Cloudflare and won't let automated tools read it. Try finding the same content on a site without bot-protection, or paste the article text directly." );
    }

    // Detect login / paywall walls
    if ( preg_match( '/<input[^>]+type=["\']password["\'][^>]*>/i', $body ) ) {
        wp_send_json_error( "This page is asking for a login before showing the content. Try an open-access version of the article, or paste the text you want to rewrite directly." );
    }

    $body = preg_replace( '#<(script|style|nav|header|footer|aside|form|noscript)[^>]*>.*?</\1>#si', '', $body );

    $text = '';
    if ( preg_match( '#<article[^>]*>(.*?)</article>#si', $body, $m ) )  $text = $m[1];
    elseif ( preg_match( '#<main[^>]*>(.*?)</main>#si', $body, $m ) )    $text = $m[1];
    else $text = $body;

    $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
    if ( mb_strlen( $text ) > 6000 ) $text = mb_substr( $text, 0, 6000 ) . '...';

    if ( mb_strlen( $text ) < 150 ) {
        wp_send_json_error( "We reached the page but couldn't find any article text on it. It might be a JavaScript-rendered app, a paywall landing page, or just very thin content. Try a different source, or paste the text directly." );
    }

    wp_send_json_success( $text );
} );

// ── Provider API functions (throw RuntimeException on failure) ────────────────

function antradus_lite_call_openai( $system, $user_msg ) {
    $api_key = get_option( 'antradus_openai_api_key', '' );
    if ( ! $api_key ) throw new \RuntimeException( 'OpenAI API key not set. Go to Settings → Antradus AI.' );
    $model = get_option( 'antradus_openai_model', 'gpt-4o' );

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

    if ( is_wp_error( $response ) ) antradus_lite_throw_request_error( $response );
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) throw new \RuntimeException( $body['error']['message'] ?? 'HTTP ' . $code );

    return $body['choices'][0]['message']['content'] ?? '';
}

function antradus_lite_call_anthropic( $system, $user_msg ) {
    $api_key = get_option( 'antradus_anthropic_api_key', '' );
    if ( ! $api_key ) throw new \RuntimeException( 'Anthropic API key not set. Go to Settings → Antradus AI.' );
    $model = get_option( 'antradus_anthropic_model', 'claude-opus-4-7' );

    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
        'timeout' => 150,
        'headers' => [
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'max_tokens' => 4096,
            'system'     => $system,
            'messages'   => [ [ 'role' => 'user', 'content' => $user_msg ] ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) antradus_lite_throw_request_error( $response );
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) throw new \RuntimeException( $body['error']['message'] ?? 'HTTP ' . $code );

    return $body['content'][0]['text'] ?? '';
}

function antradus_lite_call_gemini( $system, $user_msg ) {
    $api_key = get_option( 'antradus_gemini_api_key', '' );
    if ( ! $api_key ) throw new \RuntimeException( 'Gemini API key not set. Go to Settings → Antradus AI.' );
    $model = get_option( 'antradus_gemini_model', 'gemini-2.0-flash' );
    $url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . urlencode( $api_key );

    $response = wp_remote_post( $url, [
        'timeout' => 150,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [
            'systemInstruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
            'contents'          => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $user_msg ] ] ] ],
            'generationConfig'  => [ 'maxOutputTokens' => 4096 ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) antradus_lite_throw_request_error( $response );
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) throw new \RuntimeException( $body['error']['message'] ?? 'HTTP ' . $code );

    return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

function antradus_lite_call_openrouter( $system, $user_msg ) {
    $api_key = get_option( 'antradus_openrouter_api_key', '' );
    if ( ! $api_key ) throw new \RuntimeException( 'OpenRouter API key not set. Go to Settings → Antradus AI.' );
    $model = get_option( 'antradus_openrouter_model', 'openai/gpt-4o' );

    $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', [
        'timeout' => 150,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'HTTP-Referer'  => get_site_url(),
            'X-Title'       => 'Antradus AI',
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'messages'   => [
                [ 'role' => 'system', 'content' => $system ],
                [ 'role' => 'user',   'content' => $user_msg ],
            ],
            'max_tokens' => 4096,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) antradus_lite_throw_request_error( $response );
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) {
        $msg = $body['error']['message'] ?? ( 'HTTP ' . $code );
        throw new \RuntimeException( $msg . ' — Try a different model or check your OpenRouter credits.' );
    }

    return $body['choices'][0]['message']['content'] ?? '';
}

// ── Fetch model list from provider ────────────────────────────────────────────

add_action( 'wp_ajax_antradus_fetch_models', function () {
    check_ajax_referer( 'antradus_fetch_models', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $allowed  = [ 'openai', 'anthropic', 'gemini', 'openrouter' ];
    $provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );
    if ( ! in_array( $provider, $allowed, true ) ) wp_send_json_error( 'Unknown provider.' );

    $type = sanitize_text_field( wp_unslash( $_POST['type'] ?? 'text' ) );
    if ( ! in_array( $type, [ 'text', 'image' ], true ) ) $type = 'text';

    $api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
    if ( empty( $api_key ) ) {
        $api_key = get_option( 'antradus_' . $provider . '_api_key', '' );
    }

    // Image model fetching — only OpenAI and Gemini support image generation
    if ( $type === 'image' ) {
        switch ( $provider ) {

            case 'openai':
                if ( ! $api_key ) wp_send_json_error( 'No API key — enter or save your OpenAI key first.' );
                $res  = wp_remote_get( 'https://api.openai.com/v1/models', [
                    'timeout' => 15,
                    'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
                ] );
                if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
                $code = wp_remote_retrieve_response_code( $res );
                $data = json_decode( wp_remote_retrieve_body( $res ), true );
                if ( $code !== 200 ) wp_send_json_error( $data['error']['message'] ?? 'HTTP ' . $code );

                $models = [];
                foreach ( $data['data'] ?? [] as $m ) {
                    $id = $m['id'];
                    if ( strpos( $id, 'dall-e' ) === false && strpos( $id, 'gpt-image' ) === false ) continue;
                    $models[] = [ 'id' => $id, 'name' => $id ];
                }
                usort( $models, function ( $a, $b ) { return strcmp( $b['id'], $a['id'] ); } );
                wp_send_json_success( $models );

            case 'gemini':
                if ( ! $api_key ) wp_send_json_error( 'No API key — enter or save your Gemini key first.' );
                $res  = wp_remote_get( 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode( $api_key ), [
                    'timeout' => 15,
                ] );
                if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
                $code = wp_remote_retrieve_response_code( $res );
                $data = json_decode( wp_remote_retrieve_body( $res ), true );
                if ( $code !== 200 ) wp_send_json_error( $data['error']['message'] ?? 'HTTP ' . $code );

                $models = [];
                foreach ( $data['models'] ?? [] as $m ) {
                    $id = str_replace( 'models/', '', $m['name'] );
                    if ( strpos( $id, 'imagen' ) === false ) continue;
                    if ( ! in_array( 'predict', $m['supportedGenerationMethods'] ?? [], true ) ) continue;
                    $models[] = [ 'id' => $id, 'name' => $m['displayName'] ?? $id ];
                }
                wp_send_json_success( $models );

            case 'openrouter':
                $headers = [ 'Content-Type' => 'application/json' ];
                if ( $api_key ) $headers['Authorization'] = 'Bearer ' . $api_key;
                $res  = wp_remote_get( 'https://openrouter.ai/api/v1/models', [
                    'timeout' => 15,
                    'headers' => $headers,
                ] );
                if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
                $code = wp_remote_retrieve_response_code( $res );
                $data = json_decode( wp_remote_retrieve_body( $res ), true );
                if ( $code !== 200 ) wp_send_json_error( $data['error']['message'] ?? 'HTTP ' . $code );

                $free = [];
                $paid = [];
                foreach ( $data['data'] ?? [] as $m ) {
                    if ( empty( $m['id'] ) ) continue;
                    $arch        = $m['architecture'] ?? [];
                    $modality    = strtolower( $arch['modality'] ?? '' );
                    $output_mods = $arch['output_modalities'] ?? [];
                    $id_lower    = strtolower( $m['id'] );

                    // Known image-generation providers — never exclude by modality string
                    $is_known_image = (bool) preg_match(
                        '/flux|dall-e|stable-diffusion|sdxl|ideogram|recraft|playground|black-forest/',
                        $id_lower
                    );

                    // Skip definite text-only models (unless the ID is a known image model)
                    if ( ! $is_known_image ) {
                        if ( $modality === 'text->text' ) continue;
                        if ( preg_match( '/->text$/', $modality ) ) continue;
                    }

                    // Must output images
                    $has_image = $is_known_image
                        || strpos( $modality, '->image' ) !== false
                        || in_array( 'image', $output_mods, true );
                    if ( ! $has_image ) continue;

                    $pricing  = $m['pricing'] ?? [];
                    $is_free  = ( ( $pricing['image'] ?? ( $pricing['prompt'] ?? '1' ) ) === '0' );
                    $label    = $is_free ? '★ FREE — ' : '💰 PAID — ';
                    if ( $is_free ) {
                        $free[] = [ 'id' => $m['id'], 'name' => $label . ( $m['name'] ?? $m['id'] ) ];
                    } else {
                        $paid[] = [ 'id' => $m['id'], 'name' => $label . ( $m['name'] ?? $m['id'] ) ];
                    }
                }
                usort( $free, function ( $a, $b ) { return strcmp( $a['id'], $b['id'] ); } );
                usort( $paid, function ( $a, $b ) { return strcmp( $a['id'], $b['id'] ); } );
                wp_send_json_success( array_merge( $free, $paid ) );

            default:
                wp_send_json_error( ucfirst( $provider ) . ' does not support image generation.' );
        }
    }

    // Text model fetching
    switch ( $provider ) {

        case 'openai':
            if ( ! $api_key ) wp_send_json_error( 'No API key — enter or save your OpenAI key first.' );
            $res  = wp_remote_get( 'https://api.openai.com/v1/models', [
                'timeout' => 15,
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
            ] );
            if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
            $code = wp_remote_retrieve_response_code( $res );
            $data = json_decode( wp_remote_retrieve_body( $res ), true );
            if ( $code !== 200 ) wp_send_json_error( $data['error']['message'] ?? 'HTTP ' . $code );

            $models = [];
            foreach ( $data['data'] ?? [] as $m ) {
                $id = $m['id'];
                if ( ! preg_match( '/^(gpt-|o1|o3|o4)/', $id ) ) continue;
                if ( strpos( $id, 'realtime' ) !== false ) continue;
                if ( strpos( $id, 'audio'    ) !== false ) continue;
                if ( strpos( $id, 'tts'      ) !== false ) continue;
                if ( strpos( $id, 'whisper'  ) !== false ) continue;
                if ( strpos( $id, 'embed'    ) !== false ) continue;
                $models[] = [ 'id' => $id, 'name' => $id ];
            }
            usort( $models, function ( $a, $b ) { return strcmp( $b['id'], $a['id'] ); } );
            wp_send_json_success( $models );

        case 'anthropic':
            if ( ! $api_key ) wp_send_json_error( 'No API key — enter or save your Anthropic key first.' );
            $res  = wp_remote_get( 'https://api.anthropic.com/v1/models', [
                'timeout' => 15,
                'headers' => [
                    'x-api-key'         => $api_key,
                    'anthropic-version' => '2023-06-01',
                ],
            ] );
            if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
            $code = wp_remote_retrieve_response_code( $res );
            $data = json_decode( wp_remote_retrieve_body( $res ), true );
            if ( $code !== 200 ) wp_send_json_error( $data['error']['message'] ?? 'HTTP ' . $code );

            $models = [];
            foreach ( $data['data'] ?? [] as $m ) {
                $models[] = [ 'id' => $m['id'], 'name' => $m['display_name'] ?? $m['id'] ];
            }
            wp_send_json_success( $models );

        case 'gemini':
            if ( ! $api_key ) wp_send_json_error( 'No API key — enter or save your Gemini key first.' );
            $res  = wp_remote_get( 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode( $api_key ), [
                'timeout' => 15,
            ] );
            if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
            $code = wp_remote_retrieve_response_code( $res );
            $data = json_decode( wp_remote_retrieve_body( $res ), true );
            if ( $code !== 200 ) wp_send_json_error( $data['error']['message'] ?? 'HTTP ' . $code );

            $models = [];
            foreach ( $data['models'] ?? [] as $m ) {
                if ( ! in_array( 'generateContent', $m['supportedGenerationMethods'] ?? [], true ) ) continue;
                $id       = str_replace( 'models/', '', $m['name'] );
                $models[] = [ 'id' => $id, 'name' => $m['displayName'] ?? $id ];
            }
            wp_send_json_success( $models );

        case 'openrouter':
            $headers = [ 'Content-Type' => 'application/json' ];
            if ( $api_key ) $headers['Authorization'] = 'Bearer ' . $api_key;
            $res  = wp_remote_get( 'https://openrouter.ai/api/v1/models', [
                'timeout' => 15,
                'headers' => $headers,
            ] );
            if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
            $code = wp_remote_retrieve_response_code( $res );
            $data = json_decode( wp_remote_retrieve_body( $res ), true );
            if ( $code !== 200 ) wp_send_json_error( $data['error']['message'] ?? 'HTTP ' . $code );

            $free = [];
            $paid = [];
            foreach ( $data['data'] ?? [] as $m ) {
                if ( empty( $m['id'] ) ) continue;
                $pricing = $m['pricing'] ?? [];
                $is_free = ( ( $pricing['prompt'] ?? '1' ) === '0' ) && ( ( $pricing['completion'] ?? '1' ) === '0' );
                $label   = $is_free ? '★ FREE — ' : '💰 PAID — ';
                $entry   = [
                    'id'   => $m['id'],
                    'name' => $label . ( $m['name'] ?? $m['id'] ),
                ];
                if ( $is_free ) {
                    $free[] = $entry;
                } else {
                    $paid[] = $entry;
                }
            }
            usort( $free, function ( $a, $b ) { return strcmp( $a['id'], $b['id'] ); } );
            usort( $paid, function ( $a, $b ) { return strcmp( $a['id'], $b['id'] ); } );
            wp_send_json_success( array_merge( $free, $paid ) );
    }
} );

// ── Build generation prompt from job params ────────────────────────────────────

function antradus_lite_build_and_run_generate( $job ) {
    $keyword   = $job['keyword'];
    $source    = $job['source'];
    $style     = $job['style'];
    $tone      = $job['tone'];
    $lang      = $job['lang'];
    $niche     = $job['niche'];
    $incl_faq  = $job['incl_faq'];
    $incl_meta = $job['incl_meta'];

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

    $provider = get_option( 'antradus_provider', 'openrouter' );
    switch ( $provider ) {
        case 'anthropic':  return antradus_lite_call_anthropic( $system, $user_msg );
        case 'gemini':     return antradus_lite_call_gemini( $system, $user_msg );
        case 'openrouter': return antradus_lite_call_openrouter( $system, $user_msg );
        default:           return antradus_lite_call_openai( $system, $user_msg );
    }
}

// ── Generate content ──────────────────────────────────────────────────────────

add_action( 'wp_ajax_antradus_generate', function () {
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

    @set_time_limit( 0 );

    $job = compact( 'keyword', 'source', 'style', 'tone', 'lang', 'niche', 'incl_faq', 'incl_meta' );

    try {
        $raw = antradus_lite_build_and_run_generate( $job );

        if ( ! $raw ) throw new \RuntimeException( 'Empty response from AI provider.' );

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
    } catch ( \Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
} );

// ── Active image style prompt ─────────────────────────────────────────────────

function antradus_lite_active_image_style_prompt() {
    $presets = antradus_lite_image_presets();
    $key     = get_option( 'antradus_image_preset', 'default' );
    $preset  = $presets[ $key ] ?? $presets['default'];
    $prompt  = get_option( $preset['option'], $preset['default'] );

    $color_enabled = get_option( 'antradus_image_color_enabled', '0' );
    $color         = get_option( 'antradus_image_color', '' );
    if ( $color_enabled === '1' && $color && $color !== '#ffffff' ) {
        $prompt .= "\n\nColor grading: Shift the entire color palette toward {$color} — apply this hue as the dominant tint across lighting, shadows, and highlights throughout the composition.";
    }

    return $prompt;
}

// ── Image generation helpers ───────────────────────────────────────────────────

function antradus_lite_throw_request_error( $wp_error ) {
    $msg = $wp_error->get_error_message();
    if ( strpos( $msg, 'timed out' ) !== false || strpos( $msg, 'Operation timed out' ) !== false ) {
        throw new \RuntimeException(
            'The image model took too long to respond (over 2 minutes). ' .
            'The model may be overloaded or slow — try a lighter/faster model in Settings → Antradus AI.'
        );
    }
    throw new \RuntimeException( 'Request failed: ' . $msg );
}

function antradus_lite_save_image_to_library( $img_data, $post_id ) {
    $filename = 'antradus-ai-' . time() . '.png';
    $upload   = wp_upload_bits( $filename, null, $img_data );
    if ( $upload['error'] ) throw new \RuntimeException( 'Upload error: ' . $upload['error'] );

    $attachment_id = wp_insert_attachment( [
        'post_mime_type' => 'image/png',
        'post_title'     => $filename,
        'post_content'   => '',
        'post_status'    => 'inherit',
    ], $upload['file'], $post_id );

    if ( is_wp_error( $attachment_id ) ) throw new \RuntimeException( 'Attachment error: ' . $attachment_id->get_error_message() );
    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

    return [ 'attachment_id' => $attachment_id, 'url' => $upload['url'] ];
}

function antradus_lite_generate_image_openai( $image_prompt, $post_id ) {
    $api_key = get_option( 'antradus_openai_api_key', '' );
    if ( ! $api_key ) throw new \RuntimeException( 'OpenAI API key not set. Go to Settings → Antradus AI.' );
    $model = get_option( 'antradus_openai_image_model', 'gpt-image-1' );

    $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
        'timeout' => 120,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'  => $model,
            'prompt' => $image_prompt,
            'n'      => 1,
            'size'   => '1024x1024',
        ] ),
    ] );

    if ( is_wp_error( $response ) ) antradus_lite_throw_request_error( $response );
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) throw new \RuntimeException( $body['error']['message'] ?? 'OpenAI error (HTTP ' . $code . ')' );

    $image_data = $body['data'][0] ?? [];

    if ( ! empty( $image_data['b64_json'] ) ) {
        $img_data = base64_decode( $image_data['b64_json'] );
        if ( ! $img_data ) throw new \RuntimeException( 'Could not decode image data' );
        return antradus_lite_save_image_to_library( $img_data, $post_id );
    }

    if ( ! empty( $image_data['url'] ) ) {
        $attachment_id = media_sideload_image( $image_data['url'], $post_id, null, 'id' );
        if ( is_wp_error( $attachment_id ) ) throw new \RuntimeException( 'Could not save image: ' . $attachment_id->get_error_message() );
        return [ 'attachment_id' => $attachment_id, 'url' => wp_get_attachment_url( $attachment_id ) ];
    }

    throw new \RuntimeException( 'No image data returned by OpenAI.' );
}

function antradus_lite_generate_image_openrouter( $image_prompt, $post_id ) {
    $api_key = get_option( 'antradus_openrouter_api_key', '' );
    if ( ! $api_key ) throw new \RuntimeException( 'OpenRouter API key not set. Go to Settings → Antradus AI.' );
    $model = get_option( 'antradus_openrouter_image_model', 'black-forest-labs/flux-1.1-pro' );

    $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', [
        'timeout' => 120,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'HTTP-Referer'  => get_site_url(),
            'X-Title'       => 'Antradus AI',
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'messages'   => [ [ 'role' => 'user', 'content' => $image_prompt ] ],
            'modalities' => [ 'image', 'text' ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) antradus_lite_throw_request_error( $response );
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) throw new \RuntimeException( $body['error']['message'] ?? 'OpenRouter error (HTTP ' . $code . ')' );

    $message = $body['choices'][0]['message'] ?? [];
    $content = $message['content'] ?? null;

    // Format 1: images array (some OpenRouter models)
    $images = $message['images'] ?? [];
    if ( ! empty( $images[0]['image_url']['url'] ) ) {
        $data_url = $images[0]['image_url']['url'];
        if ( preg_match( '/^data:image\/[^;]+;base64,(.+)$/s', $data_url, $m ) ) {
            $img_data = base64_decode( $m[1] );
            if ( $img_data ) return antradus_lite_save_image_to_library( $img_data, $post_id );
        }
    }

    // Format 2: content is an array of parts (standard OpenRouter image response)
    if ( is_array( $content ) ) {
        foreach ( $content as $part ) {
            $part_url = $part['image_url']['url'] ?? '';
            if ( $part_url ) {
                if ( preg_match( '/^data:image\/[^;]+;base64,(.+)$/s', $part_url, $m ) ) {
                    $img_data = base64_decode( $m[1] );
                    if ( $img_data ) return antradus_lite_save_image_to_library( $img_data, $post_id );
                }
                if ( preg_match( '/^https?:\/\//i', $part_url ) ) {
                    $attachment_id = media_sideload_image( $part_url, $post_id, null, 'id' );
                    if ( ! is_wp_error( $attachment_id ) ) return [ 'attachment_id' => $attachment_id, 'url' => wp_get_attachment_url( $attachment_id ) ];
                }
            }
        }
    }

    // Format 3: content is a string with a URL or base64 data URL
    if ( is_string( $content ) && $content !== '' ) {
        if ( preg_match( '/^data:image\/[^;]+;base64,(.+)$/s', $content, $m ) ) {
            $img_data = base64_decode( $m[1] );
            if ( $img_data ) return antradus_lite_save_image_to_library( $img_data, $post_id );
        }
        if ( preg_match( '/https?:\/\/\S+/i', $content, $matches ) ) {
            $url           = rtrim( $matches[0], ').' );
            $attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
            if ( ! is_wp_error( $attachment_id ) ) return [ 'attachment_id' => $attachment_id, 'url' => wp_get_attachment_url( $attachment_id ) ];
        }
        $preview = mb_substr( $content, 0, 200 );
        throw new \RuntimeException( 'Model returned text instead of an image. Response: ' . $preview );
    }

    throw new \RuntimeException( 'No image returned by the model. Try a different image model in Settings → Antradus AI.' );
}

function antradus_lite_generate_image_gemini( $image_prompt, $post_id ) {
    $api_key = get_option( 'antradus_gemini_api_key', '' );
    if ( ! $api_key ) throw new \RuntimeException( 'Gemini API key not set. Go to Settings → Antradus AI.' );
    $model = get_option( 'antradus_gemini_image_model', 'imagen-3.0-generate-001' );
    $url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':predict?key=' . urlencode( $api_key );

    $response = wp_remote_post( $url, [
        'timeout' => 120,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [
            'instances'  => [ [ 'prompt' => $image_prompt ] ],
            'parameters' => [ 'sampleCount' => 1 ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) antradus_lite_throw_request_error( $response );
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) throw new \RuntimeException( $body['error']['message'] ?? 'Gemini error (HTTP ' . $code . ')' );

    $b64 = $body['predictions'][0]['bytesBase64Encoded'] ?? '';
    if ( ! $b64 ) throw new \RuntimeException( 'No image data returned by Gemini.' );

    $img_data = base64_decode( $b64 );
    if ( ! $img_data ) throw new \RuntimeException( 'Could not decode image data.' );

    return antradus_lite_save_image_to_library( $img_data, $post_id );
}

// ── Generate image ────────────────────────────────────────────────────────────

add_action( 'wp_ajax_antradus_generate_image', function () {
    check_ajax_referer( 'antradus_generate_image', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $article_text       = sanitize_textarea_field( wp_unslash( $_POST['article_text']       ?? '' ) );
    $extra_instructions = sanitize_textarea_field( wp_unslash( $_POST['extra_instructions'] ?? '' ) );
    $instructions_only  = ( $_POST['instructions_only'] ?? '0' ) === '1';
    $post_id            = absint( $_POST['post_id'] ?? 0 );

    if ( ! $article_text && ! $extra_instructions ) wp_send_json_error( 'No article text or instructions provided' );

    $style_prompt = antradus_lite_active_image_style_prompt();

    if ( $instructions_only && $extra_instructions ) {
        $image_prompt = "Generate an image of: " . $extra_instructions . "\n\nVisual style: " . $style_prompt;
    } else {
        $image_prompt  = $article_text ? "Create an image for an article about:\n\n" . mb_substr( $article_text, 0, 600 ) . "\n\n" : '';
        $image_prompt .= "Visual style: " . $style_prompt;
        if ( $extra_instructions ) $image_prompt .= "\n\nAdditional instructions: " . $extra_instructions;
    }

    @set_time_limit( 0 );

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    try {
        $provider = get_option( 'antradus_provider', 'openrouter' );
        switch ( $provider ) {
            case 'gemini':     $result = antradus_lite_generate_image_gemini( $image_prompt, $post_id );     break;
            case 'openrouter': $result = antradus_lite_generate_image_openrouter( $image_prompt, $post_id ); break;
            case 'anthropic':  throw new \RuntimeException( 'Anthropic does not support image generation. Switch provider in Settings → Antradus AI.' );
            default:           $result = antradus_lite_generate_image_openai( $image_prompt, $post_id );
        }
        wp_send_json_success( $result );
    } catch ( \Exception $e ) {
        wp_send_json_error( $e->getMessage() );
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
