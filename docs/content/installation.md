---
title: Installation
sidebar_position: 2
description: Install Behat Steps with Composer, add the packages only some steps need, and set up a JavaScript browser driver.
---

<!-- cspell:ignore chromedp dmore justinrainbow lullabot softcreatr -->

# Installation

Behat Steps is a Composer package. It needs PHP 8.3 or newer and Behat 3.33 or newer, and its Drupal steps need Drupal 11 or 12. [Supported versions](README.md#supported-versions) lists the full matrix.

## Install the package

```bash
composer require --dev drevops/behat-steps:^4
```

That's the only package you need to require. Behat, Mink with its BrowserKit driver and extension, and the Symfony components the steps use are all requirements of Behat Steps, so Composer installs them along with it. A few steps need 1 more package, which [Optional dependencies](#optional-dependencies) lists.

## Coming from the Drupal Extension

This package replaces `drupal/drupal-extension` and `drupal/drupal-driver` and conflicts with both, so Composer won't install it while your project still requires either one. The [migration guide](../../MIGRATION.md#requirements) shows how to remove them.

## Optional dependencies

Behat Steps lists the packages that only some steps need under `suggest` in its `composer.json` rather than requiring them, so a suite that doesn't use those steps doesn't install them. `composer suggests` lists them from your project. Add the ones your suite needs to its `require-dev`:

| Package | What needs it |
| --- | --- |
| `softcreatr/jsonpath` | The JSON path steps of [JSON responses](web-steps/json.md), such as `Then the JSON path :path should exist` |
| `justinrainbow/json-schema` | The JSON schema steps of [JSON responses](web-steps/json.md), such as `Then the response should match the JSON schema in the file :filename` |
| `lullabot/mink-selenium2-driver` | `@javascript` scenarios run through a Selenium server |
| `dmore/behat-chrome-extension` | `@javascript` scenarios run in headless Chrome, with no Selenium server |

The 2 browser drivers do the same job, so a suite that runs `@javascript` scenarios installs 1 of them, as [JavaScript browser drivers](#javascript-browser-drivers) describes. A suite that uses every JSON step adds both JSON packages:

```bash
composer require --dev softcreatr/jsonpath justinrainbow/json-schema
```

## JavaScript browser drivers

Scenarios tagged `@javascript` use a real browser, and the steps that need one work with the 2 browser drivers this library ships adapters for: the Selenium/WebDriver browser driver from `lullabot/mink-selenium2-driver`, or the Selenium-less `dmore/chrome-mink-driver`, which talks to Chrome directly over the Chrome DevTools Protocol. This library's own CI tests against both. Another browser driver needs [an adapter registered for it](../../MIGRATION.md#registering-an-adapter-for-another-browser-driver).

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

A profile holds 1 entry per extension, so if your profile already registers `MinkExtension`, merge these settings into that entry rather than adding a second one.

Any image that exposes a DevTools endpoint works, such as [`chromedp/headless-shell`](https://hub.docker.com/r/chromedp/headless-shell). For local visual debugging, you can override `api_url` at runtime via Behat's `BEHAT_PARAMS` environment variable, for instance to drive a headed Chrome on your host, as long as that browser can reach your site's `base_url`.

## Next

[Quick start](quick-start.md) takes you from an empty `behat.php` to a first scenario.
