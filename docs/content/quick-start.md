---
title: Quick start
sidebar_position: 3
description: Install Behat Steps, pick the context that matches your site, create your FeatureContext and behat.php, write and run your first scenario, then add a step of your own.
---

<!-- cspell:ignore docroot -->

# Quick start

Follow this page to go from an empty project to a passing test on your own site. It's for anyone setting up Behat Steps, including developers new to Behat. In 7 parts, you install the package, set up a context and the configuration, then write a scenario and run it. The last part adds a step of your own.

Behat runs tests written as plain-English scenarios, such as "When I visit the homepage, then I should see the heading Welcome", line by line against your site. Every line needs PHP code behind it. Behat Steps is a library of that code, already written and tested, so you can start by writing the scenarios.

On this page, Behat loads pages through HTTP requests from PHP, so your scenarios don't run JavaScript. [Browsers](guides/browsers.md) covers the real browsers that run JavaScript.

## Before you begin

Make sure you have the following:

- PHP 8.3 or newer, and a PHP project that uses Composer. For a site built without PHP, create a project that holds only the tests.
- A site running at an address Behat can reach, such as `http://your-site.local`. Any site works, and a Drupal site also gets the Drupal steps.

On a Drupal site, you also need the following:

- Drupal 11 or 12.
- A place to run Behat that reaches your site's code and database, such as the site's own container. The Drupal steps load Drupal inside the test run, and your scenarios then visit the pages they create. Run this page's commands there.
- A copy of the site you can write to. The Drupal steps create content and users in its database, and Behat Steps deletes them afterward, so never point Behat at your live site.
- The core Database Logging module (`dblog`) enabled, because Behat Steps checks its log in every scenario. Part 3 shows how to switch the check off instead.

## 1. Install the package

In your project's root, the folder that holds `composer.json`, run the following command:

```bash
composer require --dev drevops/behat-steps:^4
```

The command also installs Behat and Mink, the library Behat uses to drive a browser. Behat Steps works with Behat 3 from 3.33 and with Behat 4, so Composer installs the newest Behat your project allows. A few steps need 1 more package, which [Installation](installation.md#optional-dependencies) lists. The steps on this page work with what the command installs.

## 2. Pick your context

Behat reads steps from classes called contexts. In part 3 you create a context of your own, `FeatureContext`, and your own steps go in it later. `FeatureContext` extends 1 of the 2 contexts Behat Steps ships. Pick the context that matches your site:

| Your site | `FeatureContext` extends | What it brings |
| --- | --- | --- |
| Built with Drupal | `DrupalContext` | Every web step, plus the Drupal steps for content, users, caches, email, queues and more |
| Built with anything else | `WebContext` | Every web step: pages, forms, links, cookies, JSON, XML and tables |

`DrupalContext` builds on `WebContext`, so extend only 1 of them. [Contexts](guides/contexts.md) explains what each context includes and why it works this way.

## 3. Create your context and configuration

You add 2 files to your project. `FeatureContext.php` holds your context, and `behat.php` holds the configuration Behat reads. Behat 4 reads its configuration only from PHP, so use `behat.php` even though Behat 3 also accepts `behat.yml`. By the end of this page, your project looks like this:

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

On a Drupal site, you add `pages.feature` beside `homepage.feature` in part 6.

To create the 2 files:

1. Create `tests/behat/bootstrap/FeatureContext.php` with the class for your site:
   - On a site built without Drupal, extend `WebContext`:

     ```php
     <?php

     declare(strict_types=1);

     use DrevOps\BehatSteps\WebContext;

     class FeatureContext extends WebContext {

     }
     ```

   - On a Drupal site, extend `DrupalContext`:

     ```php
     <?php

     declare(strict_types=1);

     use DrevOps\BehatSteps\DrupalContext;

     class FeatureContext extends DrupalContext {

     }
     ```

   The empty class already has every step of the context it extends.

2. Create `behat.php` in your project's root with the following content:

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

3. Replace `http://your-site.local` in `base_url` with your site's address.
4. On a Drupal site, replace the `->withExtension(new Extension(BehatStepsExtension::class));` line with the following lines, which set where Drupal lives:

   ```php
     ->withExtension(new Extension(BehatStepsExtension::class, [
       'drupal' => ['drupal_root' => 'web'],
     ]));
   ```

`behat.php` sets up the following:

- The suite sets where your scenarios live and which contexts Behat loads: your `FeatureContext` and Mink's `MinkContext`. `MinkContext` adds Mink's own basic steps, such as `I fill in "Title" with "About us"`. `%paths.base%` is the folder that holds `behat.php`.
- The profile's `autoload` setting tells Behat to load your classes from `tests/behat/bootstrap`, the folder that holds `FeatureContext.php`.
- The Mink extension sets up the browser session your scenarios use. The `browserkit_http` session loads pages through HTTP requests from PHP. It's quick, but it doesn't run JavaScript.
- `BehatStepsExtension` switches Behat Steps on. A site built without Drupal uses it with no settings, and [Configuration reference](guides/configuration-reference.md#extension-options) lists every setting.

Register only these 2 contexts. Registering `WebContext` or `DrupalContext` beside `FeatureContext` defines steps twice, and Behat Steps stops the run with an error that says to keep `FeatureContext`.

On a Drupal site, `drupal_root` is the folder that holds Drupal's `index.php`, such as `web` or `docroot`. Write it as a path from the folder you run Behat in. Set `base_url` to the address of that same site, because the browser visits what the Drupal steps create. With `drupal_root` set, the Drupal steps load Drupal inside the test run and create content and users through Drupal's API. Behat Steps calls this way of reaching a site a backend, and [Key concepts](concepts.md#backends-reaching-the-site-without-the-browser) explains backends, Drush included.

`DrupalContext` also checks Drupal's log. Its `WatchdogTrait` fails a scenario when a PHP warning or error is logged between the scenario's start and its end, even one that never shows on the page. `WatchdogTrait` reads the log through the core `dblog` module. Without `dblog` enabled, every scenario fails at its start with this error:

```text
WatchdogTrait requires that the core "dblog" module is enabled, which does not hold. Meet the prerequisite, or switch WatchdogTrait off with the "watchdog.enabled" option or the "@behat-steps-skip:WatchdogTrait" tag.
```

To fix the error, enable `dblog`, or set the `watchdog.enabled` option the error names to `FALSE`. The option goes under `steps` in the `BehatStepsExtension` settings:

```php
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'drupal' => ['drupal_root' => 'web'],
    'steps' => ['watchdog' => ['enabled' => FALSE]],
  ]));
```

[Switch a trait off](guides/usage.md#switch-a-trait-off) covers the other ways to do it. If your site uses a self-signed certificate, [HTTP clients](guides/http-clients.md#configuration-a-staging-site-with-a-self-signed-certificate-behind-dockers-dns) shows the settings that accept it.

## 4. Write your first scenario

A feature file describes 1 feature of your site. Each scenario in it is 1 example of how the feature should behave, written as steps. `Given` sets up what the scenario needs, `When` does something, `Then` checks the result and `And` continues the step before it.

Create `tests/behat/features/homepage.feature` with the following content:

```gherkin
Feature: Homepage

  Scenario: Visitor opens the homepage
    When I visit "/"
    Then the response status code should be 200
    And I should see the heading "Welcome!"
```

Change `Welcome!` to the main heading of your homepage. `I should see the heading` matches a heading's text exactly, so copy the heading as it appears on your homepage.

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

Confirm that the summary reads `1 scenario (1 passed)`. Behat lists the Behat Steps methods under `FeatureContext`, the class you registered. When a step fails, Behat prints why under it and skips the rest of that scenario.

To list every step you can use, run the following command:

```bash
vendor/bin/behat --definitions=l
```

The `--definitions` option takes the following values:

- `l` lists every step's text.
- `i` adds each step's description and the method that runs it.
- Any other value shows the same entries as `i`, for the steps whose text contains that value, as part 7 shows.

The list also holds Mink's basic steps from `MinkContext`. [Web steps](web-steps/README.md) and [Drupal steps](drupal-steps/README.md) show the Behat Steps steps, with an example for each.

## 6. Try a Drupal step

On a site built without Drupal, skip this part.

Your `FeatureContext` extends `DrupalContext`, so it already has the Drupal steps. The following scenario uses the `page` content type and the `administrator` role, which a site installed with Drupal's Standard installation profile has. On a site without them, use a content type and a role your site has.

Create `tests/behat/features/pages.feature` with the following content:

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

The first step takes a table. The table's first row names the fields, and each row after it creates 1 page, here "About us". The second step creates a user with the `administrator` role and logs in through your site's login form. The scenario logs in because a new page might start unpublished, for example on a site that moderates its content. An administrator can see the page either way.

When the scenario ends, whether it passed or failed, Behat Steps deletes the page and the user.

Run `vendor/bin/behat` again, and confirm that the summary reads `2 scenarios (2 passed)`. [Drupal steps](drupal-steps/README.md) lists the rest of the Drupal steps.

## 7. Add a step of your own

To check something no shipped step covers, write a step of your own in `FeatureContext`. Follow 2 rules for every step of your own:

- Give the method a name `FeatureContext` doesn't inherit. Every method a shipped trait adds starts with the trait's name without `Trait`, such as `element` in `elementAssertHeadingExists()`, so a name like `footerShouldMention()` stays clear of them.
- Use step text that no shipped step uses. When 2 step definitions share the same text, Behat stops with an error before it runs the first step. `vendor/bin/behat --definitions="footer"` lists every step whose text contains "footer", so you can check a word before you use it.

To add a step that checks your site's footer:

1. Add the `use Behat\Step\Then;` line and the `footerShouldMention()` method to `FeatureContext.php`:

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

   On a Drupal site, use `use DrevOps\BehatSteps\DrupalContext;` and `extends DrupalContext` in place of the 2 `WebContext` lines.

   The `#[Then(...)]` attribute holds the step's text. `:text` is a placeholder: Behat captures the quoted value in that position and passes it to the method as `$text`. The method calls Mink's `assertSession()`, which fails the step when the page's `footer` element doesn't contain the text. Behat already loads `FeatureContext` through the `autoload` setting, so the step is ready to use.

2. Add a scenario that uses your step to `homepage.feature`, after the first scenario. Replace `Powered by Drupal` with text from your own site's footer:

```gherkin
  Scenario: Visitor sees who runs the site
    When I visit "/"
    Then the footer should mention "Powered by Drupal"
```

Run `vendor/bin/behat` again. The new scenario passes when your footer contains the text. When the footer doesn't, it fails with Mink's message naming the text.

## Where to next

- [Key concepts](concepts.md) explains each piece you used, from contexts to backends.
- [Contexts](guides/contexts.md) covers what each context includes, and [Your own steps in traits of your own](guides/contexts.md#your-own-steps-in-traits-of-your-own) shows how to group your steps when you have more than a few.
- [HELPERS.md](../../HELPERS.md) lists the helpers your own steps can call, the methods the shipped steps are built on. Your project has it at `vendor/drevops/behat-steps/HELPERS.md`.
- [Scenario styles](guides/scenario-styles.md) explains when to write whole scenarios in your site's own words, such as `When I publish a page titled "About us"`.
- [Usage](guides/usage.md) covers configuring the traits and the cleanup after each scenario.
- [Web steps](web-steps/README.md) and [Drupal steps](drupal-steps/README.md) list every step, with an example for each.
- [Configuration reference](guides/configuration-reference.md) lists every option and tag, and [behat.dist.php](../../behat.dist.php) sets every extension option as a reference.
- [Writing tests with AI assistants](guides/ai-assistants.md) points a coding agent at the same reference, and at the skill that writes a step of your own.
