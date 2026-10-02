# WPiko Chatbot

Development repository for the free [WPiko Chatbot WordPress plugin](https://wordpress.org/plugins/wpiko-chatbot/).

The `wpiko-chatbot-pro` add-on is maintained separately. Plugin installation and user documentation are in `readme.txt`.

## Working from VS Code

Open this `wpiko-chatbot` folder in VS Code. Use Source Control to review changes, commit them, and push to `origin` (`https://github.com/WPiko/wpiko-chatbot.git`). Git exists inside this plugin folder, so sibling plugins are outside this repository.

Committing saves a local Git revision. Pushing uploads those commits to GitHub. Neither operation publishes a WordPress.org release with the current setup.

## Verification workflow

Every push to `main` and pull request targeting `main` runs **Verify WordPress.org deployment**. You can also run it manually from the GitHub Actions tab.

The workflow:

1. Checks that the main plugin header, `WPIKO_CHATBOT_VERSION`, and `readme.txt` stable tag match.
2. Checks PHP syntax in the committed plugin package using the runner's PHP CLI. This is a syntax check, not a full WordPress integration or minimum-PHP compatibility test.
3. Checks that required runtime assets are included and development files are excluded.
4. Uses 10up's deployment action to stage SVN trunk and a temporary verification tag with `dry-run: true`.
5. Saves a plugin ZIP as a workflow artifact for seven days.

**This workflow cannot publish a release.** It does not receive SVN credentials and does not commit to SVN. The temporary `verify-...` tag is only created in the runner's SVN working copy. It deliberately avoids existing release tags, which would cause the deployment action to skip staging. The plugin version and stable tag remain unchanged.

The `.gitattributes` export exclusions apply to both `git archive` and the deployment action. Existing WordPress.org banners, icons, screenshots, and release tags are preserved; this repository does not currently contain a `.wordpress-org` directory.

Check a committed revision locally:

```sh
python3 .github/scripts/verify-plugin.py
```

This checks `HEAD`, so commit intended changes before running it. PHP CLI and Python 3 are required.

## Enabling releases later

Live publishing will be configured separately after verification. Do not push a release tag expecting deployment yet: this repository currently has no tag-triggered publishing workflow.

Before enabling releases, add `SVN_USERNAME` and the dedicated WordPress.org SVN password as `SVN_PASSWORD` in GitHub Actions secrets. Keep credentials out of source files and commits.

The eventual release workflow should validate a numeric Git tag against all three version fields before updating SVN trunk and creating the matching release tag. Update the changelog for each release. Never replace an already published version; release changes under a new version instead.

If WordPress.org Release Confirmation is enabled, a committer must also confirm the staged release on WordPress.org before it becomes available to users.
