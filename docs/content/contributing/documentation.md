---
title: Documentation
sidebar_position: 1
description: Work on these docs locally, regenerate the reference pages, follow the writing conventions, and see how the site is checked, previewed and published.
---

<!-- cspell:ignore docblocks WCAG -->

# Documentation

These docs live in `docs/`, a [Docusaurus](https://docusaurus.io/) site with its pages in `docs/content/`. Most pages are hand-written. `docs.php` generates Steps and Helpers from the source code and fills 2 tables in Configuration, and Steps and Helpers also ship with the Composer package.

## Generated pages

[`docs.php`](../../../docs.php) reads the traits' step attributes and docblocks, the extension's configuration tree and the tag registry in its own `tag_registry()`, and writes:

| Page | Holds | Ships with the package |
| --- | --- | --- |
| [Steps](../steps.md) | Every step, with its options and an example | Yes |
| [Helpers](../helpers.md) | Every public helper, with its signature | Yes |
| [Configuration](../guides/configuration.md) | The extension options and the tag reference, in 2 marked regions | No |

Run it from the repository root after changing a step, a helper, an option or a tag:

```bash
ahoy update-docs  # Regenerate the pages
ahoy lint-docs    # Fail when a page is out of date, without writing
```

Never edit inside a generated region: the next run overwrites it, and `ahoy lint-docs` fails CI until the page matches. [Validating and updating documentation](../../../CONTRIBUTING.md#validating-and-updating-documentation) lists everything else the generator checks.

A tag reaches the reference only once it's registered in `tag_registry()`, so a new tag goes there before you regenerate.

### The references in the package

A coding agent in a consuming project reads `docs/content/steps.md` and `docs/content/helpers.md` from `vendor/drevops/behat-steps/` to learn the public surface, as [Writing tests with AI assistants](../guides/ai-assistants.md) describes. `.gitattributes` keeps every other file in `docs/` out of the distribution archive, and the docs unit tests fail when either reference stops shipping or another file in `docs/` starts.

Both references link each trait's source by relative path, so those links resolve in `vendor/` as well as on GitHub. The Example links in Steps point into `tests/`, which the archive leaves out, so they resolve only on GitHub and on the site.

## Working locally

The site is an npm project of its own, separate from the Docker stack, and it needs Node.js 20 or newer. Run these from `docs/`:

```bash
npm install                  # Install the dependencies
npm start                    # Serve the site locally, reloading on every change
npm run build                # Build the static site into build/
npm run serve                # Serve the built site
npm test                     # Run the unit tests and the spell check
npm run spellcheck           # Run only the spell check
npm run test:accessibility   # Check the built site for accessibility issues
```

`npm run test:accessibility` checks the site in `build/`, so run `npm run build` first. It drives a browser through [Playwright](https://playwright.dev/), which needs its own copy of Chromium: `npx playwright install chromium` downloads it once.

## Writing pages

- **1 topic per page.** The sidebar follows the folder tree of `content/`, ordered by the `sidebar_position` in each page's front matter, and a folder's `_category_.json` names its category. A folder's `README.md` is its index page.
- **Plain Markdown.** Pages are `.md` files, which Docusaurus reads as CommonMark, so they render the same on GitHub. Docusaurus-only syntax, such as a `:::note` admonition, shows as plain text there, so keep it rare. Name a page `.mdx` only when it needs a React component.
- **Link by file.** Link to another page by its path relative to the page holding the link, and to any other file in the repository the same way. From a page at the top of `content/`, that's `[Backend resolution](guides/configuration.md#backend-resolution)` for a page and `[CONTRIBUTING.md](../../CONTRIBUTING.md#steps-format)` for a repository file. The build rewrites a link that leaves `content/` into a GitHub link, at the tag or branch a release build is made from and at `main` otherwise. It fails on a broken page link or anchor, and on a link to a repository file that doesn't exist.
- **Diagrams.** The architecture diagrams are PlantUML sources in `content/architecture/`, rendered to committed SVGs by the `update-architecture-docs` skill.
- **Spelling.** CSpell checks the hand-written pages, `docs/README.md` and the repository's root `README.md` in US English. The 2 references are generated from docblocks, so CSpell skips them. A word the docs use in several places goes in `cspell.json`, and so does a word only the architecture walkthrough uses, because its skill regenerates that page. Any other word goes below the front matter of the 1 page that uses it, as `<!-- cspell:ignore word -->`.

## Accessibility

The site aims for [WCAG 2.2](https://www.w3.org/TR/WCAG22/) level AA in both color modes. `npm run test:accessibility` runs [axe](https://github.com/dequelabs/axe-core) on every page of the build, in light and dark mode, at a desktop width and a phone width, against the WCAG A and AA rules. It also opens the search dropdown and the mobile menu, and checks how they behave from the keyboard. Automated checks catch many problems but miss others, so the writing still needs care.

Some of it is handled for you:

- Links in page text are underlined, because color alone doesn't set a link apart from the text around it.
- Text colors reach at least 4.5:1 against their background. The light-mode blue is `#266fbd`, a darker shade of the `#2a77cd` logo blue, and the dark-mode blue is `#84b2e6`, a lighter one. The code block colors live in `src/codeThemes.js`, and a unit test checks every token color of both themes.
- A table wider than the page takes keyboard focus, so it scrolls with <kbd>←</kbd> and <kbd>→</kbd>.
- The mobile menu keeps keyboard focus inside while it's open and closes on <kbd>Esc</kbd>, and the search box gets the ARIA attributes its autocomplete widget leaves out. Both fixes live in `src/theme/`, so check them when Docusaurus or the search plugin changes its markup.

The rest is down to the writing:

- Give every image alt text that says what it shows.
- Keep heading levels in order on hand-written pages.
- Make link text say where it goes, such as [Configuration](../guides/configuration.md) rather than "here".

## Checks on every pull request

The `Docs` job of the `Test` workflow installs the dependencies, builds the site, runs `npm test` and then `npm run test:accessibility`. A broken link or anchor, a failing unit test, an unknown word or an accessibility violation fails the job. The `Lint` job runs `ahoy lint-docs`, which fails when a generated page is out of date.

## Previews

When the `NETLIFY_SITE_ID` and `NETLIFY_AUTH_TOKEN` repository secrets are set, the same job deploys the built site to [Netlify](https://www.netlify.com/): every push to `main` updates the development site, and every pull request gets a preview of its own, linked in a comment on the pull request. Without the secrets, the job skips the deployment, so a pull request from a fork still builds and tests the docs.

These builds show "development" as the version in the footer, and search engines don't index them.

## Publishing

`.github/workflows/release-docs.yml` publishes the docs to [behat-steps.drevops.com](https://behat-steps.drevops.com) on GitHub Pages:

- on every release tag, with the tag as the version;
- on a manual run of the `Release docs` workflow from **Actions**, from the tag or branch you type, so the pages and their GitHub links come from the same place.

The repository's GitHub Pages source must be **GitHub Actions**, and the `github-pages` environment must let the tag or the branch you run from deploy. The custom domain needs 2 settings outside the repository: `behat-steps.drevops.com` as the custom domain in the GitHub Pages settings, and a DNS `CNAME` record pointing it at `drevops.github.io`.

The workflow reads the site URL and base path from the GitHub Pages settings, so changing the domain needs no change to the code. The published site links to the address it was built for, so run the workflow again after you change the domain. Only these release builds show the release in the footer and let search engines index the pages.
