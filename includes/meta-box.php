<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;

    global $post;
    $post_id = $post ? absint( $post->ID ) : 0;

    $css_ver = filemtime( ANTRADUS_AI_LITE_DIR . 'assets/css/admin.css' ) ?: ANTRADUS_AI_LITE_VERSION;
    $js_ver  = filemtime( ANTRADUS_AI_LITE_DIR . 'assets/js/admin.js'  ) ?: ANTRADUS_AI_LITE_VERSION;
    wp_enqueue_style( 'antradus-admin', ANTRADUS_AI_LITE_URL . 'assets/css/admin.css', [], $css_ver );
    wp_enqueue_script( 'antradus-admin', ANTRADUS_AI_LITE_URL . 'assets/js/admin.js', [], $js_ver, true );
    wp_localize_script( 'antradus-admin', 'antradusData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'postId'  => $post_id,
        'proUrl'  => ANTRADUS_AI_LITE_PRO_URL,
        'nonces'  => [
            'fetchUrl'      => wp_create_nonce( 'antradus_fetch_url' ),
            'generate'      => wp_create_nonce( 'antradus_generate' ),
            'generateImage' => wp_create_nonce( 'antradus_generate_image' ),
            'setFeatured'   => wp_create_nonce( 'antradus_set_featured' ),
        ],
    ] );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'settings_page_antradus-ai' ) return;
    $css_ver = filemtime( ANTRADUS_AI_LITE_DIR . 'assets/css/admin.css'   ) ?: ANTRADUS_AI_LITE_VERSION;
    $js_ver  = filemtime( ANTRADUS_AI_LITE_DIR . 'assets/js/settings.js'  ) ?: ANTRADUS_AI_LITE_VERSION;
    wp_enqueue_style(  'antradus-settings', ANTRADUS_AI_LITE_URL . 'assets/css/admin.css',  [], $css_ver );
    wp_enqueue_script( 'antradus-settings', ANTRADUS_AI_LITE_URL . 'assets/js/settings.js', [], $js_ver, true );
    wp_localize_script( 'antradus-settings', 'antradusSettings', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'antradus_fetch_models' ),
    ] );
} );

add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'antradus_ai_box',
        'Antradus AI Lite — Content Generator',
        'antradus_lite_meta_box_html',
        [ 'post', 'page' ],
        'normal',
        'high'
    );
} );

function antradus_lite_meta_box_html( $post ) {
    $saved_keyword = get_post_meta( $post->ID, '_antradus_keyword', true );
    $saved_url     = get_post_meta( $post->ID, '_antradus_url',     true );

    wp_nonce_field( 'antradus_save_meta', 'antradus_nonce' );

    $topics = array_filter( array_map( 'trim', explode( "\n", get_option( 'antradus_topics', "Technology\nGaming\nMedical" ) ) ) );
    ?>
    <div id="antradus-wrap">

        <div class="antradus-header">
            <span>Antradus AI Lite</span>
            <small>Article Forge</small>
        </div>

        <div class="antradus-two-col">

            <!-- ── LEFT: Content & Meta ───────────────────────────────────── -->
            <div class="antradus-col-content">

                <!-- Main topic / keyword -->
                <div class="antradus-field antradus-topic-field">
                    <label for="antradus-keyword">WHAT'S YOUR STORY? (Focus Keyword)</label>
                    <input
                        type="text"
                        id="antradus-keyword"
                        name="antradus_keyword"
                        placeholder="e.g. The future of electric cars"
                        value="<?php echo esc_attr( $saved_keyword ); ?>"
                    />
                </div>

                <div id="antradus-mode-hint"></div>

                <!-- Rewrite from external source -->
                <div class="antradus-accordion <?php echo $saved_url ? 'is-open' : ''; ?>" id="antradus-source-accordion">
                    <div class="antradus-accordion-header" data-target="antradus-source-body">
                        <span class="antradus-accordion-icon">🔗</span>
                        <span>Rewrite from External Source</span>
                        <span class="antradus-accordion-arrow">▲</span>
                    </div>
                    <div class="antradus-accordion-body" id="antradus-source-body">
                        <div class="antradus-field">
                            <label>SOURCE URL <span class="antradus-label-hint">(recraft from existing source)</span></label>
                            <input type="url" id="antradus-url" name="antradus_url" placeholder="https://example.com/article" dir="ltr" value="<?php echo esc_attr( $saved_url ); ?>" />
                        </div>

                        <!-- Merge 2 Articles — Pro feature -->
                        <a href="<?php echo esc_url( ANTRADUS_AI_LITE_PRO_URL ); ?>" target="_blank" rel="noopener" class="antradus-add-url-btn antradus-pro-btn">
                            🔒 Merge 2 Sources &mdash; Pro &nbsp;&bull;&nbsp; <strong>Upgrade Now →</strong>
                        </a>
                    </div>
                </div>

                <!-- YouTube to Article Generator — Pro feature -->
                <div class="antradus-accordion" id="antradus-yt-accordion">
                    <div class="antradus-accordion-header" data-target="antradus-yt-body">
                        <span class="antradus-accordion-icon">▶</span>
                        <span>YouTube to Article Generator</span>
                        <span class="antradus-accordion-arrow">▲</span>
                    </div>
                    <div class="antradus-accordion-body" id="antradus-yt-body">
                        <div class="antradus-pro-notice">
                            <span class="antradus-pro-notice-icon">🔒</span>
                            <div>
                                <strong>YouTube to Article Generator is a Pro feature.</strong>
                                Paste a YouTube URL and Antradus AI pulls the transcript and writes a polished, original article from scratch.
                            </div>
                            <a href="<?php echo esc_url( ANTRADUS_AI_LITE_PRO_URL ); ?>" target="_blank" rel="noopener" class="antradus-pro-upgrade-link">Upgrade Now →</a>
                        </div>
                    </div>
                </div>

                <!-- Style / Tone / Language -->
                <div class="antradus-row antradus-row-3">
                    <div class="antradus-field">
                        <label>STYLE</label>
                        <select id="antradus-style">
                            <option>Blog</option>
                            <option>News</option>
                            <option>Listicle</option>
                            <option>How-To</option>
                            <option>Opinion</option>
                        </select>
                    </div>
                    <div class="antradus-field">
                        <label>TONE</label>
                        <select id="antradus-tone">
                            <option>Formal</option>
                            <option>Conversational</option>
                            <option>Friendly</option>
                            <option>Authoritative</option>
                            <option>Humorous</option>
                        </select>
                    </div>
                    <div class="antradus-field">
                        <label>LANGUAGE</label>
                        <select id="antradus-lang">
                            <option>English</option>
                            <option>Arabic</option>
                            <option>Spanish</option>
                            <option>French</option>
                            <option>German</option>
                            <option>Portuguese</option>
                            <option>Italian</option>
                            <option>Russian</option>
                            <option>Chinese (Simplified)</option>
                            <option>Japanese</option>
                            <option>Korean</option>
                            <option>Hindi</option>
                            <option>Turkish</option>
                            <option>Dutch</option>
                            <option>Polish</option>
                            <option>Swedish</option>
                            <option>Indonesian</option>
                        </select>
                    </div>
                </div>

                <!-- Topic / Niche pills -->
                <?php if ( $topics ) : ?>
                <div class="antradus-field">
                    <label>TOPIC / NICHE <a href="<?php echo esc_url( admin_url( 'options-general.php?page=antradus-ai#antradus-topics' ) ); ?>" target="_blank" class="antradus-settings-link">⚙ Manage topics</a></label>
                    <div class="antradus-topics">
                        <?php foreach ( $topics as $topic ) : ?>
                            <button type="button" class="antradus-topic-btn" data-topic="<?php echo esc_attr( $topic ); ?>"><?php echo esc_html( $topic ); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Checkboxes -->
                <div class="antradus-checks">
                    <label><input type="checkbox" id="antradus-faq" /> Include FAQ</label>
                    <label><input type="checkbox" id="antradus-meta" checked /> Generate Meta Title &amp; Description</label>
                </div>

                <!-- Generate button + status -->
                <div class="antradus-generate-row">
                    <button type="button" id="antradus-generate-btn">Start Writing</button>
                    <a href="<?php echo esc_url( ANTRADUS_AI_LITE_PRO_URL ); ?>" target="_blank" rel="noopener" id="antradus-generate-meta-pro-btn" class="antradus-pro-btn antradus-generate-meta-pro">
                        &#128274; Generate Meta from Existing Article &mdash; Pro
                    </a>
                </div>
                <div id="antradus-status"></div>
                <p class="antradus-prompt-note"><a href="<?php echo esc_url( admin_url( 'options-general.php?page=antradus-ai#antradus-system-prompt' ) ); ?>" target="_blank" class="antradus-settings-link">⚙ Customise AI writing style</a></p>

                <!-- Meta output (hidden until content generated) -->
                <div id="antradus-meta-output" style="display:none;">
                    <h4>SEO Output</h4>
                    <div id="antradus-seo-indicator" style="display:none;margin-bottom:10px;"></div>
                    <p id="antradus-keyword-row" style="display:none;">
                        <strong>FOCUS KEYWORD</strong>
                        <button type="button" class="antradus-copy-btn" data-target="antradus-keyword-display" data-field="keyword">Copy</button><br/>
                        <span id="antradus-keyword-display"></span>
                    </p>
                    <p>
                        <strong>META TITLE</strong>
                        <button type="button" class="antradus-copy-btn" data-target="antradus-meta-title-val" data-field="title">Copy</button><br/>
                        <span id="antradus-meta-title-val"></span>
                    </p>
                    <p>
                        <strong>META DESCRIPTION</strong>
                        <button type="button" class="antradus-copy-btn" data-target="antradus-meta-desc-val" data-field="desc">Copy</button><br/>
                        <span id="antradus-meta-desc-val"></span>
                    </p>
                </div>

            </div><!-- /.antradus-col-content -->

            <!-- ── RIGHT: Image ───────────────────────────────────────────── -->
            <div class="antradus-col-image">

                <!-- Image instructions -->
                <div class="antradus-field">
                    <label>Additional details for image generation <span class="antradus-label-hint">(optional)</span> <a href="<?php echo esc_url( admin_url( 'options-general.php?page=antradus-ai#antradus-image-settings' ) ); ?>" target="_blank" class="antradus-settings-link">⚙ Image settings</a></label>
                    <textarea id="antradus-image-instructions" rows="4" placeholder="e.g. Warm Mediterranean tones, cinematic lighting, no text overlays..."></textarea>
                </div>
                <div class="antradus-checks">
                    <label><input type="checkbox" id="antradus-instructions-only" /> Skip article — use my details only</label>
                </div>

                <!-- Image button + status -->
                <button type="button" id="antradus-image-btn" disabled>&#127912; Image Generator</button>
                <div id="antradus-image-status"></div>

                <!-- Image action bar (hidden until image generated) -->
                <div id="antradus-image-ready-bar" style="display:none;">
                    <button type="button" id="antradus-preview-btn">&#128247; Preview</button>
                    <button type="button" id="antradus-set-featured-btn" disabled>&#9733; Set as Featured</button>
                </div>

                <!-- Album Generating — Pro feature -->
                <div class="antradus-accordion antradus-album-section">
                    <div class="antradus-accordion-header antradus-album-header" data-target="antradus-album-body">
                        <span class="antradus-accordion-icon">&#128444;</span>
                        <span>Album Generating</span>
                        <span class="antradus-accordion-arrow">&#9650;</span>
                    </div>
                    <div class="antradus-accordion-body antradus-album-body" id="antradus-album-body">
                        <div class="antradus-pro-notice antradus-pro-notice--col" style="margin-bottom:12px;">
                            <span class="antradus-pro-notice-icon">&#128274;</span>
                            <div>
                                <strong>Album Generating is a Pro feature.</strong>
                                Generate multiple images at once from your article or a custom description.
                                <a href="<?php echo esc_url( ANTRADUS_AI_LITE_PRO_URL ); ?>" target="_blank" rel="noopener" class="antradus-pro-upgrade-link antradus-pro-upgrade-link--row">Upgrade Now &rarr;</a>
                            </div>
                        </div>
                        <button type="button" class="antradus-album-generate-btn" disabled>
                            &#128274; Generate Album from Body
                        </button>
                        <div class="antradus-field">
                            <label>DESCRIBE YOUR ALBUM <span class="antradus-label-hint">(optional)</span></label>
                            <textarea rows="3" disabled placeholder="e.g. Show the product from different angles with bright studio lighting..." style="opacity:.5;cursor:not-allowed;"></textarea>
                        </div>
                        <div class="antradus-field">
                            <label>NUMBER OF IMAGES</label>
                            <input type="number" min="2" max="10" value="4" disabled style="opacity:.5;cursor:not-allowed;" />
                        </div>
                    </div>
                </div>

            </div><!-- /.antradus-col-image -->

        </div><!-- /.antradus-two-col -->

    </div>

    <!-- Image preview modal -->
    <div id="antradus-modal-overlay" style="display:none;">
        <div class="antradus-modal">
            <button type="button" id="antradus-modal-close">&times;</button>
            <h3>Your Image</h3>
            <img id="antradus-modal-img" src="" alt="Generated image" />
            <button type="button" id="antradus-modal-set-featured">&#9733; Set as Featured</button>
        </div>
    </div>
    <?php
}

add_action( 'save_post', function ( $post_id ) {
    if ( ! isset( $_POST['antradus_nonce'] ) || ! wp_verify_nonce( $_POST['antradus_nonce'], 'antradus_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['antradus_keyword'] ) ) update_post_meta( $post_id, '_antradus_keyword', sanitize_text_field( wp_unslash( $_POST['antradus_keyword'] ) ) );
    if ( isset( $_POST['antradus_url'] ) )     update_post_meta( $post_id, '_antradus_url',     esc_url_raw( wp_unslash( $_POST['antradus_url'] ) ) );
} );
