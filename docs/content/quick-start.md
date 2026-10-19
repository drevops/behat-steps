---
title: Quick start
sidebar_position: 3
description: Register a context, enable the extension, write a first scenario in the shipped steps, then graduate the flows that matter to domain steps of your own.
---

# Quick start

This page takes you from an empty Behat configuration to a first scenario, in 4 parts. It assumes you've [installed](installation.md) the package.

## 1. Register the vocabulary you need

The steps sit on 1 chain of contexts. `WebContext` carries every step that drives a page, and `DrupalContext` extends it with every step that reaches a Drupal site, so your suite registers exactly 1 of them:

```php
$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(DrupalContext::class)
  ->addContext(MinkContext::class);
```

Registering both is fatal: `DrupalContext` already carries the 28 web traits, so each of their steps would register twice. `MinkContext` is Mink's own context. It sits beside either one and brings the base browser steps, such as `I fill in` and `I press`.

A shipped context needs no PHP of your own. To pick your own traits instead, extend the root context and compose them:

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

`WebRawContext` registers no steps of its own. It owns the backend access, the configuration and 3 of the web helper traits. Each step trait brings the helper traits it needs, so composing `ContentTrait` also brings the entity teardown that removes what its steps created. [Usage](guides/usage.md) covers the 3 entry points, and [Extending the step vocabulary](extending/steps.md#the-rules-that-govern-composition) covers the rules for composing them.

## 2. Enable the extension

Add the extension to the profile in your `behat.php`, next to Mink's own extension:

```php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\DrupalContext;
use DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension;

$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(DrupalContext::class)
  ->addContext(MinkContext::class);

$profile = (new Profile('default'))
  ->withSuite($suite)
  ->withExtension(new Extension(MinkExtension::class, [
    'base_url' => 'http://your-site.local',
    'sessions' => [
      'browserkit_http' => ['browserkit_http' => NULL],
    ],
  ]))
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'backends' => ['drupal', 'blackbox'],
    'drupal' => ['drupal_root' => 'web'],
    'steps' => ['message' => ['selectors' => [
      'success' => '.messages.messages--status',
    ]]],
  ]));

return (new Config())->withProfile($profile);
```

The `steps` entry gives the message steps a selector for success messages, which the scenario below asserts on. Message selectors have no default, so a message step whose type has no selector fails and names the key to set.

The `backends` list says which backends a scenario may reach, and in what order. A step never names a backend, only the capability it needs, and the first backend in the list that provides that capability answers. See [Backend resolution](guides/configuration.md#backend-resolution).

Browser sessions come from Mink's own extension, so set `base_url` there to your site's address. The `browserkit_http` session loads pages over HTTP without a browser, which is all the scenario below needs. A `@javascript` scenario also needs a JavaScript session, as [behat.dist.php](../../behat.dist.php) shows.

`BehatStepsExtension` reads the `http_client_parameters` a `browserkit_http` session declares and applies them to the requests steps send from PHP, such as a file download. That way the page and the download reach the site with the same settings. [HTTP clients](guides/http-clients.md) covers the rest.

By default, `DrupalContext` fails any scenario that logs a PHP error. It reads those errors from the table the core `dblog` module creates, through a backend that can read the site's log, such as `drupal` or `drush`. So on a site without `dblog`, or under a profile without such a backend, such as `'backends' => ['blackbox']`, every scenario fails at its start. Provide what's missing, or [switch the Watchdog check off](guides/usage.md#switch-a-trait-off).

Behat 4 reads only PHP configuration: `behat.php`, or `behat.dist.php` when there's no `behat.php`. Behat 3 also accepts the same settings in `behat.yml`.

[behat.dist.php](../../behat.dist.php) is a reference: it sets every extension option, plus the trait options a project almost always configures. [Configuration](guides/configuration.md) documents all 4 configuration channels, including the suite layout to start from.

## 3. Write a scenario in the shipped vocabulary

The imperative style spells out every interaction. It needs no PHP of your own, so a flow is covered the moment you write its scenario:

```gherkin
Scenario: Editor publishes a page
  Given I am logged in as a user with the role "editor"
  When I visit "/node/add/page"
  And I fill in "Title" with "About us"
  And I press "Save"
  Then the path should be "/about-us"
  And I should see the success message "has been created"
```

The scenario assumes your site has an `editor` role and a URL alias pattern that turns "About us" into `/about-us`, so adjust both to match.

## 4. Graduate the flows that matter to domain steps

The declarative style names the behavior instead of the mechanics:

```gherkin
Scenario: Editor publishes a page
  Given I am an editor
  When I publish a page titled "About us"
  Then the page "About us" should be publicly visible at "/about-us"
```

No shipped step matches those lines. You write them yourself, on the same helpers the shipped steps are built on:

```php
#[When('I publish a page titled :title')]
public function publishPage(string $title): void {
  $this->entityLifecycleCreateNode(new EntityStub('node', 'page', [
    'title' => $title,
    'moderation_state' => 'published',
  ]));
}
```

That's the lifecycle this package is built for: start in the vocabulary for coverage on day one, then graduate the flows that matter into domain steps on the toolbox. The scenarios change language along the way, and the code underneath them stays the same.

[Scenario styles](guides/scenario-styles.md) explains when each style earns its keep. [Web helpers](web-helpers/README.md) and [Drupal helpers](drupal-helpers/README.md) list every helper a domain step can build on.
