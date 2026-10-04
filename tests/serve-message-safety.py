"""Run: python3 tests/serve-message-safety.py [--port 8768]. Requires PHP + WordPress.

Serves only test fixtures and JavaScript on a separate loopback origin. No site
bootstrap, database writes, real AJAX requests or paid AI calls are performed.
"""
import argparse
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import shutil
import subprocess
import tempfile


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--port', type=int, default=8768)
    args = parser.parse_args()
    plugin = Path(__file__).resolve().parents[1]
    wordpress = plugin.parents[2]
    with tempfile.TemporaryDirectory(prefix='wpiko-message-safety-') as directory:
        root = Path(directory)
        subprocess.run(['php', str(plugin / 'tests/message-safety.php'), str(root / 'fixtures.json')], check=True)
        shutil.copyfile(plugin / 'tests/browser-message-safety.html', root / 'index.html')
        (root / 'js').symlink_to(plugin / 'js', target_is_directory=True)
        (root / 'contact-form.js').symlink_to(plugin.parent / 'wpiko-chatbot-pro/js/contact-form.js')
        (root / 'jquery.js').symlink_to(wordpress / 'wp-includes/js/jquery/jquery.js')
        handler = partial(SimpleHTTPRequestHandler, directory=str(root))
        with ThreadingHTTPServer(('127.0.0.1', args.port), handler) as server:
            print(f'Open http://127.0.0.1:{args.port}/ for browser checks. Ctrl+C to stop.', flush=True)
            try:
                server.serve_forever()
            except KeyboardInterrupt:
                pass


if __name__ == '__main__':
    main()
