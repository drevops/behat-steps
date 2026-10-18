---
title: Contributing
description: How to report a problem or send a change, where the rules for code live, and how the repository is laid out.
---

# Contributing

Contributions are welcome, from a bug report to a new trait. Report a bug or suggest a change in the [issue queue](https://github.com/drevops/behat-steps/issues), and send changes as pull requests against `main`.

## Where the rules live

[CONTRIBUTING.md](../../../CONTRIBUTING.md) holds the rules for code in this repository: how a step reads, how methods and classes are named, what each layer may reach, and how the tests are organized. Tests, linters and `docs.php` enforce most of them, so read the sections your change touches before you start.

It also covers the [local environment](../../../CONTRIBUTING.md#local-environment-setup), [running the tests](../../../CONTRIBUTING.md#running-tests) and [continuous integration](../../../CONTRIBUTING.md#continuous-integration).

## What lives where

- `src/` - the package: the backends, the Behat extension, the 3 contexts and the step and helper traits.
- `tests/` - the unit, kernel and Behat suites, and the fixture Drupal sites the Behat suite runs against.
- `docs.php` - the generator behind [Steps](../steps.md), [Helpers](../helpers.md) and the generated tables in [Configuration](../guides/configuration.md).
- `docs/` - this site, with its pages in `docs/content/`.
- `scripts/` - the fixture site provisioning, the coverage tools and the layer and trait linters.
- `.claude/skills/` - the AI agent skills the repository ships: `migrate-behat-steps` for consuming projects, and `update-architecture-docs` for maintainers.

## See also

- [Documentation](documentation.md) - how these docs are written, generated, checked and published.
- [Architecture](../architecture/README.md) - how the layers fit together, with UML diagrams.
