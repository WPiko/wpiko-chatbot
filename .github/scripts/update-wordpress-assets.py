#!/usr/bin/env python3
"""Stage directory artwork in SVN; publish only with an explicit --publish flag."""

import argparse
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import xml.etree.ElementTree as ET


def artwork_files():
    folder = Path(__file__).resolve().parents[2] / ".wordpress-org"
    allowed = re.compile(
        r"(?:banner-(?:772x250|1544x500)(?:-rtl)?\.(?:png|jpg)|"
        r"icon-(?:128x128|256x256)\.(?:png|jpg)|icon\.svg|"
        r"screenshot-[1-9]\d*\.(?:png|jpg))"
    )
    if not folder.is_dir():
        raise ValueError("Missing .wordpress-org artwork folder")
    files = sorted(folder.iterdir())
    if not files:
        raise ValueError("Refusing to synchronize an empty artwork folder")
    for path in files:
        if path.is_symlink() or not path.is_file() or not allowed.fullmatch(path.name):
            raise ValueError("Unexpected artwork entry: " + path.name)
        content = path.read_bytes()
        if path.suffix == ".png" and not content.startswith(b"\x89PNG\r\n\x1a\n"):
            raise ValueError("Invalid PNG: " + path.name)
        if path.suffix == ".jpg" and not content.startswith(b"\xff\xd8\xff"):
            raise ValueError("Invalid JPEG: " + path.name)
        if path.suffix == ".svg" and ET.fromstring(content).tag.split("}")[-1] != "svg":
            raise ValueError("Invalid SVG: " + path.name)
    print("Artwork validated: " + str(len(files)) + " files")
    return files


def svn(*args, capture=False):
    # Never print command arguments: a publishing command contains credentials.
    result = subprocess.run(
        ["svn", *args], stdout=subprocess.PIPE if capture else None,
        text=True,
    )
    if result.returncode:
        raise ValueError("SVN " + args[0] + " failed; check the workflow log")
    return result.stdout


def synchronize(files, publish):
    if not shutil.which("svn"):
        raise ValueError("Subversion CLI is required to stage artwork")
    if publish and not all(os.environ.get(key) for key in ("SVN_USERNAME", "SVN_PASSWORD")):
        raise ValueError("Publishing requires SVN_USERNAME and SVN_PASSWORD secrets")
    mime_types = {".png": "image/png", ".jpg": "image/jpeg", ".svg": "image/svg+xml"}
    with tempfile.TemporaryDirectory(prefix="wpiko-svn-assets-") as temp:
        working = Path(temp) / "assets"
        svn("checkout", "--non-interactive", "https://plugins.svn.wordpress.org/wpiko-chatbot/assets/", str(working))
        names = {path.name for path in files}
        for existing in working.iterdir():
            if existing.name == ".svn":
                continue
            if existing.is_symlink() or not existing.is_file():
                raise ValueError("Unexpected entry in SVN artwork: " + existing.name)
            if existing.name not in names:
                svn("delete", str(existing))
        for source in files:
            shutil.copyfile(source, working / source.name)
        svn("add", "--force", str(working))
        for source in files:
            svn("propset", "svn:mime-type", mime_types[source.suffix], str(working / source.name))
        status = ET.fromstring(svn("status", "--xml", str(working), capture=True))
        changes = [entry for entry in status.findall(".//entry") if
                   entry.find("wc-status").get("item") != "normal" or
                   entry.find("wc-status").get("props") == "modified"]
        svn("status", str(working))
        if not changes:
            print("No artwork changes to publish.")
            return
        if not publish:
            print("Dry run: artwork staged; no SVN commit executed.")
            return
        # This working copy contains ONLY the directory's assets, never plugin code or tags.
        svn("commit", str(working), "--non-interactive", "--no-auth-cache",
            "--username", os.environ["SVN_USERNAME"], "--password", os.environ["SVN_PASSWORD"],
            "-m", "Update WPiko Chatbot directory artwork from GitHub")
        print("WordPress.org artwork updated.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--publish", action="store_true", help="Commit artwork changes to WordPress.org")
    mode.add_argument("--check-only", action="store_true", help="Validate local artwork without contacting SVN")
    args = parser.parse_args()
    try:
        files = artwork_files()
        if not args.check_only:
            synchronize(files, args.publish)
    except (ValueError, OSError, ET.ParseError) as error:
        parser.exit(1, "Artwork update failed: " + str(error) + "\n")
