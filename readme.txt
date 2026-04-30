=== Antradus AI Lite ===
Contributors: ehabahmad
Tags: ai, content generator, openai, seo, image generation
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered article and image generator. Write SEO-optimized content from a keyword or URL using OpenAI, Claude, Gemini, or OpenRouter.

== Description ==

**Antradus AI Lite** generates full SEO-optimized articles and matching images directly inside the WordPress post editor — without leaving your dashboard.

Choose your topic, pick a writing style and tone, and the plugin writes a complete article, SEO meta title, and meta description in seconds. Then generate a matching featured image with one click.

**Supported AI Providers**

* **OpenAI** — GPT-4o-mini and other GPT models for text; DALL-E 2 / DALL-E 3 for images
* **Anthropic** — Claude models (Haiku, Sonnet, Opus)
* **Google Gemini** — Gemini Flash and Pro models; Imagen for images
* **OpenRouter** — Access 200+ models including free tiers (Gemini Flash, Llama, Mistral, and more)

**Key Features**

* Generate full articles from a keyword or external URL
* SEO meta title and meta description generation
* 10 image style presets (Default, Gaming, Medical, News, Sports, Finance, Tech, Food, Travel, Entertainment)
* Customizable image presets with your own visual style prompts
* Optional color cast overlay on generated images
* 5 writing styles x 5 tones x 17 languages
* FAQ section generation
* Auto-upload generated images to the WordPress Media Library
* One-click set as Featured Image
* Works with both Classic Editor and Block Editor (Gutenberg)
* Optional disable Gutenberg for Posts
* Customizable system prompt to define your writing style
* Topic/niche buttons for quick keyword entry
* SSRF-protected external URL fetching
* AI-powered correction of public figure names in keywords

**Pro Version**

The [Pro version](https://webops.ae/antradus-ai/) adds:

* YouTube transcript to article
* Multi-source article merging (combine 2+ URLs)
* Bulk image album generation
* Generate meta from any existing post

== Installation ==

1. Upload the `antradus-ai-lite` folder to the `/wp-content/plugins/` directory, or install directly via the WordPress **Plugins > Add New** screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings > Antradus AI** and enter your API key for your chosen provider.
4. Open any post or page, find the **Antradus AI** meta box, enter a keyword, and click **Generate Article**.

== Frequently Asked Questions ==

= Which API key do I need? =

You need an API key from at least one supported provider:

* [OpenAI](https://platform.openai.com/)
* [Anthropic](https://console.anthropic.com/)
* [Google AI Studio](https://aistudio.google.com/)
* [OpenRouter](https://openrouter.ai/) — offers free models requiring only a free account

= Does this plugin store my content on external servers? =

No. Your keywords and articles are sent directly to the AI provider you have configured. This plugin does not store, log, or transmit your data to any server other than the provider you choose. Refer to each provider's privacy policy for details on how they handle request data.

= Does image generation work with all providers? =

Image generation is available via OpenAI (gpt-image/DALL-E 2 / DALL-E 3), Google Gemini (Imagen), and OpenRouter (Flux and compatible models). Anthropic does not offer an image generation API.

= Can I use a free AI model? =

Yes. OpenRouter provides access to free models such as Google Gemini Flash Experimental and others. Select OpenRouter as your provider, create a free account at openrouter.ai, and choose a free model in the settings.

= Can I customize the writing style? =

Yes. You can set a custom system prompt under **Settings > Antradus AI** to define the writing style, persona, language, or any standing instructions the AI should follow for every article.

= Does it work with the Classic Editor? =

Yes. The plugin automatically detects whether a post uses the Block Editor or the Classic Editor and loads the correct interface.

= Will it conflict with other AI plugins? =

The plugin uses a unique `antradus_` option prefix and `antradus_lite_` function prefix throughout. If you upgrade to Antradus AI Premium, the Lite version deactivates automatically to prevent conflicts.

== Screenshots ==

1. The Antradus AI meta box inside the Block Editor — article generation panel
2. The image generation panel with style presets
3. Settings page — provider and API key configuration
4. Settings page — image preset customization

== Changelog ==

= 1.0.0 =
* Initial release.
* Article generation from keyword or URL using OpenAI, Anthropic, Gemini, and OpenRouter.
* Image generation with 10 visual style presets via OpenAI, Gemini, and OpenRouter.
* Classic Editor and Block Editor support.
* SEO meta title and description generation.
* AI-powered public figure name correction.
* SSRF-protected URL fetching.

== External Services ==

This plugin sends data to third-party AI services to generate article content and images. **No data is sent unless you have configured an API key and explicitly click a generate button.**

**OpenAI** — text generation (GPT models) and image generation (DALL-E)

* [Privacy Policy](https://openai.com/policies/privacy-policy)
* [Terms of Use](https://openai.com/policies/usage-policies)

**Anthropic** — text generation (Claude models)

* [Privacy Policy](https://www.anthropic.com/legal/privacy)
* [Terms of Use](https://www.anthropic.com/legal/usage-policy)

**Google Gemini (Google AI)** — text generation (Gemini models) and image generation (Imagen)

* [Privacy Policy](https://policies.google.com/privacy)
* [Terms of Use](https://ai.google.dev/gemini-api/terms)

**OpenRouter** — gateway to 200+ AI models for text and image generation

* [Privacy Policy](https://openrouter.ai/privacy)
* [Terms of Use](https://openrouter.ai/terms)

Data sent to these services includes: the keyword or URL you provide, the article text (when generating an image), and any custom system prompt you configure. API keys are stored in your WordPress database and are never sent to any server other than the respective provider.
