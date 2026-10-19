---
title: Contributing
description: How to report a problem or send a change, where the rules for code live, and how the repository is laid out.
---

# Contributing

Contributions are welcome, from a bug report to a new trait. Report a bug or suggest a change in the [issue queue](https://github.com/drevops/behat-steps/issues). Send changes as pull requests against `main`, or against `3.x` for a fix to the 3.x LTS release.

## Where the rules live

[CONTRIBUTING.md](../../../CONTRIBUTING.md) holds the rules for code in this repository: how a step reads, how methods and classes are named, what each layer may reach, and how the tests are organized. Tests, linters and `docs.php` enforce most of them, so read the sections your change touches before you start.

It also covers the [local environment](../../../CONTRIBUTING.md#local-environment-setup), [running the tests](../../../CONTRIBUTING.md#running-tests) and [continuous integration](../../../CONTRIBUTING.md#continuous-integration).

## What lives where

- `src/` holds the package: the backends, the Behat extension, the 3 contexts and the step and helper traits.
- `tests/` holds the unit, kernel and Behat suites, and the fixture Drupal sites for Drupal 11 and 12 that all 3 suites run against.
- `docs.php` generates the [Web steps](../web-steps/README.md), [Drupal steps](../drupal-steps/README.md), [Web helpers](../web-helpers/README.md) and [Drupal helpers](../drupal-helpers/README.md) pages and the generated tables in [Configuration](../guides/configuration.md).
- `docs/` holds this site, with its pages in `docs/content/`.
- `scripts/` holds the fixture site provisioning, the coverage tools, the layer and trait linters, and the script that draws the architecture diagrams.
- `.claude/skills/` holds the AI agent skills kept in this repository: `migrate-behat-steps` for projects that use the package, and `update-architecture-docs` for maintainers.

## See also

- [Documentation](documentation.md): how to write these docs and regenerate the step and helper pages.
- [Architecture](../architecture/README.md): how the layers fit together, in logical architecture, class inheritance and data flow diagrams.
