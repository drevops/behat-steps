---
title: Quick start
sidebar_position: 3
description: Install Behat Steps, point Behat at your site, write and run your first scenario, then add a step of your own.
---

# Quick start

This page takes you from nothing to a passing test. You'll install the package, tell Behat where your site is, write a scenario that opens your homepage and checks it, and run it. Then you'll add a step of your own, so you know where your own code goes.

You need a PHP project that uses Composer, and a site running at an address Behat can reach, such as `http://your-site.local`. The site can be built with anything. If it's a Drupal site, there's an extra part for you after the first run.

If Behat is new to you, it reads tests written as plain-English scenarios, such as "When I visit the homepage, then I should see the heading Welcome", and runs each line against your site. Every line needs PHP code behind it, and Behat Steps is a library of that code, already written and tested, so you can start with the scenarios.

## 1. Install the package

Run this in your project's root, the folder that holds `composer.json`:

```bash
composer require --dev drevops/behat-steps:^4
```

Composer installs Behat and Mink, the library Behat uses to drive a browser, along with it. A few steps need 1 more package, which [Installation](installation.md#optional-dependencies) lists, but you won't need any of them here.

## 2. Create the configuration file

Behat reads its settings from `behat.php` in your project's root. Your scenarios go in `tests/behat/features`, so by the end of this page your project looks like this:

```text
your-project/
├── behat.php
├── composer.json
├── tests/
│   └── behat/
│       └── features/
│           └── homepage.feature
└── vendor/
```

Create `behat.php` with this content, and change `base_url` to your site's address:

```php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension;
use DrevOps\BehatSteps\WebContext;

$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(WebContext::class)
  ->addContext(MinkContext::class);

$profile = (new Profile('default'))
  ->withSuite($suite)
  ->withExtension(new Extension(MinkExtension::class, [
    'base_url' => 'http://your-site.local',
    'sessions' => [
      'browserkit_http' => ['browserkit_http' => NULL],
    ],
  ]))
  ->withExtension(new Extension(BehatStepsExtension::class));

return (new Config())->withProfile($profile);
```

Here's what each part does:

- The suite tells Behat where your scenarios live and which contexts to load. `%paths.base%` is the folder that holds `behat.php`. A context is a PHP class holding step definitions, the code behind each line of a scenario. `WebContext` holds every step Behat Steps ships for websites, and `MinkContext` adds Mink's own basic steps, such as `I fill in "Title" with "About us"` and `I press "Save"`.
- The Mink extension gives your scenarios a browser. The `browserkit_http` session loads pages over plain HTTP from PHP, with no real browser behind it. It's quick, but it doesn't run JavaScript, and [JavaScript browser drivers](installation.md#javascript-browser-drivers) covers adding a real browser when you need one.
- The last extension switches Behat Steps on. A plain website needs no settings here, and [Configuration](guides/configuration.md) lists them for when you do.

If your site uses a self-signed certificate, [HTTP clients](guides/http-clients.md#configuration-a-staging-site-with-a-self-signed-certificate-behind-dockers-dns) shows the settings that accept it.

Behat 4 reads its configuration only from PHP, so stick with `behat.php` even though Behat 3 also accepts `behat.yml`.

## 3. Write your first scenario

Create `tests/behat/features/homepage.feature`:

```gherkin
Feature: Homepage

  Scenario: Visitor opens the homepage
    When I visit "/"
    Then the response status code should be 200
    And I should see the heading "Welcome!"
```

A feature file describes 1 feature of your site. Each scenario under it is 1 example of how the feature should behave, written as steps: `When` does something, `Then` checks the result, and `And` continues the step before it.

Change `Welcome!` to the main heading of your own homepage. `I should see the heading` matches a heading's text exactly, so copy it as it appears on the page.

## 4. Run it

```bash
vendor/bin/behat
```

Behat prints each step and the method that ran it, then a summary:

```text
Feature: Homepage

  Scenario: Visitor opens the homepage          # tests/behat/features/homepage.feature:3
    When I visit "/"                            # DrevOps\BehatSteps\WebContext::pathVisit()
    Then the response status code should be 200 # Behat\MinkExtension\Context\MinkContext::assertResponseStatus()
    And I should see the heading "Welcome!"     # DrevOps\BehatSteps\WebContext::elementAssertHeadingExists()

1 scenario (1 passed)
3 steps (3 passed)
```

That's your first passing test. When a step fails, Behat prints why under it and skips the rest of that scenario.

To see every step you can use, ask Behat for the list:

```bash
vendor/bin/behat --definitions=l
```

[Web steps](web-steps/README.md) has the same steps with an example for each.

## 5. Add the Drupal steps

Skip this part if your site isn't built with Drupal.

Behat Steps has a second set of steps just for Drupal sites, for content, users, caches, email, queues and more. They come with `DrupalContext`. Swap it in for `WebContext` and tell Behat Steps where Drupal lives, which makes your `behat.php` look like this:

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
    'drupal' => ['drupal_root' => 'web'],
  ]));

return (new Config())->withProfile($profile);
```

2 things changed:

- `DrupalContext` replaces `WebContext`. It builds on `WebContext`, so it holds every web step as well as the Drupal ones. Register only 1 of the 2: with both, every web step would be defined twice, and Behat Steps stops the run to tell you so. [Key concepts](concepts.md#3-contexts-each-building-on-the-last) shows how the contexts build on each other.
- `drupal_root` is the folder that holds Drupal's `index.php`, as a path from the folder you run Behat in. With it, the Drupal steps load Drupal inside the test run and create content, users and the rest directly, without going through your site's forms. Behat Steps calls this way of reaching a site a backend, and [Key concepts](concepts.md#backends-reaching-the-site-without-the-browser) explains backends, Drush included.

Now add a scenario that only a Drupal site can run, in `tests/behat/features/pages.feature`:

```gherkin
Feature: Pages

  Scenario: Administrator views a new page
    Given the following "page" content exists:
      | title    |
      | About us |
    And I am logged in as a user with the role "administrator"
    When I visit the "page" content page with the title "About us"
    Then I should see the heading "About us"
```

The first step creates a Basic page called "About us" in Drupal. The second creates a user with the `administrator` role and logs in through your site's login form. When the scenario ends, Behat Steps deletes both the page and the user, so test data doesn't pile up on your site. The scenario logs in because a new page may start unpublished, for example on a site that moderates its content, and an administrator can see it either way.

Run `vendor/bin/behat` again, and both scenarios should pass.

`DrupalContext` also keeps an eye on Drupal's log while each scenario runs, and fails a scenario that logs a PHP error, so you hear about errors that never show on the page. It reads the log through the core `dblog` module. If `dblog` isn't enabled on your site, every scenario fails at its start until you enable it or [switch the check off](guides/usage.md#switch-a-trait-off).

## 6. Add a step of your own

Sooner or later you'll want to say something no shipped step says, in your site's own words. That's a step of your own, and it goes in a context class of your own. Put it in `tests/behat/bootstrap/FeatureContext.php`, beside your features folder:

```text
your-project/
├── behat.php
└── tests/
    └── behat/
        ├── bootstrap/
        │   └── FeatureContext.php
        └── features/
            └── homepage.feature
```

```php
<?php

declare(strict_types=1);

use Behat\Step\Then;
use DrevOps\BehatSteps\WebContext;

class FeatureContext extends WebContext {

  #[Then('the footer should mention :text')]
  public function footerShouldMention(string $text): void {
    $this->assertSession()->elementTextContains('css', 'footer', $text);
  }

}
```

- `FeatureContext` extends `WebContext`, so it holds every shipped web step as well as yours. On a Drupal site, extend `DrupalContext` instead.
- The `#[Then(...)]` attribute holds the step's text. `:text` is a placeholder: Behat captures the quoted value in that position and passes it to the method as `$text`.
- The method uses Mink's `assertSession()`, which fails the step when the page's `footer` element doesn't contain the text. Your step can also call the methods the shipped steps are built on, which [Web helpers](web-helpers/README.md) and [Drupal helpers](drupal-helpers/README.md) list.

Pick text that no shipped step uses. Behat refuses to start when 2 step definitions share the same text, so drop any step of your own that matches a shipped one, or [compose your own context](guides/usage.md#compose-your-own-context) without the trait that ships it.

Then make 2 changes to `behat.php`: register `FeatureContext` in place of `WebContext`, and tell Behat which folder to load it from with the profile's `autoload` setting:

```php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension;

$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(FeatureContext::class)
  ->addContext(MinkContext::class);

$profile = (new Profile('default', ['autoload' => ['%paths.base%/tests/behat/bootstrap']]))
  ->withSuite($suite)
  ->withExtension(new Extension(MinkExtension::class, [
    'base_url' => 'http://your-site.local',
    'sessions' => [
      'browserkit_http' => ['browserkit_http' => NULL],
    ],
  ]))
  ->withExtension(new Extension(BehatStepsExtension::class));

return (new Config())->withProfile($profile);
```

On a Drupal site, keep the `drupal` setting from part 5 on the last extension.

Now use your step in `homepage.feature`, with text from your own site's footer:

```gherkin
  Scenario: Visitor sees who runs the site
    When I visit "/"
    Then the footer should mention "Powered by Drupal"
```

That step describes your site in its own terms. Take the idea further and whole scenarios read that way, such as `When I publish a page titled "About us"`, with the clicks and form fields inside your step definitions. [Scenario styles](guides/scenario-styles.md) explains when that's worth it, and [Extending the step vocabulary](extending/steps.md) covers writing steps of your own in depth.

## Where to next

- [Key concepts](concepts.md) explains each piece you just used, from contexts to backends.
- [Usage](guides/usage.md) covers picking a context, configuring the traits and the cleanup after each scenario.
- [Web steps](web-steps/README.md) and [Drupal steps](drupal-steps/README.md) list every step, with an example for each.
- [Configuration](guides/configuration.md) lists every option and tag, and [behat.dist.php](../../behat.dist.php) sets every extension option as a reference.
- [Writing tests with AI assistants](guides/ai-assistants.md) points a coding agent at the same reference.
