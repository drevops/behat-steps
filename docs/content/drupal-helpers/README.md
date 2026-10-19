---
title: Drupal helpers
description: The public helpers behind the Drupal steps, with a page per trait, which your own step definitions call the same way.
custom_edit_url: null
---

# Drupal helpers

These are the public helpers the Drupal steps are built on. Your own step definitions call them on `$this`, the same way the shipped steps do, as [Scenario styles](../guides/scenario-styles.md) explains. A trait's page gives each of its helpers a signature and a description.

[HELPERS.md](../../../HELPERS.md) holds these helpers and the [web helpers](../web-helpers/README.md) on 1 page. It ships with the package, so an [AI coding agent](../guides/ai-assistants.md) in your project can read it from `vendor/`.

[//]: # (START_INDEX)

| Name | Class | Helpers | Description |
| --- | --- | --- | --- |
| [Authentication](auth.md) | `Drupal\AuthTrait` | 6 | Creates users and roles, logs them in, and removes them afterwards. |
| [BigPipe](big-pipe.md) | `Drupal\BigPipeTrait` | 2 | Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios. |
| [Blocks](block.md) | `Drupal\BlockTrait` | 6 | Manage Drupal blocks. |
| [Caches and cron](cache.md) | `Drupal\CacheTrait` | 2 | Invalidate Drupal caches and run cron from within a scenario. |
| [Configuration](config.md) | `Drupal\ConfigTrait` | 3 | Assert and set stored Drupal configuration values with automatic revert. |
| [Content](content.md) | `Drupal\ContentTrait` | 8 | Manage Drupal content with workflow and moderation support. |
| [Content blocks](content-block.md) | `Drupal\ContentBlockTrait` | 4 | Manage Drupal content blocks. |
| [DraggableViews](draggableviews.md) | `Drupal\DraggableviewsTrait` | 3 | Order items in the Drupal Draggable Views. |
| [Drush](drush.md) | `Drupal\DrushTrait` | 3 | Run Drush commands and assert their output. |
| [ECK entities](eck.md) | `Drupal\EckTrait` | 5 | Manage Drupal ECK entities of an existing entity type and bundle. |
| [Email](email.md) | `Drupal\EmailTrait` | 11 | Test Drupal email functionality with content verification. |
| [Entity lifecycle](entity-lifecycle.md) | `Drupal\EntityLifecycleTrait` | 7 | Creates Drupal entities and removes them when the scenario ends. |
| [Entity queries](query.md) | `Drupal\QueryTrait` | 5 | Finds the entities a step reads, asserts on or deletes. |
| [File field fixtures](fixture-file.md) | `Drupal\FixtureFileTrait` | 1 | Resolves a fixture file path for a file or image field. |
| [Files](file.md) | `Drupal\FileTrait` | 6 | Manage Drupal file entities with upload and storage operations. |
| [Media](media.md) | `Drupal\MediaTrait` | 5 | Manage Drupal media entities with type-specific field handling. |
| [Menus](menu.md) | `Drupal\MenuTrait` | 6 | Manage Drupal menus and menu links. |
| [Modules](module.md) | `Drupal\ModuleTrait` | 5 | Enable and disable Drupal modules with automatic state restoration. |
| [Paragraphs](paragraphs.md) | `Drupal\ParagraphsTrait` | 4 | Manage Drupal paragraphs entities with structured field data. |
| [Queues](queue.md) | `Drupal\QueueTrait` | 5 | Manage and assert Drupal queue state. |
| [Redirects](redirect.md) | `Drupal\RedirectTrait` | 3 | Manage Drupal redirect entities provided by the contrib `redirect` module. |
| [Search API](search-api.md) | `Drupal\SearchApiTrait` | 3 | Run Drupal Search API indexing and cron hooks. |
| [State API](state.md) | `Drupal\StateTrait` | 3 | Manage and assert Drupal State API values with automatic revert. |
| [Taxonomy](taxonomy.md) | `Drupal\TaxonomyTrait` | 6 | Manage Drupal taxonomy terms with vocabulary organization. |
| [Testmode](testmode.md) | `Drupal\TestmodeTrait` | 2 | Configure Drupal Testmode module for controlled testing scenarios. |
| [Users](user.md) | `Drupal\UserTrait` | 12 | Manage Drupal users with role and permission assignments. |
| [Watchdog](watchdog.md) | `Drupal\WatchdogTrait` | 3 | Assert Drupal does not trigger PHP errors during scenarios using Watchdog. |
| [Webforms](webform.md) | `Drupal\WebformTrait` | 4 | Manage Drupal webforms. |

[//]: # (END_INDEX)
