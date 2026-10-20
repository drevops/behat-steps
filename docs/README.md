# Behat Steps documentation

This directory holds the source of the [Behat Steps documentation site](https://behat-steps.drevops.com), built with [Docusaurus](https://docusaurus.io/). The pages live in `content/`, and the home page in `src/pages/index.js`.

This README covers the site itself: running it on your machine, how the home page and the build work, accessibility, the checks on every pull request, previews and publishing. To write or change a page, see [Documentation](content/contributing/documentation.md), which covers the generated pages and the writing conventions.

## Working locally

The site is an npm project of its own, separate from the Docker stack, and it needs Node.js 24.22.0 or newer, as `engines` in `package.json` states. Run these from `docs/`, or from the repository root with `--prefix docs`:

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

## How the build treats a page

- **Links.** The build rewrites a link that leaves `content/` into a GitHub link. It points at the tag or branch a release build comes from, at the commit a CI build comes from, and at `main` for a local build. The build fails on a broken page link or anchor, and on a link to a repository file that doesn't exist.
- **Gherkin highlighting.** Prism's Gherkin grammar recognizes a step keyword only after indentation, so `src/theme/prism-include-languages.js` widens it to the start of a line. A step example highlights the same with or without indentation.
- **Diagrams in 2 color modes.** A diagram's page embeds its light SVG with a `#gh-light-mode-only` fragment and its dark SVG with `#gh-dark-mode-only`, the convention GitHub follows. Docusaurus keeps the fragment on the built image, and `src/css/custom.css` hides the image for the other color mode, with the link around it, so the site's own color toggle switches the diagram. `tests/unit/content/diagrams.test.js` fails a diagram embedded without its pair.
- **Images in a lightbox.** `src/theme/MDXComponents.js` hands every Markdown image and link to the components in `src/components/Lightbox`, which open an image in a dialog over the page:
  - Selecting an image with alt text opens it, and so does selecting a link to an `.avif`, `.gif`, `.jpeg`, `.jpg`, `.png`, `.svg` or `.webp` file that holds only an image, which is how a diagram is embedded. An image without alt text, an image inside any other link and a link with text beside its image stay a plain image or an ordinary link. A click on the link with a modifier key keeps the browser's own behavior, so <kbd>Ctrl</kbd>-click, or <kbd>Cmd</kbd>-click on a Mac, opens the linked file in a new tab.
  - In the dialog, selecting the image zooms it to whichever is larger, its natural size or twice its fitted size, and selecting it again fits it back in the window. A mouse drag pans a zoomed image. A double-click opens the dialog once, fitted, as a single click does.
  - The **Close** button, <kbd>Esc</kbd> and a click beside the image close the dialog. For a linked image, the **Open the image in a new tab** button opens the linked file.

## The home page

The home page at `/` isn't a Markdown page: it's a React page, `src/pages/index.js`. The introduction it links to lives at `/introduction`, set by the `slug` in the front matter of `content/README.md`. Pages and docs share the site root, so `onDuplicateRoutes: 'throw'` in `docusaurus.config.js` fails the build when a doc's slug claims a page's path.

The page's words sit in lists at the top of `src/pages/index.js`, 1 per section: the example feature files, the "works with" row, the features, the flow, the reasons, the quick start steps and the docs cards. Changing what the page says usually means changing a list, not the markup.

- **Counts.** The step and trait counts come from the generated pages of the 2 step sections, and the helper count from `HELPERS.md` at the repository root, since the site has no helper pages. `src/referenceCounts.js`, a local plugin, counts them at build time, so they follow `ahoy update-docs` with no edit to the page. The build fails when a section holds no steps or `HELPERS.md` holds no helpers.
- **Versions.** `BEHAT_VERSIONS`, `DRUPAL_VERSIONS` and `PHP_VERSIONS` hold the versions the page names. A unit test compares them with the 4.x row of the [supported versions](content/README.md#supported-versions) table and fails until they match.
- **Example scenarios.** The feature files in the hero use shipped steps only. A unit test matches every step in them against the patterns in `STEPS.md`, so renaming a step fails the test until the example follows.
- **Components.** The feature file, the install command with its copy button and the icons are `FeatureFile`, `CopyCommand` and `Icon` in `src/components`.
- **Look.** The colors come from the `--behat-steps-*` tokens in `src/css/custom.css`, and the accents of each section take the 4 bar colors of the logo in turn. The feature file and the commands stay dark in both color modes, so the code keeps the colors of the dark code theme. The page uses the system fonts, like the doc pages.
- **Motion.** The feature file marks its steps as passed one after another, and each block below the fold plays a short entrance as it scrolls into view. Without JavaScript, or when the reader's system asks for reduced motion, everything shows at once, and a printout shows every block too.

## Accessibility

The site aims for [WCAG 2.2](https://www.w3.org/TR/WCAG22/) level AA in both color modes. `npm run test:accessibility` runs [axe](https://github.com/dequelabs/axe-core) on every page of the build, in light and dark mode, at a desktop width and a phone width, against the WCAG A and AA rules. These scans run with reduced motion, so the home page shows every block at once, and a separate check scans the home page again after scrolling through it with motion on. It also opens the search dropdown, the mobile menu and a diagram in the lightbox, switches the home page's feature file from the keyboard and copies its install command, and checks how they behave.

Automated checks catch many problems but miss others. The site handles some of it for you:

- Links in page text are underlined, because color alone doesn't set a link apart from the text around it.
- Text colors have at least 4.5:1 contrast against their background. The light-mode blue is `#266fbd`, a darker shade of the `#2a77cd` logo blue, and the dark-mode blue is `#84b2e6`, a lighter one. The code block colors live in `src/codeThemes.js`, and a unit test checks every token color of both themes.
- A table wider than the page takes keyboard focus, so you can scroll it with <kbd>←</kbd> and <kbd>→</kbd>.
- The mobile menu keeps keyboard focus inside while it's open and closes on <kbd>Esc</kbd>, and the search box gets the ARIA attributes its autocomplete widget leaves out. Both fixes live in `src/theme/`, so check them when Docusaurus or the search plugin changes its markup.
- The lightbox is a modal dialog named after the image's alt text, and nothing on the page behind it can take keyboard focus. Focus starts on the image, where <kbd>Enter</kbd> zooms in and out and the arrow keys scroll a zoomed image, and <kbd>Esc</kbd> closes the dialog and returns focus to the image or link that opened it.

The rest is up to whoever writes the page, and [Documentation](content/contributing/documentation.md#writing-pages) lists what that takes.

## Checks on every pull request

The `Docs` job of the `Test` workflow installs the dependencies, builds the site, runs `npm test` and then `npm run test:accessibility`. A broken link or anchor, a failing unit test, an unknown word or an accessibility violation fails the job. The `Lint` job runs `ahoy lint-docs`, which fails when a generated page is out of date.

## Previews

When the `NETLIFY_SITE_ID` and `NETLIFY_AUTH_TOKEN` repository secrets are set, the same job deploys the built site to [Netlify](https://www.netlify.com/). Every push to `main` updates the [development site](https://behat-steps-docs.netlify.app/), and every pull request gets a preview of its own, linked in a comment on the pull request. Without the secrets, the job skips the deployment, so a pull request from a fork still builds and tests the docs.

These builds show "development" as the version in the footer. Search engines don't index them, and they don't load Google Analytics.

## Publishing

`.github/workflows/release-docs.yml` publishes the docs to [behat-steps.drevops.com](https://behat-steps.drevops.com) on GitHub Pages:

- on every pushed tag whose name holds no `/`, release tags included, with the tag as the version;
- on a manual run of the `Release docs` workflow from **Actions**, from the tag or branch you type, so the pages and their GitHub links come from the same place.

A tag pushed on a maintenance branch that carries `release-docs.yml` republishes the site from that branch and replaces the newer docs, so remove the workflow from the branch when you cut it.

The repository's GitHub Pages source must be **GitHub Actions**, and the `github-pages` environment must allow deployments from release tags and from the branch you start a manual run on. The custom domain needs 2 settings outside the repository: `behat-steps.drevops.com` as the custom domain in the GitHub Pages settings, and a DNS `CNAME` record pointing it at `drevops.github.io`.

The workflow reads the site URL and base path from the GitHub Pages settings, so you can change the domain without changing the code. The published site links to the address it was built for, so run the workflow again after you change the domain. Only these release builds show the release in the footer, let search engines index the pages and send page views to Google Analytics.
