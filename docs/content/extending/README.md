---
title: Extending
sidebar_position: 7
description: What each of the 3 layers lets a project extend, which page covers it, and what semantic versioning covers for each extension point.
---

# Extending

Behat Steps ships 3 layers, and each one takes code of your own where the shipped behavior stops short of what your suite needs. This section has 1 page per layer, and [Logical architecture](../architecture/logical-architecture.md) shows how the layers stack.

| Layer | What you extend | Page |
| --- | --- | --- |
| The step vocabulary: `src/Steps` and the 3 contexts | Steps of your own, a trait's seams, a context's HTTP client, options for a trait of your own, and inherited steps and hooks | [Extending the step vocabulary](steps.md) |
| The extension layer: `src/Extension` | The HTTP client factory, browser adapters, the registries and authenticators, and option resolution | [Extending the extension layer](extension.md) |
| The backend layer: `src/Backend` | Field handlers, creation aliases, the Core of the `drupal` backend, and a backend of your own | [Extending the backend layer](backend.md) |

## What semantic versioning covers

[Public API and versioning](../versioning.md) lists the 5 surfaces semantic versioning covers. It doesn't cover the internals of `src/Extension` and `src/Backend` that none of them exposes, so a minor release can change those, and several extension points reach into them:

| Extension point | What semantic versioning covers |
| --- | --- |
| [A step of your own](steps.md#writing-a-step-of-your-own) | The helpers it calls, such as `entityLifecycleCreateNode()`, `entityLifecycleRegister()` and `httpDetachedClient()` |
| [A trait's seam](steps.md#go-further-than-the-options) | The seam itself, a public `<trait>Get<Noun>()` method that the trait's helpers page lists, such as `modalGetSelectors()` on [Modals](../web-helpers/modal.md) |
| [A context's HTTP client](steps.md#a-context-override-a-flaky-staging-server) | `getHttpClientFactory()`, a helper |
| [Options for a trait of your own](steps.md#declare-options-for-a-trait-of-your-own) | `getOption()` and the typed readers such as `getOptionBool()`, which are helpers. `Option` and the `<prefix>ConfigSchema()` method name sit on none of the 5 surfaces, so they aren't covered |
| [Inherited steps and hooks](steps.md#the-rules-that-govern-composition) | The step text and the 3 context base classes. The rules in the table there are Behat's own |
| [The HTTP client factory](extension.md#the-http-client-factory-and-browser-adapters) | `getHttpClientFactory()`. The `behat_steps.http_client_factory` service, the arguments the container passes it and the protected methods of `HttpClientFactory` aren't covered |
| [A browser adapter](extension.md#the-http-client-factory-and-browser-adapters) | `getBrowserCapabilityResolver()`, a helper |
| [A registry or an authenticator](extension.md#swap-a-registry-or-an-authenticator) | The helpers that hand them to a context: `getBackendRegistry()`, `getBasicAuthenticator()`, `authGetAuthenticator()` and `authGetUserRegistry()`. The `*.class` parameters and the arguments the container passes aren't covered |
| [Option resolution](extension.md#replace-option-resolution) | `getOptionResolverFactory()`, a helper. The `behat_steps.config.resolver_factory` service isn't covered |
| [Field handlers](backend.md#field-handlers) | The `drupal.field_handlers` option, `FieldHandlerCapabilityInterface` and `FieldHandlerInterface`. The shipped handlers' protected methods aren't covered |
| [Creation aliases](backend.md#creation-aliases) | The `drupal.creation_aliases` and `drush.creation_aliases` options, `CreationAliasCapabilityInterface`, `CreationAliasInterface`, `PreCreateAliasInterface` and `PostCreateAliasInterface` |
| [A Core of your own](backend.md#core) | The `drupal.core` option. `CoreInterface` and the Core classes aren't covered |
| [A backend of your own](backend.md#a-backend-of-your-own) | The `backends` option, and the `backendFor()` and `getBackend()` helpers. The `behat_steps.backend` service tag isn't covered, and neither is any capability interface that no helper's signature names |

## Where to next

- [Extending the step vocabulary](steps.md) is the place to start for a first step of your own.
- [Public API and versioning](../versioning.md) lists the 5 surfaces in full, with the exception types a failing step reports.
