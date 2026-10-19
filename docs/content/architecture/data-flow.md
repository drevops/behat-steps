---
title: Data flow
sidebar_position: 3
description: What Behat Steps runs when a suite starts, when a scenario starts, on each step and when a scenario ends.
---

# Data flow

This view shows what runs when. Select the diagram to open it at full size.

[![Data flow: what runs when a suite starts, when a scenario starts, on each step and when a scenario ends](data-flow.svg)](data-flow.svg)

Behat owns the loop, and the library runs at 4 points in it. The diagram numbers every step in the order it runs and colors it by the part of the system that runs it:

1. **The suite starts.** Behat loads `BehatStepsExtension`, which registers the backends the configuration lists and wires the services. `WebContext::assertOneContext()` fails a suite that registers 2 of the shipped contexts, since both would register every web step.
2. **A scenario starts.** Behat builds fresh contexts, and `BackendAwareInitializer` injects the extension's services into each. `SkipTagListener` rejects a `@behat-steps-skip:` tag that names no composed trait. `BackendListener` gives `BackendRegistry` the scenario's backend order: the configured `backends` list, with any backend a `@backend:` tag names moved to the front. Then each trait's `BeforeScenario` hook asks `skipTag()` whether the trait is switched off, and `assertPrerequisites()` fails the scenario when the site lacks what the trait needs.
3. **Each step runs.** Behat matches the Gherkin line to a `#[Given]`, `#[When]` or `#[Then]` attribute on a trait method and calls it on the context. The step reaches out 1 of 3 ways: it drives the page through the Mink session, it asks `backendFor()` for a capability to read or change the site, or it sends a request of its own through 1 of the 3 HTTP clients. It then asserts on what came back, and a failure throws.
4. **The scenario ends.** `EntityLifecycleTrait` deletes what the scenario created, newest first, and `AuthTrait` deletes the scenario's users and then its roles, each through the capability that deletes that entity type.
