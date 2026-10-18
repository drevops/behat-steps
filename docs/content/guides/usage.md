---
title: Usage
sidebar_position: 1
description: The extension-plus-context model - composing a context, registering it, configuring the traits it holds and switching their hooks off, and what happens to the entities a scenario creates.
---

# Usage

A project uses this package by composing a context out of traits and pointing an extension at the site. The extension and the context answer different questions, and a trait reads from both.

| Layer | Answers | Scope |
| --- | --- | --- |
| The extension | How the package reaches the site, and what every trait defaults to | Per profile |
| The context | Which vocabulary a suite gets, and which of those defaults it overrides | Per context |
| The trait | What a step does, and which options change it | Per trait |

[Configuration](configuration.md) is the reference for every option and tag. This page explains the model those options sit in.

## The 3 entry points

The vocabulary sits on a single chain. Each entry point adds what's listed under its name to everything above it, and a consumer extends exactly 1 of them:

```
        Behat\MinkExtension\Context\RawMinkContext
                          |
                    WebRawContext
      backend access, configuration, hook dispatch,
               3 web helper traits, no steps
                          |
                     WebContext
                use Steps\Web\*  (28)
                          |
                   DrupalContext
                use Steps\Drupal\*  (29)
```

| Extend | When |
| --- | --- |
| `DrupalContext` | The suite tests a Drupal site and wants all 57 step traits. The Watchdog check needs the core `dblog` module and a backend such as `drupal`, or it's [switched off](#switch-a-trait-off) |
| `WebContext` | The suite tests a web page and wants the 28 web step traits |
| `WebRawContext` | The project picks its own traits; each one brings the helpers it needs |

`WebContext` composes every trait under `Steps\Web`, and `DrupalContext` every trait under `Steps\Drupal` on top of it. A trait that could fail a scenario for a reason the scenario didn't ask about carries an `enabled` option, so a project switches it off through configuration instead of composing its own context.

```php
<?php

use DrevOps\BehatSteps\DrupalContext;

class FeatureContext extends DrupalContext {}
```

## Register 1 context, not 2

`DrupalContext` extends `WebContext`, so a suite registering both would register the 28 web traits twice and die on a `RedundantStepException` naming an arbitrary step. `WebContext::assertOneContext()` runs on `BeforeSuite` and reports the real mistake instead. Register the class lowest in the chain and drop the rest:

```php
$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(FeatureContext::class)
  ->addContext(MinkContext::class);
```

A Drupal suite needs `DrupalContext` alone: `I visit` and the `{{ }}`, `[?...]` and `[relative:...]` transforms come with it, because `PathTrait`, `RandomTrait`, `MappingTrait` and `DateTrait` are inherited from `WebContext`.

The chain has 1 cost: there's no way to remove an inherited step, so a Drupal project can't take the Drupal step traits without the 28 web ones. A project whose own step text collides with a shipped web step drops to `WebRawContext` and composes what it wants by hand.

## Compose your own context

A context of your own is `WebRawContext` plus the traits whose steps the suite needs.

```php
<?php

use DrevOps\BehatSteps\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\JavascriptTrait;
use DrevOps\BehatSteps\Steps\Web\WaitTrait;

class UiContext extends WebRawContext {

  use JavascriptTrait;
  use WaitTrait;

}
```

A context that adds steps of its own builds them on the helpers, as [Writing a step of your own](../extending/steps.md#writing-a-step-of-your-own) describes.

[The rules that govern composition](../extending/steps.md#the-rules-that-govern-composition) cover what Behat does when a context re-composes a trait or overrides an inherited step or hook.

## Register it against the extension

1 profile carries the extension; each suite carries its contexts.

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(UiContext::class);

$profile = (new Profile('default'))
  ->withSuite($ui)
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'backends' => ['drupal', 'blackbox'],
    'drupal' => ['drupal_root' => 'web'],
  ]));
```

## Configure a trait

Each configurable trait declares its own options, named after the trait in snake case. Their profile-wide defaults live under `steps`:

```php
->withExtension(new Extension(BehatStepsExtension::class, [
  'drupal' => ['drupal_root' => 'web'],

  'steps' => [
    'wait' => ['ajax_timeout' => 10],
    'watchdog' => ['fail_on_errors' => TRUE],
  ],
]));
```

A value that has to differ between 2 suites of the same profile goes in the context's `config` argument instead. `UiContext` above composes `JavascriptTrait`, so it's the context that accepts the `javascript` group:

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(UiContext::class, [
    'config' => ['javascript' => ['fail_on_errors' => FALSE]],
  ]);
```

And a single scenario opts out with the tag the option declares:

```gherkin
@javascript @js-errors
Scenario: The legacy report still renders
  Given I visit "/reports/legacy"
```

The 3 reach the same value, most specific last: `steps`, then the context's `config`, then the feature and scenario tags. [Trait options](configuration.md#trait-options) states the full chain, and a trait's page under [Web steps](../web-steps/README.md) or [Drupal steps](../drupal-steps/README.md) lists its options beside its steps.

## Switch a trait off

2 options recur across the traits that carry hooks, and they mean different things:

- `enabled` turns the trait's hooks off entirely: nothing is collected, and no backend is bootstrapped on its account.
- `fail_on_errors` leaves the collection running and stops what it collects from failing the scenario.

A project that never wants a trait's gate turns it off once:

```php
'steps' => ['watchdog' => ['enabled' => FALSE]],
```

rather than tagging every feature file with `@behat-steps-skip:WatchdogTrait`.

Several traits carry hooks that run around every scenario or step, and 1 tag switches off every hook of a trait for a single scenario or feature:

```gherkin
@behat-steps-skip:TRAIT
```

`TRAIT` is the name of the trait, as in `@behat-steps-skip:EmailTrait`, and the tag works on the `Feature:` line as well as the `Scenario:` line. A skip tag fails the run before the scenario starts when its value isn't shaped like a trait name, such as a hook method, or when it names a trait none of your suite's contexts compose, such as a misspelled one.

`WatchdogTrait` is the gate you're most likely to meet first, because `DrupalContext` composes it. It reads the errors each scenario logged from the `watchdog` table, which only the core `dblog` module creates. Since it reads that table in the Behat process, it also needs a backend that loads Drupal there, such as `drupal`.

Those are the trait's prerequisites, and it checks them when a scenario starts. Here's what happens with and without `dblog`:

| Watchdog check | `dblog` enabled | `dblog` not enabled |
| --- | --- | --- |
| On, the default | Reads the errors after the last step, and fails a scenario that logged one | Fails the scenario at its start, before any step runs |
| Off, through `watchdog.enabled` or `@behat-steps-skip:WatchdogTrait` | Reads nothing and bootstraps no backend | Reads nothing and bootstraps no backend |

A profile that lists no backend loading Drupal into the Behat process, such as `'backends' => ['drush', 'blackbox']`, fails at the start the same way. The error names the prerequisite that doesn't hold and both ways out: meet it, or switch the trait off as above. A scenario that uninstalls `dblog` itself starts with the table in place, so it fails at its last step instead.

Setting `fail_on_errors` to `FALSE` or tagging the scenario `@error` won't help with an unmet prerequisite. Both decide what happens to errors that were read, and without the table or a backend to read it through, there's nothing to read.

## Entities a scenario creates

### Automatic cleanup

Every entity a scenario creates through a creation step or an `entityLifecycleCreate*()` helper is registered on the context and deleted in reverse creation order at the end of the scenario, so the test database stays clean across long suites. Reverse order means a node comes down before the term it references.

[Register what it creates](../extending/steps.md#register-what-it-creates) covers a step of your own that creates an entity itself.

To keep all entities after a scenario, add `@behat-steps-skip:EntityLifecycleTrait` to the scenario or feature. `@behat-steps-skip:AuthTrait` does the same for users and roles.

To keep only the entities of 1 type, add `@behat-steps-entity-cleanup-skip:ENTITY_TYPE_ID` (for example `@behat-steps-entity-cleanup-skip:media`). Repeat the tag to keep several types.

To keep what a whole run creates, set `BEHAT_STEPS_DISABLE_CLEANUP`, as [Environment variables](configuration.md#4-environment-variables) describes.

### Several entities with the same title

When several entities share the title, label, name or description a step names, the step acts on the newest one. That's the entity the scenario just created, even on a site that already holds a page called "About us".

- Nodes, terms, media, content blocks, menu links and ECK entities are compared by ID, which only grows when an entity is created, so the highest ID wins. Saving an older entity again doesn't make it newer.
- Blocks and menus have machine names rather than numeric IDs, so the one the scenario created last wins. When the scenario created none of them, the machine name that comes last in natural order wins, so `block_10` beats `block_2`.
- A `... does not exist` step removes every match, and a step that only checks whether an entity exists, such as `the :content_type content with the title :title should not exist`, looks at every match.

## Where to next

For a value no option reaches, [Go further than the options](../extending/steps.md#go-further-than-the-options) shows how to override the trait's own seam in the composing context, and the rest of [Extending the step vocabulary](../extending/steps.md) covers steps of your own.
