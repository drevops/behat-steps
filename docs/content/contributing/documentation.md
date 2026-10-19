---
title: Documentation
sidebar_position: 1
description: Change these docs, from regenerating the step and helper pages after a code change to the conventions for writing a page.
---

<!-- cspell:ignore docblocks -->

# Documentation

This page is for you when you change these docs, whether that's a guide, a page in this section or the docblock of a step you added. Most pages are hand-written. `docs.php` generates the step and helper pages from the source code and fills 2 tables in Configuration. It also writes both references as 1 file each, and those ship with the Composer package.

The site that renders these pages is a project of its own. Its README, [docs/README.md](../../README.md), covers running it on your machine to preview a change, the checks every pull request runs, previews and publishing.

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

The run removes the page of a trait or class that no longer exists, so a renamed trait leaves no page behind.

Run it from the repository root after you change a step, a helper, an option or a tag:

```bash
ahoy update-docs  # Regenerate the pages
ahoy lint-docs    # Fail when a page is out of date, without writing
```

Don't edit inside a generated region: the next run overwrites it, and `ahoy lint-docs` fails CI until the page matches. [Validating and updating documentation](../../../CONTRIBUTING.md#validating-and-updating-documentation) lists everything else the generator checks.

A tag appears in the reference only after you register it in `tag_registry()`, so add a new tag there before you regenerate.

### The references in the package

A coding agent in a consuming project reads `STEPS.md` and `HELPERS.md` from `vendor/drevops/behat-steps/` to learn the public surface, as [Writing tests with AI assistants](../guides/ai-assistants.md) describes. `.gitattributes` keeps `docs/` out of the distribution archive, and the docs unit tests fail when either reference stops shipping or when a file in `docs/` starts shipping.

Both references link each trait's source by relative path, so those links resolve in `vendor/` as well as on GitHub. The Example links in `STEPS.md` point into `tests/`, which the archive leaves out, so they resolve only on GitHub and on the site.

## Writing pages

- **1 topic per page.** The sidebar follows the folder tree of `content/`, ordered by the `sidebar_position` in each page's front matter, and a folder's `_category_.json` names its category. A folder's `README.md` is its index page.
- **Plain Markdown.** Pages are `.md` files, which Docusaurus reads as CommonMark, so they render the same on GitHub. Docusaurus-only syntax, such as a `:::note` admonition, shows as plain text there, so keep it rare. Name a page `.mdx` only when it needs a React component.
- **Code blocks.** Name the language on each fence, such as `php`, `bash`, `yaml` or `gherkin`. A step example highlights the same with or without indentation.
- **Link by file.** Link to another page by its path relative to the page holding the link, and to any other file in the repository the same way. From a page at the top of `content/`, that's `[Backend resolution](guides/configuration.md#backend-resolution)` for a page and `[CONTRIBUTING.md](../../CONTRIBUTING.md#steps-format)` for a repository file. The build turns a link that leaves `content/` into a GitHub link, and it fails on a broken page link or anchor, and on a link to a repository file that doesn't exist.
- **Diagrams.** An architecture diagram is a draw.io source in `content/architecture/`, beside the SVG its page embeds. The `update-architecture-docs` skill draws them through [scripts/draw-diagram.php](../../../scripts/draw-diagram.php). After you edit a source in draw.io, export the SVGs again with `php scripts/draw-diagram.php --export docs/content/architecture` from the repository root, because the unit suite fails an SVG exported from an older source.
- **Spelling.** CSpell checks the hand-written pages, `docs/README.md` and the repository's root `README.md` in US English. The step and helper pages are generated from docblocks, so CSpell skips them, apart from the hand-written `README.md` of each section. A word the docs use in several places goes in `cspell.json`, and so does a word only the architecture pages use, because their skill regenerates them. Any other word goes below the front matter of the 1 page that uses it, as `<!-- cspell:ignore word -->`.
- **Accessibility.** Give every image alt text that says what it shows, keep heading levels in order, and make link text say where it goes, such as [Configuration](../guides/configuration.md) rather than "here". Automated checks catch many problems but miss others, so the writing still needs care.
