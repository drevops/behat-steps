---
title: Web steps
sidebar_label: Overview
sidebar_position: 0
description: The steps that work on any website, how to read a trait's page and find a step, and every web trait with what it covers.
custom_edit_url: null
---

# Web steps

Web steps work on any website, whatever it's built with. They visit pages, fill in and submit forms, follow links and read cookies and response headers. They also check JSON, XML and tables, all through the browser Mink drives. `WebContext` brings every one of them, and `DrupalContext` extends `WebContext`, so a `FeatureContext` built on either already has them all. [Contexts](../guides/contexts.md) explains which one to extend.

The steps come grouped into traits, 1 per job, such as cookies, form fields or JSON responses. The table at the end of this page lists every trait and what it covers, and each name opens the trait's page.

## How a trait's page reads

The page opens with the trait's class and its links: the source, the feature file that tests it and its helpers in `HELPERS.md`. A description of what the trait does follows. Then, where the trait has them:

- Prerequisites: what the site has to provide before the trait's steps can run.
- Options: the settings the trait reads, with their defaults and the tags that change them for 1 scenario. [Configure a trait](../guides/usage.md#configure-a-trait) shows where to set them.
- Steps: every step of the trait, each under its `Given`, `When` or `Then` keyword. Open a step to see what it does and an example you can copy into a scenario.

A few steps need a real browser, because they run the page's JavaScript, press keys or resize the window. They fail under the BrowserKit session the quick start sets up, and [Browsers](../guides/browsers.md) covers the browsers that run them.

## Find a step

You can find a step in 3 ways:

- Search the site with the box at the top of the page.
- Run `vendor/bin/behat --definitions=l` in your project. It lists every step your suite registers, Mink's and your own included.
- Open [STEPS.md](../../../STEPS.md), which holds these steps and the [Drupal steps](../drupal-steps/README.md) on 1 page. It ships with the package, so an [AI coding agent](../guides/ai-assistants.md) in your project can read it from `vendor/`.

## Every web trait

[//]: # (START_INDEX)

| Name | Trait | Description |
| --- | --- | --- |
| [Accessibility](accessibility.md) | `AccessibilityTrait` | Assess accessibility of rendered pages. |
| [Basic authentication](basic-auth.md) | `BasicAuthTrait` | Keep HTTP basic authentication applied across session resets. |
| [Cookies](cookie.md) | `CookieTrait` | Verify and inspect browser cookies. |
| [Dropzone uploads](dropzone.md) | `DropzoneTrait` | Simulate a real multi-file drag-and-drop gesture onto a Dropzone target. |
| [Elements](element.md) | `ElementTrait` | Interact with HTML elements using CSS selectors and DOM attributes. |
| [Failure diagnostics](diagnostics.md) | `DiagnosticsTrait` | Append on-failure diagnostics to the failure message of any failed step. |
| [File downloads](file-download.md) | `FileDownloadTrait` | Test file download functionality with content verification. |
| [Form fields](field.md) | `FieldTrait` | Manipulate form fields and verify widget functionality. |
| [Iframes](iframe.md) | `IframeTrait` | Switch between iframes and the root document. |
| [JavaScript errors](javascript.md) | `JavascriptTrait` | Automatically detect JavaScript errors during test execution. |
| [JSON responses](json.md) | `JsonTrait` | Assert JSON responses with path and schema checks. |
| [Keyboard](keyboard.md) | `KeyboardTrait` | Simulate keyboard interactions in the browser. |
| [Links](link.md) | `LinkTrait` | Verify link elements with attribute and content assertions. |
| [Mapped values](mapping.md) | `MappingTrait` | Replace `{{ Key }}` tokens in step arguments and table cells. |
| [Messages](message.md) | `MessageTrait` | Assert status, error, warning and success messages rendered on the page. |
| [Meta tags](metatag.md) | `MetatagTrait` | Assert `<meta>` tags and head/SEO markup in page markup. |
| [Modals](modal.md) | `ModalTrait` | Interact with and assert modals. |
| [Page regions](region.md) | `RegionTrait` | Interact with and assert against named page regions. |
| [Paths](path.md) | `PathTrait` | Navigate and verify paths with URL validation. |
| [Random values](random.md) | `RandomTrait` | Replace random-value tokens in step arguments and table cells. |
| [Relative dates](date.md) | `DateTrait` | Convert relative date expressions into timestamps or formatted dates. |
| [Response headers](response.md) | `ResponseTrait` | Verify HTTP response headers. |
| [Responsive layouts](responsive.md) | `ResponsiveTrait` | Test responsive layouts with viewport control. |
| [REST APIs](rest.md) | `RestTrait` | Test REST APIs with lightweight steps. |
| [Shell commands](command.md) | `CommandTrait` | Run local shell commands and assert on their result. |
| [Tables](table.md) | `TableTrait` | Interact with HTML table elements and assert their content. |
| [Waits](wait.md) | `WaitTrait` | Wait for a period of time or for AJAX to finish. |
| [XML responses](xml.md) | `XmlTrait` | Assert XML responses with element and attribute checks. |

[//]: # (END_INDEX)
