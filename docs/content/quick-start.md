---
title: Quick start
sidebar_position: 3
description: Install Behat Steps, pick the context that matches your site, create your FeatureContext and behat.php, write and run your first scenario, then add a step of your own.
---

<!-- cspell:ignore docroot -->

# Quick start

This page takes you from nothing to a passing test on your own site, whether or not you've used Behat before. In 7 parts, you'll install the package, set up a context and the configuration, then write a scenario and run it. The last part adds a step of your own, so you know where your own code goes.

Behat reads tests written as plain-English scenarios, such as "When I visit the homepage, then I should see the heading Welcome", and runs each line against your site. Every line needs PHP code behind it, and Behat Steps is a library of that code, already written and tested, so you can start with the scenarios.

The setup here runs scenarios without JavaScript, which needs nothing but PHP. [Browsers](guides/browsers.md) covers the real browsers that run the rest.

## Before you begin

Make sure you have the following:

- PHP 8.3 or newer, and a PHP project that uses Composer. For a site that isn't built with PHP, that can be a project of its own that holds only the tests.
- A site running at an address Behat can reach, such as `http://your-site.local`. It can be built with anything, and a Drupal site gets the Drupal steps as well.
- On a Drupal site, a place to run Behat that reaches Drupal's code and database, such as the site's own container, because the Drupal steps load Drupal inside the test run. Run this page's commands there.
- On a Drupal site, a copy you can write to rather than your live site, because the Drupal steps create content and users in its database and delete them afterwards.
- On a Drupal site, the core Database Logging module (`dblog`) enabled, because `DrupalContext` checks its log in every scenario. Part 3 shows how to switch the check off instead.

## 1. Install the package

Run this in your project's root, the folder that holds `composer.json`:

```bash
composer require --dev drevops/behat-steps:^4
```

Composer installs Behat and Mink, the library Behat uses to drive a browser, along with it. Behat Steps works with Behat 3, from 3.33, and with Behat 4, so Composer installs the newest Behat your project allows. A few steps need 1 more package, which [Installation](installation.md#optional-dependencies) lists, but you won't need any of them here.

## 2. Pick your context

Behat reads steps from classes called contexts. Behat Steps puts its steps on 2 of them, and you pick the one that matches your site:

| Your site | Your context extends | What it brings |
| --- | --- | --- |
| Built with Drupal | `DrupalContext` | Every web step, plus the Drupal steps for content, users, caches, email, queues and more |
| Built with anything else | `WebContext` | Every web step: pages, forms, links, cookies, JSON, XML and tables |

`DrupalContext` builds on `WebContext`, so it has every web step too, and you never need both. You don't register the one you picked directly: you extend it in a context of your own, `FeatureContext`, which is where your own steps go later. [Contexts](guides/contexts.md) explains what each one includes and why it works this way.

## 3. Create your context and configuration

Your project gets 2 files of its own: `FeatureContext`, the context, and `behat.php`, the configuration Behat reads. By the end of this page your project looks like this:

```text
your-project/
├── behat.php
├── composer.json
├── tests/
│   └── behat/
│       ├── bootstrap/
│       │   └── FeatureContext.php
│       └── features/
│           └── homepage.feature
└── vendor/
```

On a Drupal site, part 6 adds `pages.feature` beside `homepage.feature`.

Create `tests/behat/bootstrap/FeatureContext.php`. On a site that isn't built with Drupal, it extends `WebContext`:

```php
<?php

declare(strict_types=1);

use DrevOps\BehatSteps\WebContext;

class FeatureContext extends WebContext {

}
```

On a Drupal site, it extends `DrupalContext` instead:

```php
<?php

declare(strict_types=1);

use DrevOps\BehatSteps\DrupalContext;

class FeatureContext extends DrupalContext {

}
```

It's empty, and that's fine: it already has every step of the context it extends.

Then create `behat.php` in your project's root, and change `base_url` to your site's address:

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

On a Drupal site, replace the line that registers `BehatStepsExtension` with this one, which also says where Drupal lives:

```php
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'drupal' => ['drupal_root' => 'web'],
  ]));
```

Here's what each part does:

- The suite tells Behat where your scenarios live and which contexts to load: your `FeatureContext`, and Mink's `MinkContext`, which adds Mink's own basic steps, such as `I fill in "Title" with "About us"` and `I press "Save"`. `%paths.base%` is the folder that holds `behat.php`. Register only these 2: your context already holds every Behat Steps step, and registering `WebContext` or `DrupalContext` beside it would define every web step twice, so Behat Steps stops the run with an error that says so.
- The profile's `autoload` setting tells Behat to load your classes from `tests/behat/bootstrap`, where `FeatureContext.php` lives.
- The Mink extension gives your scenarios a browser. The `browserkit_http` session loads pages over plain HTTP from PHP, with no real browser behind it. It's quick, but it doesn't run JavaScript, and [Browsers](guides/browsers.md) covers the real browsers you can add when you need one.
- The last extension switches Behat Steps on. A site that isn't Drupal needs no settings here, and [Configuration reference](guides/configuration-reference.md#extension-options) lists them for when you do.

On a Drupal site, `drupal_root` is the folder that holds Drupal's `index.php`, as a path from the folder you run Behat in, such as `web` or `docroot`. With it, the Drupal steps load Drupal inside the test run and create content, users and the rest directly, without going through your site's forms. Behat Steps calls this way of reaching a site a backend, and [Key concepts](concepts.md#backends-reaching-the-site-without-the-browser) explains backends, Drush included.

`DrupalContext` also checks Drupal's log, so you hear about PHP warnings and errors that never show on the page. Its `WatchdogTrait` notes the newest log entry when a scenario starts, and fails the scenario if a PHP warning or error was logged after that entry by the time the scenario ends. It reads the log through the core `dblog` module, so if `dblog` isn't enabled on your site, every scenario fails at its start with this error:

```text
WatchdogTrait requires that the core "dblog" module is enabled, which does not hold. Meet the prerequisite, or switch WatchdogTrait off with the "watchdog.enabled" option or the "@behat-steps-skip:WatchdogTrait" tag.
```

Enable the module, or switch the check off in the last extension's settings:

```php
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'drupal' => ['drupal_root' => 'web'],
    'steps' => ['watchdog' => ['enabled' => FALSE]],
  ]));
```

[Switch a trait off](guides/usage.md#switch-a-trait-off) covers the other ways to do it.

If your site uses a self-signed certificate, [HTTP clients](guides/http-clients.md#configuration-a-staging-site-with-a-self-signed-certificate-behind-dockers-dns) shows the settings that accept it.

Behat 4 reads its configuration only from PHP, so stick with `behat.php` even though Behat 3 also accepts `behat.yml`.

## 4. Write your first scenario

Create `tests/behat/features/homepage.feature`:

```gherkin
Feature: Homepage

  Scenario: Visitor opens the homepage
    When I visit "/"
    Then the response status code should be 200
    And I should see the heading "Welcome!"
```

A feature file describes 1 feature of your site. Each scenario under it is 1 example of how the feature should behave, written as steps: `Given` sets up what the scenario needs, `When` does something, `Then` checks the result, and `And` continues the step before it.

Change `Welcome!` to the main heading of your own homepage. `I should see the heading` matches a heading's text exactly, so copy it as it appears on the page.

## 5. Run it

From your project's root, run Behat:

```bash
vendor/bin/behat
```

Behat prints each step and the method that ran it, then a summary:

```text
Feature: Homepage

  Scenario: Visitor opens the homepage          # tests/behat/features/homepage.feature:3
    When I visit "/"                            # FeatureContext::pathVisit()
    Then the response status code should be 200 # Behat\MinkExtension\Context\MinkContext::assertResponseStatus()
    And I should see the heading "Welcome!"     # FeatureContext::elementAssertHeadingExists()

1 scenario (1 passed)
3 steps (3 passed)
```

That's your first passing test. The steps report `FeatureContext`, because that's the class you registered, and their code comes from the context it extends. When a step fails, Behat prints why under it and skips the rest of that scenario.

To see every step you can use, ask Behat for the list:

```bash
vendor/bin/behat --definitions=l
```

[Web steps](web-steps/README.md) has the same steps with an example for each.

## 6. Try a Drupal step

If your site isn't built with Drupal, skip this part.

Your `FeatureContext` extends `DrupalContext`, so the Drupal steps are already there. Add a scenario that only a Drupal site can run, in `tests/behat/features/pages.feature`:

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

The first step creates a Basic page called "About us" in Drupal. The second creates a user with the `administrator` role and logs in through your site's login form. When the scenario ends, whether it passed or failed, Behat Steps deletes both the page and the user, so test data doesn't pile up on your site. The scenario logs in because a new page might start unpublished, for example on a site that moderates its content, and an administrator can see it either way.

Run `vendor/bin/behat` again, and both scenarios should pass. [Drupal steps](drupal-steps/README.md) lists the rest.

## 7. Add a step of your own

Sooner or later you'll want to say something no shipped step says, in your site's own words. That's a step of your own, and it goes in your `FeatureContext`:

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

On a Drupal site, keep `use DrevOps\BehatSteps\DrupalContext;` and `extends DrupalContext` in place of the `WebContext` lines, then add the `use Behat\Step\Then;` line and the method.

- The `#[Then(...)]` attribute holds the step's text. `:text` is a placeholder: Behat captures the quoted value in that position and passes it to the method as `$text`.
- The method uses Mink's `assertSession()`, which fails the step when the page's `footer` element doesn't contain the text. Your step can also call the helpers, the methods the shipped steps are built on. [HELPERS.md](../../HELPERS.md) lists them, and your project has it at `vendor/drevops/behat-steps/HELPERS.md`.
- Pick text that no shipped step uses. When 2 step definitions share the same text, Behat stops with an error before it runs the first step, so reword any step of your own that matches a shipped one. `vendor/bin/behat --definitions="footer"` lists every step whose text contains "footer", so you can check a word before you use it.

`behat.php` already registers `FeatureContext` and loads it from `tests/behat/bootstrap`, so there's nothing to change there. Add a scenario that uses your step to `homepage.feature`, below the first one, with text from your own site's footer:

```gherkin
  Scenario: Visitor sees who runs the site
    When I visit "/"
    Then the footer should mention "Powered by Drupal"
```

Run `vendor/bin/behat` again. The new scenario passes when your footer contains the text, and fails with Mink's message naming the text when it doesn't.

That step describes your site in its own terms. When you have more than a few steps like it, group them into traits of your own that `FeatureContext` uses, as [Contexts](guides/contexts.md#your-own-steps-in-traits-of-your-own) shows. Take the idea further and whole scenarios read that way, such as `When I publish a page titled "About us"`, with the clicks and form fields inside your step definitions. [Scenario styles](guides/scenario-styles.md) explains when that's worth it.

## Where to next

- [Key concepts](concepts.md) explains each piece you just used, from contexts to backends.
- [Contexts](guides/contexts.md) covers what each context includes and where your own steps go, and [Usage](guides/usage.md) covers configuring the traits and the cleanup after each scenario.
- [Web steps](web-steps/README.md) and [Drupal steps](drupal-steps/README.md) list every step, with an example for each.
- [Configuration reference](guides/configuration-reference.md) lists every option and tag, and [behat.dist.php](../../behat.dist.php) sets every extension option as a reference.
- [Writing tests with AI assistants](guides/ai-assistants.md) points a coding agent at the same reference, and at the skill that writes a step of your own.
