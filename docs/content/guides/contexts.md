---
title: Contexts
sidebar_position: 1
description: What each of the 3 contexts includes and why the library ships them this way, which one your FeatureContext extends, and where your own steps go.
---

# Contexts

A context is the PHP class Behat finds step definitions in, as [Key concepts](../concepts.md#how-behat-runs-a-scenario) explains. Behat Steps ships 3 of them. Your project extends exactly 1 in a class of its own, `FeatureContext`, and your own steps go in it or in traits it uses.

This page shows what each context includes, which one to extend and where your own steps go. [Usage](usage.md) covers configuring the traits inside your context.

## What each context includes

Each context builds on the one before it:

- `WebRawContext` extends Mink's `RawMinkContext` and has no steps. It holds the plumbing every step relies on: the backends, the browser's capabilities, the 3 HTTP clients, trait options, prerequisite checks and hook dispatch. It comes with 3 helper traits too: `LastStepTrait`, `RequestHeadersTrait` and `StringTrait`.
- `WebContext` extends `WebRawContext` and adds every trait under `Steps\Web`: the 28 traits of [web steps](../web-steps/README.md), which work on any website.
- `DrupalContext` extends `WebContext` and adds every trait under `Steps\Drupal`: the 29 traits of [Drupal steps](../drupal-steps/README.md), and the user registry their login steps need.

A step trait also brings the helper traits it calls. The traits that create content, for example, bring `EntityLifecycleTrait`, which deletes what a scenario created. [Steps layer](../architecture/steps-layer.md) draws how it all fits together.

## Which one to extend

Pick the context that matches your site:

- On a Drupal site, extend `DrupalContext`. It inherits every web step from `WebContext`, including `I visit` from `PathTrait` and the value transforms of `MappingTrait`, `RandomTrait` and `DateTrait`: `{{ }}`, `[?...]` and `[relative:...]`. Never register `WebContext` beside your `FeatureContext`. Its Watchdog check fails every scenario on a site without the core `dblog` module until you enable the module or [switch the check off](usage.md#switch-a-trait-off).
- On any other site, extend `WebContext`.
- Extend `WebRawContext` only when you want to pick every trait yourself, as [Compose your own context](#compose-your-own-context) shows.

`FeatureContext` lives in `tests/behat/bootstrap/FeatureContext.php`, and on a Drupal site it can start empty:

```php
<?php

declare(strict_types=1);

use DrevOps\BehatSteps\DrupalContext;

class FeatureContext extends DrupalContext {

}
```

Your suite registers `FeatureContext` and Mink's `MinkContext`, and nothing else. The [quick start](../quick-start.md) shows the whole `behat.php`.

## Why 1 context holds every step

Behat lets a suite register as many contexts as you want, and plenty of step libraries ship a context per topic for you to combine. Behat Steps puts every step on 1 context instead, for 3 reasons:

- There's nothing to pick. Every shipped step is on the context you extend, so a scenario can use any of them without a change to your PHP.
- Your own steps have 1 home. With several contexts, every new step raises the question of which one it belongs in, and a step in 1 context can't call a helper that only another context has. With 1 `FeatureContext`, every helper is on `$this`.
- Nothing gets defined twice. Behat registers a trait's steps once for every registered context that has the trait, and stops with an error when 2 definitions share the same text. Registering 2 contexts built on `WebContext`, such as `DrupalContext` beside your `FeatureContext`, would define every web step twice. `WebContext::assertOneContext()` stops that run before the first scenario and names the context to drop.

Having every step costs little. A trait for a contrib module looks for its module only when one of its steps runs. A check that can fail a scenario for a reason unrelated to what the scenario tests, such as the Watchdog check, carries an `enabled` option you can [switch off](usage.md#switch-a-trait-off) in `behat.php`. An inherited step can't be removed. When a step of yours needs the exact text of a shipped one, reword yours, or [compose your own context](#compose-your-own-context) without the trait that ships it.

## Your own steps in traits of your own

A step of your own can go straight into `FeatureContext`, as the [quick start](../quick-start.md#7-add-a-step-of-your-own) shows. When you have more than a few, group them into traits, 1 per area of your site, and have `FeatureContext` use them. The library groups its own steps the same way. With 1 trait, the layout looks like this:

```text
tests/behat/
├── bootstrap/
│   ├── FeatureContext.php
│   └── PublishingTrait.php
└── features/
    └── publishing.feature
```

`FeatureContext` uses the trait:

```php
<?php

declare(strict_types=1);

use DrevOps\BehatSteps\DrupalContext;

class FeatureContext extends DrupalContext {

  use PublishingTrait;

}
```

The trait holds the steps, each built on a helper:

```php
<?php

declare(strict_types=1);

use Behat\Step\Given;
use Behat\Step\When;

/**
 * @phpstan-require-extends \DrevOps\BehatSteps\DrupalContext
 */
trait PublishingTrait {

  #[Given('a page titled :title exists')]
  public function publishingCreatePage(string $title): void {
    $this->contentCreate('page', ['title' => $title]);
  }

  #[Given('I am a site administrator')]
  public function publishingLoginAsAdministrator(): void {
    $this->userCreateAndLogin('administrator');
  }

  #[When('I open the page titled :title')]
  public function publishingVisitPage(string $title): void {
    $this->contentVisitActionPageWithTitle('page', $title);
  }

}
```

Here's what each part of the trait does:

- Each step calls 1 of the library's helpers on `$this`. [HELPERS.md](../../../HELPERS.md) lists them all. Call only the public ones: a protected method can change in any release.
- The page and the user these helpers create are deleted when the scenario ends, as they are for the shipped steps.
- The `@phpstan-require-extends` line names the class the helpers come from, for your editor and PHPStan.
- Every method starts with the trait's name, as the library's own do, so it can't collide with a shipped method or with another trait of yours.

Behat loads both files from `tests/behat/bootstrap` by their names, through the profile's `autoload` setting in `behat.php`:

```php
$profile = (new Profile('default', ['autoload' => ['%paths.base%/tests/behat/bootstrap']]))
```

A scenario in `tests/behat/features/publishing.feature` then reads in your site's own words:

```gherkin
Feature: Publishing

  Scenario: Administrator opens a new page
    Given a page titled "About us" exists
    And I am a site administrator
    When I open the page titled "About us"
    Then I should see the heading "About us"
```

An AI coding agent writes steps like these with the `create-behat-step` skill, which ships with the package. Describe the step, and the agent writes it in a trait like this one and adds the trait to `FeatureContext`. [Writing tests with AI assistants](ai-assistants.md#write-a-step-of-your-own) shows how to run the skill.

[Scenario styles](scenario-styles.md) explains when steps in your own words pay off. When your trait needs options or hooks, [Anatomy of a trait](../architecture/anatomy-of-a-trait.md) walks through a trait that has both.

## Compose your own context

A context built on `WebRawContext` takes only the traits you list:

```php
<?php

declare(strict_types=1);

use DrevOps\BehatSteps\Steps\Web\JavascriptTrait;
use DrevOps\BehatSteps\Steps\Web\WaitTrait;
use DrevOps\BehatSteps\WebRawContext;

class UiContext extends WebRawContext {

  use JavascriptTrait;
  use WaitTrait;

}
```

Compose a context like this when a shipped step's text collides with a step you need to keep, or when a suite should offer only a narrow set of steps, such as an API suite built on `RestTrait`, `JsonTrait` and `ResponseTrait`. Each step trait brings the helper traits it needs, so composing `ContentTrait` also brings the entity cleanup that removes what its steps created.

1 profile carries the extension, and each suite registers its own context:

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

[The rules that govern composition](../extending/steps.md#the-rules-that-govern-composition) cover what Behat does when a context composes a trait twice or overrides an inherited step or hook.
