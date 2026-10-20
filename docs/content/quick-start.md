---
title: Quick start
sidebar_position: 3
description: Install Behat Steps, pick the context that matches your site, create your FeatureContext and behat.php, write and run your first scenario, then add a step of your own.
---

# Quick start

This page takes you from nothing to a passing test. You'll install the package, pick the context that matches your site, create your context and the configuration file, write a scenario that opens your homepage and checks it, and run it. Then you'll add a step of your own, so you know where your own code goes.

You need a PHP project that uses Composer, and a site running at an address Behat can reach, such as `http://your-site.local`. The site can be built with anything, and Drupal sites get a set of steps of their own.

If Behat is new to you, it reads tests written as plain-English scenarios, such as "When I visit the homepage, then I should see the heading Welcome", and runs each line against your site. Every line needs PHP code behind it, and Behat Steps is a library of that code, already written and tested, so you can start with the scenarios.

## 1. Install the package

Run this in your project's root, the folder that holds `composer.json`:

```bash
composer require --dev drevops/behat-steps:^4
```

Composer installs Behat and Mink, the library Behat uses to drive a browser, along with it. A few steps need 1 more package, which [Installation](installation.md#optional-dependencies) lists, but you won't need any of them here.

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

On a Drupal site, the last extension also says where Drupal lives:

```php
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'drupal' => ['drupal_root' => 'web'],
  ]));
```

Here's what each part does:

- The suite tells Behat where your scenarios live and which contexts to load: your `FeatureContext`, and Mink's `MinkContext`, which adds Mink's own basic steps, such as `I fill in "Title" with "About us"` and `I press "Save"`. `%paths.base%` is the folder that holds `behat.php`. Register only these 2: your context already holds every Behat Steps step, and registering `WebContext` or `DrupalContext` beside it would define every web step twice, which Behat Steps stops the run to tell you about.
- The profile's `autoload` setting tells Behat to load your classes from `tests/behat/bootstrap`, where `FeatureContext.php` lives.
- The Mink extension gives your scenarios a browser. The `browserkit_http` session loads pages over plain HTTP from PHP, with no real browser behind it. It's quick, but it doesn't run JavaScript, and [Browsers](guides/browsers.md) covers the real browsers you can add when you need one.
- The last extension switches Behat Steps on. A site that isn't Drupal needs no settings here, and [Configuration](guides/configuration.md) lists them for when you do.

On a Drupal site, `drupal_root` is the folder that holds Drupal's `index.php`, as a path from the folder you run Behat in. With it, the Drupal steps load Drupal inside the test run and create content, users and the rest directly, without going through your site's forms. Behat Steps calls this way of reaching a site a backend, and [Key concepts](concepts.md#backends-reaching-the-site-without-the-browser) explains backends, Drush included.

`DrupalContext` also keeps an eye on Drupal's log while each scenario runs, and fails a scenario that logs a PHP error, so you hear about errors that never show on the page. It reads the log through the core `dblog` module. If `dblog` isn't enabled on your site, every scenario fails at its start until you enable it or [switch the check off](guides/usage.md#switch-a-trait-off).

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

A feature file describes 1 feature of your site. Each scenario under it is 1 example of how the feature should behave, written as steps: `When` does something, `Then` checks the result, and `And` continues the step before it.

Change `Welcome!` to the main heading of your own homepage. `I should see the heading` matches a heading's text exactly, so copy it as it appears on the page.

## 5. Run it

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

Skip this part if your site isn't built with Drupal.

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

The first step creates a Basic page called "About us" in Drupal. The second creates a user with the `administrator` role and logs in through your site's login form. When the scenario ends, Behat Steps deletes both the page and the user, so test data doesn't pile up on your site. The scenario logs in because a new page may start unpublished, for example on a site that moderates its content, and an administrator can see it either way.

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

On a Drupal site, keep `extends DrupalContext` and its `use` line, and add the same method.

- The `#[Then(...)]` attribute holds the step's text. `:text` is a placeholder: Behat captures the quoted value in that position and passes it to the method as `$text`.
- The method uses Mink's `assertSession()`, which fails the step when the page's `footer` element doesn't contain the text. Your step can also call the helpers, the methods the shipped steps are built on. [HELPERS.md](../../HELPERS.md) lists them, and your project has it at `vendor/drevops/behat-steps/HELPERS.md`.
- Pick text that no shipped step uses. Behat refuses to start when 2 step definitions share the same text, so reword any step of your own that matches a shipped one.

`behat.php` already registers `FeatureContext` and loads it from `tests/behat/bootstrap`, so there's nothing to change there. Use your step in `homepage.feature`, with text from your own site's footer:

```gherkin
  Scenario: Visitor sees who runs the site
    When I visit "/"
    Then the footer should mention "Powered by Drupal"
```

That step describes your site in its own terms. Once you have more than a few steps like it, group them into traits of your own that `FeatureContext` uses, as [Contexts](guides/contexts.md#your-own-steps-in-traits-of-your-own) shows. Take the idea further and whole scenarios read that way, such as `When I publish a page titled "About us"`, with the clicks and form fields inside your step definitions. [Scenario styles](guides/scenario-styles.md) explains when that's worth it.

## Where to next

- [Key concepts](concepts.md) explains each piece you just used, from contexts to backends.
- [Contexts](guides/contexts.md) covers what each context includes and where your own steps go, and [Usage](guides/usage.md) covers configuring the traits and the cleanup after each scenario.
- [Web steps](web-steps/README.md) and [Drupal steps](drupal-steps/README.md) list every step, with an example for each.
- [Configuration](guides/configuration.md) lists every option and tag, and [behat.dist.php](../../behat.dist.php) sets every extension option as a reference.
- [Writing tests with AI assistants](guides/ai-assistants.md) points a coding agent at the same reference.
