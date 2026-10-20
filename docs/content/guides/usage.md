---
title: Usage
sidebar_position: 2
description: Configure the traits your context holds or switch their checks off, and learn what happens to the content a scenario creates.
---

# Usage

This guide covers the traits inside your context day to day: how to configure them, how to switch a trait's checks off and what happens to the content your scenarios create. [Contexts](contexts.md) covers which context to extend and where your own steps go, and if traits or backends are new to you, [Key concepts](../concepts.md) explains them first.

Your context is built out of traits, and the extension points it at your site. The extension and the context answer different questions, and each trait reads from both:

| Layer | Answers | Scope |
| --- | --- | --- |
| The extension | How the package reaches the site, and what every trait defaults to | Per profile |
| The context | Which steps a suite gets, and which of those defaults it overrides | Per context |
| The trait | What a step does, and which options change it | Per trait |

[Configuration](configuration.md) is the reference for every option and tag. This page explains how they fit together.

## Configure a trait

Each configurable trait declares its own options, grouped under the trait's name in snake case. Their profile-wide defaults live under `steps`:

```php
->withExtension(new Extension(BehatStepsExtension::class, [
  'drupal' => ['drupal_root' => 'web'],

  'steps' => [
    'wait' => ['ajax_timeout' => 10],
    'watchdog' => ['fail_on_errors' => TRUE],
  ],
]));
```

The message steps are 1 group you'll almost always set. Steps such as `I should see the success message :message` need the CSS selector your site prints that message in, and the `selectors` option of the `message` group has no default:

```php
'steps' => [
  'message' => ['selectors' => [
    'success' => '.messages.messages--status',
  ]],
],
```

It takes 1 selector per severity: `default`, `error`, `success` and `warning`.

When a value has to differ between 2 suites of the same profile, put it in the context's `config` argument instead. A context accepts the groups of the traits it holds, and `FeatureContext` extends `DrupalContext`, which holds `JavascriptTrait`, so it accepts the `javascript` group:

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(FeatureContext::class, [
    'config' => ['javascript' => ['fail_on_errors' => FALSE]],
  ]);
```

And a single scenario opts out with the tag the option declares:

```gherkin
@javascript @js-errors
Scenario: The legacy report still renders
  Given I visit "/reports/legacy"
```

All 3 set the same value, and the most specific wins: `steps`, then the context's `config`, then the feature and scenario tags. [Trait options](configuration.md#trait-options) states the full order, and a trait's page under [Web steps](../web-steps/README.md) or [Drupal steps](../drupal-steps/README.md) lists its options beside its steps.

## Switch a trait off

2 options recur across the traits that carry hooks, and they mean different things:

- `enabled` turns the trait's hooks off entirely: nothing is collected, and no backend is bootstrapped on its account.
- `fail_on_errors` leaves the collection running and stops what it collects from failing the scenario.

If you never want a trait's check, turn it off once:

```php
'steps' => ['watchdog' => ['enabled' => FALSE]],
```

That beats tagging every feature file with `@behat-steps-skip:WatchdogTrait`.

Several traits carry hooks that run around every scenario or step, and 1 tag switches off every hook of a trait for a single scenario or feature:

```gherkin
@behat-steps-skip:TRAIT
```

`TRAIT` is the name of the trait, as in `@behat-steps-skip:EmailTrait`, and the tag works on the `Feature:` line as well as the `Scenario:` line. A skip tag fails the run before the scenario starts when its value isn't shaped like a trait name, such as a hook method, or when it names a trait none of your suite's contexts compose, such as a misspelled one.

`WatchdogTrait` is the check you're most likely to meet first, because `DrupalContext` composes it. It reads the errors each scenario logged from the `watchdog` table, which only the core `dblog` module creates. It reads that table through `LogCapabilityInterface`, so it also needs a backend that provides it. Both `drupal` and `drush` do: the in-process backend queries the table, and the Drush backend runs `watchdog:show`.

Those are the trait's prerequisites, and it checks them when a scenario starts. Here's what happens with and without `dblog`:

| Watchdog check | `dblog` enabled | `dblog` not enabled |
| --- | --- | --- |
| On, the default | Reads the errors after the last step, and fails a scenario that logged one | Fails the scenario at its start, before any step runs |
| Off, through `watchdog.enabled` or `@behat-steps-skip:WatchdogTrait` | Reads nothing and bootstraps no backend | Reads nothing and bootstraps no backend |

A profile that lists no backend providing `LogCapabilityInterface`, such as `'backends' => ['blackbox']`, fails at the start the same way. The error names the prerequisite that doesn't hold and both fixes: meet it, or switch the trait off with `watchdog.enabled` or the skip tag. A scenario that uninstalls `dblog` itself starts with the table in place, so it fails at its last step instead.

Setting `fail_on_errors` to `FALSE` or tagging the scenario `@error` won't help with an unmet prerequisite. Both decide what happens to errors that were read, and without the table or a backend to read it through, there's nothing to read.

## Entities a scenario creates

### Automatic cleanup

Every entity a scenario creates through a creation step or an `entityLifecycleCreate*()` helper is registered on the context and deleted in reverse creation order at the end of the scenario, so your test database stays clean across long suites. Reverse order means a node is deleted before the term it references.

[Register what it creates](../extending/steps.md#register-what-it-creates) covers a step of your own that creates an entity itself.

To keep all entities after a scenario, add `@behat-steps-skip:EntityLifecycleTrait` to the scenario or feature. `@behat-steps-skip:AuthTrait` does the same for users and roles.

To keep only the entities of 1 type, add `@behat-steps-entity-cleanup-skip:ENTITY_TYPE_ID`, such as `@behat-steps-entity-cleanup-skip:media`. Repeat the tag to keep several types.

To keep what a whole run creates, set `BEHAT_STEPS_DISABLE_CLEANUP`, as [Environment variables](configuration.md#4-environment-variables) describes.

### Several entities with the same title

When several entities share the title, label, name or description a step names, the step acts on the newest one. If your scenario created 1 of them, that's the one it acts on, even on a site that already holds a page called "About us". If it created none, the step acts on the newest of the entities already there.

- Nodes, terms, media, content blocks, menu links and ECK entities are compared by ID, which only grows when an entity is created, so the highest ID wins. Saving an older entity again doesn't make it newer.
- Blocks and menus have machine names rather than numeric IDs, so the one the scenario created last wins. When the scenario created none of them, the machine name that comes last in natural order wins, so `block_10` beats `block_2`.
- A `... does not exist` step removes every match, and a step that only checks whether an entity exists, such as `the :content_type content with the title :title should not exist`, looks at every match.

## Where to next

For a value no option reaches, [Go further than the options](../extending/steps.md#go-further-than-the-options) shows how to override the trait's own seam in your context, and the rest of [Extending the step vocabulary](../extending/steps.md) covers steps of your own.
