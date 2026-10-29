---
title: Introduction
slug: /introduction
sidebar_position: 1
description: Behat Steps is a library of ready-made, tested Behat steps for any website and for Drupal sites, with the helpers behind every step for steps of your own.
---

# Behat Steps

[Behat](https://behat.org) lets you write tests as plain-English scenarios, then runs them against your site. This page introduces Behat Steps, the library that gives those scenarios their steps, so you can tell whether it suits your project before you [install it](installation.md). Here's a scenario:

```gherkin
Feature: Contact page

  Scenario: Visitor finds the contact page
    When I visit "/contact"
    Then the response status code should be 200
    And I should see the heading "Contact"
```

Each of those lines is a step, and Behat runs a step only when there's PHP code behind it. Behat Steps is that code: hundreds of steps for pages, forms, links, cookies, APIs and Drupal sites. They're already written, documented and tested, so you can go straight to writing scenarios.

The steps come as PHP traits: 28 that work on any website, and 29 that only mean something on a Drupal site. The library [tests its own steps](../../tests/behat/features) to catch false positives and negatives. Every step follows [the same wording rules](../../CONTRIBUTING.md#steps-format), so the language stays consistent from one trait to the next. When no shipped step says what you mean, you write your own. Your step can call the same helpers the shipped steps are built on.

## What you get

Here's what comes with the library:

- [Web steps](web-steps/README.md) and [Drupal steps](drupal-steps/README.md) list every step, with a page per trait and an example for each step.
- Every step is a thin wrapper over named helpers. [HELPERS.md](../../HELPERS.md) lists every helper, and it ships with the package.
- A step names the capability it needs, never a [backend](architecture/backend-layer.md#how-a-step-picks-a-backend). So a step whose capability Drush provides, such as clearing caches, runs through Drush as well as in-process.
- The steps work with 3 [browsers](guides/browsers.md): BrowserKit for HTTP requests from PHP, and Selenium2 or Chrome for a real browser that runs JavaScript.
- After each scenario, the library [deletes the entities](guides/usage.md#entities-a-scenario-creates) its steps created, in reverse creation order.
- The step and helper references ship with the package as Markdown, so an [AI coding agent](guides/ai-assistants.md) in your project reads the same reference you do. A skill ships beside them that has the agent write a step of your own on the helpers.
- [Architecture](architecture/README.md) shows how the layers fit together, with a page per layer and a trait taken apart line by line.

## How it fits together

You extend 1 of the library's contexts, the classes that hold its steps, in a context of your own, `FeatureContext`. Then you register `FeatureContext` in your Behat configuration, with the Behat Steps extension and Mink. Mink is the library that drives the browser. [Contexts](guides/contexts.md) explains which context to extend.

A step that works on a page goes through Mink's browser session. A step that needs the site itself, such as one that creates a user, goes through a backend such as Drupal or Drush. [Key concepts](concepts.md) explains each piece, with a diagram of the whole.

## Supported versions

Each major version runs on the following versions of Behat, Drupal and PHP:

| Version | Behat | Drupal | PHP | Support |
| --- | --- | --- | --- | --- |
| 4.x | 3.33+, 4 | 11, 12 | 8.3, 8.4, 8.5 | Receives new features, bugfixes and security updates. |
| 3.x | 3 | 10, 11 | 8.2, 8.3, 8.4, 8.5 | LTS until 1 July 2027. Its latest minor receives bugfixes and security updates only. |
| 2.x | 3 | 9, 10 | 8.2+ | Unsupported. Receives no updates. |

## Where to next

- New to Behat or to this library? Start with [Installation](installation.md), then the [Quick start](quick-start.md), which takes you to a passing scenario. [Key concepts](concepts.md) explains the pieces as you meet them.
- To upgrade a 3.x project, see [Migrating from 3.x](migrating.md), which covers the migration guide and the AI agent skill that applies it.
- For steps of your own, [Scenario styles](guides/scenario-styles.md) explains when they pay off, and [HELPERS.md](../../HELPERS.md) lists what they can call.
