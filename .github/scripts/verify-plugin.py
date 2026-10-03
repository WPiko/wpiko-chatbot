#!/usr/bin/env python3
"""Check the committed WordPress plugin package without changing source files."""

import argparse
import io
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import tarfile
import tempfile
from urllib.error import HTTPError
from urllib.request import Request, urlopen


def require_new_release(version):
    """Reject an existing release or a version older than SVN's stable tag."""
    base = "https://plugins.svn.wordpress.org/wpiko-chatbot/"
    headers = {"User-Agent": "WPiko-release-verification"}
    try:
        with urlopen(Request(base + "tags/" + version + "/", headers=headers), timeout=30):
            pass
    except HTTPError as error:
        if error.code != 404:
            raise ValueError("Could not check the existing SVN tags") from error
    else:
        raise ValueError("Version " + version + " already exists on WordPress.org")

    with urlopen(Request(base + "trunk/readme.txt", headers=headers), timeout=30) as response:
        readme = response.read().decode("utf-8")
    match = re.search(r"^Stable tag:\s*(\S+)\s*$", readme, re.MULTILINE | re.IGNORECASE)
    if not match or not re.fullmatch(r"\d+(?:\.\d+){2,3}", match.group(1)):
        raise ValueError("Could not determine WordPress.org's current stable version")
    numbers = lambda value: tuple(int(part) for part in value.split(".")) + (0,) * (4 - len(value.split(".")))
    if numbers(version) <= numbers(match.group(1)):
        raise ValueError("The release must be newer than WordPress.org version " + match.group(1))
    print("New release verified against WordPress.org: " + version)


def verify(ref, expected_version=None):
    root = Path(__file__).resolve().parents[2]
    archive = subprocess.run(
        ["git", "archive", "--format=tar", ref],
        cwd=root,
        check=True,
        stdout=subprocess.PIPE,
    ).stdout

    with tarfile.open(fileobj=io.BytesIO(archive)) as package:
        files = {}
        for member in package.getmembers():
            path = PurePosixPath(member.name)
            if path.is_absolute() or ".." in path.parts:
                raise ValueError("Unsafe archive path: " + member.name)
            if member.isdir():
                continue
            if not member.isfile():
                raise ValueError("Unexpected archive entry: " + member.name)
            files[member.name] = package.extractfile(member).read()

    required = {
        "wpiko-chatbot.php", "readme.txt", "index.php",
        "assets/images/chatbot-icon.png", "js/wpiko-chatbot.js",
        "css/wpiko-chatbot.css", "sounds/message-notification.mp3",
    }
    missing = required - files.keys()
    if missing:
        raise ValueError("Missing runtime files: " + ", ".join(sorted(missing)))

    forbidden = {".git", ".github", ".svn", "wordpress-org-assets", ".vscode",
                 ".idea", "node_modules", "__pycache__", "docs", "tests"}
    for name in files:
        path = PurePosixPath(name)
        if forbidden.intersection(path.parts) or path.name in {
            ".DS_Store", ".gitignore", ".gitattributes", "README.md",
            "CONTRIBUTING.md", ".env",
        } or path.name.startswith(".env.") or path.suffix in {
            ".log", ".sql", ".pem", ".key", ".zip", ".bak",
        }:
            raise ValueError("Non-distributable file in package: " + name)

    plugin = files["wpiko-chatbot.php"].decode("utf-8")
    readme = files["readme.txt"].decode("utf-8")
    patterns = {
        "plugin header": (r"^\s*\*\s*Version:\s*(\S+)\s*$", plugin),
        "version constant": (
            r"define\(\s*['\"]WPIKO_CHATBOT_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)",
            plugin,
        ),
        "readme stable tag": (r"^Stable tag:\s*(\S+)\s*$", readme),
    }
    versions = {}
    for label, (pattern, source) in patterns.items():
        match = re.search(pattern, source, re.MULTILINE | re.IGNORECASE)
        if not match:
            raise ValueError("Missing " + label)
        versions[label] = match.group(1)
    if len(set(versions.values())) != 1:
        raise ValueError("Versions do not match: " + str(versions))
    version = versions["plugin header"]
    if not re.fullmatch(r"\d+\.\d+\.\d+(?:\.\d+)?", version):
        raise ValueError("Expected a numeric release version, got " + version)
    if expected_version is not None and version != expected_version:
        raise ValueError("Release tag does not match plugin version: " + expected_version)

    if not shutil.which("php"):
        raise ValueError("PHP CLI is required to check plugin syntax")
    php_files = sorted(name for name in files if name.endswith(".php"))
    with tempfile.TemporaryDirectory(prefix="wpiko-plugin-check-") as temp:
        for name in php_files:
            target = Path(temp) / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(files[name])
            result = subprocess.run(
                ["php", "-l", str(target)],
                stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True,
            )
            if result.returncode:
                raise ValueError("PHP syntax failed for " + name + "\n" + result.stdout)
    print("Version fields match: " + version)
    print("PHP syntax passed: " + str(len(php_files)) + " files")
    print("Plugin archive checked: " + str(len(files)) + " runtime files; development files excluded")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ref", default="HEAD", help="Git revision to verify")
    parser.add_argument("--expect-version", help="Require a matching numeric release tag")
    parser.add_argument("--require-new-release", action="store_true", help="Reject existing versions and downgrades on WordPress.org")
    args = parser.parse_args()
    if args.require_new_release and args.expect_version is None:
        parser.error("--require-new-release needs --expect-version")
    try:
        verify(args.ref, args.expect_version)
        if args.require_new_release:
            require_new_release(args.expect_version)
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        parser.exit(1, "Verification failed: " + str(error) + "\n")
