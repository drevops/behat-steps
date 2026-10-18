---
title: Installation
sidebar_position: 2
description: Install Behat Steps with Composer on Behat 3 or Behat 4, add the packages only some traits need, and set up a JavaScript browser driver.
---

<!-- cspell:ignore chromedp dmore justinrainbow lullabot softcreatr -->

# Installation

Behat Steps is a Composer package. It needs PHP 8.3 or newer and Behat 3.33 or newer, and its Drupal steps need Drupal 11 or 12. [Supported versions](README.md#supported-versions) lists the full matrix.

## Install the package

```bash
composer require --dev drevops/behat-steps:^4
```

With `minimum-stability: stable`, Composer's default, that installs Behat 3. Behat 4 needs `friends-of-behat/mink-extension` 3, which has only an alpha release so far, and Composer takes a stability flag such as `@alpha` only from your own `composer.json`. To install on Behat 4, ask for both:

```bash
composer require --dev drevops/behat-steps:^4 behat/behat:^4 \
  friends-of-behat/mink-extension:^3.0@alpha
```

A project whose `minimum-stability` already admits alpha releases can get Behat 4 from the first command, but nothing in it pins the major. Require `behat/behat:^4`, as above, when the suite needs Behat 4.

## Coming from the Drupal Extension

This package replaces `drupal/drupal-extension` and `drupal/drupal-driver` and conflicts with both, so Composer won't install it while your project still requires either one. The [migration guide](../../MIGRATION.md#requirements) shows how to remove them.

## Optional dependencies

To keep installs lean, packages needed by only some traits are declared as `suggest` rather than hard requirements. The hard requirements are Behat, Mink with its BrowserKit driver and extension, and a set of Symfony components. Add the suggested packages for the traits you use to your project's `require-dev` - run `composer suggests` to list them:

- **`JsonTrait`** needs `softcreatr/jsonpath` for JSON path steps and `justinrainbow/json-schema` for JSON schema steps.
- **`@javascript` scenarios** need a JavaScript-capable browser driver, as [JavaScript browser drivers](#javascript-browser-drivers) describes.

## JavaScript browser drivers

Steps that require a real browser, which scenarios tagged `@javascript` use, work with the 2 browser drivers this library ships adapters for: the Selenium/WebDriver browser driver from `lullabot/mink-selenium2-driver`, or the Selenium-less `dmore/chrome-mink-driver`, which talks to Chrome directly over the Chrome DevTools Protocol. Both are exercised by this library's own CI. Another browser driver needs [an adapter registered for it](../../MIGRATION.md#registering-an-adapter-for-another-browser-driver).

To run `@javascript` scenarios without a Selenium server, add [`dmore/behat-chrome-extension`](https://gitlab.com/behat-chrome/behat-chrome-extension), which pulls in `dmore/chrome-mink-driver`, and point it at a headless Chrome. It accepts Behat 3 and, from 1.5.0, Behat 4, and this library's own CI runs it on both.

```php
use Behat\Config\Extension;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use DMore\ChromeExtension\Behat\ServiceContainer\ChromeExtension;

$profile
  ->withExtension(new Extension(ChromeExtension::class))
  ->withExtension(new Extension(MinkExtension::class, [
    'browser_name' => 'chrome',
    'javascript_session' => 'chrome',
    'sessions' => [
      'chrome' => ['chrome' => ['api_url' => 'http://chrome:9222']],
    ],
  ]));
```

A profile holds 1 entry per extension, so a profile that already registers `MinkExtension` merges these settings into that entry rather than adding a second one.

Any image that exposes a DevTools endpoint works, such as [`chromedp/headless-shell`](https://hub.docker.com/r/chromedp/headless-shell). For local visual debugging you can override `api_url` at runtime via Behat's `BEHAT_PARAMS` environment variable - for instance to drive a headed Chrome on your host - as long as that browser can reach your site's `base_url`.

## Next

[Quick start](quick-start.md) takes you from an empty `behat.php` to a first scenario.
