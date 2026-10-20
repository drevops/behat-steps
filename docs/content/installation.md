---
title: Installation
sidebar_position: 2
description: Install Behat Steps with Composer, add the packages only some steps need, and set up a browser driver for scenarios that need JavaScript.
---

<!-- cspell:ignore chromedp dmore justinrainbow lullabot softcreatr -->

# Installation

Follow this page to install Behat Steps in your PHP project, add the packages only some steps need and set up a real browser for scenarios that run JavaScript. It doesn't write a scenario: the [Quick start](quick-start.md) does that.

## Before you begin

Check the following before you start:

- PHP 8.3 or newer, and Composer.
- Behat 3, from 3.33, or Behat 4, or no Behat at all yet, since Composer installs it with Behat Steps.
- Drupal 11 or 12, if you want the Drupal steps.
- Neither `drupal/drupal-extension` nor `drupal/drupal-driver`. Behat Steps does their job itself and declares a conflict with both, so Composer won't install it while either one is installed, whether your project requires it or another package pulls it in. The [Requirements](../../MIGRATION.md#requirements) section of the migration guide shows the command that removes them, for any project, and the rest of the guide lists what replaces their steps and classes.

[Supported versions](README.md#supported-versions) lists the full matrix.

## Install the package

Run this in your project's root:

```bash
composer require --dev drevops/behat-steps:^4
```

That's the only package you need to require. Composer installs the rest with it, because Behat Steps requires them:

- Behat, as `behat/behat`
- Mink, the library Behat uses to drive a browser, as `behat/mink`
- Mink's BrowserKit driver, as `behat/mink-browserkit-driver`
- Mink's Behat extension, as `friends-of-behat/mink-extension`
- The Symfony components the steps use

Confirm that Composer installed it: `composer show drevops/behat-steps` prints the release it installed and the packages it requires.

A few steps need 1 more package each, which [Optional dependencies](#optional-dependencies) lists.

## Optional dependencies

Behat Steps lists 4 packages under `suggest` in its `composer.json` rather than requiring them, because only some steps need them, so you don't install what your suite never uses. `composer suggests drevops/behat-steps` lists the ones your project doesn't have yet. Add the ones your suite needs to its `require-dev`:

| Package | What needs it |
| --- | --- |
| `softcreatr/jsonpath` | The JSON path steps of [JSON responses](web-steps/json.md), such as `Then the JSON path :path should exist` |
| `justinrainbow/json-schema` | The JSON schema steps of [JSON responses](web-steps/json.md), such as `Then the response should match the JSON schema in the file :filename` |
| `lullabot/mink-selenium2-driver` | `@javascript` scenarios run through a Selenium server |
| `dmore/behat-chrome-extension` | `@javascript` scenarios run in headless Chrome, with no Selenium server |

The Selenium driver and the Chrome extension for Behat, `dmore/behat-chrome-extension`, do the same job, so a suite that runs `@javascript` scenarios installs 1 of them, and a suite without any installs neither, as [JavaScript browser drivers](#javascript-browser-drivers) describes. A suite that uses every JSON step adds both JSON packages:

```bash
composer require --dev softcreatr/jsonpath justinrainbow/json-schema
```

## JavaScript browser drivers

Mink, the library Behat uses to drive a browser, talks to the browser through a browser driver. The BrowserKit driver that comes with Behat Steps loads pages over plain HTTP from PHP: it's fast, but it never runs the page's JavaScript. When a scenario needs JavaScript, tag it `@javascript`, and Mink runs it in a real browser through a second driver instead.

The steps that need a real browser work with the 2 browser drivers this library ships adapters for, the classes that declare what each driver can do: the Selenium/WebDriver browser driver from `lullabot/mink-selenium2-driver`, or the Selenium-less `dmore/chrome-mink-driver`, which talks to Chrome directly over the Chrome DevTools Protocol. This library's own CI tests against both. [Browsers](guides/browsers.md) compares them with BrowserKit and lists the steps that need a real browser. Another browser driver needs [an adapter registered for it](../../MIGRATION.md#registering-an-adapter-for-another-browser-driver).

### Run JavaScript scenarios in headless Chrome

These steps change the `behat.php` the [Quick start](quick-start.md) creates, so if your project has none yet, start there and come back. To run `@javascript` scenarios in headless Chrome, with no Selenium server, follow these steps:

1. Add [`dmore/behat-chrome-extension`](https://gitlab.com/behat-chrome/behat-chrome-extension), which pulls in `dmore/chrome-mink-driver`. It accepts Behat 3 and, from 1.5.0, Behat 4, and this library's own CI runs it on both:

   ```bash
   composer require --dev dmore/behat-chrome-extension
   ```

2. In `behat.php`, register its extension, and give Mink a `chrome` session that points at a headless Chrome beside the `browserkit_http` session the [Quick start](quick-start.md) sets up. `$profile` is the `default` profile of that file:

   ```php
   use Behat\Config\Extension;
   use Behat\MinkExtension\ServiceContainer\MinkExtension;
   use DMore\ChromeExtension\Behat\ServiceContainer\ChromeExtension;

   $profile
     ->withExtension(new Extension(ChromeExtension::class))
     ->withExtension(new Extension(MinkExtension::class, [
       'base_url' => 'http://your-site.local',
       'javascript_session' => 'chrome',
       'sessions' => [
         'browserkit_http' => ['browserkit_http' => NULL],
         'chrome' => ['chrome' => ['api_url' => 'http://chrome:9222']],
       ],
     ]));
   ```

   This is the whole `MinkExtension` entry. A profile takes each extension once, and Behat stops with an error saying the extension already exists when a profile registers it twice. So if your profile already registers `MinkExtension`, add `javascript_session` and the `chrome` session to that entry, and keep your own `base_url` and sessions.

   Mink runs every scenario tagged `@javascript` on the `javascript_session`, and every other scenario on the first session that can't run JavaScript, `browserkit_http` here. Only the tagged scenarios run in Chrome.

   In `'chrome' => ['chrome' => [...]]`, the first `chrome` names the session, which `javascript_session` points at, and the second picks the Chrome driver, whose settings follow. `api_url` is the address of Chrome's DevTools endpoint. In `http://chrome:9222`, `chrome` is the host name Chrome listens on, such as the name of its Docker Compose service, and 9222 is its port, so replace both with your own. Chrome loads `base_url` itself, so set it to an address Chrome can reach, as step 3 explains.

3. Start a headless Chrome that Behat reaches at `api_url` and that reaches your `base_url` in turn. Any image that exposes a DevTools endpoint works, such as [`chromedp/headless-shell`](https://hub.docker.com/r/chromedp/headless-shell). When your site runs in Docker Compose, a service like this one starts a headless Chrome beside it:

   ```yaml
   services:
     chrome:
       image: chromedp/headless-shell:latest
       shm_size: 1gb
   ```

   A container in the same Compose project, such as the one Behat runs in, reaches it at `http://chrome:9222`, the `api_url` in step 2. Chrome reaches the site by its service name in turn, so `base_url` uses that name and the port the site listens on inside the Compose network, such as `http://nginx:8080`, rather than a port published to your machine.

   `shm_size` gives Chrome more shared memory than Docker's 64 MB default, which a large page can run out of. This repository runs the same image as its `chrome_headless` service in [docker-compose.yml](../../docker-compose.yml), which its own tests reach at `http://chrome_headless:9222`.

Confirm that Behat reaches Chrome the first time you run a scenario tagged `@javascript`. An error that starts `Could not fetch version information from http://chrome:9222/json/version` means Behat can't reach `api_url`.

### Watch the browser in a headed Chrome

When Behat runs on your machine, you can watch the browser in a headed Chrome, one with a window, instead of a headless one. These steps build on the `chrome` session, the `ChromeExtension` registration and the `use` lines from [Run JavaScript scenarios in headless Chrome](#run-javascript-scenarios-in-headless-chrome). To set it up, follow these steps:

1. Start Chrome on your machine with `--remote-debugging-port=9222` and a `--user-data-dir` of its own, since Chrome 136 and newer ignore the port on your everyday profile.
2. In `behat.php`, add a profile that points `api_url` at that Chrome, and replace the file's `return` line with the snippet's, which registers both profiles:

   ```php
   use Behat\Config\Config;
   use Behat\Config\Profile;

   // Runs with "vendor/bin/behat -p headed".
   $headed = (new Profile('headed'))
     ->withExtension(new Extension(MinkExtension::class, [
       'sessions' => [
         'chrome' => ['chrome' => ['api_url' => 'http://localhost:9222']],
       ],
     ]));

   return (new Config())->withProfile($profile)->withProfile($headed);
   ```

   A profile other than `default` starts from `default` and overrides only what it sets, so registering `MinkExtension` again in `headed`, a profile of its own, is fine, and the headed run keeps every other setting. `BEHAT_PARAMS` can't make this change, because it can't override a setting `behat.php` makes, as [Environment variables](guides/configuration.md#4-environment-variables) explains.

3. Run Behat with that profile: `vendor/bin/behat -p headed`. Behat reaches the Chrome at `http://localhost:9222`, and the Chrome has to reach your site's `base_url` in turn. Chrome loads the pages of each `@javascript` scenario in a window of its own.

When Behat runs in a container, `localhost` is the container itself, and a Chrome on your machine can't open a Compose address such as `http://nginx:8080`. To watch a browser in that setup, run `@javascript` scenarios through Selenium on an image that shows its browser in a web page, such as `selenium/standalone-chromium`, as [Run JavaScript scenarios through Selenium](#run-javascript-scenarios-through-selenium) shows. Open the port its step 3 publishes in your own browser, with the image's default password, `secret`.

### Run JavaScript scenarios through Selenium

To run `@javascript` scenarios through a Selenium server instead, follow these steps:

1. Add `lullabot/mink-selenium2-driver`:

   ```bash
   composer require --dev lullabot/mink-selenium2-driver
   ```

   If your project requires the original, `behat/mink-selenium2-driver`, remove it and require the fork instead, since the fork replaces it and a project can't require both:

   ```bash
   composer remove --dev behat/mink-selenium2-driver
   composer require --dev lullabot/mink-selenium2-driver
   ```

2. In `behat.php`, give Mink a `selenium2` session that points at your Selenium server, and set `javascript_session` to it:

   ```php
   $profile
     ->withExtension(new Extension(MinkExtension::class, [
       'base_url' => 'http://your-site.local',
       'browser_name' => 'chrome',
       'javascript_session' => 'selenium2',
       'sessions' => [
         'browserkit_http' => ['browserkit_http' => NULL],
         'selenium2' => ['selenium2' => ['wd_host' => 'http://selenium:4444/wd/hub']],
       ],
     ]));
   ```

   `wd_host` is the Selenium server's address, where `selenium` is its host name, such as the name of its Docker Compose service, and 4444 the port `selenium/standalone-chromium` listens on. `browser_name` is the browser it starts. The browser loads `base_url` itself, so set it to an address that browser can reach. [behat.dist.php](../../behat.dist.php) sets the session up the same way.

3. Start a Selenium server that Behat reaches at `wd_host`. When your site runs in Docker Compose, a service like this one starts it beside the site:

   ```yaml
   services:
     selenium:
       image: selenium/standalone-chromium:latest
       shm_size: 1gb
       ports:
         - "7900"
   ```

   `ports` publishes the page that shows the browser, on its port 7900, to a free port of your machine, which `docker compose port selenium 7900` prints. Leave it out when you don't watch the browser. This repository's Selenium service in [docker-compose.yml](../../docker-compose.yml) runs the same image, which its own tests reach at `http://chrome:4444/wd/hub`.

`lullabot/mink-selenium2-driver` is a fork of `behat/mink-selenium2-driver` that works with Selenium 4. The original, in every release up to and including 1.7.0, opens a session through the JSON Wire Protocol, which Selenium 4 dropped, so a Selenium 4 server refuses it with `Could not start a new session. No nodes support the capabilities in the request`. The fork speaks the W3C WebDriver protocol Selenium 4 expects.

The fork provides the same `Selenium2Driver` class and declares that it replaces the original, so Composer installs only 1 of them. A Drupal project that requires `drupal/core-dev` already has the fork, because `core-dev` requires it. `composer show lullabot/mink-selenium2-driver` prints the fork's details when your project has it, and `Package "lullabot/mink-selenium2-driver" not found` when it doesn't.

## Where to next

- The [Quick start](quick-start.md) takes you from an empty `behat.php` to a passing scenario.
- If your suite already has contexts of its own, [Contexts](guides/contexts.md) shows which Behat Steps context your `FeatureContext` extends and where your own steps go.
