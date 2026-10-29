DOMPurify 3.4.16, vendored from https://github.com/cure53/DOMPurify/tree/3.4.16.

`purify.min.js` is the upstream distribution with one change: the last line,
`//# sourceMappingURL=purify.min.js.map`, is removed because the source map is
not shipped (browsers would request it and log a 404 in developer tools). See `LICENSE` for the
upstream Apache-2.0 / MPL-2.0 dual license. This dependency is served locally;
visitors do not load it from a third-party CDN.

When upgrading, replace the distribution and license from the same upstream tag,
remove the `sourceMappingURL` line again, update the script version in wpiko-chatbot.php, and run the message-safety tests.
