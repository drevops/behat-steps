---
title: Quick start
sidebar_position: 3
description: Register a context, enable the extension, write a first scenario in the shipped steps, then graduate the flows that matter to domain steps of your own.
---

# Quick start

This page takes a project from an empty Behat configuration to a first scenario in 4 parts. It assumes the package is [installed](installation.md).

## 1. Register the vocabulary you need

The vocabulary sits on 1 chain. `WebContext` carries every step that drives a page, and `DrupalContext` extends it with every step that reaches a Drupal site, so a suite registers exactly one of them:

```php
$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(DrupalContext::class)
  ->addContext(MinkContext::class);
```

Registering both is fatal, because `DrupalContext` already carries the 28 web traits and each of their steps would register twice. `MinkContext` is Mink's own context: it sits beside either one and brings the base browser steps, such as `I fill in` and `I press`.

That needs no PHP of your own. To pick your own traits instead, extend the root context and compose them:

```php
<?php

use DrevOps\BehatSteps\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\CookieTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends WebRawContext {

  use CookieTrait;

}
```

`WebRawContext` registers no steps of its own: it owns the backend access, the configuration and 3 of the web helper traits. A step trait brings the helper traits it needs, so composing `ContentTrait` also brings the entity teardown that removes what its steps created. [Usage](guides/usage.md) covers the 3 entry points and the rules that govern composing them.

## 2. Enable the extension

Ensure that your `behat.php` enables the extension:

```php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\MinkExtension\Context\MinkContext;
use DrevOps\BehatSteps\DrupalContext;
use DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension;

$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(DrupalContext::class)
  ->addContext(MinkContext::class);

$profile = (new Profile('default'))
  ->withSuite($suite)
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'backends' => ['drupal', 'blackbox'],
    'drupal' => ['drupal_root' => 'web'],
    'steps' => ['message' => ['selectors' => [
      'success' => '.messages.messages--status',
    ]]],
  ]));

return (new Config())->withProfile($profile);
```

The `steps` entry gives the message steps the selector of the success message, which the scenario below asserts on. Message selectors have no default.

The `backends` list says which backends a scenario may reach, and in what order. A step never names a backend - it names the capability it needs, and the first backend in the list providing that capability answers. See [Backend resolution](guides/configuration.md#backend-resolution).

Browser sessions come from Mink's own extension, `Behat\MinkExtension\ServiceContainer\MinkExtension`, registered in the same profile as [behat.dist.php](../../behat.dist.php) shows. `BehatStepsExtension` reads the `http_client_parameters` a `browserkit_http` session declares and sends the requests steps make from PHP, such as a file download, with the same options. So the page and the download reach the site with the same settings, and [HTTP clients](guides/http-clients.md) covers the rest.

By default, `DrupalContext` fails any scenario that logs a PHP error, reading those errors from the table the core `dblog` module creates, through a backend that loads Drupal into the Behat process. On a site without `dblog`, or under a profile without such a backend, every scenario fails at its start until you provide what's missing or [switch the Watchdog check off](guides/usage.md#switch-a-trait-off).

Behat 4 reads only PHP configuration, from `behat.php` or, when there is no `behat.php`, from `behat.dist.php`. Behat 3 also accepts the same settings in `behat.yml`.

[behat.dist.php](../../behat.dist.php) sets every extension option, plus the trait options a project almost always sets, as a reference, and [Configuration](guides/configuration.md) documents all 4 configuration channels, including the suite layout to start from.

## 3. Write a scenario in the shipped vocabulary

The imperative style spells the interaction out and needs no PHP of your own, so the suite covers a flow the moment it is written:

```gherkin
Scenario: Editor publishes a page
  Given I am logged in as a user with the role "editor"
  When I visit "/node/add/page"
  And I fill in "Title" with "About us"
  And I press "Save"
  Then the path should be "/about-us"
  And I should see the success message "has been created"
```

It assumes a site with an `editor` role and a URL alias pattern that turns "About us" into `/about-us`, so adjust both to your site.

## 4. Graduate the flows that matter to domain steps

The declarative style names the behavior instead of the mechanics:

```gherkin
Scenario: Editor publishes a page
  Given I am an editor
  When I publish a page titled "About us"
  Then the page "About us" should be publicly visible at "/about-us"
```

No shipped step matches those lines. You write them yourself, over the same helpers the shipped steps are built on:

```php
#[When('I publish a page titled :title')]
public function publishPage(string $title): void {
  $this->entityLifecycleCreateNode(new EntityStub('node', 'page', [
    'title' => $title,
    'moderation_state' => 'published',
  ]));
}
```

That is the lifecycle this package is built for: start in the vocabulary for coverage on day one, then graduate the flows that matter into domain steps on the toolbox. The scenarios change language; the code underneath them does not.

[Scenario styles](guides/scenario-styles.md) explains when each style earns its keep, and [Helpers](helpers.md) lists every helper a domain step can build on.
