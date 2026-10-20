---
title: Web helpers
sidebar_label: Overview
sidebar_position: 0
description: What a helper is, how a step of your own calls one, and every trait or class whose helpers work on any website, WebRawContext included.
custom_edit_url: null
---

# Web helpers

Every shipped step is a thin wrapper. It reads its arguments and calls a public method that does the real work: the step `the cookie :name should exist` is 1 line that calls `cookieAssertExists()`. Those public methods are the helpers, and they're yours to call too. A step you write in your `FeatureContext`, or in a trait of your own that it uses, finds every helper on `$this`, exactly as the shipped steps do.

These pages list the helpers that work on any website. The [Drupal helpers](../drupal-helpers/README.md) cover the ones that need a Drupal site.

## A step of your own on a helper

Say your site stores the visitor's cookie choice in a `cookie_policy` cookie. A step that checks it takes 1 line, because `CookieTrait` already knows how to read cookies under every browser:

```php
<?php

declare(strict_types=1);

use Behat\Step\Then;

/**
 * @phpstan-require-extends \DrevOps\BehatSteps\WebContext
 */
trait PrivacyTrait {

  #[Then('the visitor should have accepted the cookie policy')]
  public function privacyAssertCookiePolicyAccepted(): void {
    $this->cookieAssertExists('cookie_policy', 'accepted');
  }

}
```

`FeatureContext` uses the trait with `use PrivacyTrait;`, and the step is ready. The `@phpstan-require-extends` line tells your editor and PHPStan which class the helpers come from. [Contexts](../guides/contexts.md#your-own-steps-in-traits-of-your-own) shows the whole setup.

## What makes a method a helper

- It's `public`. A `protected` method is part of how a trait works inside, and any release can change it. A public one is part of the package's API, so [semantic versioning](../architecture/versioning.md) covers it: it won't change in a way that breaks your code before the next major release.
- Its name starts with the name of its trait, such as `cookieGetAll()` in `CookieTrait`, so 2 traits mixed into 1 context never collide. Keep the methods of your own traits clear of those prefixes and yours won't collide either.
- It isn't a step, a hook or a transform. Those belong to Behat, which calls them for you.

[Root context](web-raw-context.md) lists what `WebRawContext` adds for every context: `backendFor()` to reach the site through a backend, `browserDriverFor()` to use a feature of the browser, the 3 [HTTP clients](../guides/http-clients.md), and `getOption()` and its typed relatives to read a trait's options.

## Reading a helper's page

Each page lists the helpers of 1 trait or class, as signatures. Open one to read what it does, and follow the Source link for the details. [HELPERS.md](../../../HELPERS.md) holds the same list for every helper, web and Drupal, on 1 page. It ships with the package, so an [AI coding agent](../guides/ai-assistants.md) in your project can read it from `vendor/`.

## Every class with web helpers

[//]: # (START_INDEX)

| Name | Class | Helpers | Description |
| --- | --- | --- | --- |
| [Accessibility](accessibility.md) | `AccessibilityTrait` | 21 | Assess accessibility of rendered pages. |
| [Basic authentication](basic-auth.md) | `BasicAuthTrait` | 1 | Keep HTTP basic authentication applied across session resets. |
| [Cookies](cookie.md) | `CookieTrait` | 4 | Verify and inspect browser cookies. |
| [Dropzone uploads](dropzone.md) | `DropzoneTrait` | 1 | Simulate a real multi-file drag-and-drop gesture onto a Dropzone target. |
| [Elements](element.md) | `ElementTrait` | 8 | Interact with HTML elements using CSS selectors and DOM attributes. |
| [Failure diagnostics](diagnostics.md) | `DiagnosticsTrait` | 12 | Append on-failure diagnostics to the failure message of any failed step. |
| [File downloads](file-download.md) | `FileDownloadTrait` | 8 | Test file download functionality with content verification. |
| [Fixture files](fixture-directory.md) | `FixtureDirectoryTrait` | 5 | Resolves fixture files in the directory Mink's `files_path` parameter names. |
| [Form fields](field.md) | `FieldTrait` | 13 | Manipulate form fields and verify widget functionality. |
| [Headings](heading.md) | `HeadingTrait` | 1 | Finds a heading by its text within the page or an element of it. |
| [Iframes](iframe.md) | `IframeTrait` | 1 | Switch between iframes and the root document. |
| [JavaScript errors](javascript.md) | `JavascriptTrait` | 2 | Automatically detect JavaScript errors during test execution. |
| [JSON responses](json.md) | `JsonTrait` | 7 | Assert JSON responses with path and schema checks. |
| [Mapped values](mapping.md) | `MappingTrait` | 3 | Replace `{{ Key }}` tokens in step arguments and table cells. |
| [Messages](message.md) | `MessageTrait` | 3 | Assert status, error, warning and success messages rendered on the page. |
| [Meta tags](metatag.md) | `MetatagTrait` | 14 | Assert `<meta>` tags and head/SEO markup in page markup. |
| [Modals](modal.md) | `ModalTrait` | 9 | Interact with and assert modals. |
| [Page regions](region.md) | `RegionTrait` | 4 | Interact with and assert against named page regions. |
| [Paths](path.md) | `PathTrait` | 4 | Navigate and verify paths with URL validation. |
| [Random values](random.md) | `RandomTrait` | 10 | Replace random-value tokens in step arguments and table cells. |
| [Relative dates](date.md) | `DateTrait` | 3 | Convert relative date expressions into timestamps or formatted dates. |
| [Request headers](request-headers.md) | `RequestHeadersTrait` | 1 | Holds the request headers shared by the traits that issue HTTP requests. |
| [Responsive layouts](responsive.md) | `ResponsiveTrait` | 7 | Test responsive layouts with viewport control. |
| [REST APIs](rest.md) | `RestTrait` | 2 | Test REST APIs with lightweight steps. |
| [Root context](web-raw-context.md) | `WebRawContext` | 21 | Root context carrying the plumbing every suite needs. |
| [Shell commands](command.md) | `CommandTrait` | 2 | Run local shell commands and assert on their result. |
| [Tables](table.md) | `TableTrait` | 10 | Interact with HTML table elements and assert their content. |
| [Vertical tables](table-transpose.md) | `TableTransposeTrait` | 2 | Reads a vertical Gherkin table as 1 set of values per entity. |
| [Waits](wait.md) | `WaitTrait` | 2 | Wait for a period of time or for AJAX to finish. |
| [XML responses](xml.md) | `XmlTrait` | 9 | Assert XML responses with element and attribute checks. |

[//]: # (END_INDEX)
