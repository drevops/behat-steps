---
title: Web helpers
description: The public helpers behind the web steps, with a page per trait or class, which your own step definitions call the same way.
custom_edit_url: null
---

# Web helpers

These are the public helpers the web steps are built on. Your own step definitions call them on `$this`, the same way the shipped steps do, as [Scenario styles](../guides/scenario-styles.md) explains. A page gives each helper of a trait or class a signature and a description, and [Root context](web-raw-context.md) covers what `WebRawContext` itself adds.

[HELPERS.md](../../../HELPERS.md) holds these helpers and the [Drupal helpers](../drupal-helpers/README.md) on 1 page. It ships with the package, so an [AI coding agent](../guides/ai-assistants.md) in your project can read it from `vendor/`.

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
