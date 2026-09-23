# Usage

A project uses this package by composing a context out of traits and pointing an extension at the site. The extension and the context answer different questions, and a trait reads from both.

| Layer | Answers | Scope |
| --- | --- | --- |
| The extension | How the package reaches the site, and what every trait defaults to | Per profile |
| The context | Which vocabulary a suite gets, and which of those defaults it overrides | Per context |
| The trait | What a step does, and which options change it | Per trait |

[Configuration](configuration.md) is the reference for every option and tag. This page is the model those options sit in.

## Compose a context

A context is `RawContext` plus the traits whose steps the suite needs. `RawContext` registers no steps: it owns the scenario lifecycle - driver access, authentication, entity creation and cleanup.

```php
<?php

use DrevOps\BehatSteps\Behat\Context\RawContext;
use DrevOps\BehatSteps\Steps\Drupal\WatchdogTrait;
use DrevOps\BehatSteps\Steps\Generic\JavascriptTrait;
use DrevOps\BehatSteps\Steps\Generic\WaitTrait;

class UiContext extends RawContext {

  use JavascriptTrait;
  use WaitTrait;
  use WatchdogTrait;

}
```

For a suite that needs no PHP at all, register `DrupalContext`, which is `RawContext` plus a curated set of traits.

## Register it against the extension

One profile carries the extension; each suite carries its contexts.

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(UiContext::class);

$profile = (new Profile('default'))
  ->withSuite($ui)
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'drivers' => ['drupal', 'blackbox'],
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

A value that has to differ between two suites of the same profile travels as the context's `config` argument instead. `UiContext` composes `JavascriptTrait` above, so it is the context that accepts the `javascript` group:

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

The three reach the same value, most specific last: `steps`, then the context's `config`, then the feature and scenario tags. [Trait options](configuration.md#trait-options) states the full chain, and [STEPS.md](../STEPS.md) lists the options of each trait beside its steps.

## Switch a trait off

Two options recur across the traits that carry hooks, and they mean different things:

- `enabled` turns the trait's hooks off entirely: nothing is collected, and no driver is bootstrapped on its account.
- `fail_on_errors` leaves the collection running and stops it failing the scenario.

A project that never wants a trait's gate sets it once:

```php
'steps' => ['watchdog' => ['enabled' => FALSE]],
```

rather than tagging every feature file with `@behat-steps-skip:WatchdogTrait`.

## Go further than the options

An option covers the values a trait expects to vary. Anything else is an override of the trait's own seam in the composing context:

```php
class UiContext extends RawContext {

  use ModalTrait;

  public function modalGetSelectors(): array {
    return ['.acme-dialog'];
  }

}
```

An override replaces the resolution entirely, so the option, the `steps` default and any tag no longer reach that value. [HELPERS.md](../HELPERS.md) lists every seam a context can override.
