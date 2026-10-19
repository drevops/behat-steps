<p align="center">
  <a href="https://behat-steps.drevops.com" rel="noopener"><img width=200px height=200px src="logo.png" alt="Behat Steps documentation"></a>
</p>

<h1 align="center">Behat Steps</h1>

<h3 align="center">A collection of Behat steps for PHP and Drupal projects</h3>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/drevops/behat-steps.svg)](https://github.com/drevops/behat-steps/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/drevops/behat-steps.svg)](https://github.com/drevops/behat-steps/pulls)
[![Test](https://github.com/drevops/behat-steps/actions/workflows/test.yml/badge.svg)](https://github.com/drevops/behat-steps/actions/workflows/test.yml)
[![codecov](https://codecov.io/gh/drevops/behat-steps/graph/badge.svg?token=0UFU5VNNPI)](https://codecov.io/gh/drevops/behat-steps)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/behat-steps)
![LICENSE](https://img.shields.io/github/license/drevops/behat-steps)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

[![Total Downloads](https://poser.pugx.org/drevops/behat-steps/downloads)](https://packagist.org/packages/drevops/behat-steps)

[![Vortex Ecosystem](https://img.shields.io/badge/%F0%9F%8C%80-Vortex%20Ecosystem-2C5A68?style=for-the-badge&labelColor=65ACBC)](https://github.com/drevops/vortex)

[![Join our community](https://img.shields.io/badge/Join%20our%20community-Slack-4A154B?style=for-the-badge&logo=slack&logoColor=white)](https://drupal.slack.com/archives/C4T2JHG9K)

[Read the documentation](https://behat-steps.drevops.com)
</div>

---

Behat Steps is a collection of reusable testing actions and assertions for automated testing with [Behat](https://behat.org). It ships steps that work on any website and steps that only mean something on a Drupal site, and every step is a thin wrapper over named helpers that your own step definitions can call too.

## Features

- [Web steps](https://behat-steps.drevops.com/web-steps) and [Drupal steps](https://behat-steps.drevops.com/drupal-steps) for pages, forms, APIs, email, caches, content and more, with an example for each.
- [Web helpers](https://behat-steps.drevops.com/web-helpers) and [Drupal helpers](https://behat-steps.drevops.com/drupal-helpers) behind every step, for the domain steps you write yourself.
- [Backends](https://behat-steps.drevops.com/guides/configuration#backend-resolution) picked by capability, so a step runs in-process or through Drush.
- Support for Behat 3.33+ and Behat 4, Drupal 11 and 12, and PHP 8.3 to 8.5.
- BrowserKit, Selenium and Chrome DevTools [browser drivers](https://behat-steps.drevops.com/installation#javascript-browser-drivers).
- [Cleanup](https://behat-steps.drevops.com/guides/usage#entities-a-scenario-creates) of the entities a scenario creates.
- Step and helper references that ship with the package, for [AI coding agents](https://behat-steps.drevops.com/guides/ai-assistants).

## Installation

Add the package to your project's development dependencies:

```bash
composer require --dev drevops/behat-steps:^4
```

Composer installs Behat, Mink and the rest of what the steps run on along with it. A few steps need a package of their own, which Behat Steps suggests rather than requires:

- `softcreatr/jsonpath` and `justinrainbow/json-schema` for the JSON path and JSON schema steps.
- `lullabot/mink-selenium2-driver` or `dmore/behat-chrome-extension` for `@javascript` scenarios, which drive a real browser.

[Installation](https://behat-steps.drevops.com/installation) covers the optional dependencies and the JavaScript browser drivers, and the [Quick start](https://behat-steps.drevops.com/quick-start) takes you to a first scenario.

## Migrate from 3.x

[MIGRATION.md](MIGRATION.md) takes a project from 3.14 to 4.0, and an AI coding agent can apply it for you. To migrate by hand or with an agent, see [Migrate from 3.x](https://behat-steps.drevops.com/migrating).

## Write tests with an AI coding agent

The step and helper references ship with the package as [STEPS.md](STEPS.md) and [HELPERS.md](HELPERS.md), so a coding agent in your project reads them from `vendor/drevops/behat-steps/`. For a snippet to add to your `CLAUDE.md` or `AGENTS.md`, see [Write tests with an AI coding agent](https://behat-steps.drevops.com/guides/ai-assistants).

## Contributing

Contributions are welcome. [CONTRIBUTING.md](CONTRIBUTING.md) holds the rules for code in this repository and the local development setup. The documentation site's [Contributing](https://behat-steps.drevops.com/contributing) section covers the repository layout and how the site is built.

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
