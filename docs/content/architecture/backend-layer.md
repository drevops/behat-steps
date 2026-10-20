---
title: Backend layer
sidebar_position: 2
description: How a step reaches the site without the browser, from capability-driven backend selection to the 3 backends, the capabilities they provide and the rules the layer follows.
---

# Backend layer

The backend layer, `src/Backend`, is the part of Behat Steps that talks to the site directly: it creates content and users, clears caches, runs cron and reads the log, without going through the site's pages. It knows nothing about Behat or Mink, which keeps it usable outside a test run.

## How a step picks a backend

A step never names a backend. It asks for a capability, the 1 job it needs done, such as clearing caches, and the first backend in the scenario's order that provides that capability does the work. Select the diagram to open it at full size.

[![Backend selection: a step asks BackendRegistry for a capability, which asks the scenario's backends in order and runs the first one that provides it](backend-selection.svg#gh-light-mode-only)](backend-selection.svg)
[![Backend selection: a step asks BackendRegistry for a capability, which asks the scenario's backends in order and runs the first one that provides it](backend-selection.dark.svg#gh-dark-mode-only)](backend-selection.dark.svg)

Take `Given the cache has been cleared`. Its step method calls `backendFor(CacheCapabilityInterface::class)`, and `BackendRegistry` asks the scenario's backends in order whether they implement `CacheCapabilityInterface`:

1. The order comes from the `backends` list in your `behat.php`. A `@backend:NAME` tag on the scenario or its feature moves that backend to the front, and `BackendListener` settles the order as each scenario starts. Without a `backends` list, every registered backend takes part, in the order it was registered.
2. The first backend that implements the capability does the work. The registry bootstraps it the first time any step needs it, and every later step reuses it.
3. When no backend in the order provides the capability, the step fails with an `UnsupportedBackendActionException` that names the capability and the order it searched.

So the same step runs in-process under `backends: [drupal, drush]` and over Drush in a scenario tagged `@backend:drush`, with no change to the step. A backend of your own serves the shipped steps as soon as it implements their capabilities, as [A backend of your own](../extending/backend.md#a-backend-of-your-own) shows, and [Backend resolution](../guides/configuration.md#backend-resolution) states the rules in full.

## The 3 backends

| Backend | Class | How it reaches the site | Capabilities |
| --- | --- | --- | --- |
| `drupal` | `Backend\Drupal\DrupalBackend` | Bootstraps Drupal inside the Behat process and calls its API through `Core` | 34 of the 35 |
| `drush` | `Backend\Drupal\DrushBackend` | Runs `drush` commands against the site | 14 |
| `blackbox` | `Backend\BlackboxBackend` | It doesn't: it stands for a site you can only browse | None |

The `drupal` backend exists once your `behat.php` sets the `drupal` key, and `drush` once it sets the `drush` key. `blackbox` always exists.

The 1 capability the `drupal` backend lacks is `DrushCapabilityInterface`, which runs a Drush command, so a step that runs Drush itself always reaches the `drush` backend. [Which backend serves a capability](../../../CONTRIBUTING.md#which-backend-serves-a-capability) lists the capabilities Drush serves, and why each other one stays in-process.

## Capabilities

A capability is an interface, such as `CacheCapabilityInterface` or `UserCapabilityInterface`. A backend provides one by implementing it, so asking whether it does is an `instanceof` check, and a backend implements a capability only when it serves every method of it.

- The 11 capabilities any backend may provide sit in `src/Backend/Capability`: authentication, cache, content, creation aliases, cron, entity lookups, languages, mail, the request environment, roles and users.
- The 24 that only a Drupal backend provides sit in `src/Backend/Drupal/Capability`, such as blocks, config, files, the log, modules, nodes, queues, the State API, terms and workflows.

`BackendInterface` itself declares only 3 methods: `bootstrap()`, `isBootstrapped()` and `getRandom()`, the random value generator steps use for test data. Everything else a backend can do is a capability, so a backend for another kind of site starts from very little and adds what it can serve.

## The contract every backend keeps

Every create and delete on a capability follows 1 contract, so the cleanup at the end of a scenario can delete whatever the scenario created without checking first. A create returns the stub of the entity it created, an update or a copy returns a stub as well, and a delete returns nothing and does nothing when its target doesn't exist.

A capability method also leaves the site consistent behind it. `moduleInstall()` leaves the container and the router rebuilt, and `deleteUser()` drains the batch that canceling an account queues, so a step calls the method and compensates for nothing.

## What the layer may reach

[scripts/lint-layers.php](../../../scripts/lint-layers.php) holds the boundary. `src/Backend` references no Behat, Mink, extension or step code, and its root references no Drupal code either, so its contracts speak of entity types and storage values rather than of Drupal. The Drupal and Drush backends, `Core` with its field handlers and creation aliases, and the Drupal-only capabilities live in `src/Backend/Drupal`. [Layers](../../../CONTRIBUTING.md#layers) lists every rule.

## Where to next

- [Extending the backend layer](../extending/backend.md) covers field handlers, creation aliases, a Core of your own and a backend of your own.
- [Extension layer](extension-layer.md) shows how the extension registers the backends and sets each scenario's order.
