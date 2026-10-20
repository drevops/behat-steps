---
title: Extension layer
sidebar_position: 3
description: What BehatStepsExtension plugs into Behat and Mink, the services it hands your context, and how a trait gets options without a constructor.
---

# Extension layer

The extension layer, `src/Extension`, connects Behat Steps to Behat and Mink. `BehatStepsExtension` reads the `behat_steps` settings in your `behat.php`, registers the backends, and plugs a small piece into each place where Behat and Mink let an extension in. Select the diagram to open it at full size.

[![Extension layer: the extension points of Behat and Mink, what BehatStepsExtension registers into each one, and the services it hands to your context](extension-layer.svg#gh-light-mode-only)](extension-layer.svg)
[![Extension layer: the extension points of Behat and Mink, what BehatStepsExtension registers into each one, and the services it hands to your context](extension-layer.dark.svg#gh-dark-mode-only)](extension-layer.dark.svg)

## What it plugs in

Each entry names a place where Behat or Mink lets an extension in, then the piece Behat Steps adds there:

- Configuration: `BehatStepsExtension::configure()` declares the `behat_steps` key, with `backends`, `regions`, the login text and selectors, `steps`, and the `drupal` and `drush` settings that `DrupalBackendProvider` adds.
- Context initializers: `BackendAwareInitializer` hands every context Behat builds the services below.
- Event dispatcher: `SkipTagListener` and then `BackendListener` run as each scenario starts. The first rejects a `@behat-steps-skip:` tag that names no composed trait, and the second sets the backend order and publishes the scenario's tags.
- Attribute readers: `HookAttributeReader` turns `#[BeforeNodeCreate]` and the other creation hook attributes into hooks.
- Class generator: `ClassGenerator` writes the starter context of `behat --init`, which extends `WebRawContext`.
- Mink's browser driver factories: `BrowserKitFactory` builds `browserkit_http` sessions exactly as Mink does, and records their `http_client_parameters` for the HTTP clients.
- Mink's selectors: `RegionSelector` adds the `region` selector, which turns a region name from `regions` into its CSS.
- Mink's `DocumentElement`: a `DocumentElement` of Behat Steps' own takes its place, so page text reads the way a browser renders it.

## What your context receives

`WebRawContext` implements `BackendAwareInterface`, so `BackendAwareInitializer` hands it, and every context built on it, these services through its `set*()` methods:

- `BackendRegistry`, which holds the backends and each scenario's order, and answers `backendFor()`.
- `TraitOptionResolverFactory`, which builds the context's option resolver.
- `HttpClientFactory`, which builds the detached and bare HTTP clients on 1 transport carrying your `browserkit_http` connection settings.
- The hook dispatcher, the basic authenticator and the extension's parameters.

`DrupalContext` also implements `UserAwareInterface`, so it gets the user registry and the authenticator its login steps need. Each service sits behind an interface with 1 implementation, and [Extending the extension layer](../extending/extension.md) shows how to put a class of your own in its place.

## How a trait gets options without a constructor

A trait can't have a constructor of its own, and Behat builds a fresh context for every scenario, so a trait has no obvious place to receive its settings. Behat Steps gives it 1 in 4 parts:

1. The trait declares its options in a method named after its prefix, `<prefix>ConfigSchema()`, which returns `Option` objects: a name, a default and a description, plus any tags that set the option for 1 scenario. `WaitTrait` declares `enabled` and `ajax_timeout` in `waitConfigSchema()`. The default carries the option's type, so `5` makes `ajax_timeout` an integer.
2. The context finds the declarations. `ConfigSchemaReader` looks through every method of the context's class, its traits' included, for names ending in `ConfigSchema`, and calls each 1. The group is the method's prefix in snake case, so `waitConfigSchema()` declares the `wait` group. Nothing registers a trait anywhere, which is why a trait of your own takes part the same way.
3. The resolver settles each value. Starting from the declared default, `TraitOptionResolver` applies the extension's `steps` settings, then the context's own `config` argument, and then, as each scenario runs, the tags of its feature and of the scenario itself. In `config`, a group or an option no trait declares fails while Behat builds the context, before any step runs, and so does a value of the wrong type. In `steps`, a group or an option the context's traits don't declare is skipped, so 1 profile can carry the defaults of every suite, while a value of the wrong type fails as soon as the context reads its options.
4. The trait reads a value through a method `WebRawContext` provides, typed to the declared default, such as `$this->getOptionInt('wait', 'ajax_timeout')`. It reads each option in a public `<prefix>Get...()` method, `waitGetAjaxTimeout()` here, so a project can override that method when no option reaches far enough.

The trait stays a plain trait: it holds steps, hooks and helpers, the context it lands in carries the settings, and every context that composes it offers the same options. The trait names `WebRawContext` as the class it needs with `@phpstan-require-extends`, which is how it relies on `getOptionInt()` without declaring it. [Configure a trait](../guides/usage.md#configure-a-trait) shows the 3 places you set a value, and [Anatomy of a trait](anatomy-of-a-trait.md) declares options in a trait of its own.

## Prerequisites, browsers and HTTP clients

- `Prerequisite` and `PrerequisiteReader` let a trait declare what it needs from the site, in a `<prefix>Prerequisites()` method, and `WebRawContext::assertPrerequisites()` checks it, failing the scenario with a message that names what's missing.
- `Extension\Browser` holds the browser capabilities, 1 adapter per browser driver and the resolver behind `browserDriverFor()`. [Browsers](../guides/browsers.md) compares what each browser driver provides.
- `Extension\Http` holds the factory behind the detached and bare clients, which [HTTP clients](../guides/http-clients.md) covers.

## The Drupal half

`Extension\Drupal` holds what the extension needs for a Drupal site. `DrupalBackendProvider` adds the `drupal` and `drush` settings, the Drupal login text and selector defaults, and the 2 backends, each registered only when its key is set. The node, term and language creation hooks live beside it.

## What the layer may reach

[scripts/lint-layers.php](../../../scripts/lint-layers.php) keeps `src/Extension` free of step code and of the 3 contexts, apart from `ClassGenerator`, whose template names `WebRawContext`. The root of the layer references no Drupal code, apart from `BehatStepsExtension`, which lists the backend providers it ships. [Layers](../../../CONTRIBUTING.md#layers) lists every rule.

## Where to next

- [Steps layer](steps-layer.md) covers the traits that call these services.
- [Data flow](data-flow.md) shows when each piece runs.
