---
title: Steps layer
sidebar_position: 4
description: The step traits and helper traits under src/Steps, how the 3 contexts compose them, how a step reaches the page and the site, and the rules every trait follows.
---

# Steps layer

The steps layer, `src/Steps`, is the part you use every day: the traits that hold every step and every helper, which [Extending](../extending/steps.md) calls the step vocabulary. The 3 contexts at the package root compose them, and your `FeatureContext` extends 1 of those. Select the diagram to zoom in.

[![Steps layer: the contexts from WebRawContext to your FeatureContext, and the trait directories each one composes](steps-layer.svg#gh-light-mode-only)](steps-layer.svg)
[![Steps layer: the contexts from WebRawContext to your FeatureContext, and the trait directories each one composes](steps-layer.dark.svg#gh-dark-mode-only)](steps-layer.dark.svg)

## What's in it

The layer has 5 directories:

- `src/Steps/Web` holds 28 step traits for any website.
- `src/Steps/Drupal` holds 29 step traits for a Drupal site.
- `src/Steps/Helper/Web` holds 10 helper traits. They cover fixture directories, heading lookup, JavaScript error collection, JSON path lookup, last-step tracking, the request header bag, REST response reads, string shaping, table transposition and token replacement.
- `src/Steps/Helper/Drupal` holds 5 helper traits: the entity lifecycle, authentication, static caches, fixture files and entity lookups.
- `src/Steps/Exception` holds `AssertionException`, which a trait that never uses the browser throws when an assertion fails.

Only the traits in the 2 step trait directories hold steps. A trait sits in the directory of the sites its steps serve. `Web` holds the steps that mean something on any site. `Drupal` holds the ones that mean something only on a Drupal site, whether they reach a backend or drive the page alone. [scripts/lint-traits.php](../../../scripts/lint-traits.php) fails a step trait that composes another step trait, and a helper trait that carries a step.

## How the contexts compose it

Each context builds on the one before it:

- `WebRawContext` registers no steps. It carries the plumbing and composes 3 web helper traits: `LastStepTrait`, `RequestHeadersTrait` and `StringTrait`.
- `WebContext` extends it and composes every trait under `Steps/Web`.
- `DrupalContext` extends that and composes every trait under `Steps/Drupal`.

`ContextCompositionTest` checks that each context composes every trait of its directory and nothing else. It also holds each trait to 1 composition across the 3 contexts, because a subclass that composes its parent's trait again registers every step in it twice. [Contexts](../guides/contexts.md) covers which context you extend and why 1 holds everything.

A step trait composes the helper traits its own body calls, so it brings what it needs into any context that composes it. The traits that create entities compose `EntityLifecycleTrait` and so bring the `AfterScenario` pass that deletes what they created. Within 1 class, PHP merges a helper trait that several step traits compose into 1 copy. Those traits share the helper's 1 registry, 1 hook and 1 deletion pass, which deletes the newest entity first.

A trait names the class it needs with `@phpstan-require-extends`: Mink's `RawMinkContext` when a Mink session is all it touches, and `WebRawContext` when it reaches a backend, an option or an HTTP client.

## How a step reaches out

A step reaches out in 1 of 3 ways, through methods of `WebRawContext` or Mink:

- The page, through the Mink session: `$this->getSession()`, and `browserDriverFor()` for a capability of the browser, such as running JavaScript. [Browsers](../guides/browsers.md) lists which browser provides what.
- The site itself, through `backendFor()` and a capability, as [Backend layer](backend-layer.md) describes.
- A request of its own, through 1 of the 4 [HTTP clients](../guides/http-clients.md).

The traits fall into 3 groups by what they need from a backend:

- `MessageTrait`, `MappingTrait`, `RegionTrait` and `BasicAuthTrait` need the extension's configuration but no backend.
- Every other web trait needs no backend, and neither do `BatchTrait`, `BigPipeTrait` and `ConfigOverrideTrait` under `Steps/Drupal`. `ConfigOverrideTrait` passes its signal to the scenario's backends that accept it, and works when there are none.
- Every other trait names the capabilities it works through, so it runs on any backend that provides them.

[What a trait needs from the backend](../../../CONTRIBUTING.md#what-a-trait-needs-from-the-backend) lists which trait uses which.

## The rules every trait follows

- A step body is a thin wrapper: it parses its arguments, calls helpers and guards on what they return. `StepBodyTest` holds every step to that shape, so whatever a scenario can do, a step of yours can do through the same helpers.
- Every member starts with its trait's name without `Trait`, such as `cookieGetAll()` in `CookieTrait`, so 2 traits in 1 context never collide. `TraitMethodNamingTest` holds the names to their conventions.
- Members come in a fixed order: composition, constants and properties, then hooks, then `Given`, `When` and `Then` steps, then helpers. `MemberOrderTest` holds it.
- A `public` helper is part of the API that semantic versioning covers, and a `protected` one can change in any release, as [Public API and versioning](versioning.md) describes.

[Anatomy of a trait](anatomy-of-a-trait.md) shows every one of these in 1 trait, and [CONTRIBUTING.md](../../../CONTRIBUTING.md) holds them in full.

## What the layer may reach

[scripts/lint-layers.php](../../../scripts/lint-layers.php) keeps the web half free of Drupal: `src/Steps/Web`, the web helper traits, `src/Steps/Exception`, `WebRawContext` and `WebContext` reference no Drupal code. The Drupal half reaches the site only through capabilities, never through a Drupal class or Drupal's own functions. [Layers](../../../CONTRIBUTING.md#layers) lists every rule.

## Where to next

- [Anatomy of a trait](anatomy-of-a-trait.md) walks through a trait from top to bottom.
- [Extending the step vocabulary](../extending/steps.md) covers steps of your own, a trait's seams and the rules Behat applies to composition.
