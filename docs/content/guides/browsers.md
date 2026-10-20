---
title: Browsers
sidebar_position: 5
description: The 3 browsers Behat Steps works with, BrowserKit, Selenium2 and Chrome, what each one can do, which steps need which and how a scenario picks one.
---

<!-- cspell:ignore chromedp dmore lullabot -->

# Browsers

Every step that touches a page goes through Mink, the library Behat uses to drive a browser. Mink runs each of its sessions on a browser driver, and Behat Steps works with 3 of them: BrowserKit, Selenium2 and Chrome. They don't all do the same things, so the one a scenario runs on decides which steps it can use. This page compares the 3 and shows how a scenario picks one.

## BrowserKit

BrowserKit isn't a browser at all. It sends HTTP requests from PHP and reads the HTML that comes back, the way a crawler would, so it's quick and needs nothing besides PHP. It never runs the page's JavaScript, though, and there's no window to resize and no keyboard. It comes with Behat Steps, and the `browserkit_http` session in the [quick start](../quick-start.md) uses it.

## Selenium2

The Selenium2 browser driver, from `lullabot/mink-selenium2-driver`, controls a real browser, such as Chrome or Firefox, through a Selenium server. The page runs as it would for a visitor, JavaScript included, so you need a Selenium server and a browser for it to drive, usually as Docker containers.

## Chrome

The Chrome browser driver, from `dmore/chrome-mink-driver`, controls Chrome directly over the Chrome DevTools Protocol, with no Selenium server in between. Chrome usually runs headless, with no window on screen, and any image that exposes a DevTools endpoint works, such as `chromedp/headless-shell`. `dmore/behat-chrome-extension` registers it with Mink, and [Installation](../installation.md#javascript-browser-drivers) shows the settings.

## What each one can do

A step never names a browser. It requests a capability, such as running JavaScript, and Behat Steps checks it against an adapter that declares what each browser driver provides. Here's what the 3 adapters declare:

| Capability | BrowserKit | Selenium2 | Chrome |
| --- | --- | --- | --- |
| Run the page's JavaScript | No | Yes | Yes |
| Press keys | No | Yes | Yes |
| Read cookies | Yes | Yes | Yes |
| Set request headers | Yes | No | Yes |
| Lend its HTTP client to a step | Yes | No | No |

Here's what needs each one:

- Running the page's JavaScript: the AJAX waits, iframes, modals, drag-and-drop uploads and some form widgets.
- Pressing keys: the [Keyboard](../web-steps/keyboard.md) steps.
- Reading cookies: the [Cookies](../web-steps/cookie.md) steps, and the detached [HTTP client](http-clients.md), which sends the visitor's cookies.
- Setting request headers: the header [Config overrides](../drupal-steps/config-override.md) sends, which a Selenium2 session goes without.
- Lending its HTTP client to a step: the [REST APIs](../web-steps/rest.md) steps, whose response becomes the page.

A step that needs a capability the session's browser lacks fails with an `UnsupportedDriverActionException` naming it. A few checks switch themselves off instead: the JavaScript error check of [JavaScript errors](../web-steps/javascript.md) does on a browser that can't run JavaScript, so a BrowserKit scenario never fails for it.

Some steps ask Mink directly for something only a real browser has, such as resizing the window for [Responsive layouts](../web-steps/responsive.md). Under BrowserKit, Mink reports that it can't.

Selenium2 sends its key presses as synthetic events, through the JavaScript library Selenium2's browser driver bundles, while Chrome sends real key events. Both work for the keyboard steps.

## How a scenario picks a browser

A suite usually runs most scenarios on BrowserKit, because it's fast, and only the scenarios that need a real browser on Selenium2 or Chrome. Mink's settings say which session is which:

- `default_session` runs every scenario that isn't tagged `@javascript`. Left out, it's the first session whose browser can't run JavaScript, such as `browserkit_http`.
- `javascript_session` runs every scenario tagged `@javascript`. Left out, it's the first session whose browser can.

Naming both, as here, saves anyone reading the file from working it out:

```php
->withExtension(new Extension(MinkExtension::class, [
  'base_url' => 'http://your-site.local',
  'default_session' => 'browserkit_http',
  'javascript_session' => 'chrome',
  'sessions' => [
    'browserkit_http' => ['browserkit_http' => NULL],
    'chrome' => ['chrome' => ['api_url' => 'http://chrome:9222']],
  ],
]))
```

Then tag only the scenarios that need JavaScript:

```gherkin
@javascript
Scenario: Visitor opens the menu
  When I visit "/"
  And I click on the element "button.menu-toggle"
  Then the element "nav.main-menu" should be displayed
```

The Chrome session also needs `ChromeExtension` registered beside Mink's extension, as [Installation](../installation.md#javascript-browser-drivers) shows.

## Choose between Selenium2 and Chrome

They do the same job for a JavaScript scenario, so a suite installs 1 of them:

- Pick Chrome for the shorter setup: it needs no Selenium server, and it can set request headers. This library's own CI runs it on both Behat 3 and Behat 4.
- Pick Selenium2 when you need a browser other than Chrome, such as Firefox, or already run a Selenium grid.

A browser driver beyond these 3 works after you register an adapter that declares its capabilities, as [Registering an adapter for another browser driver](../../../MIGRATION.md#registering-an-adapter-for-another-browser-driver) shows.
