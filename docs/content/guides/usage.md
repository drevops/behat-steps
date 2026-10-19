---
title: Usage
sidebar_position: 1
description: The extension-plus-context model - composing a context, registering it, configuring the traits it holds and switching their hooks off, and what happens to the entities a scenario creates.
---

# Usage

You use this package in 2 parts: a context composed out of traits, and an extension pointed at your site. They answer different questions, and a trait reads from both.

| Layer | Answers | Scope |
| --- | --- | --- |
| The extension | How the package reaches the site, and what every trait defaults to | Per profile |
| The context | Which vocabulary a suite gets, and which of those defaults it overrides | Per context |
| The trait | What a step does, and which options change it | Per trait |

[Configuration](configuration.md) is the reference for every extension option and tag, and a trait's own page lists the trait's options. This page explains the model those options sit in.

## The 3 entry points

The steps sit on a single chain of contexts. Each entry point adds what's listed under its name to everything above it, and you extend exactly 1 of them:

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
| `DrupalContext` | The suite tests a Drupal site and wants all 57 step traits. The Watchdog check needs the core `dblog` module and a backend that reads the log, such as `drupal` or `drush`, or it's [switched off](#switch-a-trait-off) |
| `WebContext` | The suite tests a web page and wants the 28 web step traits |
| `WebRawContext` | The project picks its own traits; each one brings the helpers it needs |

`WebContext` composes every trait under `Steps\Web`, and `DrupalContext` adds every trait under `Steps\Drupal` on top of it. Some traits can fail a scenario for a reason the scenario never asked about, such as a PHP error in the site's log. Each of those carries an `enabled` option, so you switch it off in configuration instead of composing a context of your own.

```php
<?php

use DrevOps\BehatSteps\DrupalContext;

class FeatureContext extends DrupalContext {}
```

## Register 1 context, not 2

`DrupalContext` extends `WebContext`, so a suite registering both would register the 28 web traits twice and die on a `RedundantStepException` that names an arbitrary step. `WebContext::assertOneContext()` runs on `BeforeSuite` and reports the real mistake instead. Register the class lowest in the chain, and drop the rest:

```php
$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(FeatureContext::class)
  ->addContext(MinkContext::class);
```

A Drupal suite needs `DrupalContext` alone: `I visit` and the `{{ }}`, `[?...]` and `[relative:...]` transforms come with it, because `PathTrait`, `RandomTrait`, `MappingTrait` and `DateTrait` are inherited from `WebContext`.

The chain has 1 cost. There's no way to remove an inherited step, so you can't take the Drupal step traits without the 28 web ones. If a step of your own collides with a shipped web step, drop to `WebRawContext` and compose what you want by hand.

## Compose your own context

A context of your own is `WebRawContext` plus the traits whose steps your suite needs.

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

To add steps of your own, build them on the helpers, as [Writing a step of your own](../extending/steps.md#writing-a-step-of-your-own) describes.

[The rules that govern composition](../extending/steps.md#the-rules-that-govern-composition) cover what Behat does when a context re-composes a trait or overrides an inherited step or hook.

## Register it against the extension

The profile carries the extension, and each suite carries its own contexts:

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

Each configurable trait declares its own options, in a group named after the trait in snake case: `WaitTrait` reads `wait`. Their profile-wide defaults live under `steps`:

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

And a single scenario opts out with the tag the option declares, here `@js-errors`:

```gherkin
@javascript @js-errors
Scenario: The legacy report still renders
  Given I visit "/reports/legacy"
```

All 3 set the same value, and the most specific wins: `steps`, then the context's `config`, then the feature and scenario tags. [Trait options](configuration.md#trait-options) states the full chain, and a trait's page under [Web steps](../web-steps/README.md) or [Drupal steps](../drupal-steps/README.md) lists its options beside its steps.

## Switch a trait off

2 options recur across the traits that carry hooks, and they mean different things:

- `enabled` set to `FALSE` turns the trait's hooks off: nothing is collected, and no backend is bootstrapped on its account.
- `fail_on_errors` set to `FALSE` leaves the collection running and stops what it collects from failing the scenario.

If you never want a trait's gate, turn it off once in the profile instead of tagging every feature file with `@behat-steps-skip:WatchdogTrait`:

```php
'steps' => ['watchdog' => ['enabled' => FALSE]],
```

Several traits carry hooks that run around every scenario or step, and 1 tag switches a trait's hooks off for a single scenario or feature:

```gherkin
@behat-steps-skip:TRAIT
```

`TRAIT` is the trait's name, as in `@behat-steps-skip:EmailTrait`, and the tag works on the `Feature:` line as well as the `Scenario:` line. A typo can't slip through quietly: the run fails before the scenario starts when the value isn't shaped like a trait name, such as a hook method, or names a trait none of your suite's contexts compose, such as a misspelled one.

2 kinds of hook ignore the tag, because they have nothing to switch off. A hook that only resets the trait's own state still runs, and so does a hook that acts only on the trait's own tag, such as `ResponsiveTrait`'s on `@breakpoint:`: removing that tag is what switches it off.

`WatchdogTrait` is the gate you're most likely to meet first, because `DrupalContext` composes it. It reads the errors each scenario logged from the `watchdog` table, which only the core `dblog` module creates. It reads that table through `LogCapabilityInterface`, so it also needs a backend that provides it. Both `drupal` and `drush` do: the in-process backend queries the table, and the Drush backend runs `watchdog:show`.

Those are the trait's prerequisites, and it checks them when a scenario starts. Here's what happens with and without `dblog`:

| Watchdog check | `dblog` enabled | `dblog` not enabled |
| --- | --- | --- |
| On, the default | Reads the errors after the last step, and fails a scenario that logged one | Fails the scenario at its start, before any step runs |
| Off, through `watchdog.enabled` or `@behat-steps-skip:WatchdogTrait` | Reads nothing and bootstraps no backend | Reads nothing and bootstraps no backend |

A profile that lists no backend providing `LogCapabilityInterface`, such as `'backends' => ['blackbox']`, fails at the start the same way. The error names the prerequisite that doesn't hold and both ways out: meet it, or switch the trait off as above. A scenario that uninstalls `dblog` itself starts with the table in place, so it fails at its last step instead.

Setting `fail_on_errors` to `FALSE` or tagging the scenario `@error` won't help with an unmet prerequisite. Both decide what happens to errors that were read, and without the table or a backend to read it through, there's nothing to read.

## Entities a scenario creates

### Automatic cleanup

Every entity a scenario creates through a creation step or an `entityLifecycleCreate*()` helper is registered on the context. When the scenario ends, they're deleted in reverse creation order, so the test database stays clean across long suites. Reverse order means a node comes down before the term it references.

[Register what it creates](../extending/steps.md#register-what-it-creates) covers a step of your own that creates an entity itself.

To keep all entities after a scenario, add `@behat-steps-skip:EntityLifecycleTrait` to the scenario or feature. `@behat-steps-skip:AuthTrait` does the same for users and roles.

To keep only the entities of 1 type, add `@behat-steps-entity-cleanup-skip:ENTITY_TYPE_ID` (for example `@behat-steps-entity-cleanup-skip:media`). Repeat the tag to keep several types.

To keep what a whole run creates, set `BEHAT_STEPS_DISABLE_CLEANUP`, as [Environment variables](configuration.md#4-environment-variables) describes.

### Several entities with the same title

When several entities share the title, label, name or description a step names, the step acts on the newest one. That's the entity the scenario just created, even on a site that already holds a page called "About us".

- Nodes, terms, users, media, content blocks, menu links and ECK entities are compared by ID, which only grows when an entity is created, so the highest ID wins. Saving an older entity again doesn't make it newer.
- Blocks, menus and webform templates have machine names rather than numeric IDs, so the one the scenario created last wins. When the scenario created none of them, the machine name that comes last in natural order wins, so `block_10` beats `block_2`.
- A `... does not exist` step removes every match, and a step that only checks whether an entity exists, such as `the :content_type content with the title :title should not exist`, looks at every match.
- 2 lookups inside the creation steps take the first match instead. An entity reference field given a label takes the first entity the lookup returns, which isn't necessarily the newest, and a vocabulary that `the following :vocabulary terms exist:` names by its label resolves to the machine name that comes first in natural order. Give the referenced entity's ID or the vocabulary's machine name to pick a specific one.

## Where to next

For a value no option reaches, [Go further than the options](../extending/steps.md#go-further-than-the-options) shows how to override the trait's own seam in your context. The rest of [Extending the step vocabulary](../extending/steps.md) covers steps of your own.
