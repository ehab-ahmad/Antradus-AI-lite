<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Image preset definitions ───────────────────────────────────────────────────
function antradus_lite_image_presets() {
    return [
        'default' => [
            'label'   => '🌐 Default',
            'option'  => 'antradus_image_prompt',
            'default' => 'A high-end lifestyle marketing banner in a clean editorial thumbnail style, professional commercial photography, soft cinematic lighting, shallow depth of field, warm color grading, visually pleasing composition with subject on one side and negative space on the other for text, modern bold sans-serif headline typography overlay, mixed font weights (white + accent color like yellow), minimal UI-style icons or graphic elements, subtle decorative shapes, premium brand feel, social media thumbnail layout, ultra realistic, sharp focus, high detail, 4K, visually engaging, emotionally appealing, advertising style',
        ],
        'gaming' => [
            'label'   => '🎮 Gaming',
            'option'  => 'antradus_image_prompt_gaming',
            'default' => 'High-end gaming banner thumbnail, dramatic neon-lit game environment, cinematic lens flare, deep shadow contrast with vivid electric highlights in blue and purple, first-person or character silhouette focal point, action-frozen motion blur on periphery, bold heavy sans-serif game title in gradient chrome text, HUD-inspired graphic elements and geometric frames, particle effects and energy sparks, dark atmospheric background with glowing depth, aggressive dynamic composition, 4K ultra-sharp, hyperrealistic game art style, adrenaline-inducing, commercial esports aesthetic',
        ],
        'medical' => [
            'label'   => '🩺 Medical',
            'option'  => 'antradus_image_prompt_medical',
            'default' => 'Clean medical article thumbnail, professional clinical photography, bright sterile whites and soft sky blues, shallow depth of field on medical subject (doctor, stethoscope, anatomy model, or lab setting), calm reassuring composition, subject centered with breathing negative space, modern clean sans-serif headline in deep navy or teal, subtle cross or pulse-line icon accent, minimal flat graphic overlays, warm yet authoritative brand tone, trustworthy and credible aesthetic, softbox studio lighting, ultra-sharp detail, 4K, editorial healthcare magazine style',
        ],
        'news' => [
            'label'   => '📰 News',
            'option'  => 'antradus_image_prompt_news',
            'default' => 'Urgent news broadcast thumbnail, bold high-contrast photojournalism style, desaturated background image with selective color pop, strong horizontal red or yellow ticker-bar graphic element, tight crop on subject face or key event object, hard directional lighting, dramatic shadows, serif or condensed sans-serif bold headline in white with red accent, LIVE or BREAKING badge in urgent red, timestamp and channel bug in corner, dynamic skewed text overlay for urgency, cinematic news reportage aesthetic, 4K, emotionally charged, broadcast journalism visual language',
        ],
        'sports' => [
            'label'   => '⚡ Sports',
            'option'  => 'antradus_image_prompt_sports',
            'default' => 'High-energy sports editorial thumbnail, peak-action freeze frame composition, motion-streaked stadium or arena background with bokeh crowd lights, athlete in dynamic pose as hero subject, high-contrast punchy color grading with warm golden hour tones, tight telephoto compression, condensed bold sans-serif sport stats or score overlay, team colors as dominant accent palette, speed lines or light trail graphic elements, gritty texture grain, dramatic underlighting on athlete, ESPN or Sky Sports broadcast banner aesthetic, 4K ultra sharp, raw athletic power, emotionally electric',
        ],
        'finance' => [
            'label'   => '📈 Finance',
            'option'  => 'antradus_image_prompt_finance',
            'default' => 'Premium finance editorial thumbnail, sharp corporate photography, cool neutral palette of slate blue, charcoal and white, minimalist graph or upward arrow graphic accent, professional in suit as subject or abstract fintech visual, clean grid-based layout with strong left-to-right information flow, bold condensed sans-serif headline in deep navy, subtle gold or green accent color for key stat or number, glassmorphism-lite data card overlay, soft directional studio lighting, Bloomberg or WSJ editorial aesthetic, 4K ultra-detail, authoritative and confident',
        ],
        'tech' => [
            'label'   => '🔬 Technology',
            'option'  => 'antradus_image_prompt_tech',
            'default' => 'Cutting-edge tech editorial thumbnail, macro or abstract technology subject (circuit board, glowing chip, robotic hand, quantum visual), deep cool blue-black background with electric cyan or violet accent glow, shallow depth of field with precise focus on key detail, futuristic clean sans-serif headline in white with neon blue accent, minimal floating UI elements suggesting data or AI, subtle wireframe geometric overlay, precise sharp optical clarity, Wired or MIT Tech Review visual style, 4K, intellectually engaging, innovation-forward aesthetic, premium digital publication feel',
        ],
        'food' => [
            'label'   => '🍽️ Food',
            'option'  => 'antradus_image_prompt_food',
            'default' => 'Editorial food and lifestyle thumbnail, professional overhead or 45-degree hero shot, warm natural light with soft fill, hero dish or product as centered subject surrounded by artful negative space props, rich earthy or pastel color palette, shallow depth of field with perfect bokeh on background, casual elegant serif or rounded sans-serif headline in warm white or off-cream, ingredient or lifestyle accent icons, clean wooden or marble surface texture, Bon Appétit or Kinfolk editorial aesthetic, ultra-sharp food photography, 4K, indulgent and inviting, emotionally warm',
        ],
        'travel' => [
            'label'   => '✈️ Travel',
            'option'  => 'antradus_image_prompt_travel',
            'default' => 'Cinematic travel editorial thumbnail, wide establishing shot of iconic destination or cultural scene, golden hour or blue hour soft atmospheric lighting, lone figure or cultural detail as human-scale anchor, rich warm earth tones with vivid sky, subject placed at rule-of-thirds intersection leaving sky or negative space for headline, bold adventurous sans-serif in white with contrasting color flag or location tag, subtle compass or map pin graphic element, film grain texture overlay, National Geographic or Condé Nast Traveller visual language, 4K, wanderlust-inducing, emotionally transportive',
        ],
        'entertainment' => [
            'label'   => '🎬 Entertainment',
            'option'  => 'antradus_image_prompt_entertainment',
            'default' => 'Cinematic entertainment thumbnail, dramatic movie poster composition, key character or film subject in strong hero lighting against deep atmospheric background, teal-and-orange Hollywood color grading, lens breathing blur effect at edges for filmic depth, bold italic serif or display font headline in metallic white with drop detail, star rating badge or release date tag, layered depth from foreground element to background, Variety or The Hollywood Reporter editorial style, ultra-sharp central focus with artistic peripheral softness, 4K, emotionally compelling, premium streaming platform aesthetic',
        ],
    ];
}

add_action( 'admin_menu', function () {
    add_options_page( 'Antradus AI', 'Antradus AI', 'manage_options', 'antradus-ai', 'antradus_lite_settings_page' );
} );

add_action( 'admin_init', function () {
    $preserve = function ( $key ) {
        return function ( $new ) use ( $key ) {
            $new = sanitize_text_field( $new );
            return empty( $new ) ? get_option( $key, '' ) : $new;
        };
    };

    register_setting( 'antradus_settings_group', 'antradus_provider',             [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'openrouter' ] );
    register_setting( 'antradus_settings_group', 'antradus_openai_api_key',        [ 'sanitize_callback' => $preserve( 'antradus_openai_api_key' ) ] );
    register_setting( 'antradus_settings_group', 'antradus_openai_model',          [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'gpt-4o' ] );
    register_setting( 'antradus_settings_group', 'antradus_anthropic_api_key',     [ 'sanitize_callback' => $preserve( 'antradus_anthropic_api_key' ) ] );
    register_setting( 'antradus_settings_group', 'antradus_anthropic_model',       [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'claude-opus-4-7' ] );
    register_setting( 'antradus_settings_group', 'antradus_gemini_api_key',        [ 'sanitize_callback' => $preserve( 'antradus_gemini_api_key' ) ] );
    register_setting( 'antradus_settings_group', 'antradus_gemini_model',          [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'gemini-2.0-flash' ] );
    register_setting( 'antradus_settings_group', 'antradus_openrouter_api_key',    [ 'sanitize_callback' => $preserve( 'antradus_openrouter_api_key' ) ] );
    register_setting( 'antradus_settings_group', 'antradus_openrouter_model',      [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'openai/gpt-4o' ] );
    register_setting( 'antradus_settings_group', 'antradus_topics',                [ 'sanitize_callback' => 'sanitize_textarea_field', 'default' => "Technology\nGaming\nMedical" ] );
    register_setting( 'antradus_settings_group', 'antradus_system_prompt',         [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_openai_image_model',         [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'gpt-image-1' ] );
    register_setting( 'antradus_settings_group', 'antradus_gemini_image_model',        [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'imagen-3.0-generate-001' ] );
    register_setting( 'antradus_settings_group', 'antradus_openrouter_image_model',    [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'black-forest-labs/flux-1.1-pro' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_preset',              [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'default' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt',              [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt_gaming',       [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt_medical',      [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt_news',         [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt_sports',       [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt_finance',      [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt_tech',         [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt_food',         [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt_travel',       [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt_entertainment',[ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_color',               [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#ff6b35' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_color_enabled',       [ 'sanitize_callback' => function ( $v ) { return $v === '1' ? '1' : '0'; }, 'default' => '0' ] );
    register_setting( 'antradus_settings_group', 'antradus_disable_gutenberg_posts',   [ 'sanitize_callback' => function ( $v ) { return $v === '1' ? '1' : '0'; }, 'default' => '1' ] );
} );

add_filter( 'use_block_editor_for_post_type', function ( $use, $post_type ) {
    if ( $post_type === 'post' && get_option( 'antradus_disable_gutenberg_posts', '1' ) === '1' ) {
        return false;
    }
    return $use;
}, 10, 2 );

function antradus_lite_sanitize_api_key( $new ) {
    $new = sanitize_text_field( $new );
    return empty( $new ) ? get_option( 'antradus_openai_api_key', '' ) : $new;
}

function antradus_lite_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $provider         = get_option( 'antradus_provider', 'openrouter' );
    $openai_key       = get_option( 'antradus_openai_api_key', '' );
    $openai_model     = get_option( 'antradus_openai_model', 'gpt-4o-mini' );
    $anthropic_key    = get_option( 'antradus_anthropic_api_key', '' );
    $anthropic_model  = get_option( 'antradus_anthropic_model', 'claude-haiku-4-5-20251001' );
    $gemini_key       = get_option( 'antradus_gemini_api_key', '' );
    $gemini_model     = get_option( 'antradus_gemini_model', 'gemini-2.0-flash-lite' );
    $openrouter_key   = get_option( 'antradus_openrouter_api_key', '' );
    $openrouter_model = get_option( 'antradus_openrouter_model', 'google/gemini-2.0-flash-exp:free' );

    $openai_image_model      = get_option( 'antradus_openai_image_model', 'dall-e-2' );
    $gemini_image_model      = get_option( 'antradus_gemini_image_model', 'imagen-3.0-generate-001' );
    $openrouter_image_model  = get_option( 'antradus_openrouter_image_model', 'black-forest-labs/flux-1.1-pro' );
    $image_preset        = get_option( 'antradus_image_preset', 'default' );
    $image_color         = get_option( 'antradus_image_color', '#ff6b35' );
    $image_color_enabled = get_option( 'antradus_image_color_enabled', '0' );
    $presets             = antradus_lite_image_presets();
    $topics              = get_option( 'antradus_topics', "Technology\nGaming\nMedical" );

    $default_system  = "You are a professional content writer. Write engaging, SEO-friendly articles for a general audience.\n\n";
    $default_system .= "If one source article is provided, use it only as a fact source — do not follow its structure. ";
    $default_system .= "Write a completely original article from scratch with a different angle, opening, and narrative flow.\n\n";
    $default_system .= "Rules:\n- Open with a compelling hook: a scene, question, or bold statement. Never a definition.\n";
    $default_system .= "- Use H1, H2, H3 in clean HTML only — no other tags\n- Short paragraphs, 2-3 lines max\n";
    $default_system .= "- Conversational and confident tone\n- Light authority cues where relevant\n- Target length: 1000-1400 words";
    $system_prompt = get_option( 'antradus_system_prompt', $default_system );

    ?>
    <div class="wrap antradus-settings-wrap">

        <div class="antradus-settings-layout">

            <nav class="antradus-settings-nav">
                <p class="antradus-nav-heading">QUICK NAV</p>
                <a href="#antradus-api-model">AI Providers</a>
                <a href="#antradus-topics">Topics</a>
                <a href="#antradus-system-prompt">System Prompt</a>
                <a href="#antradus-editor-settings">Editor</a>
                <a href="#antradus-image-settings">Image Settings</a>
            </nav>

            <div class="antradus-settings-main">
                <form method="post" action="options.php">
                    <?php settings_fields( 'antradus_settings_group' ); ?>

                    <!-- ── AI Providers ──────────────────────────────────────── -->
                    <div class="antradus-settings-section" id="antradus-api-model">
                        <h2><span class="antradus-section-icon">🔑</span> AI Providers &amp; Models</h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Active Provider</th>
                                <td>
                                    <select name="antradus_provider" id="antradus-provider-select" class="regular-text">
                                        <option value="openrouter" <?php selected( $provider, 'openrouter' ); ?>>OpenRouter</option>
                                        <option value="openai"     <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
                                        <option value="anthropic"  <?php selected( $provider, 'anthropic' ); ?>>Anthropic (Claude)</option>
                                        <option value="gemini"     <?php selected( $provider, 'gemini' ); ?>>Google Gemini</option>
                                    </select>
                                    <p class="description">The AI provider used for article generation. Only the selected provider's settings are shown below.</p>
                                </td>
                            </tr>
                        </table>

                        <!-- OpenAI -->
                        <div class="antradus-provider-section antradus-provider-openai" data-provider="openai">
                            <h3 class="antradus-provider-heading">OpenAI <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener" class="antradus-apikey-link">Get API key →</a></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">API Key</th>
                                    <td>
                                        <?php if ( $openai_key ) : ?>
                                            <input type="password" name="antradus_openai_api_key" value="" placeholder="Key saved — enter a new one to replace it" class="regular-text" autocomplete="new-password" data-key-saved="1" />
                                            <p class="description" style="color:#00a32a;">&#10003; Key is saved. Leave blank to keep existing.</p>
                                        <?php else : ?>
                                            <input type="password" name="antradus_openai_api_key" value="" placeholder="sk-..." class="regular-text" autocomplete="new-password" />
                                            <p class="description">Your OpenAI API key. Also required for image generation regardless of active text provider.</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Model</th>
                                    <td>
                                        <div class="antradus-fetch-row">
                                            <select name="antradus_openai_model" id="antradus-model-select-openai" data-saved="<?php echo esc_attr( $openai_model ); ?>">
                                                <option value="<?php echo esc_attr( $openai_model ); ?>" selected><?php echo esc_html( $openai_model ?: 'gpt-4o' ); ?></option>
                                            </select>
                                            <button type="button" class="button antradus-fetch-models-btn" data-provider="openai">&#8635; Fetch Models</button>
                                            <span class="antradus-model-status" id="antradus-model-status-openai"></span>
                                        </div>
                                        <p class="description">e.g. gpt-4o, gpt-4.1, o3. Click Fetch to load all available models.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Image Model</th>
                                    <td>
                                        <div class="antradus-fetch-row">
                                            <select name="antradus_openai_image_model" id="antradus-image-model-select-openai" data-saved="<?php echo esc_attr( $openai_image_model ); ?>">
                                                <option value="<?php echo esc_attr( $openai_image_model ); ?>" selected><?php echo esc_html( $openai_image_model ?: 'gpt-image-1' ); ?></option>
                                            </select>
                                            <button type="button" class="button antradus-fetch-models-btn" data-provider="openai" data-type="image">&#8635; Fetch Image Models</button>
                                            <span class="antradus-model-status" id="antradus-image-model-status-openai"></span>
                                        </div>
                                        <p class="description">e.g. gpt-image-1, dall-e-3. Click Fetch to load available image models.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Anthropic -->
                        <div class="antradus-provider-section antradus-provider-anthropic" data-provider="anthropic">
                            <h3 class="antradus-provider-heading">Anthropic <span class="antradus-provider-tag">Claude</span> <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener" class="antradus-apikey-link">Get API key →</a></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">API Key</th>
                                    <td>
                                        <?php if ( $anthropic_key ) : ?>
                                            <input type="password" name="antradus_anthropic_api_key" value="" placeholder="Key saved — enter a new one to replace it" class="regular-text" autocomplete="new-password" data-key-saved="1" />
                                            <p class="description" style="color:#00a32a;">&#10003; Key is saved. Leave blank to keep existing.</p>
                                        <?php else : ?>
                                            <input type="password" name="antradus_anthropic_api_key" value="" placeholder="sk-ant-..." class="regular-text" autocomplete="new-password" />
                                            <p class="description">Your Anthropic API key from console.anthropic.com.</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Model</th>
                                    <td>
                                        <div class="antradus-fetch-row">
                                            <select name="antradus_anthropic_model" id="antradus-model-select-anthropic" data-saved="<?php echo esc_attr( $anthropic_model ); ?>">
                                                <option value="<?php echo esc_attr( $anthropic_model ); ?>" selected><?php echo esc_html( $anthropic_model ?: 'claude-opus-4-7' ); ?></option>
                                            </select>
                                            <button type="button" class="button antradus-fetch-models-btn" data-provider="anthropic">&#8635; Fetch Models</button>
                                            <span class="antradus-model-status" id="antradus-model-status-anthropic"></span>
                                        </div>
                                        <p class="description">e.g. claude-opus-4-7, claude-sonnet-4-6. Click Fetch to load all available models.</p>
                                    </td>
                                </tr>
                            </table>
                            <p class="antradus-no-image-note">&#128247; Image generation is <strong>not supported</strong> by Anthropic. Switch to OpenAI, Gemini, or OpenRouter to use image features.</p>
                        </div>

                        <!-- Google Gemini -->
                        <div class="antradus-provider-section antradus-provider-gemini" data-provider="gemini">
                            <h3 class="antradus-provider-heading">Google <span class="antradus-provider-tag">Gemini</span> <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" class="antradus-apikey-link">Get API key →</a></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">API Key</th>
                                    <td>
                                        <?php if ( $gemini_key ) : ?>
                                            <input type="password" name="antradus_gemini_api_key" value="" placeholder="Key saved — enter a new one to replace it" class="regular-text" autocomplete="new-password" data-key-saved="1" />
                                            <p class="description" style="color:#00a32a;">&#10003; Key is saved. Leave blank to keep existing.</p>
                                        <?php else : ?>
                                            <input type="password" name="antradus_gemini_api_key" value="" placeholder="AIza..." class="regular-text" autocomplete="new-password" />
                                            <p class="description">Your Google AI Studio API key from aistudio.google.com.</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Model</th>
                                    <td>
                                        <div class="antradus-fetch-row">
                                            <select name="antradus_gemini_model" id="antradus-model-select-gemini" data-saved="<?php echo esc_attr( $gemini_model ); ?>">
                                                <option value="<?php echo esc_attr( $gemini_model ); ?>" selected><?php echo esc_html( $gemini_model ?: 'gemini-2.0-flash' ); ?></option>
                                            </select>
                                            <button type="button" class="button antradus-fetch-models-btn" data-provider="gemini">&#8635; Fetch Models</button>
                                            <span class="antradus-model-status" id="antradus-model-status-gemini"></span>
                                        </div>
                                        <p class="description">e.g. gemini-2.0-flash, gemini-1.5-pro. Click Fetch to load all available models.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Image Model</th>
                                    <td>
                                        <div class="antradus-fetch-row">
                                            <select name="antradus_gemini_image_model" id="antradus-image-model-select-gemini" data-saved="<?php echo esc_attr( $gemini_image_model ); ?>">
                                                <option value="<?php echo esc_attr( $gemini_image_model ); ?>" selected><?php echo esc_html( $gemini_image_model ?: 'imagen-3.0-generate-001' ); ?></option>
                                            </select>
                                            <button type="button" class="button antradus-fetch-models-btn" data-provider="gemini" data-type="image">&#8635; Fetch Image Models</button>
                                            <span class="antradus-model-status" id="antradus-image-model-status-gemini"></span>
                                        </div>
                                        <p class="description">e.g. imagen-3.0-generate-001. Click Fetch to load available Imagen models.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- OpenRouter -->
                        <div class="antradus-provider-section antradus-provider-openrouter" data-provider="openrouter">
                            <h3 class="antradus-provider-heading">OpenRouter <a href="https://openrouter.ai/keys" target="_blank" rel="noopener" class="antradus-apikey-link">Get API key →</a></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">API Key</th>
                                    <td>
                                        <?php if ( $openrouter_key ) : ?>
                                            <input type="password" name="antradus_openrouter_api_key" value="" placeholder="Key saved — enter a new one to replace it" class="regular-text" autocomplete="new-password" data-key-saved="1" />
                                            <p class="description" style="color:#00a32a;">&#10003; Key is saved. Leave blank to keep existing.</p>
                                        <?php else : ?>
                                            <input type="password" name="antradus_openrouter_api_key" value="" placeholder="sk-or-..." class="regular-text" autocomplete="new-password" />
                                            <p class="description">Your OpenRouter API key from openrouter.ai. Fetch Models works without a key.</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Model</th>
                                    <td>
                                        <div class="antradus-fetch-row">
                                            <select name="antradus_openrouter_model" id="antradus-model-select-openrouter" data-saved="<?php echo esc_attr( $openrouter_model ); ?>">
                                                <option value="<?php echo esc_attr( $openrouter_model ); ?>" selected><?php echo esc_html( $openrouter_model ?: 'openai/gpt-4o' ); ?></option>
                                            </select>
                                            <button type="button" class="button antradus-fetch-models-btn" data-provider="openrouter">&#8635; Fetch Models</button>
                                            <span class="antradus-model-status" id="antradus-model-status-openrouter"></span>
                                        </div>
                                        <p class="description">e.g. openai/gpt-4o, anthropic/claude-opus-4-7. Fetches all 200+ models from OpenRouter.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Image Model</th>
                                    <td>
                                        <div class="antradus-fetch-row">
                                            <select name="antradus_openrouter_image_model" id="antradus-image-model-select-openrouter" data-saved="<?php echo esc_attr( $openrouter_image_model ); ?>">
                                                <option value="<?php echo esc_attr( $openrouter_image_model ); ?>" selected><?php echo esc_html( $openrouter_image_model ?: 'black-forest-labs/flux-1.1-pro' ); ?></option>
                                            </select>
                                            <button type="button" class="button antradus-fetch-models-btn" data-provider="openrouter" data-type="image">&#8635; Fetch Image Models</button>
                                            <span class="antradus-model-status" id="antradus-image-model-status-openrouter"></span>
                                        </div>
                                        <p class="description">e.g. black-forest-labs/flux-1.1-pro, openai/dall-e-3. Click Fetch to load available image generation models.</p>
                                    </td>
                                </tr>
                            </table>
                            <p class="antradus-openrouter-note">&#9888; <strong>Note:</strong> Providers serving free models often retain and/or train on prompts and completions and their results might not be the best.</p>
                        </div>
                    </div>

                    <!-- ── Topics ────────────────────────────────────────────── -->
                    <div class="antradus-settings-section" id="antradus-topics">
                        <h2><span class="antradus-section-icon">📌</span> Topics / Niches</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Topics</th>
                                <td>
                                    <textarea name="antradus_topics" rows="6" class="large-text" style="font-size:13px;"><?php echo esc_textarea( $topics ); ?></textarea>
                                    <p class="description">One topic per line. These appear as clickable buttons in the content generator.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- ── System Prompt ─────────────────────────────────────── -->
                    <div class="antradus-settings-section" id="antradus-system-prompt">
                        <h2><span class="antradus-section-icon">🔥</span> System Prompt</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">System Prompt</th>
                                <td>
                                    <textarea name="antradus_system_prompt" rows="14" class="large-text" style="font-family:monospace;font-size:12px;"><?php echo esc_textarea( $system_prompt ); ?></textarea>
                                    <p class="description">Base AI instructions. Keyword, language, style, tone, niche, and output format are appended automatically.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- ── Editor Settings ──────────────────────────────────── -->
                    <div class="antradus-settings-section" id="antradus-editor-settings">
                        <h2><span class="antradus-section-icon">✏️</span> Editor Settings</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Classic Editor for Posts</th>
                                <td>
                                    <input type="hidden" name="antradus_disable_gutenberg_posts" value="0" />
                                    <label>
                                        <input type="checkbox" name="antradus_disable_gutenberg_posts" value="1" <?php checked( get_option( 'antradus_disable_gutenberg_posts', '1' ), '1' ); ?> />
                                        <strong>Disable the Gutenberg block editor for Posts</strong>
                                    </label>
                                    <p class="description" style="margin-top:6px;">
                                        When enabled, all <strong>Posts</strong> will use the Classic Editor. Pages are not affected.
                                    </p>
                                    <p class="description" style="margin-top:4px; color: #2271b1;">
                                        &#9432; Antradus AI works on both editors, but is easier to use with the Classic Editor — the generated HTML inserts cleanly without block conversion.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- ── Image Generation ──────────────────────────────────── -->
                    <div class="antradus-settings-section" id="antradus-image-settings">
                        <h2><span class="antradus-section-icon">🖼️</span> Image Generation</h2>
                        <p class="description" style="margin-bottom:16px;">Image models are set per provider above. Select a style preset, customise its prompt if needed, then mark it as active. A color cast can optionally shift the image's overall palette.</p>

                        <!-- Style preset tabs -->
                        <div class="antradus-tab-group" id="antradus-image-tab-group">
                            <div class="antradus-tab-nav">
                                <?php foreach ( $presets as $key => $preset_data ) :
                                    $is_active_tab = ( $key === $image_preset );
                                ?>
                                <button type="button"
                                    class="antradus-tab-btn<?php echo $is_active_tab ? ' is-active' : ''; ?>"
                                    data-tab="<?php echo esc_attr( $key ); ?>">
                                    <?php echo esc_html( $preset_data['label'] ); ?>
                                    <?php if ( $is_active_tab ) : ?><span class="antradus-tab-active-badge">&#10003;</span><?php endif; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>

                            <?php foreach ( $presets as $key => $preset_data ) :
                                $is_active_panel = ( $key === $image_preset );
                                $saved_prompt    = get_option( $preset_data['option'], $preset_data['default'] );
                            ?>
                            <div class="antradus-tab-panel<?php echo $is_active_panel ? ' is-active' : ''; ?>"
                                 id="antradus-image-tab-panel-<?php echo esc_attr( $key ); ?>"
                                 <?php if ( ! $is_active_panel ) echo 'style="display:none;"'; ?>>
                                <label class="antradus-preset-active-label">
                                    <input type="radio" name="antradus_image_preset" value="<?php echo esc_attr( $key ); ?>" <?php checked( $image_preset, $key ); ?> />
                                    <strong>Set as active preset</strong> — this style will be used when generating images
                                </label>
                                <textarea name="<?php echo esc_attr( $preset_data['option'] ); ?>" rows="5" class="large-text antradus-preset-textarea"><?php echo esc_textarea( $saved_prompt ); ?></textarea>
                                <p class="description" style="margin-top:4px;">Edit freely — your changes are saved with the form.</p>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Color cast -->
                        <div class="antradus-color-cast-wrap">
                            <label class="antradus-color-cast-toggle">
                                <input type="hidden"   name="antradus_image_color_enabled" value="0" />
                                <input type="checkbox" name="antradus_image_color_enabled" value="1" id="antradus-color-enabled" <?php checked( $image_color_enabled, '1' ); ?> />
                                <strong>Apply color cast</strong> — shift the image palette toward a chosen hue
                            </label>
                            <div id="antradus-color-picker-row" class="antradus-color-picker-row"<?php echo $image_color_enabled !== '1' ? ' style="display:none;"' : ''; ?>>
                                <input type="color" name="antradus_image_color" value="<?php echo esc_attr( $image_color ?: '#ff6b35' ); ?>" id="antradus-color-picker" />
                                <span class="antradus-color-cast-hint">Selected color will be appended to the active preset as a dominant tint instruction.</span>
                            </div>
                            <p class="description" style="margin-top:6px;">Leave unchecked to use the preset as-is with no color override.</p>
                        </div>
                    </div>

                    <?php submit_button( 'Save Changes' ); ?>
                </form>
            </div>

        </div>
    </div>
    <?php
}
