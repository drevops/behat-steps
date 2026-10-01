# Usage

A project uses this package by composing a context out of traits and pointing an extension at the site. The extension and the context answer different questions, and a trait reads from both.

| Layer | Answers | Scope |
| --- | --- | --- |
| The extension | How the package reaches the site, and what every trait defaults to | Per profile |
| The context | Which vocabulary a suite gets, and which of those defaults it overrides | Per context |
| The trait | What a step does, and which options change it | Per trait |

[Configuration](configuration.md) is the reference for every option and tag. This page is the model those options sit in.

## The 3 entry points

The vocabulary sits on a single chain. Every class is honest about what it drags in, and a consumer extends exactly one of them:

```
        Behat\MinkExtension\Context\RawMinkContext
                          |
                    WebRawContext
       driver access, configuration, hook dispatch,
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
| `DrupalContext` | The suite tests a Drupal site and wants all 57 step traits. The Watchdog check needs the core `dblog` module and a driver such as `drupal`, or it's [switched off](#switch-a-trait-off) |
| `WebContext` | The suite tests a web page and wants the 28 web step traits |
| `WebRawContext` | The project picks its own traits; each one brings the helpers it needs |

`WebContext` composes every trait under `Steps\Web`, and `DrupalContext` every trait under `Steps\Drupal` on top of it. A trait that could fail a scenario for a reason it did not ask about carries an `enabled` option, so a project switches it off through configuration rather than by composing its own context.

```php
<?php

use DrevOps\BehatSteps\Behat\Context\DrupalContext;

class FeatureContext extends DrupalContext {}
```

## Register one context, not two

`DrupalContext` extends `WebContext`, so a suite registering both would register the 28 web traits twice and die on a `RedundantStepException` naming an arbitrary step. `WebContext::assertOneContext()` runs on `BeforeSuite` and reports the real mistake instead. Register the class lowest in the chain and drop the rest:

```php
$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(FeatureContext::class)
  ->addContext(MinkContext::class);
```

A Drupal suite needs `DrupalContext` alone: `I visit` and the `{{ }}`, `[?...]` and `[relative:...]` transforms come with it, because `RandomTrait`, `MappingTrait` and `DateTrait` are inherited from `WebContext`.

It costs one thing, stated plainly: there is no way to remove an inherited step, so a Drupal project cannot take the Drupal step traits without the 28 web ones. A project whose own step text collides with a shipped web step drops to `WebRawContext` and composes what it wants by hand.

## Compose your own context

A context of your own is `WebRawContext` plus the traits whose steps the suite needs.

```php
<?php

use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\JavascriptTrait;
use DrevOps\BehatSteps\Steps\Web\WaitTrait;

class UiContext extends WebRawContext {

  use JavascriptTrait;
  use WaitTrait;

}
```

A suite that writes its own Drupal steps composes the helper for the concern it touches. `EntityLifecycleTrait` brings entity creation and the `AfterScenario` pass that removes what was created:

```php
<?php

use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;

class SpecContext extends WebRawContext {

  use EntityLifecycleTrait;

  #[When('I publish a page titled :title')]
  public function publish(string $title): void {
    $this->entityLifecycleNodeCreate(new EntityStub('node', 'page', ['title' => $title]));
  }

}
```

`WebRawContext` also composes 3 of the web helper traits - `LastStepTrait`, `RequestHeadersTrait` and `StringTrait` - so a project's own step definitions reach them on `$this`. Composing one of those helper traits in a step trait as well shares the same state rather than duplicating it.

## The rules that govern composition

Behat's attribute inheritance behaves differently for a trait that is composed and a class that is extended, in opposite directions:

| What the project does | Result |
| --- | --- |
| Registers 2 contexts composing the same trait | Fatal, `RedundantStepException` |
| A subclass re-composes a trait its parent already has | Fatal, every step in it registers twice |
| A subclass overrides an inherited step, no attribute | Works, the subclass body runs, the step text is inherited |
| A subclass overrides an inherited step and repeats the attribute | Fatal, the pattern registers twice |
| A subclass overrides an inherited step with a different pattern | Both patterns register, both run the subclass body |
| A composed trait's method is redeclared without the attribute | The step is silently removed |
| A composed trait is aliased `as protected` and the attribute redeclared | Works, wraps the original |
| A subclass overrides an inherited hook, no attribute | Works, the subclass body runs |
| A subclass overrides an inherited hook and repeats the attribute | **Runs twice, silently** |

The last row is the least discoverable: `HookRepository` does not deduplicate, so a hook whose attribute is repeated on the override fires once for the parent declaration and once for the subclass one. Override an inherited hook without repeating its attribute.

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
- `fail_on_errors` leaves the collection running and stops what it collects from failing the scenario.

A project that never wants a trait's gate sets it once:

```php
'steps' => ['watchdog' => ['enabled' => FALSE]],
```

rather than tagging every feature file with `@behat-steps-skip:WatchdogTrait`.

`WatchdogTrait` is the gate you're most likely to meet first, because `DrupalContext` composes it. It reads the errors each scenario logged from the `watchdog` table, and only the core `dblog` module creates that table. It reads the table in the Behat process, so it also needs a driver that loads Drupal there, such as `drupal`.

Those are the trait's prerequisites, and it checks them when a scenario starts. Here's what happens with and without `dblog`:

| Watchdog check | `dblog` enabled | `dblog` not enabled |
| --- | --- | --- |
| On, the default | Reads the errors after the last step, and fails a scenario that logged one | Fails the scenario at its start, before any step runs |
| Off, through `watchdog.enabled` or `@behat-steps-skip:WatchdogTrait` | Reads nothing and bootstraps no driver | Reads nothing and bootstraps no driver |

A profile that lists no driver loading Drupal into the Behat process, such as `'drivers' => ['drush', 'blackbox']`, fails at the start the same way. The error names the prerequisite that doesn't hold and both ways out: meet it, or switch the trait off as above. A scenario that uninstalls `dblog` itself starts with the table in place, so it fails at its last step instead.

Setting `fail_on_errors` to `FALSE` or tagging the scenario `@error` won't help with an unmet prerequisite. Both decide what happens to errors that were read, and without the table or a driver to read it through, there's nothing to read.

## Go further than the options

An option covers the values a trait expects to vary. Anything else is an override of the trait's own seam in the composing context:

```php
class UiContext extends WebRawContext {

  use ModalTrait;

  public function modalGetSelectors(): array {
    return ['.acme-dialog'];
  }

}
```

A suite that registers `WebContext` overrides the same seam by extending it and redeclaring the method, without repeating any Behat attribute:

```php
class AcmeWebContext extends WebContext {

  public function modalGetSelectors(): array {
    return ['.acme-dialog'];
  }

}
```

An override replaces the resolution entirely, so the option, the `steps` default and any tag no longer reach that value. [HELPERS.md](../HELPERS.md) lists every seam a context can override.
