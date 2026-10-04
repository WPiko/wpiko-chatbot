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

Exclude development tests from public release ZIPs.
