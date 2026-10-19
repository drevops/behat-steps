---
title: Introduction
slug: /introduction
sidebar_position: 1
description: Behat Steps is a library of ready-made, tested Behat steps for testing any website and every Drupal site, with the helpers behind every step for steps of your own.
---

# Behat Steps

[Behat](https://behat.org) lets you write tests as plain-English scenarios, then runs them against your site. Here's one:

```gherkin
Feature: Contact page

  Scenario: Visitor finds the contact page
    When I visit "/contact"
    Then the response status code should be 200
    And I should see the heading "Contact"
```

Each line is a step, and Behat can only run a step once some PHP code stands behind it. Behat Steps is that code: hundreds of steps for pages, forms, links, cookies, APIs and Drupal sites, already written, documented and tested, so you can go straight to writing scenarios.

The steps come as PHP traits: 28 that work on any website, and 29 that only mean something on a Drupal site. The library [tests its own steps](../../tests/behat/features) to avoid false positives and negatives, and every step follows [the same wording rules](../../CONTRIBUTING.md#steps-format), so the language stays consistent from one trait to the next. When no shipped step says what you mean, you write your own, and it can call the same helpers the shipped steps are built on.

## What you get

- [Web steps](web-steps/README.md) and [Drupal steps](drupal-steps/README.md) list every step, with a page per trait and an example for each step.
- Every step is a thin wrapper over named helpers, which [Web helpers](web-helpers/README.md) and [Drupal helpers](drupal-helpers/README.md) list, so your own steps can call the same code.
- A step asks for the capability it needs, never for a [backend](guides/configuration.md#backend-resolution), so a step whose capability Drush provides, such as clearing caches or creating users, runs through Drush as well as in-process.
- Behat 3.33+ and Behat 4, Drupal 11 and 12, and PHP 8.3 to 8.5 are [supported](#supported-versions).
- The steps work with the BrowserKit, Selenium and Chrome DevTools [browser drivers](installation.md#javascript-browser-drivers).
- After each scenario, the library [deletes the entities](guides/usage.md#entities-a-scenario-creates) its steps created, in reverse creation order.
- The step and helper references ship with the package as Markdown, so an [AI coding agent](guides/ai-assistants.md) in your project reads the same reference you do.
- [Architecture](architecture/README.md) shows how the layers fit together in 3 diagrams, which an AI agent maintains through the `update-architecture-docs` skill.

## How it fits together

You register 1 of the library's contexts, the classes that hold its steps, in your Behat configuration, along with the Behat Steps extension and Mink, the library that drives the browser. A step that works on a page goes through Mink's browser session. A step that needs the site itself, such as one that creates a user, goes through a backend such as Drupal or Drush. [Key concepts](concepts.md) explains each piece, with a diagram of the whole.

## Supported versions

| Version | Behat | Drupal | PHP | Support |
| --- | --- | --- | --- | --- |
| 4.x | 3.33+, 4 | 11, 12 | 8.3, 8.4, 8.5 | The current major. Receives new features, bugfixes and security updates. |
| 3.x | 3 | 10, 11 | 8.2, 8.3, 8.4, 8.5 | The current minor is LTS until 1 July 2027 and receives bugfixes and security updates only. |
| 2.x | 3 | 9, 10 | 8.2+ | Unsupported |

## Where to next

- New to Behat or to this library? Start with [Installation](installation.md), then the [Quick start](quick-start.md), which takes you to a passing scenario. [Key concepts](concepts.md) explains the pieces as you meet them.
- To upgrade a 3.x project, see [Migrating from 3.x](migrating.md), which covers the migration guide and the AI agent skill that applies it.
- For steps of your own, [Scenario styles](guides/scenario-styles.md) explains when they pay off. [Web helpers](web-helpers/README.md) and [Drupal helpers](drupal-helpers/README.md) list what they can call.
