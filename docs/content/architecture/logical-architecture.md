---
title: Logical architecture
sidebar_position: 1
description: The 3 layers of Behat Steps, the contexts on top of them, which way every dependency points, and the tooling beside the library.
---

# Logical architecture

This view shows the parts of the library and which way they depend on each other. Select the diagram to open it at full size.

[![Logical architecture: your project above the contexts and the 3 library layers, the site under test below them, and the repository tooling beside them](logical-architecture.svg)](logical-architecture.svg)

The library is 3 layers with 3 contexts on top, and every dependency points down:

- **Contexts.** `WebRawContext` registers no steps: it hands the traits their backends, browser capabilities, HTTP clients and options. `WebContext` adds the 28 web step traits, and `DrupalContext` adds the 29 Drupal ones. Your project registers 1 of them, or a context of its own that extends 1.
- **Step vocabulary**, `src/Steps`. The step traits under `Steps\Web` and `Steps\Drupal`, and the 13 helper traits under `Steps\Helper`, which register no steps. A step body is a thin wrapper over those helpers.
- **Extension layer**, `src/Extension`. `BehatStepsExtension` wires the library into Behat: the listeners and registries that set up each scenario, option resolution and prerequisites, the authenticators, the browser capability adapters, the HTTP clients and the creation hooks.
- **Backend layer**, `src/Backend`. A backend talks to the site and knows nothing about Behat. `BackendInterface` declares only bootstrapping and a random value generator, and everything else a backend can do is a capability interface. `DrupalBackend` runs Drupal in the same PHP process and provides 34 of the 35 capabilities, `DrushBackend` runs `drush` and provides 14, and `BlackboxBackend` provides none, for a site the suite can only browse.

A step never names a backend. It asks for a capability, and `BackendRegistry` answers with the first backend in the scenario's order that provides it. That's why the same steps run in-process, over Drush, or on a backend of your own.

`scripts/lint-layers.php` keeps the arrows pointing down: `src/Backend` references no Behat, Mink, extension or step code, and `src/Extension` no step code. [Layers](../../../CONTRIBUTING.md#layers) lists every rule.

Beside the library sits the tooling that ships with the repository but not with the package. `docs.php` reflects `WebContext` and `DrupalContext` to write the step and helper references, `docs/` builds this site, `scripts/provision.php` builds the fixture Drupal site, and `tests/` runs the unit, kernel and Behat suites against it.
