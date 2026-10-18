---
title: Introduction
sidebar_position: 1
description: Behat Steps is a library of reusable Behat steps for testing websites, with steps for Drupal sites and the helpers behind every step.
---

# Behat Steps

Behat Steps is a collection of reusable testing actions and assertions for automated testing with [Behat](https://behat.org). It helps you write reliable, readable and maintainable tests faster, because the steps most projects need are already written, tested and documented.

The steps come as PHP traits: 28 that work on any website, and 29 that only mean something on a Drupal site. Every method starts with the name of the trait that declares it, such as `cookieGetAll()` in `CookieTrait`, so none of them collides with a method in your own context. Step text is a different matter: Behat refuses to register 2 definitions with the same pattern, so drop any step of your own whose text matches a shipped one.

The library keeps solid [test coverage](../../tests/behat/features) to avoid false positives and negatives, and every step follows [clear guidelines](../../CONTRIBUTING.md#steps-format) so the language stays consistent from one trait to the next.

## What you get

- 🧩 **[Steps](steps.md)** - every step, grouped by trait, with an example for each.
- 🧰 **[Helpers](helpers.md)** - every step is a thin wrapper over named helpers, so your own domain steps call the same code.
- 🔌 **[Backends](guides/configuration.md#backend-resolution)** - a step names the capability it needs rather than a backend, so a step whose capability Drush provides, such as clearing caches or creating users, runs through Drush as well as in-process.
- ✅ **[Versions](#supported-versions)** - Behat 3.33+ and Behat 4, Drupal 11 and 12, and PHP 8.3 to 8.5.
- 🌐 **[Browser drivers](installation.md#javascript-browser-drivers)** - BrowserKit, Selenium and Chrome DevTools.
- 🧹 **[Cleanup](guides/usage.md#entities-a-scenario-creates)** - entities a scenario creates through its steps are deleted after it, in reverse creation order.
- 🤖 **[AI assistants](guides/ai-assistants.md)** - the step and helper references ship with the package as Markdown, so a coding agent in your project reads the same reference you do.
- 📐 **[Architecture](architecture/README.md)** - a walkthrough of how the layers fit together, with UML diagrams, maintained by an AI agent through the `update-architecture-docs` skill.

## How it fits together

Your project registers 1 of the shipped contexts, or composes step traits into a context of its own, and registers the Behat Steps extension beside Mink's. The traits decide what each step does. A step that drives the page uses the browser session Mink's extension opens, and a step that needs the site itself names a capability, which the extension answers with the first backend in your `backends` list that provides it, such as Drupal or Drush.

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
