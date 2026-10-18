---
title: Helpers
description: Every public helper the steps are built on, with a page per trait or class, which your own step definitions call the same way.
toc_max_heading_level: 2
custom_edit_url: null
---

# Helpers

Every public helper the steps are built on, grouped by the trait or class that declares it. Your own step definitions call them on `$this`, the same way the shipped steps do, as [Scenario styles](../guides/scenario-styles.md) explains. A class's page gives each of its helpers a signature and a description.

[HELPERS.md](../../../HELPERS.md) holds the same reference on 1 page. It ships with the package, so an [AI coding agent](../guides/ai-assistants.md) in your project can read it from `vendor/`.

[//]: # (START_INDEX)

## Index of Web helpers

| Class | Helpers | Description |
| --- | --- | --- |
| [AccessibilityTrait](web/accessibility.md) | 21 | Assess accessibility of rendered pages. |
| [BasicAuthTrait](web/basic-auth.md) | 1 | Keep HTTP basic authentication applied across session resets. |
| [CommandTrait](web/command.md) | 2 | Run local shell commands and assert on their result. |
| [CookieTrait](web/cookie.md) | 4 | Verify and inspect browser cookies. |
| [DateTrait](web/date.md) | 3 | Convert relative date expressions into timestamps or formatted dates. |
| [DiagnosticsTrait](web/diagnostics.md) | 12 | Append on-failure diagnostics to the failure message of any failed step. |
| [DropzoneTrait](web/dropzone.md) | 1 | Simulate a real multi-file drag-and-drop gesture onto a Dropzone target. |
| [ElementTrait](web/element.md) | 8 | Interact with HTML elements using CSS selectors and DOM attributes. |
| [FieldTrait](web/field.md) | 13 | Manipulate form fields and verify widget functionality. |
| [FileDownloadTrait](web/file-download.md) | 8 | Test file download functionality with content verification. |
| [IframeTrait](web/iframe.md) | 1 | Switch between iframes and the root document. |
| [JavascriptTrait](web/javascript.md) | 2 | Automatically detect JavaScript errors during test execution. |
| [JsonTrait](web/json.md) | 7 | Assert JSON responses with path and schema checks. |
| [MappingTrait](web/mapping.md) | 3 | Replace `{{ Key }}` tokens in step arguments and table cells. |
| [MessageTrait](web/message.md) | 3 | Assert status, error, warning and success messages rendered on the page. |
| [MetatagTrait](web/metatag.md) | 14 | Assert `<meta>` tags and head/SEO markup in page markup. |
| [ModalTrait](web/modal.md) | 9 | Interact with and assert modals. |
| [PathTrait](web/path.md) | 4 | Navigate and verify paths with URL validation. |
| [RandomTrait](web/random.md) | 10 | Replace random-value tokens in step arguments and table cells. |
| [RegionTrait](web/region.md) | 4 | Interact with and assert against named page regions. |
| [ResponsiveTrait](web/responsive.md) | 7 | Test responsive layouts with viewport control. |
| [RestTrait](web/rest.md) | 2 | Test REST APIs with lightweight steps. |
| [TableTrait](web/table.md) | 10 | Interact with HTML table elements and assert their content. |
| [WaitTrait](web/wait.md) | 2 | Wait for a period of time or for AJAX to finish. |
| [XmlTrait](web/xml.md) | 9 | Assert XML responses with element and attribute checks. |
| [FixtureDirectoryTrait](web/fixture-directory.md) | 5 | Resolves fixture files in the directory Mink's `files_path` parameter names. |
| [HeadingTrait](web/heading.md) | 1 | Finds a heading by its text within the page or an element of it. |
| [RequestHeadersTrait](web/request-headers.md) | 1 | Holds the request headers shared by the traits that issue HTTP requests. |
| [TableTransposeTrait](web/table-transpose.md) | 2 | Reads a vertical Gherkin table as 1 set of values per entity. |

## Index of Drupal helpers

| Class | Helpers | Description |
| --- | --- | --- |
| [Drupal\BigPipeTrait](drupal/big-pipe.md) | 2 | Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios. |
| [Drupal\BlockTrait](drupal/block.md) | 6 | Manage Drupal blocks. |
| [Drupal\CacheTrait](drupal/cache.md) | 2 | Invalidate Drupal caches and run cron from within a scenario. |
| [Drupal\ConfigTrait](drupal/config.md) | 3 | Assert and set stored Drupal configuration values with automatic revert. |
| [Drupal\ContentBlockTrait](drupal/content-block.md) | 4 | Manage Drupal content blocks. |
| [Drupal\ContentTrait](drupal/content.md) | 8 | Manage Drupal content with workflow and moderation support. |
| [Drupal\DraggableviewsTrait](drupal/draggableviews.md) | 3 | Order items in the Drupal Draggable Views. |
| [Drupal\DrushTrait](drupal/drush.md) | 3 | Run Drush commands and assert their output. |
| [Drupal\EckTrait](drupal/eck.md) | 5 | Manage Drupal ECK entities of an existing entity type and bundle. |
| [Drupal\EmailTrait](drupal/email.md) | 11 | Test Drupal email functionality with content verification. |
| [Drupal\FileTrait](drupal/file.md) | 6 | Manage Drupal file entities with upload and storage operations. |
| [Drupal\MediaTrait](drupal/media.md) | 5 | Manage Drupal media entities with type-specific field handling. |
| [Drupal\MenuTrait](drupal/menu.md) | 6 | Manage Drupal menus and menu links. |
| [Drupal\ModuleTrait](drupal/module.md) | 5 | Enable and disable Drupal modules with automatic state restoration. |
| [Drupal\ParagraphsTrait](drupal/paragraphs.md) | 4 | Manage Drupal paragraphs entities with structured field data. |
| [Drupal\QueueTrait](drupal/queue.md) | 5 | Manage and assert Drupal queue state. |
| [Drupal\RedirectTrait](drupal/redirect.md) | 3 | Manage Drupal redirect entities provided by the contrib `redirect` module. |
| [Drupal\SearchApiTrait](drupal/search-api.md) | 3 | Run Drupal Search API indexing and cron hooks. |
| [Drupal\StateTrait](drupal/state.md) | 3 | Manage and assert Drupal State API values with automatic revert. |
| [Drupal\TaxonomyTrait](drupal/taxonomy.md) | 6 | Manage Drupal taxonomy terms with vocabulary organization. |
| [Drupal\TestmodeTrait](drupal/testmode.md) | 2 | Configure Drupal Testmode module for controlled testing scenarios. |
| [Drupal\UserTrait](drupal/user.md) | 12 | Manage Drupal users with role and permission assignments. |
| [Drupal\WatchdogTrait](drupal/watchdog.md) | 3 | Assert Drupal does not trigger PHP errors during scenarios using Watchdog. |
| [Drupal\WebformTrait](drupal/webform.md) | 4 | Manage Drupal webforms. |
| [Drupal\AuthTrait](drupal/auth.md) | 6 | Creates users and roles, logs them in, and removes them afterwards. |
| [Drupal\EntityLifecycleTrait](drupal/entity-lifecycle.md) | 7 | Creates Drupal entities and removes them when the scenario ends. |
| [Drupal\FixtureFileTrait](drupal/fixture-file.md) | 1 | Resolves a fixture file path for a file or image field. |
| [Drupal\QueryTrait](drupal/query.md) | 5 | Finds the entities a step reads, asserts on or deletes. |

## Index of Toolbox helpers

| Class | Helpers | Description |
| --- | --- | --- |
| [WebRawContext](toolbox/web-raw-context.md) | 21 | Root context carrying the plumbing every suite needs. |

[//]: # (END_INDEX)
