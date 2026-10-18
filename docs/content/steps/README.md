---
title: Steps
description: Every step Behat Steps registers, with a page per trait that gives each step an example and lists the trait's options and prerequisites.
toc_max_heading_level: 2
custom_edit_url: null
---

# Steps

Every step Behat Steps registers, grouped by the trait that declares it. A trait's page gives each of its steps an example and lists the options and the prerequisites the trait declares.

[STEPS.md](../../../STEPS.md) holds the same reference on 1 page. It ships with the package, so an [AI coding agent](../guides/ai-assistants.md) in your project can read it from `vendor/`.

[//]: # (START_INDEX)

## Index of Web steps

| Class | Description |
| --- | --- |
| [AccessibilityTrait](web/accessibility.md) | Assess accessibility of rendered pages. |
| [BasicAuthTrait](web/basic-auth.md) | Keep HTTP basic authentication applied across session resets. |
| [CommandTrait](web/command.md) | Run local shell commands and assert on their result. |
| [CookieTrait](web/cookie.md) | Verify and inspect browser cookies. |
| [DateTrait](web/date.md) | Convert relative date expressions into timestamps or formatted dates. |
| [DiagnosticsTrait](web/diagnostics.md) | Append on-failure diagnostics to the failure message of any failed step. |
| [DropzoneTrait](web/dropzone.md) | Simulate a real multi-file drag-and-drop gesture onto a Dropzone target. |
| [ElementTrait](web/element.md) | Interact with HTML elements using CSS selectors and DOM attributes. |
| [FieldTrait](web/field.md) | Manipulate form fields and verify widget functionality. |
| [FileDownloadTrait](web/file-download.md) | Test file download functionality with content verification. |
| [IframeTrait](web/iframe.md) | Switch between iframes and the root document. |
| [JavascriptTrait](web/javascript.md) | Automatically detect JavaScript errors during test execution. |
| [JsonTrait](web/json.md) | Assert JSON responses with path and schema checks. |
| [KeyboardTrait](web/keyboard.md) | Simulate keyboard interactions in the browser. |
| [LinkTrait](web/link.md) | Verify link elements with attribute and content assertions. |
| [MappingTrait](web/mapping.md) | Replace `{{ Key }}` tokens in step arguments and table cells. |
| [MessageTrait](web/message.md) | Assert status, error, warning and success messages rendered on the page. |
| [MetatagTrait](web/metatag.md) | Assert `<meta>` tags and head/SEO markup in page markup. |
| [ModalTrait](web/modal.md) | Interact with and assert modals. |
| [PathTrait](web/path.md) | Navigate and verify paths with URL validation. |
| [RandomTrait](web/random.md) | Replace random-value tokens in step arguments and table cells. |
| [RegionTrait](web/region.md) | Interact with and assert against named page regions. |
| [ResponseTrait](web/response.md) | Verify HTTP response headers. |
| [ResponsiveTrait](web/responsive.md) | Test responsive layouts with viewport control. |
| [RestTrait](web/rest.md) | Test REST APIs with lightweight steps. |
| [TableTrait](web/table.md) | Interact with HTML table elements and assert their content. |
| [WaitTrait](web/wait.md) | Wait for a period of time or for AJAX to finish. |
| [XmlTrait](web/xml.md) | Assert XML responses with element and attribute checks. |

## Index of Drupal steps

| Class | Description |
| --- | --- |
| [Drupal\BatchTrait](drupal/batch.md) | Wait for Drupal's Batch API to finish. |
| [Drupal\BigPipeTrait](drupal/big-pipe.md) | Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios. |
| [Drupal\BlockTrait](drupal/block.md) | Manage Drupal blocks. |
| [Drupal\CacheTrait](drupal/cache.md) | Invalidate Drupal caches and run cron from within a scenario. |
| [Drupal\ConfigOverrideTrait](drupal/config-override.md) | Disable Drupal config overrides from settings.php during a scenario. |
| [Drupal\ConfigTrait](drupal/config.md) | Assert and set stored Drupal configuration values with automatic revert. |
| [Drupal\ContentBlockTrait](drupal/content-block.md) | Manage Drupal content blocks. |
| [Drupal\ContentTrait](drupal/content.md) | Manage Drupal content with workflow and moderation support. |
| [Drupal\DraggableviewsTrait](drupal/draggableviews.md) | Order items in the Drupal Draggable Views. |
| [Drupal\DrushTrait](drupal/drush.md) | Run Drush commands and assert their output. |
| [Drupal\EckTrait](drupal/eck.md) | Manage Drupal ECK entities of an existing entity type and bundle. |
| [Drupal\EmailTrait](drupal/email.md) | Test Drupal email functionality with content verification. |
| [Drupal\EntityTrait](drupal/entity.md) | Create entities of a type that has no dedicated trait. |
| [Drupal\FileTrait](drupal/file.md) | Manage Drupal file entities with upload and storage operations. |
| [Drupal\LanguageTrait](drupal/language.md) | Create the languages a scenario needs. |
| [Drupal\MediaTrait](drupal/media.md) | Manage Drupal media entities with type-specific field handling. |
| [Drupal\MenuTrait](drupal/menu.md) | Manage Drupal menus and menu links. |
| [Drupal\ModuleTrait](drupal/module.md) | Enable and disable Drupal modules with automatic state restoration. |
| [Drupal\ParagraphsTrait](drupal/paragraphs.md) | Manage Drupal paragraphs entities with structured field data. |
| [Drupal\QueueTrait](drupal/queue.md) | Manage and assert Drupal queue state. |
| [Drupal\RedirectTrait](drupal/redirect.md) | Manage Drupal redirect entities provided by the contrib `redirect` module. |
| [Drupal\SearchApiTrait](drupal/search-api.md) | Run Drupal Search API indexing and cron hooks. |
| [Drupal\StateTrait](drupal/state.md) | Manage and assert Drupal State API values with automatic revert. |
| [Drupal\TaxonomyTrait](drupal/taxonomy.md) | Manage Drupal taxonomy terms with vocabulary organization. |
| [Drupal\TestmodeTrait](drupal/testmode.md) | Configure Drupal Testmode module for controlled testing scenarios. |
| [Drupal\TimeTrait](drupal/time.md) | Control system time in tests using Drupal state overrides. |
| [Drupal\UserTrait](drupal/user.md) | Manage Drupal users with role and permission assignments. |
| [Drupal\WatchdogTrait](drupal/watchdog.md) | Assert Drupal does not trigger PHP errors during scenarios using Watchdog. |
| [Drupal\WebformTrait](drupal/webform.md) | Manage Drupal webforms. |

[//]: # (END_INDEX)
