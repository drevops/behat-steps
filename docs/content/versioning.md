---
title: Public API and versioning
sidebar_position: 10
description: The 5 surfaces semantic versioning covers, what sits outside them, and the exception types a failing step reports.
---

# Public API and versioning

This package follows [semantic versioning](https://semver.org), which covers 5 surfaces. A breaking change to any of them waits for a major release:

- **Step text** - the pattern a scenario matches, listed in [Web steps](web-steps/README.md) and [Drupal steps](drupal-steps/README.md).
- **Helpers** - every public method the step traits, the helper traits and `WebRawContext` contribute that isn't a step, a hook or a transform, listed in [Web helpers](web-helpers/README.md) and [Drupal helpers](drupal-helpers/README.md). They sit on `$this` in your own context, so a domain step depends on them exactly as a scenario depends on step text. A `protected` method is an implementation detail and carries no such promise.
- **Configuration** - the options under the `behat_steps` key and the tags, listed in [Configuration](guides/configuration.md), along with the contracts that a class named in those options implements: `FieldHandlerInterface` for `drupal.field_handlers`, and `PreCreateAliasInterface` or `PostCreateAliasInterface` for `drupal.creation_aliases` and `drush.creation_aliases`.
- **Exceptions** - which exception type a failure reports, listed under [Exceptions](#exceptions) below.
- **Context base classes** - `WebRawContext`, `WebContext` and `DrupalContext`, which a project extends.

Semantic versioning doesn't cover 2 things: any member whose docblock carries `@internal`, and the internals of `src/Backend` and `src/Extension` that none of the surfaces above exposes.

To add or replace a field handler, use the `drupal.field_handlers` option, or do it from a context through `FieldHandlerCapabilityInterface`. For a creation alias, use the `drupal.creation_aliases` and `drush.creation_aliases` options, or do it from a context through `CreationAliasCapabilityInterface`. Don't subclass `Core` or a backend for either: a subclass of `Core`, a backend or a field handler relies on protected methods that a minor release can rename or stop calling.

The same goes for a Core of your own that the `drupal.core` option names: semantic versioning covers the option, but not `CoreInterface` or the Core classes your class builds on. When a release adds a version core for the Drupal major your suite runs on, a class built on a Core of the package fails until it extends the new version core, as [Core](extending/backend.md#core) describes.

## Exceptions

This library reports failures with a small, fixed set of exception types, mostly [Mink's](https://mink.behat.org/en/latest/):

| Exception                           | When thrown                                                        |
|-------------------------------------|--------------------------------------------------------------------|
| `ElementNotFoundException`          | An element on the page or in an XML response is not found          |
| `ExpectationException`              | Assertion fails (value mismatch, state verification)               |
| `AssertionException`                | Assertion fails in a step with no Mink session                     |
| `UnsupportedDriverActionException`  | The browser driver lacks a capability the step needs               |
| `UnsupportedBackendActionException` | No backend the scenario lists provides a capability the step needs |
| `\RuntimeException`                 | Invalid input or processing error (not an assertion)               |

`ElementNotFoundException` extends `ExpectationException`, so catching `ExpectationException` covers both. An attribute, a JSON path or a table column isn't an element, so a missing one throws `ExpectationException`.

Traits that never touch the browser, such as `Steps\Web\CommandTrait` and `Steps\Drupal\ConfigTrait`, throw `DrevOps\BehatSteps\Steps\Exception\AssertionException` when an assertion fails, because `ExpectationException` needs a browser driver, which those traits don't have.

Example error messages:

```
Element matching css "#my-element" not found.
Link with title "My Link" not found.
Select with id|name|label "My Select" not found.
The cookie "session" was not set.
```

## Where to next

- [Extending](extending/README.md#what-semantic-versioning-covers) goes through each extension point and what semantic versioning covers for it.
- [Supported versions](README.md#supported-versions) lists which majors still receive fixes, and when 3.x LTS ends.
- [Migrating from 3.x](migrating.md) covers the move to 4.0.
