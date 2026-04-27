<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_options_page( 'Antradus AI', 'Antradus AI', 'manage_options', 'antradus-ai', 'antradus_lite_settings_page' );
} );

add_action( 'admin_init', function () {
    register_setting( 'antradus_settings_group', 'antradus_openai_api_key', [ 'sanitize_callback' => 'antradus_lite_sanitize_api_key' ] );
    register_setting( 'antradus_settings_group', 'antradus_openai_model',   [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'gpt-4o' ] );
    register_setting( 'antradus_settings_group', 'antradus_topics',         [ 'sanitize_callback' => 'sanitize_textarea_field', 'default' => "Technology\nGaming\nMedical" ] );
    register_setting( 'antradus_settings_group', 'antradus_system_prompt',  [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_model',    [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'gpt-image-1' ] );
    register_setting( 'antradus_settings_group', 'antradus_image_prompt',   [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
} );

function antradus_lite_sanitize_api_key( $new ) {
    $new = sanitize_text_field( $new );
    return empty( $new ) ? get_option( 'antradus_openai_api_key', '' ) : $new;
}

function antradus_lite_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $saved_key   = get_option( 'antradus_openai_api_key', '' );
    $model       = esc_attr( get_option( 'antradus_openai_model', 'gpt-4o' ) );
    $topics      = esc_textarea( get_option( 'antradus_topics', "Technology\nGaming\nMedical" ) );
    $image_model = esc_attr( get_option( 'antradus_image_model', 'gpt-image-1' ) );

    $default_system  = "You are a professional content writer. Write engaging, SEO-friendly articles for a general audience.\n\n";
    $default_system .= "If one source article is provided, use it only as a fact source — do not follow its structure. ";
    $default_system .= "Write a completely original article from scratch with a different angle, opening, and narrative flow.\n\n";
    $default_system .= "Rules:\n- Open with a compelling hook: a scene, question, or bold statement. Never a definition.\n";
    $default_system .= "- Use H1, H2, H3 in clean HTML only — no other tags\n- Short paragraphs, 2-3 lines max\n";
    $default_system .= "- Conversational and confident tone\n- Light authority cues where relevant\n- Target length: 1000-1400 words";
    $system_prompt = esc_textarea( get_option( 'antradus_system_prompt', $default_system ) );

    $default_image  = "A high-end lifestyle marketing banner in a clean editorial thumbnail style, professional commercial photography, ";
    $default_image .= "soft cinematic lighting, shallow depth of field, warm color grading, visually pleasing composition with subject on one side ";
    $default_image .= "and negative space on the other for text, modern bold sans-serif headline typography overlay, mixed font weights (white + accent color like yellow), ";
    $default_image .= "minimal UI-style icons or graphic elements, subtle decorative shapes, premium brand feel, social media thumbnail layout, ";
    $default_image .= "ultra realistic, sharp focus, high detail, 4K, visually engaging, emotionally appealing, advertising style";
    $image_prompt = esc_textarea( get_option( 'antradus_image_prompt', $default_image ) );
    ?>
    <div class="wrap antradus-settings-wrap">

        <div class="antradus-settings-layout">

            <nav class="antradus-settings-nav">
                <p class="antradus-nav-heading">QUICK NAV</p>
                <a href="#antradus-api-model">API &amp; Model</a>
                <a href="#antradus-topics">Topics</a>
                <a href="#antradus-system-prompt">System Prompt</a>
                <a href="#antradus-image-settings">Image Settings</a>
            </nav>

            <div class="antradus-settings-main">
                <form method="post" action="options.php">
                    <?php settings_fields( 'antradus_settings_group' ); ?>

                    <div class="antradus-settings-section" id="antradus-api-model">
                        <h2><span class="antradus-section-icon">🔑</span> API &amp; Model</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">OpenAI API Key</th>
                                <td>
                                    <?php if ( $saved_key ) : ?>
                                        <input type="password" name="antradus_openai_api_key" value="" placeholder="Key saved — enter a new one to replace it" class="regular-text" autocomplete="new-password" />
                                        <p class="description" style="color:#00a32a;">&#10003; Key is saved. Leave blank to keep existing.</p>
                                    <?php else : ?>
                                        <input type="password" name="antradus_openai_api_key" value="" placeholder="sk-..." class="regular-text" autocomplete="new-password" />
                                        <p class="description">Paste your OpenAI API key. It will never be shown after saving.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Text Model</th>
                                <td>
                                    <input type="text" name="antradus_openai_model" value="<?php echo $model; ?>" class="regular-text" />
                                    <p class="description">e.g. gpt-4o, gpt-4.1, gpt-4-turbo</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="antradus-settings-section" id="antradus-topics">
                        <h2><span class="antradus-section-icon">📌</span> Topics / Niches</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Topics</th>
                                <td>
                                    <textarea name="antradus_topics" rows="6" class="large-text" style="font-size:13px;"><?php echo $topics; ?></textarea>
                                    <p class="description">One topic per line. These appear as clickable buttons in the content generator.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="antradus-settings-section" id="antradus-system-prompt">
                        <h2><span class="antradus-section-icon">🔥</span> System Prompt</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">System Prompt</th>
                                <td>
                                    <textarea name="antradus_system_prompt" rows="14" class="large-text" style="font-family:monospace;font-size:12px;"><?php echo $system_prompt; ?></textarea>
                                    <p class="description">Base AI instructions. Keyword, language, style, tone, niche, and output format are appended automatically.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="antradus-settings-section" id="antradus-image-settings">
                        <h2><span class="antradus-section-icon">🖼️</span> Image Generation</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Image Model</th>
                                <td>
                                    <input type="text" name="antradus_image_model" value="<?php echo $image_model; ?>" class="regular-text" />
                                    <p class="description">e.g. gpt-image-1, dall-e-3</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Image Style Prompt</th>
                                <td>
                                    <textarea name="antradus_image_prompt" rows="6" class="large-text" style="font-family:monospace;font-size:12px;"><?php echo $image_prompt; ?></textarea>
                                    <p class="description">Visual style description combined with the article summary to generate the featured image.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( 'Save Changes' ); ?>
                </form>
            </div>

        </div>
    </div>
    <?php
}
