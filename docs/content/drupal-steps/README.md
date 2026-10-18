---
title: Drupal steps
description: The steps that only work on a Drupal site, with a page per trait that gives each step an example and lists the trait's options and prerequisites.
custom_edit_url: null
---

# Drupal steps

These steps only mean something on a Drupal site. `DrupalContext` composes all of them, on top of the [web steps](../web-steps/README.md) it inherits from `WebContext`. A trait's page gives each of its steps an example and lists the options and the prerequisites the trait declares.

[STEPS.md](../../../STEPS.md) holds these steps and the web steps on 1 page. It ships with the package, so an [AI coding agent](../guides/ai-assistants.md) in your project can read it from `vendor/`.

[//]: # (START_INDEX)

| Name | Trait | Description |
| --- | --- | --- |
| [Batch API](batch.md) | `Drupal\BatchTrait` | Wait for Drupal's Batch API to finish. |
| [BigPipe](big-pipe.md) | `Drupal\BigPipeTrait` | Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios. |
| [Blocks](block.md) | `Drupal\BlockTrait` | Manage Drupal blocks. |
| [Caches and cron](cache.md) | `Drupal\CacheTrait` | Invalidate Drupal caches and run cron from within a scenario. |
| [Config overrides](config-override.md) | `Drupal\ConfigOverrideTrait` | Disable Drupal config overrides from settings.php during a scenario. |
| [Configuration](config.md) | `Drupal\ConfigTrait` | Assert and set stored Drupal configuration values with automatic revert. |
| [Content](content.md) | `Drupal\ContentTrait` | Manage Drupal content with workflow and moderation support. |
| [Content blocks](content-block.md) | `Drupal\ContentBlockTrait` | Manage Drupal content blocks. |
| [DraggableViews](draggableviews.md) | `Drupal\DraggableviewsTrait` | Order items in the Drupal Draggable Views. |
| [Drush](drush.md) | `Drupal\DrushTrait` | Run Drush commands and assert their output. |
| [ECK entities](eck.md) | `Drupal\EckTrait` | Manage Drupal ECK entities of an existing entity type and bundle. |
| [Email](email.md) | `Drupal\EmailTrait` | Test Drupal email functionality with content verification. |
| [Entities](entity.md) | `Drupal\EntityTrait` | Create entities of a type that has no dedicated trait. |
| [Files](file.md) | `Drupal\FileTrait` | Manage Drupal file entities with upload and storage operations. |
| [Languages](language.md) | `Drupal\LanguageTrait` | Create the languages a scenario needs. |
| [Media](media.md) | `Drupal\MediaTrait` | Manage Drupal media entities with type-specific field handling. |
| [Menus](menu.md) | `Drupal\MenuTrait` | Manage Drupal menus and menu links. |
| [Modules](module.md) | `Drupal\ModuleTrait` | Enable and disable Drupal modules with automatic state restoration. |
| [Paragraphs](paragraphs.md) | `Drupal\ParagraphsTrait` | Manage Drupal paragraphs entities with structured field data. |
| [Queues](queue.md) | `Drupal\QueueTrait` | Manage and assert Drupal queue state. |
| [Redirects](redirect.md) | `Drupal\RedirectTrait` | Manage Drupal redirect entities provided by the contrib `redirect` module. |
| [Search API](search-api.md) | `Drupal\SearchApiTrait` | Run Drupal Search API indexing and cron hooks. |
| [State API](state.md) | `Drupal\StateTrait` | Manage and assert Drupal State API values with automatic revert. |
| [System time](time.md) | `Drupal\TimeTrait` | Control system time in tests using Drupal state overrides. |
| [Taxonomy](taxonomy.md) | `Drupal\TaxonomyTrait` | Manage Drupal taxonomy terms with vocabulary organization. |
| [Testmode](testmode.md) | `Drupal\TestmodeTrait` | Configure Drupal Testmode module for controlled testing scenarios. |
| [Users](user.md) | `Drupal\UserTrait` | Manage Drupal users with role and permission assignments. |
| [Watchdog](watchdog.md) | `Drupal\WatchdogTrait` | Assert Drupal does not trigger PHP errors during scenarios using Watchdog. |
| [Webforms](webform.md) | `Drupal\WebformTrait` | Manage Drupal webforms. |

[//]: # (END_INDEX)
