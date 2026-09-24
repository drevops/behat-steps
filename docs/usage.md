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
             the 4 web helper traits, no steps
                          |
                     WebContext
                use Steps\Web\*  (28)
                          |
                   DrupalContext
             use DrupalApiTrait + Steps\Drupal\*  (29)
```

| Extend | When |
| --- | --- |
| `DrupalContext` | The suite tests a Drupal site and wants all 57 steps |
| `WebContext` | The suite tests a web page and wants the 28 web steps |
| `WebRawContext` | The project picks its own traits, and composes `DrupalApiTrait` when it needs the Drupal lifecycle |

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

It costs one thing, stated plainly: there is no way to remove an inherited step, so a Drupal project cannot take the Drupal steps without the 28 web ones. A project whose own step text collides with a shipped web step drops to `WebRawContext` and composes what it wants by hand.

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

A suite that writes its own Drupal steps composes `DrupalApiTrait` and declares the contract its methods answer to:

```php
<?php

use DrevOps\BehatSteps\Behat\Context\DrupalApiInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Helper\DrupalApiTrait;

class SpecContext extends WebRawContext implements DrupalApiInterface {

  use DrupalApiTrait;

  #[When('I publish a page titled :title')]
  public function publish(string $title): void {
    $this->nodeCreate(new EntityStub('node', 'page', ['title' => $title]));
  }

}
```

`WebRawContext` also composes the 4 web helper traits, so a project's own step definitions reach them on `$this`. Composing one of those helper traits in a step trait as well shares the same state rather than duplicating it.

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
- `fail_on_errors` leaves the collection running and stops it failing the scenario.

A project that never wants a trait's gate sets it once:

```php
'steps' => ['watchdog' => ['enabled' => FALSE]],
```

rather than tagging every feature file with `@behat-steps-skip:WatchdogTrait`.

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
