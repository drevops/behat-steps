---
title: Extension layer
sidebar_position: 3
description: What BehatStepsExtension plugs into Behat and Mink, the services it hands your context, and how a trait gets options without a constructor.
---

# Extension layer

The extension layer, `src/Extension`, connects Behat Steps to Behat and Mink. `BehatStepsExtension` reads the `behat_steps` settings in your `behat.php` and registers the backends. It also plugs a small piece into each place where Behat and Mink let an extension in. Select the diagram to zoom in.

[![Extension layer: the extension points of Behat and Mink, what BehatStepsExtension registers into each one and the services it hands to your context](extension-layer.svg#gh-light-mode-only)](extension-layer.svg)
[![Extension layer: the extension points of Behat and Mink, what BehatStepsExtension registers into each one and the services it hands to your context](extension-layer.dark.svg#gh-dark-mode-only)](extension-layer.dark.svg)

## What it plugs in

Each entry names a place where Behat or Mink lets an extension in, then the piece Behat Steps adds there:

- Configuration: `BehatStepsExtension::configure()` declares the `behat_steps` key. It holds `backends`, `regions`, `steps` and `blackbox`, plus 4 login settings: `login_field`, `login_wait`, `text` and `selectors`. `DrupalBackendProvider` adds the `drupal` and `drush` settings.
- Context initializers: `BackendAwareInitializer` hands each context Behat builds on `WebRawContext` the services that [What your context receives](#what-your-context-receives) lists.
- Event dispatcher: `SkipTagListener` and then `BackendListener` run as each scenario starts. The first rejects a `@behat-steps-skip:` tag that names no composed trait, and the second sets the backend order and publishes the scenario's tags.
- Attribute readers: `HookAttributeReader` turns `#[BeforeNodeCreate]` and the other creation hook attributes into hooks.
- Class generator: `ClassGenerator` writes the starter context of `behat --init`, which extends `WebRawContext`.
- Mink's browser driver factories: `BrowserKitFactory` builds `browserkit_http` sessions exactly as Mink does, and records their `http_client_parameters` for the HTTP clients.
- Mink's selectors: `RegionSelector` adds the `region` selector, which turns a region name from `regions` into its CSS.
- Mink's `DocumentElement`: a `DocumentElement` of Behat Steps' own takes its place, so the page text a step reads matches what a browser renders.

## What your context receives

`WebRawContext` implements `BackendAwareInterface`. `BackendAwareInitializer` hands these services to `WebRawContext`, and to every context built on it, through their `set*()` methods:

- `BackendRegistry`, which holds the backends and each scenario's order, and resolves each `backendFor()` call.
- `TraitOptionResolverFactory`, which builds the context's option resolver.
- `HttpClientFactory`, which builds the detached and bare HTTP clients and the default REST session on 1 transport carrying your `browserkit_http` connection settings.
- The hook dispatcher, the basic authenticator and the extension's parameters.

`DrupalContext` also implements `UserAwareInterface`, so it gets the user registry and the authenticator its login steps need. Each service the extension builds sits behind an interface with 1 implementation, and [Extending the extension layer](../extending/extension.md) shows how to put a class of your own in its place.

## How a trait gets options without a constructor

A trait can't have a constructor of its own, and Behat builds a fresh context for every scenario, so a trait has no obvious place to receive its settings. Behat Steps solves this in 4 parts:

1. The trait declares its options in a method named after its prefix, `<prefix>ConfigSchema()`. The method returns `Option` objects, each with a name, a default and a description, plus any tags that set the option for 1 scenario. `WaitTrait` declares `enabled` and `ajax_timeout` in `waitConfigSchema()`. The default carries the option's type, so `5` makes `ajax_timeout` an integer.
2. `ConfigSchemaReader` finds the declarations. It looks through every method of the context's class, including the methods of its traits, for names ending in `ConfigSchema`, and calls each of them. The group is the method's prefix in snake case, so `waitConfigSchema()` declares the `wait` group. Nothing registers a trait anywhere, so a trait of your own takes part the same way.
3. The resolver settles each value. `TraitOptionResolver` starts from the declared default and applies the extension's `steps` settings, then the context's own `config` argument. As each scenario runs, it then applies the tags of the feature and of the scenario itself:
   - In `config`, a group or an option no trait declares fails while Behat builds the context, before any step runs. So does a value of the wrong type.
   - In `steps`, a group or an option the context's traits don't declare is skipped, so 1 profile can carry the defaults of every suite. A value of the wrong type fails when the context first reads its options.
4. The trait reads a value through a method `WebRawContext` provides, typed to the declared default, such as `$this->getOptionInt('wait', 'ajax_timeout')`. It reads each option in a public `<prefix>Get...()` method, `waitGetAjaxTimeout()` here, so a project can override that method when no option reaches far enough.

The trait stays a plain trait that holds steps, hooks and helpers. The context that composes it carries the settings. Every context that composes it offers the same options. The trait names `WebRawContext` as the class it needs with `@phpstan-require-extends`, so it can call `getOptionInt()` without declaring it. [Configure a trait](../guides/usage.md#configure-a-trait) shows the 3 places you set a value, and [Anatomy of a trait](anatomy-of-a-trait.md) shows a trait of its own that declares options.

## Prerequisites, browsers and HTTP clients

The layer also holds these pieces:

- `Prerequisite` and `PrerequisiteReader` let a trait declare what it needs from the site in a `<prefix>Prerequisites()` method. `WebRawContext::assertPrerequisites()` checks the declaration and fails the scenario with a message that names what's missing.
- `Extension\Browser` holds the browser capabilities, 1 adapter per browser driver and the resolver behind `browserDriverFor()`. [Browsers](../guides/browsers.md) compares what each browser driver provides.
- `Extension\Http` holds the factory behind the detached and bare clients and the default REST session, which [HTTP clients](../guides/http-clients.md) covers.

## The Drupal half

`Extension\Drupal` holds what the extension needs for a Drupal site. `DrupalBackendProvider` adds the `drupal` and `drush` settings and the Drupal defaults of the login text and selectors. It also adds the Drupal and Drush backends, each registered only when its `drupal` or `drush` key is set. The node and term creation hooks and the language creation scopes sit beside it.

## What the layer may reach

[scripts/lint-layers.php](../../../scripts/lint-layers.php) keeps `src/Extension` free of step code and of the 3 contexts, apart from `ClassGenerator`, whose template names `WebRawContext`. The root of the layer references no Drupal code, apart from `BehatStepsExtension`, which lists the backend providers it ships. [Layers](../../../CONTRIBUTING.md#layers) lists every rule.

## Where to next

- [Steps layer](steps-layer.md) covers the traits that call these services.
- [Data flow](data-flow.md) shows when each piece runs.
