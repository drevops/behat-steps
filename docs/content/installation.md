---
title: Installation
sidebar_position: 2
description: Install Behat Steps with Composer, add the packages only some steps need, and set up a JavaScript browser driver.
---

<!-- cspell:ignore chromedp dmore justinrainbow lullabot softcreatr -->

# Installation

Behat Steps installs with Composer. It needs PHP 8.3 or newer and Behat 3.33 or newer, and its Drupal steps need Drupal 11 or 12. [Supported versions](README.md#supported-versions) lists the full matrix.

## Install the package

```bash
composer require --dev drevops/behat-steps:^4
```

That's the only package you need to require. Behat, Mink with its BrowserKit driver and extension, and the Symfony components the steps use are all requirements of Behat Steps, so Composer brings them in for you. A few steps need 1 more package, which [Optional dependencies](#optional-dependencies) lists.

## Coming from the Drupal Extension

This package replaces `drupal/drupal-extension` and `drupal/drupal-driver`, and it conflicts with both, so Composer won't install it while your project still requires either one. The [migration guide](../../MIGRATION.md#requirements) shows how to remove them.

## Optional dependencies

Some steps need a package the rest don't. Behat Steps lists those under `suggest` in its `composer.json` instead of requiring them, so a suite that doesn't use those steps doesn't install them: no JSON schema validator for a suite that never checks a schema. Run `composer suggests` in your project to see them, and add the ones your suite needs to its `require-dev`:

| Package | What needs it |
| --- | --- |
| `softcreatr/jsonpath` | The JSON path steps of [JSON responses](web-steps/json.md), such as `Then the JSON path :path should exist` |
| `justinrainbow/json-schema` | The JSON schema steps of [JSON responses](web-steps/json.md), such as `Then the response should match the JSON schema in the file :filename` |
| `lullabot/mink-selenium2-driver` | `@javascript` scenarios run through a Selenium server |
| `dmore/behat-chrome-extension` | `@javascript` scenarios run in headless Chrome, with no Selenium server |

The 2 browser drivers do the same job, so if your suite runs `@javascript` scenarios, install 1 of them, as [JavaScript browser drivers](#javascript-browser-drivers) describes. If it uses every JSON step, add both JSON packages:

```bash
composer require --dev softcreatr/jsonpath justinrainbow/json-schema
```

The [Drush](drupal-steps/drush.md) steps also need Drush itself, version 13 or newer, because they run through the `drush` backend.

## JavaScript browser drivers

A scenario tagged `@javascript` runs in a real browser. The steps that need one work with the 2 JavaScript browser drivers this library ships adapters for:

- The Selenium/WebDriver browser driver from `lullabot/mink-selenium2-driver`.
- The Selenium-less `dmore/chrome-mink-driver`, which talks to Chrome directly over the Chrome DevTools Protocol.

The library's own CI tests against both. Any other browser driver needs [an adapter registered for it](../../MIGRATION.md#registering-an-adapter-for-another-browser-driver).

To run `@javascript` scenarios without a Selenium server, add [`dmore/behat-chrome-extension`](https://gitlab.com/behat-chrome/behat-chrome-extension), which pulls in `dmore/chrome-mink-driver`, and point it at a headless Chrome. It accepts Behat 3, and Behat 4 from version 1.5.0, and the library's CI runs it on both.

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

A profile holds 1 entry per extension, so if your profile already registers `MinkExtension`, merge these settings into that entry instead of adding a second one.

Any image that exposes a DevTools endpoint works, such as [`chromedp/headless-shell`](https://hub.docker.com/r/chromedp/headless-shell). To watch the browser while you debug, override `api_url` for a run through Behat's `BEHAT_PARAMS` environment variable, for instance to drive a headed Chrome on your host. That browser has to reach your site's `base_url`.

## Next

[Quick start](quick-start.md) takes you from an empty `behat.php` to a first scenario.
