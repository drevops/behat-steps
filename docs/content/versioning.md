---
title: Public API and versioning
sidebar_position: 7
description: The 5 surfaces semantic versioning covers, what sits outside them, and the exception types a failing step reports.
---

# Public API and versioning

This package follows [semantic versioning](https://semver.org), and 5 surfaces are covered by it. A breaking change to any of them waits for a major release:

- **Step text** - the pattern a scenario matches, listed in [Steps](steps.md).
- **Helpers** - every public method the step traits, the helper traits and `WebRawContext` contribute that is not a step, a hook or a transform, listed in [Helpers](helpers.md). They sit on `$this` in your own context, so a domain step depends on them exactly as a scenario depends on step text. A `protected` method is an implementation detail and carries no such promise.
- **Configuration** - the options under the `behat_steps` key and the tags, listed in [Configuration](guides/configuration.md), along with the contracts a class those options name implements: `FieldHandlerInterface` for `drupal.field_handlers`, and `PreCreateAliasInterface` or `PostCreateAliasInterface` for `drupal.creation_aliases` and `drush.creation_aliases`.
- **Exceptions** - which exception type a failure reports, listed under [Exceptions](#exceptions) below.
- **Context base classes** - `WebRawContext`, `WebContext` and `DrupalContext`, which a project extends.

2 things sit outside it: any member whose docblock carries `@internal`, and the internals of `src/Backend` and `src/Extension` that none of the surfaces above exposes.

A project adds or replaces a field handler through the `drupal.field_handlers` option, or from a context through `FieldHandlerCapabilityInterface`, and a creation alias through the `drupal.creation_aliases` and `drush.creation_aliases` options, or from a context through `CreationAliasCapabilityInterface`, rather than a subclass of `Core` or a backend: a subclass of `Core`, a backend or a field handler relies on protected methods a minor release can rename or stop calling. The same holds for a Core of your own that the `drupal.core` option names: the option is covered, and `CoreInterface` and the Core classes it builds on aren't. A release that adds a version core for the Drupal major a suite runs fails a class built on a Core of the package until it extends the new version core, as [Core](guides/configuration.md#core) describes.

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

`DrevOps\BehatSteps\Steps\Exception\AssertionException` is thrown by traits that never touch the browser, such as `Steps\Web\CommandTrait` and `Steps\Drupal\ConfigTrait`. `ExpectationException` needs a browser driver, which those traits do not have, so they report a failed assertion with this instead.

Example error messages:

```
Element matching css "#my-element" not found.
Link with title "My Link" not found.
Select with id|name|label "My Select" not found.
The cookie "session" was not set.
```
