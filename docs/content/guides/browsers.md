---
title: Browsers
sidebar_position: 5
description: The 3 browsers Behat Steps works with, BrowserKit, Selenium2 and Chrome, what each one can do, which steps need which and how a scenario picks one.
---

<!-- cspell:ignore chromedp dmore lullabot -->

# Browsers

Every step that touches a page goes through Mink, the library Behat uses to drive a browser. Mink runs each of its sessions on a browser driver, and Behat Steps works with 3 of them: BrowserKit, Selenium2 and Chrome. The 3 can do different things, so the one a scenario runs on decides which steps it can use. This page compares them and shows how a scenario picks one. [Installation](../installation.md#javascript-browser-drivers) covers setting up Selenium2 or Chrome.

## BrowserKit

BrowserKit isn't a browser at all. It sends HTTP requests from PHP and reads the HTML that comes back, the way a crawler does. That makes it quick, and it needs nothing besides PHP. It never runs the page's JavaScript, and it has no window to resize and no keyboard. It comes with Behat Steps, and the `browserkit_http` session in the [quick start](../quick-start.md) uses it.

## Selenium2

The Selenium2 browser driver comes from `lullabot/mink-selenium2-driver`. It controls a real browser, such as Chrome or Firefox, through a Selenium server. The page runs as it would for a visitor, JavaScript included. So you need a Selenium server and a browser for it to drive, usually as Docker containers.

That package is a fork of `behat/mink-selenium2-driver` that speaks the W3C WebDriver protocol, and [Installation](../installation.md#run-javascript-scenarios-through-selenium) shows how to switch to it.

## Chrome

The Chrome browser driver comes from `dmore/chrome-mink-driver`. It controls Chrome directly over the Chrome DevTools Protocol, with no Selenium server in between. Chrome usually runs headless, with no window on screen. Any image that exposes a DevTools endpoint works, such as `chromedp/headless-shell`. `dmore/behat-chrome-extension` registers the driver with Mink, and [Installation](../installation.md#run-javascript-scenarios-in-headless-chrome) shows the settings.

## What each one can do

A step never names a browser. It requests a capability, such as running JavaScript. Behat Steps checks the capability against an adapter, a class that declares what 1 browser driver provides. Here's what the 3 adapters declare:

| Capability | BrowserKit | Selenium2 | Chrome |
| --- | --- | --- | --- |
| Run the page's JavaScript | No | Yes | Yes |
| Press keys | No | Yes | Yes |
| Read cookies | Yes | Yes | Yes |
| Set request headers | Yes | No | Yes |
| Lend its HTTP client to a step | Yes | No | No |

These steps and hooks need each capability:

- Running the page's JavaScript: the AJAX waits, iframes, modals, drag-and-drop uploads and some form widgets.
- Pressing keys: the [Keyboard](../web-steps/keyboard.md) steps.
- Reading cookies: the [Cookies](../web-steps/cookie.md) steps, and the detached [HTTP client](http-clients.md), which sends the visitor's cookies.
- Setting request headers: the `X-Config-No-Override` header that [Config overrides](../drupal-steps/config-override.md) sends.
- Lending its HTTP client to a step: the [REST APIs](../web-steps/rest.md) steps, whose response becomes the page.

A step that needs a capability the session's browser lacks fails with an `UnsupportedDriverActionException` naming the capability. Some steps ask Mink directly for something only a real browser has, such as resizing the window for [Responsive layouts](../web-steps/responsive.md). Under BrowserKit, Mink throws the same exception for those steps.

A few hooks and steps carry on when the browser lacks a capability, skipping or replacing the part that needs it:

- The JavaScript error check of [JavaScript errors](../web-steps/javascript.md) doesn't run on a browser that can't run JavaScript, so a BrowserKit scenario never fails for it.
- The `@disable-form-validation` tag and the `the browser validation for the form :selector is disabled` step of [Form fields](../web-steps/field.md) change nothing on a browser that can't run JavaScript, because that browser doesn't validate forms either.
- `I fill in the WYSIWYG field :field with :value`, also of [Form fields](../web-steps/field.md), sets the field's value directly on a browser that can't run JavaScript, rather than through its editor.
- The `X-Config-No-Override` header of [Config overrides](../drupal-steps/config-override.md) isn't sent on a browser that can't set request headers.

Selenium2 sends key presses as synthetic events, through a JavaScript library its browser driver bundles. Chrome sends real key events. Both work for the keyboard steps.

## How a scenario picks a browser

A suite usually runs most scenarios on BrowserKit, because it's fast, and only the scenarios that need a real browser on Selenium2 or Chrome. 2 Mink settings name the session each scenario runs on:

- `default_session` names the session for every scenario that isn't tagged `@javascript`. Left out, it's the first session whose browser can't run JavaScript, such as `browserkit_http`, or the first session when every browser can.
- `javascript_session` names the session for every scenario tagged `@javascript`. Left out, it's the first session whose browser can run JavaScript.

Name both in `behat.php`, so anyone reading it sees which session runs which scenarios:

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

The Chrome session also needs `ChromeExtension` registered beside Mink's extension, as [Installation](../installation.md#run-javascript-scenarios-in-headless-chrome) shows.

## Choose between Selenium2 and Chrome

Selenium2 and Chrome do the same job for a JavaScript scenario, so a suite installs 1 of them:

- Pick Chrome for the shorter setup, with no Selenium server, or when a step sets request headers. This library's own CI runs it on both Behat 3 and Behat 4.
- Pick Selenium2 when you need a browser other than Chrome, such as Firefox, or already run a Selenium grid.

A browser driver beyond these 3 works after you register an adapter that declares its capabilities, as [Registering an adapter for another browser driver](../../../MIGRATION.md#registering-an-adapter-for-another-browser-driver) shows.
