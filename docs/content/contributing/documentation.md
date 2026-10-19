---
title: Documentation
sidebar_position: 1
description: Work on these docs locally, regenerate the reference pages, follow the writing conventions, and see how the site is checked, previewed and published.
---

<!-- cspell:ignore docblocks -->

# Documentation

These docs live in `docs/`, a [Docusaurus](https://docusaurus.io/) site with its pages in `docs/content/` and a [home page](#the-home-page) of its own. Most pages are hand-written. `docs.php` generates the step and helper pages from the source code and fills 2 tables in Configuration. It also writes both references as 1 file each, which ship with the Composer package.

## Generated pages

[`docs.php`](../../../docs.php) reads the traits' step attributes and docblocks, the extension's configuration tree and the tag registry in its own `tag_registry()`, and writes:

| File | Holds | Ships with the package |
| --- | --- | --- |
| [Web steps](../web-steps/README.md), [Drupal steps](../drupal-steps/README.md) | An index of the context's step traits, in the marked region of the section's `README.md`, and a page per trait beside it, with its options and an example for each step | No |
| [Web helpers](../web-helpers/README.md), [Drupal helpers](../drupal-helpers/README.md) | An index of the context's classes with helpers, in the marked region of the section's `README.md`, and a page per class beside it, with each helper's signature. `WebRawContext` has its page in Web helpers | No |
| [`STEPS.md`](../../../STEPS.md) | Every step on 1 page, at the repository root | Yes |
| [`HELPERS.md`](../../../HELPERS.md) | Every public helper on 1 page, at the repository root | Yes |
| [Configuration](../guides/configuration.md) | The extension options and the tag reference, in 2 marked regions | No |

A page takes its name from the `@title` tag in the docblock of its trait or class, such as `@title Batch API` on `Drupal\BatchTrait`, and the sidebar and the index list a section's pages in the order of those names. `ahoy lint-docs` fails on a trait or class with a page and no title.

The run removes a page of a trait or class that no longer exists, so a renamed trait leaves no page behind.

Run it from the repository root after changing a step, a helper, an option or a tag:

```bash
ahoy update-docs  # Regenerate the pages
ahoy lint-docs    # Fail when a page is out of date, without writing
```

Never edit inside a generated region: the next run overwrites it, and `ahoy lint-docs` fails CI until the page matches. [Validating and updating documentation](../../../CONTRIBUTING.md#validating-and-updating-documentation) lists everything else the generator checks.

A tag appears in the reference only after you register it in `tag_registry()`, so add a new tag there before you regenerate.

### The references in the package

A coding agent in a consuming project reads `STEPS.md` and `HELPERS.md` from `vendor/drevops/behat-steps/` to learn the public surface, as [Writing tests with AI assistants](../guides/ai-assistants.md) describes. `.gitattributes` keeps `docs/` out of the distribution archive, and the docs unit tests fail when either reference stops shipping or when a file in `docs/` starts shipping.

Both references link each trait's source by relative path, so those links resolve in `vendor/` as well as on GitHub. The Example links in `STEPS.md` point into `tests/`, which the archive leaves out, so they resolve only on GitHub and on the site.

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
- **Code blocks.** Name the language on each fence, such as `php`, `bash`, `yaml` or `gherkin`. Prism's Gherkin grammar recognizes a step keyword only after indentation, so `src/theme/prism-include-languages.js` widens it to the start of a line, and a step example highlights the same with or without indentation.
- **Link by file.** Link to another page by its path relative to the page holding the link, and to any other file in the repository the same way. From a page at the top of `content/`, that's `[Backend resolution](guides/configuration.md#backend-resolution)` for a page and `[CONTRIBUTING.md](../../CONTRIBUTING.md#steps-format)` for a repository file. The build rewrites a link that leaves `content/` into a GitHub link that points at the tag or branch a release build comes from, at the commit a CI build comes from, and at `main` for a local build. It fails on a broken page link or anchor, and on a link to a repository file that doesn't exist.
- **Diagrams.** The architecture diagrams are PlantUML sources in `content/architecture/`, and the `update-architecture-docs` skill renders them to committed SVGs.
- **Spelling.** CSpell checks the hand-written pages, `docs/README.md` and the repository's root `README.md` in US English. The step and helper pages are generated from docblocks, so CSpell skips them, apart from the hand-written `README.md` of each section. A word the docs use in several places goes in `cspell.json`, and so does a word only the architecture walkthrough uses, because its skill regenerates that page. Any other word goes below the front matter of the 1 page that uses it, as `<!-- cspell:ignore word -->`.

## The home page

The home page at `/` isn't a Markdown page: it's a React page, `src/pages/index.js`. The introduction it links to lives at `/introduction`, set by the `slug` in the front matter of `content/README.md`. Pages and docs share the site root, so `onDuplicateRoutes: 'throw'` in `docusaurus.config.js` fails the build when a doc's slug claims a page's path.

The page's words sit in lists at the top of `src/pages/index.js`, 1 per section: the example feature files, the "works with" row, the features, the flow, the reasons, the quick start steps and the docs cards. Changing what the page says usually means changing a list, not the markup.

- **Counts.** The step, trait and helper counts come from the generated pages. `src/referenceCounts.js`, a local plugin, counts them in the 4 reference sections at build time, so they follow `ahoy update-docs` with no edit to the page. The build fails when a section holds no steps or helpers.
- **Versions.** `BEHAT_VERSIONS`, `DRUPAL_VERSIONS` and `PHP_VERSIONS` hold the versions the page names. A unit test compares them with the 4.x row of the [supported versions](../README.md#supported-versions) table and fails until they match.
- **Example scenarios.** The feature files in the hero use shipped steps only. A unit test matches every step in them against the patterns in `STEPS.md`, so renaming a step fails the test until the example follows.
- **Components.** The feature file, the install command with its copy button and the icons are `FeatureFile`, `CopyCommand` and `Icon` in `src/components`.
- **Look.** The colors come from the `--behat-steps-*` tokens in `src/css/custom.css`, and the accents of each section take the 4 bar colors of the logo in turn. The feature file and the commands stay dark in both color modes, so the code keeps the colors of the dark code theme. The page uses the system fonts, like the doc pages.
- **Motion.** The feature file marks its steps as passed one after another, and each block below the fold plays a short entrance as it scrolls into view. Without JavaScript, or when the reader's system asks for reduced motion, everything shows at once, and a printout shows every block too.

## Accessibility

The site aims for [WCAG 2.2](https://www.w3.org/TR/WCAG22/) level AA in both color modes. `npm run test:accessibility` runs [axe](https://github.com/dequelabs/axe-core) on every page of the build, in light and dark mode, at a desktop width and a phone width, against the WCAG A and AA rules. These scans run with reduced motion, so the home page shows every block at once, and a separate check scans the home page again after scrolling through it with motion on. It also opens the search dropdown and the mobile menu, switches the home page's feature file from the keyboard and copies its install command, and checks how they behave. Automated checks catch many problems but miss others, so the writing still needs care.

The site handles some of it for you:

- Links in page text are underlined, because color alone doesn't set a link apart from the text around it.
- Text colors have at least 4.5:1 contrast against their background. The light-mode blue is `#266fbd`, a darker shade of the `#2a77cd` logo blue, and the dark-mode blue is `#84b2e6`, a lighter one. The code block colors live in `src/codeThemes.js`, and a unit test checks every token color of both themes.
- A table wider than the page takes keyboard focus, so you can scroll it with <kbd>←</kbd> and <kbd>→</kbd>.
- The mobile menu keeps keyboard focus inside while it's open and closes on <kbd>Esc</kbd>, and the search box gets the ARIA attributes its autocomplete widget leaves out. Both fixes live in `src/theme/`, so check them when Docusaurus or the search plugin changes its markup.

The rest is up to you:

- Give every image alt text that says what it shows.
- Keep heading levels in order on hand-written pages.
- Make link text say where it goes, such as [Configuration](../guides/configuration.md) rather than "here".

## Checks on every pull request

The `Docs` job of the `Test` workflow installs the dependencies, builds the site, runs `npm test` and then `npm run test:accessibility`. A broken link or anchor, a failing unit test, an unknown word or an accessibility violation fails the job. The `Lint` job runs `ahoy lint-docs`, which fails when a generated page is out of date.

## Previews

When the `NETLIFY_SITE_ID` and `NETLIFY_AUTH_TOKEN` repository secrets are set, the same job deploys the built site to [Netlify](https://www.netlify.com/): every push to `main` updates the development site, and every pull request gets a preview of its own, linked in a comment on the pull request. Without the secrets, the job skips the deployment, so a pull request from a fork still builds and tests the docs.

These builds show "development" as the version in the footer. Search engines don't index them, and they don't load Google Analytics.

## Publishing

`.github/workflows/release-docs.yml` publishes the docs to [behat-steps.drevops.com](https://behat-steps.drevops.com) on GitHub Pages:

- on every release tag, with the tag as the version;
- on a manual run of the `Release docs` workflow from **Actions**, from the tag or branch you type, so the pages and their GitHub links come from the same place.

A tag pushed on a maintenance branch that carries `release-docs.yml` republishes the site from that branch and replaces the newer docs, so remove the workflow from the branch when you cut it.

The repository's GitHub Pages source must be **GitHub Actions**, and the `github-pages` environment must allow deployments from release tags and from the branch you start a manual run on. The custom domain needs 2 settings outside the repository: `behat-steps.drevops.com` as the custom domain in the GitHub Pages settings, and a DNS `CNAME` record pointing it at `drevops.github.io`.

The workflow reads the site URL and base path from the GitHub Pages settings, so you can change the domain without changing the code. The published site links to the address it was built for, so run the workflow again after you change the domain. Only these release builds show the release in the footer, let search engines index the pages and send page views to Google Analytics.
