# Message security regression checks

Run from the base plugin directory, with both plugins installed beside each
other in a WordPress checkout. These tests use real WordPress formatting/KSES
and plugin functions; database rows, authorization and network responses are
simulated. They do not bootstrap the site, contact OpenAI or modify the database.

```sh
php tests/message-safety.php
python3 tests/serve-message-safety.py
```

For the second command, open the printed loopback URL in a browser. The browser
checks use the actual frontend scripts, DOMPurify, Pro contact form script and
WordPress jQuery. They exercise restored history, streamed and non-streamed
completion, PHP-generated HTML inserted through jQuery, malicious URLs/markup,
contact prefill, old contact buttons and a missing sanitizer dependency.

Use the separate loopback test origin. Do not run the HTML fixture inside a real
site session: the fixture clears its own sessionStorage and mocks chat requests.
Stop the test server with Ctrl+C; its temporary fixtures are then removed.

The CLI checks include old raw database rows for all message roles, both HTML
exporters, Pro REST output, plain-text escaping, emoji, links, code blocks,
contact markers and product-card markup. `WPIKO_TEST_WP_ROOT` may point to a
separate WordPress root for the PHP checks. PHP DOM support is required.

The existing Pro order-lookup suite can be run separately from the plugins directory:

```sh
php -d disable_functions=curl_init,curl_setopt,curl_exec,curl_error,curl_getinfo,curl_close wpiko-chatbot-pro/tests/controlled-order-lookup.php
```

The Pro takeover auto-release check runs from the Pro plugin directory with
`php tests/takeover-auto-release.php` (no WordPress needed). On the local site,
`/wp-content/plugins/wpiko-chatbot-pro/tests/takeover-auto-release-live.php`
(as an admin) runs it against the real tables with two test conversations and
removes them afterwards.

Exclude development tests from public release ZIPs.


## Chat cost-abuse regression tests

Run `php tests/rate-limits.php` from the plugin directory. This checks the real
AJAX handler, request builder and limit policy with fake credentials and storage:
default and opt-out/zero settings, hourly/daily/burst caps, the site-wide ceiling,
normal and streaming rejections, signed-in requests, custom-limit bypass,
canonical IPs, untrusted proxy headers, input/output bounds, and failed storage.
It makes no network calls.

On a local disposable WordPress database, also run:

```sh
wp --skip-plugins --skip-themes eval-file tests/rate-limits-db.php
```

This requires PHP `pcntl`. It creates and removes a uniquely named test table,
checks 240 reservations through 24 independent MySQL connections against a cap
of 25, verifies expiry, and checks that missing storage blocks requests. It does
not change site settings or conversations and does not call OpenAI.


## Admin screen fallback test

Run `php tests/admin-tab-content.php` from the plugin directory. It renders the
real `admin/templates/admin-wrapper.php` with stubbed screens and checks that an
unknown or unavailable tab opens the Dashboard, and that add-on screens printed
through the `wpiko_chatbot_admin_tab_content` filter appear once, without the
Dashboard. It needs no WordPress install and makes no network calls.


## Conversation memory

Run `php tests/conversation-memory.php` from the plugin directory. It checks the
Conversation Memory choices and defaults (tab only for existing sites, 7 days
for new ones), the 30-day cap on the filter, the data passed to the chat script
(including the account marker that clears remembered chats on log in/out), how
an expired `previous_response_id` error from OpenAI is recognised, and the
recovery request built from the recent messages. It needs no WordPress install
and makes no network calls.

`tests/history-recovery-live.php` checks the recovery against the real OpenAI
API on the local site. Open it as an admin at
`/wp-content/plugins/wpiko-chatbot/tests/history-recovery-live.php`. It first
checks, for a guest and for the logged-in admin, that the server keeps the link
between the browser and its conversation for 7 days (across visits) or 1 day
(tab only) and that another account cannot continue it (no OpenAI requests;
needs Pro's controlled order lookup). Then it sends one
normal and one streaming request that continue from a response ID OpenAI does
not have, expects both answers to use the earlier messages, and removes its test
conversations. It refuses to run for non-admins and on hosts other than
`.local`, `.test` or `.localhost`.


## Topic focus

Run `php tests/topic-focus.php` from the plugin directory. It checks that the
"Only questions about this website" rule is sent by default (also without
knowledge files), that "Any question" sends no rule, that the owner's specific
instructions are still sent, the main template wording and the
`wpiko_chatbot_topic_focus_instructions` filter. No WordPress or network needed.

`tests/topic-focus-live.php` sends real questions through the local site, with
`?mode=website` or `?mode=any` and `&part=1` or `&part=2` (five questions each),
and prints the answers for review. Open the links one at a time, as an admin on
a `.local`, `.test` or `.localhost` site. It restores the setting and removes
its test conversations.
