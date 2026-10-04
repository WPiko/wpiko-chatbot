=== WPiko AI Chatbot – ChatGPT Assistant & Customer Support ===
Contributors: wpiko
Tags: chatbot, chatgpt, openai, customer-service, woocommerce
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 2.1.0
Requires PHP: 7.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chatbot powered by your own OpenAI account. Learns your pages in minutes and answers visitors 24/7. No monthly subscription.

== Description ==

WPiko AI Chatbot adds an AI assistant to your WordPress site that answers visitor questions using OpenAI's latest models. It runs on your own OpenAI account, so you pay OpenAI directly for what you use (usually a few dollars a month for a small site) and there is no WPiko subscription.

A 3-step setup wizard connects your OpenAI account, teaches the chatbot about your business from your own pages, and lets you test it before it goes live.

https://www.youtube.com/watch?v=yRSKh06YWRc

### 💬 What you can do

- Answer visitor questions 24/7 with on-brand replies
- Teach the chatbot about your business from your website pages and your own documents (PDF, Word, text, Markdown)
- Review conversations to see what visitors ask
- Show the chatbot on every page with a floating button, or embed it with the `[wpiko_chatbot]` shortcode

### 🆓 Free features

- Setup wizard: connect OpenAI, teach your business and test the chatbot in about 5 minutes
- Quick learn from your pages: pick up to 10 pages and the chatbot answers from their content
- Knowledge files: upload PDFs and documents to the chatbot's knowledge base
- Clear OpenAI diagnostics: the plugin tests your key with a real message and tells you exactly what to fix (for example "your OpenAI account has no credit") instead of a generic error
- Works with OpenAI's latest models through the Responses API, with streaming replies
- Floating chatbot and shortcode embed, with page, device and position rules
- Proactive greeting card that invites visitors to chat
- Appearance presets, colors, avatar and a live preview
- Pre-made questions and conversation starters
- Customize every interface text and error message in any language
- Conversation history with export and translation
- Per-IP hourly and daily limits to protect your OpenAI budget
- Loads its scripts only on pages where the chatbot appears
- Encrypted API key storage

### 🚀 WPiko Chatbot Pro adds

- Live chat: take over any conversation from the AI and reply to visitors yourself (Admin Takeover)
- Mobile app (PWA) with push notifications to reply from your phone
- Full website scan with AI-generated Q&A and no page limit
- Q&A Builder to control exact answers
- WooCommerce integration: product cards, product recommendations and order questions
- Lead capture: email capture and a contact form with file attachments and reCAPTCHA
- Analytics: conversations, devices, locations and usage trends
- AI-assisted replies for operators and advanced conversation tools

[Compare Free and Pro](https://wpiko.com/chatbot-pricing/)

### 🔑 What you need

- An OpenAI Platform account (platform.openai.com). This is separate from ChatGPT: a ChatGPT Plus plan does not include API usage.
- Prepaid credit on that account. The OpenAI API is prepaid, and $5 is enough to start. Without credit the key is accepted but the chatbot cannot answer, WPiko detects this and shows you how to fix it.
- An API key ("secret key") from your OpenAI dashboard.

### 🌍 Any Language & Localization Support

You can customize every text element your visitors see, from the chatbot name and welcome message to input placeholders and error notifications. The AI replies in the language your visitor writes in, and your knowledge files can be in any language.

### 🔐 Fast and Secure

The chatbot's scripts and styles only load on pages where it is shown. Your API key is stored encrypted, admin actions are permission-checked, and visitors never see technical error details.

### 📱 Mobile-Friendly

The chatbot adapts to phones, tablets and desktops, with touch-friendly controls, adjustable dimensions and per-device visibility.

Learn more: [WPiko Chatbot](https://wpiko.com/chatbot)

== Installation ==

1. Install the plugin from the WordPress plugins screen (or upload it to `/wp-content/plugins/wpiko-chatbot`) and activate it.
2. The setup wizard opens automatically. You can also open it any time from WPiko Chatbot → Setup Wizard.
3. Connect OpenAI: add credit to your OpenAI Platform account, create a secret key and paste it. WPiko sends a tiny test message to confirm everything works.
4. Teach it your business: describe what you do and pick the pages the chatbot should learn from.
5. Test it in the wizard, then switch on the floating chatbot. Done.

== Frequently Asked Questions ==

= Do I need an OpenAI API key? =

Yes. The chatbot runs on your own OpenAI account. Create a secret key at platform.openai.com and make sure the account has prepaid credit. The setup wizard walks you through it.

= My ChatGPT Plus subscription should cover this, right? =

No. ChatGPT subscriptions and the OpenAI API are billed separately. The API needs its own prepaid credit in your OpenAI Platform billing settings.

= The chatbot says "temporarily unavailable". What's wrong? =

Almost always your OpenAI account has no credit, or the key was deleted. When you (as a site admin) chat with the bot, you see the exact reason and a link to fix it. You can also go to WPiko Chatbot → API Key and click "Test connection".

= How much does it cost to run the chatbot? =

You pay OpenAI per use. For most small sites that is a few dollars a month. You can choose a cheaper model in AI Configuration and set hourly and daily limits per visitor under User Limits.

= How does the chatbot learn about my business? =

Pick up to 10 pages in "Quick learn from your pages" and upload documents such as price lists or FAQs in File Management. With Pro, Scan Website covers every page of your site with AI-written Q&A, and WooCommerce products stay in sync.

= Does it include live chat? =

Live chat, where you take over a conversation from the AI and reply yourself, is a Pro feature. The free version is an AI chatbot.

= Can I use this for customer support? =

Yes. It answers common questions around the clock and tells visitors to contact your team when it doesn't know something, instead of guessing.

= Does it work with WooCommerce? =

The free chatbot works on WooCommerce stores and can learn from your store pages. Pro adds product cards, product recommendations and order questions.

= Can I customize the appearance? =

Yes. Choose an appearance preset or set your own colors, avatar, size and position, with a live preview.

= How do I embed the chatbot on a specific page? =

Use the `[wpiko_chatbot]` shortcode on any page or post.

= Does it slow down my site? =

The chatbot's scripts and styles only load on pages where it is shown, and notification sounds only load after a visitor starts chatting.

= Does the chatbot support languages other than English? =

Yes. It replies in the visitor's language, and you can translate every interface text and error message in the settings.

= What happens to my data if I delete the plugin? =

Your settings and conversations are kept so nothing is lost if you reinstall. To remove everything on deletion, turn on "Delete all WPiko Chatbot data" under Manage & Tools → Debug Log → Plugin data.

= What is the Mobile App (PWA)? =

A Pro feature that lets admins and agents monitor conversations, reply and receive push notifications from a phone or tablet. It runs from your website, so no app store download is needed.

= How does Admin Takeover work? =

A Pro feature: pause the AI and chat with a visitor live from the mobile app or WordPress admin. When you release the conversation, the AI continues where you left off.

== Screenshots ==

1. The chatbot on your site, answering visitors from your own pages.
2. The setup wizard connects your OpenAI account and checks the key and credit for you.
3. Quick learn from your pages: tick the pages your chatbot should know about.
4. Test the chatbot in the wizard before it goes live on your site.
5. Style presets and full color, avatar and position settings with a live preview.
6. Conversations: every chat in one inbox, with search, download and translation.
7. Clear connection diagnostics: the exact OpenAI problem and a button to fix it.
8. (Pro) Live chat takeover from the mobile app, with push notifications.

== Changelog ==

= 2.1.0 =
* Security: Fixed stored cross-site scripting in conversation messages and HTML transcripts, and secured completed and cached chatbot replies. Thanks to Ali Hidayat for responsibly reporting the assistant-message vulnerability.
* Update: Removed deprecated GPT-5, GPT-5 Mini, GPT-5 Nano, GPT-5.1, and GPT-5.4 Nano from AI Configuration and their model metadata. Saved selections of unsupported models use the existing GPT-6 Luna fallback.
* Feature: Added GPT-6.1 Sol to AI Configuration with supported reasoning efforts, file search, and model details.
* Added controlled WooCommerce order lookup: signed-in owners receive enabled details, visitors matching an order number and checkout email receive basic status only.
* Retired order file syncing and added background removal of old order exports, with knowledge-file filtering and browser/account-bound AI history.
* Update: Fresh installations now default to GPT-6 Luna. Existing saved model selections are preserved.
* Security: Admin-only AJAX actions now check user permissions.
* Feature: Setup wizard that opens after activation: connect OpenAI, teach the chatbot about your business, test it and go live. It leaves the menu once setup is done and can be run again from the Dashboard.
* Feature: "Quick learn from your pages": pick up to 10 pages and the chatbot answers from their content. Pages that change are flagged for a refresh. Untick every page to remove them from the chatbot again.
* Improve: Pages already added with WPiko Chatbot Pro's Scan Website are marked "Covered by Scan Website" in Quick learn and are never stored twice. The list updates by itself when pages are scanned or files are deleted, and a short side-by-side comparison explains Quick learn and Scan Website.
* Feature: The API key is now tested with a real (tiny) request, so a key on an OpenAI account without credit is detected immediately instead of failing silently later.
* Feature: "Test connection" button and connection status on the API Key screen.
* Feature: Optional feedback form when deactivating the plugin.
* Feature: Optional "Delete all data when the plugin is deleted" setting and a proper uninstall routine.
* Improve: New chatbots get site-aware default instructions (site name, tagline, reply in the visitor's language, never invent business details) instead of empty instructions.
* Improve: Knowledge, WooCommerce product and order instructions are now managed by the plugin for all installations. Previous custom text in those fields is no longer used, Specific System Instructions remain editable for additional preferences.
* Improve: Revised knowledge instructions search when business facts are needed, explain and combine retrieved information, and handle missing answers naturally.
* Improve: WooCommerce product guidance now handles missing fields, product variations, recorded prices and stock, and recommendations based on synced catalog data more carefully.
* Improve: "Answer only from the uploaded files" knowledge rules now apply only when knowledge files exist.
* Improve: The floating chatbot switches on automatically after the first successful connection, and the dashboard warns when the chatbot is not visible anywhere on the site.
* Improve: Until an API key is added, the chatbot is only shown to site admins, never to visitors.
* Performance: Scripts, styles and sounds (including Pro's) now load only on pages where the chatbot is displayed.
* Performance: Notification sounds are no longer downloaded on page load, they load after the visitor starts chatting.
* Improve: New notification sounds: a softer message chime, a gentler error tone and a soft swoosh when the chat is cleared. The sound files are about 80% smaller (20 KB in total instead of 110 KB).
* Performance: Each chat message no longer makes an extra OpenAI request to list knowledge files (the result is cached).
* Improve: Requests that fail because the OpenAI account has no credit are no longer retried, so visitors get an answer faster.
* Improve: AI Configuration shows Pro training tools (Scan Website, Q&A Builder, WooCommerce) with a short explanation when Pro is not installed.
* Developer: New `wpiko_chatbot_enqueue_frontend_assets` and `wpiko_chatbot_responses_file_deleted` actions, plus `wpiko_chatbot_site_knowledge_covered_pages`, `wpiko_chatbot_load_frontend_assets`, `wpiko_chatbot_show_floating`, `wpiko_chatbot_site_knowledge_page_limit` and `wpiko_chatbot_default_main_instructions` filters.

Older releases: `changelog.txt`.

== Upgrade Notice ==

= 2.1.0 =
Added: Adds a setup wizard, "Quick learn from your pages" and clear OpenAI error messages. WPiko Chatbot Pro users: update Pro to 2.1.0 as well.

== External Services ==

This plugin relies on third-party external services to provide its functionality. Below is detailed information about each service used:

**OpenAI API**
* **What it is**: OpenAI's API service that provides access to advanced AI language models and assistants.
* **What it's used for**: This service powers the core chatbot functionality, including generating responses, managing conversations, creating and updating AI assistants, handling file uploads for knowledge base, and managing vector stores for file search capabilities.
* **What data is sent**: User messages/questions, chatbot configuration settings (name, instructions, model selection), uploaded files for the knowledge base, conversation context, and API authentication tokens.
* **When data is sent**: Every time a user interacts with the chatbot, when configuring the AI assistant, when uploading files to the knowledge base, and when administrators test the API connection.
* **Service provider**: OpenAI
* **Terms of Service**: https://openai.com/terms/
* **Privacy Policy**: https://openai.com/privacy/

**WPiko feedback service (wpiko.com)**
* **What it is**: WPiko's own server, which receives optional feedback about the plugin.
* **What it's used for**: Understanding why people stop using the plugin, so it can be improved.
* **What data is sent**: Only when you deactivate the plugin, choose a reason in the feedback form and click "Submit & deactivate": the reason, your optional comment, the plugin, WordPress and PHP versions, site language, how far setup got (API key present, connection status, wizard finished, number of learned pages, number of conversations, Pro/WooCommerce active) and days since installation. Your email address (which you can edit in the form) and your site address are sent only if you tick "You can email me about this". Nothing is sent if you click "Skip & deactivate".
* **When data is sent**: Only when you submit the deactivation feedback form.
* **Service provider**: WPiko
* **Terms of Service**: https://wpiko.com/terms-and-conditions/
* **Privacy Policy**: https://wpiko.com/privacy-policy/

**IP-API.com Location Service**
* **What it is**: A geolocation service that provides location information based on IP addresses.
* **What it's used for**: To determine the approximate geographical location of website visitors for analytics and conversation tracking purposes (available in Pro version).
* **What data is sent**: The visitor's IP address only.
* **When data is sent**: When a user starts a conversation with the chatbot (only if analytics features are enabled).
* **Service provider**: IP-API.com
* **Terms of Service**: https://ip-api.com/docs/legal
* **Privacy Policy**: https://ip-api.com/docs/legal
