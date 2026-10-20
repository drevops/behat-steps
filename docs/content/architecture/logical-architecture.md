---
title: Logical architecture
sidebar_position: 1
description: The 3 layers of Behat Steps, the contexts on top of them, which way every dependency points and the tooling beside the library.
---

# Logical architecture

This view shows the parts of the library and which way they depend on each other. Select the diagram to open it at full size.

[![Logical architecture: your project above the contexts and the 3 library layers, the site under test below them, and the repository tooling beside them](logical-architecture.svg#gh-light-mode-only)](logical-architecture.svg)
[![Logical architecture: your project above the contexts and the 3 library layers, the site under test below them, and the repository tooling beside them](logical-architecture.dark.svg#gh-dark-mode-only)](logical-architecture.dark.svg)

The library is 3 layers with 3 contexts on top, and every dependency points down:

- The contexts sit on top. `WebRawContext` registers no steps: it hands the traits their backends, browser capabilities, HTTP clients and options. `WebContext` adds the 28 web step traits, and `DrupalContext` adds the 29 Drupal ones. Your project's `FeatureContext` extends 1 of them and uses step traits of your own, as [Contexts](../guides/contexts.md) describes.
- The [Steps layer](steps-layer.md), `src/Steps`, holds the step traits under `Steps\Web` and `Steps\Drupal`, and the 13 helper traits under `Steps\Helper`, which register no steps. A step body is a thin wrapper over those helpers.
- The [Extension layer](extension-layer.md), `src/Extension`, holds `BehatStepsExtension`, which wires the library into Behat: the listeners and registries that set up each scenario, option resolution and prerequisites, the authenticators, the browser capability adapters, the HTTP clients and the creation hooks.
- The [Backend layer](backend-layer.md), `src/Backend`, talks to the site and references nothing from Behat. `BackendInterface` declares only bootstrapping and a random value generator, and everything else a backend can do is a capability interface. `DrupalBackend` runs Drupal in the same PHP process and provides 34 of the 35 capabilities, and `DrushBackend` runs `drush` and provides 14. `BlackboxBackend` provides none, for a site the suite can only browse.

A step never names a backend. It requests a capability, and `BackendRegistry` returns the first backend in the scenario's order that provides it. That's why the same steps run in-process, over Drush or on a backend of your own.

`scripts/lint-layers.php` keeps the arrows pointing down: `src/Backend` references no Behat, Mink, extension or step code, and `src/Extension` no step code. [Layers](../../../CONTRIBUTING.md#layers) lists every rule.

Beside the library sits the tooling that ships with the repository but not with the package:

- `docs.php` reflects `WebContext` and `DrupalContext` to write the step and helper references.
- `docs/` builds this site.
- `scripts/provision.php` builds the fixture Drupal site.
- `tests/` runs the unit, kernel and Behat suites against it.
