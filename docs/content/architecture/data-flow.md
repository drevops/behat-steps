---
title: Data flow
sidebar_position: 5
description: What runs when a suite starts, when a scenario starts, on each step and when a scenario ends, and which of it is Behat's, which is Behat Steps' and which is the site's.
---

# Data flow

This view shows what runs when, and whose code it is. Select the diagram to open it at full size.

[![Data flow: the 4 phases of a run as columns, and bands for Behat and Mink, Behat Steps and the site under test, with every step numbered in the order it runs](data-flow.svg#gh-light-mode-only)](data-flow.svg)
[![Data flow: the 4 phases of a run as columns, and bands for Behat and Mink, Behat Steps and the site under test, with every step numbered in the order it runs](data-flow.dark.svg#gh-dark-mode-only)](data-flow.dark.svg)

Behat owns the loop, and the library runs at 4 points in it. Those 4 points are the diagram's columns, and its 3 bands say whose code runs each part: Behat and Mink at the top, Behat Steps in the middle, and the site under test at the bottom. Inside the Behat Steps band, each box takes the color of its layer, and the numbers give the order:

1. **The suite starts.** Behat reads `behat.php` and loads `BehatStepsExtension`, which registers the backends the configuration lists and wires the services. `WebContext::assertOneContext()` runs as a `BeforeSuite` hook and fails a suite that registers 2 contexts built on `WebContext`, such as `WebContext` and `DrupalContext`, since both would register every web step.
2. **A scenario starts.** Behat builds fresh contexts, and `BackendAwareInitializer` injects the extension's services into each. `SkipTagListener` rejects a `@behat-steps-skip:` tag that names no composed trait. `BackendListener` gives `BackendRegistry` the scenario's backend order: the configured `backends` list, with any backend a `@backend:` tag names moved to the front. Then each trait's `BeforeScenario` hook asks `skipTag()` whether the trait is switched off, and `assertPrerequisites()` fails the scenario when the site lacks what the trait needs.
3. **Each step runs.** Behat matches the Gherkin line to a `#[Given]`, `#[When]` or `#[Then]` attribute on a trait method and calls it on the context. The step reaches out 1 of 3 ways: it drives the page through the Mink session, it asks `backendFor()` for a capability to read or change the site, or it sends a request of its own through 1 of the 3 HTTP clients. It then asserts on what came back, and a failure throws.
4. **The scenario ends.** Behat runs the `AfterScenario` hooks. `EntityLifecycleTrait` deletes the entities the scenario created through its helpers or registered with `entityLifecycleRegister()`, newest first, and `AuthTrait` deletes the scenario's users and then its roles, each through the capability that deletes that entity type. An entity a step creates directly through a backend and never registers stays in place.

The [Backend layer](backend-layer.md) zooms in on how `backendFor()` picks a backend, and the [Extension layer](extension-layer.md) on what the extension wires up at the start.
