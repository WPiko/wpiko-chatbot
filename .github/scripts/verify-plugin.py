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

    forbidden = {".git", ".github", ".svn", ".wordpress-org", ".vscode",
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
    args = parser.parse_args()
    try:
        verify(args.ref, args.expect_version)
    except (ValueError, subprocess.CalledProcessError) as error:
        parser.exit(1, "Verification failed: " + str(error) + "\n")
