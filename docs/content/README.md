---
title: Introduction
sidebar_position: 1
description: Behat Steps is a library of reusable Behat steps for testing websites, with steps for Drupal sites and the helpers behind every step.
---

# Behat Steps

Behat Steps is a collection of reusable testing actions and assertions for automated testing with [Behat](https://behat.org). It helps you write reliable, readable and maintainable tests faster, because the steps most projects need are already written, tested and documented.

The steps come as PHP traits: 28 that work on any website, and 29 that only mean something on a Drupal site. Every method a trait contributes starts with the trait's own name, so none of them clashes with the step definitions you already have.

The library keeps solid [test coverage](../../tests/behat/features) to avoid false positives and negatives, and every step follows [clear guidelines](../../CONTRIBUTING.md#steps-format) so the language stays consistent from one trait to the next.

## What you get

- 🧩 **[Steps](steps.md)** - 57 step traits, with an example for every step.
- 🧰 **[Helpers](helpers.md)** - every step is a thin wrapper over named helpers, so your own domain steps call the same code.
- 🔌 **[Backends](guides/configuration.md#backend-resolution)** - a step names the capability it needs rather than a backend, so a step whose capability Drush provides, such as clearing caches or creating users, runs through Drush as well as in-process.
- ✅ **[Versions](#supported-versions)** - Behat 3.33+ and Behat 4, Drupal 11 and 12, and PHP 8.3 to 8.5.
- 🌐 **[Browser drivers](installation.md#javascript-browser-drivers)** - BrowserKit, Selenium and Chrome DevTools.
- 🧹 **[Cleanup](guides/usage.md#entities-a-scenario-creates)** - entities a scenario creates through its steps are deleted after it, in reverse creation order.
- 🤖 **[AI assistants](guides/ai-assistants.md)** - the step and helper references ship with the package as Markdown, so a coding agent in your project reads the same reference you do.
- 📐 **[Architecture](architecture/README.md)** - a walkthrough of how the layers fit together, with UML diagrams, maintained by an AI agent through the `update-architecture-docs` skill.

## How it fits together

Your project composes step traits into a Behat context and registers the Behat Steps extension. The extension decides how a step reaches the site, through the browser or through a backend such as Drupal or Drush, and the traits decide what each step does.

![Component architecture: the 3 library layers, the consuming project and the runtime around them](architecture/architecture.svg)

[Usage](guides/usage.md) covers that model from your project's side, and [Architecture](architecture/README.md) walks through each layer.

## Supported versions

| Version | Behat | Drupal | PHP | Support |
| --- | --- | --- | --- | --- |
| 4.x | 3.33+, 4 | 11, 12 | 8.3, 8.4, 8.5 | The current major. Receives new features, bugfixes and security updates. |
| 3.x | 3 | 10, 11 | 8.2, 8.3, 8.4, 8.5 | The current minor is LTS until 1 July 2027 and receives bugfixes and security updates only. |
| 2.x | 3 | 9, 10 | 8.2+ | Unsupported |

## Where to next

- New to Behat Steps? Start with [Installation](installation.md), then the [Quick start](quick-start.md).
- Upgrading a 3.x project? [Migrating from 3.x](migrating.md) covers the guide and the AI agent skill that applies it.
- Writing steps of your own? [Scenario styles](guides/scenario-styles.md) explains when they pay off, and [Helpers](helpers.md) lists what they can call.
