---
title: Web steps
description: The steps that work on any website, with a page per trait that gives each step an example and lists the trait's options and prerequisites.
custom_edit_url: null
---

# Web steps

These steps work on any website, whatever it's built with. `WebContext` composes all of them, and so does `DrupalContext`, which extends it. A trait's page gives each of its steps an example and lists the options and the prerequisites the trait declares.

[STEPS.md](../../../STEPS.md) holds these steps and the [Drupal steps](../drupal-steps/README.md) on 1 page. It ships with the package, so an [AI coding agent](../guides/ai-assistants.md) in your project can read it from `vendor/`.

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
