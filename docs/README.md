# Behat Steps documentation

This directory holds the source of the [Behat Steps documentation site](https://behat-steps.drevops.com), built with [Docusaurus](https://docusaurus.io/). The pages live in `content/`, and the home page in `src/pages/index.js`.

This README is for contributors who work on the site itself. It covers running the site on your machine, how the build and the home page work, accessibility, the checks on every pull request, previews and publishing. To write or change a page, see [Documentation](content/contributing/documentation.md), which covers the generated pages and the writing conventions.

## Working locally

The site is an npm project of its own, separate from the Docker stack. It needs Node.js 26.13.0 or newer, as `engines` in `package.json` states. Run these commands from `docs/`, or from the repository root with `--prefix docs`:

```bash
npm install                  # Install the dependencies
npm start                    # Serve the site locally, reloading on every change
npm run build                # Build the static site into build/
npm run serve                # Serve the built site
npm test                     # Run the unit tests and the spell check
npm run spellcheck           # Run only the spell check
npm run test:accessibility   # Check the built site for accessibility issues
```

`npm run test:accessibility` checks the site in `build/`, so run `npm run build` first. It drives a browser through [Playwright](https://playwright.dev/), which needs its own copy of Chromium. `npx playwright install chromium` downloads it once.

## How the build treats a page

- **Links.** The build rewrites a link that leaves `content/` into a GitHub link. In a release build, the GitHub link points at the tag or branch that `release-docs.yml` publishes. In CI, it points at the commit under test, and in a local build at `main`. The build fails on a broken page link or anchor, and on a link to a repository file that doesn't exist.
- **Gherkin highlighting.** Prism's Gherkin grammar recognizes a step keyword only after indentation, so `src/theme/prism-include-languages.js` widens the grammar to the start of a line. A step example highlights the same with or without indentation.
- **Diagrams in 2 color modes.** A page embeds a diagram's light SVG with a `#gh-light-mode-only` fragment and its dark SVG with `#gh-dark-mode-only`, the convention GitHub follows. Docusaurus keeps the fragment on the built image. `src/css/custom.css` hides the image of the other color mode and the link around it, so the site's color toggle switches the diagram. `tests/unit/content/diagrams.test.js` fails a diagram embedded without its pair.
- **Images in a lightbox.** `src/theme/MDXComponents.js` renders every Markdown image and link through the components in `src/components/Lightbox`. They open an image in a dialog over the page:
  - Selecting an image with alt text opens it. So does selecting a link to an `.avif`, `.gif`, `.jpeg`, `.jpg`, `.png`, `.svg` or `.webp` file when the link holds only an image with alt text. Diagrams are embedded this way. A double-click opens the dialog with the image fitted to the window, the same as a single click. An image without alt text, an image inside any other link and a link with text beside its image stay a plain image or an ordinary link. A click with a modifier key keeps the browser's own behavior: <kbd>Ctrl</kbd>-click, or <kbd>Cmd</kbd>-click on a Mac, opens the linked file in a new tab.
  - In the dialog, selecting the image zooms it to its natural size or twice its fitted size, whichever is larger. Selecting it again fits it back in the window. A mouse drag pans a zoomed image.
  - The **Close** button, <kbd>Esc</kbd> and a click beside the image close the dialog. For a linked image, the **Open the image in a new tab** button opens the linked file.

## The home page

The home page at `/` is a React page, `src/pages/index.js`. The introduction it links to lives at `/introduction`, set by the `slug` in the front matter of `content/README.md`. Pages and docs share the site root, so `onDuplicateRoutes: 'throw'` in `docusaurus.config.js` fails the build when a doc's slug claims a page's path.

`src/pages/index.js` holds the page's words in lists at its top, 1 per section. The sections are the example feature files, the "works with" row, the features, the flow, the reasons, the quick start steps and the docs cards. To change what the page says, edit its section's list.

- **Counts.** The step and trait counts come from the generated pages of the 2 step sections. The helper count comes from `HELPERS.md` at the repository root, because the site has no helper pages. A local plugin, `src/referenceCounts.js`, counts them at build time, so the counts follow `ahoy update-docs` with no edit to the page. The build fails when a section holds no steps or `HELPERS.md` holds no helpers.
- **Versions.** `BEHAT_VERSIONS`, `DRUPAL_VERSIONS` and `PHP_VERSIONS` hold the versions the page names. A unit test compares them with the 4.x row of the [supported versions](content/README.md#supported-versions) table and fails until they match.
- **Example scenarios.** The example feature files use shipped steps only. A unit test matches every step in them against the patterns in `STEPS.md`, so renaming a step fails the test until the example follows.
- **Components.** The feature file, the install command with its copy button and the icons are `FeatureFile`, `CopyCommand` and `Icon` in `src/components`.
- **Look.** The colors come from the `--behat-steps-*` tokens in `src/css/custom.css`. Each section's accents take the 4 bar colors of the logo in turn. The feature file and the commands stay dark in both color modes, so the code keeps the colors of the dark code theme. The page uses the system fonts, like the doc pages.
- **Motion.** The feature file marks its steps as passed one after another. Each block outside the first screen plays a short entrance as it scrolls into view. Every block shows without its entrance when JavaScript is off, when the reader's system is set to reduce motion and in a printout.

## Accessibility

The site aims for [WCAG 2.2](https://www.w3.org/TR/WCAG22/) level AA in both color modes. `npm run test:accessibility` runs [axe](https://github.com/dequelabs/axe-core) on every page of the build against the WCAG A and AA rules. It scans each page in light and dark mode, at a desktop width and a phone width. These scans run with reduced motion, so the home page shows every block from the start.

A separate check scans the home page again after scrolling through it with motion on. `npm run test:accessibility` also opens the search dropdown, the mobile menu and a diagram in the lightbox, and checks how they behave. It switches the home page's feature file from the keyboard and copies its install command.

Automated checks miss some problems. The site's code already covers these:

- Links in page text are underlined, because color alone doesn't set a link apart from the text around it.
- Text colors have at least 4.5:1 contrast against their background. The light-mode blue is `#266fbd`, a darker shade of the `#2a77cd` logo blue. The dark-mode blue is `#84b2e6`, a lighter shade of it. The code block colors live in `src/codeThemes.js`, and a unit test checks every token color of both themes.
- A table wider than the page takes keyboard focus, so you can scroll it with <kbd>←</kbd> and <kbd>→</kbd>.
- The mobile menu keeps keyboard focus inside while it's open, and closes on <kbd>Esc</kbd>. The search box gets the ARIA attributes its autocomplete widget leaves out. Both fixes live in `src/theme/`, so check them after Docusaurus or the search plugin changes its markup.
- The lightbox is a modal dialog named after the image's alt text. Nothing on the page behind it can take keyboard focus. Focus starts on the image, where <kbd>Enter</kbd> zooms in and out and the arrow keys scroll a zoomed image. <kbd>Esc</kbd> closes the dialog and returns focus to the image or link that opened it.

Whoever writes a page handles the rest, and [Documentation](content/contributing/documentation.md#writing-pages) lists what that takes.

## Checks on every pull request

The `Docs` job of the `Test` workflow installs the dependencies, builds the site, runs `npm test` and then `npm run test:accessibility`. A broken link or anchor, a failing unit test, an unknown word or an accessibility violation fails the job. The `Lint` job runs `ahoy lint-docs`, which fails when a generated page is out of date.

## Previews

When the `NETLIFY_SITE_ID` and `NETLIFY_AUTH_TOKEN` repository secrets are set, the `Docs` job deploys the built site to [Netlify](https://www.netlify.com/). Every push to `main` updates the [development site](https://behat-steps-docs.netlify.app/). Any other build gets a preview of its own, and the job links a pull request's preview in a comment on it. GitHub gives a pull request from a fork no secrets, so the job skips the deployment and still builds and tests the docs.

The Netlify builds show "development" as the version in the footer. Search engines don't index them, and they don't load Google Analytics.

## Publishing

`.github/workflows/release-docs.yml` publishes the docs to [behat-steps.drevops.com](https://behat-steps.drevops.com) on GitHub Pages in 2 cases:

- A pushed tag whose name holds no `/`, release tags included. The tag becomes the version.
- A manual run of the **Release docs** workflow from **Actions**, from the tag or branch you type. The pages and their GitHub links then come from that same tag or branch.

A tag pushed to a maintenance branch commit that still carries `release-docs.yml` republishes the site from that branch, replacing the newer docs. Remove the workflow from a maintenance branch when you cut it.

The repository's GitHub Pages source must be **GitHub Actions**. The `github-pages` environment must allow deployments from release tags and from the branch you start a manual run on. The custom domain needs 2 settings outside the repository: `behat-steps.drevops.com` as the custom domain in the GitHub Pages settings, and a DNS `CNAME` record pointing it at `drevops.github.io`.

The workflow reads the site URL and base path from the GitHub Pages settings, so you change the domain there and nowhere in the code. The published site links to the address it was built for, so run the workflow again after you change the domain. Only these release builds show the release in the footer, let search engines index the pages and send page views to Google Analytics.
