---
title: Drupal steps
sidebar_label: Overview
sidebar_position: 0
description: The steps that only work on a Drupal site, what they need from it, how to read a trait's page and find a step, and every Drupal trait with what it covers.
custom_edit_url: null
---

# Drupal steps

Drupal steps only mean something on a Drupal site. They create content, users, terms and media, clear caches, run cron and queues, check the emails the site sends and the errors it logs, and more. `DrupalContext` brings every one of them, on top of the [web steps](../web-steps/README.md) it inherits from `WebContext`. So on a Drupal site, your `FeatureContext` extends `DrupalContext` and has both, as [Contexts](../guides/contexts.md) explains.

The steps come grouped into traits, 1 per job, such as content, users or the State API. The table at the end of this page lists every trait and what it covers, and each name opens the trait's page.

## What they need from your site

Most Drupal steps work on the site directly instead of through its pages: creating a user, for instance, doesn't fill in the registration form. They reach the site through a backend, either Drupal itself, loaded into the test run, or Drush. The `drupal_root` setting from the [quick start](../quick-start.md) is all most sites need, and [Key concepts](../concepts.md#backends-reaching-the-site-without-the-browser) explains backends.

Traits for contrib modules, such as Webform, Search API or Redirect, cost nothing on a site without the module. A trait like that looks for its module only when one of its steps runs, and that step fails with a message naming the module.

## How a trait's page reads

The page opens with the trait's class name, links to its source, to the feature file that tests it and to its helpers, and a description of what it does. Then, where the trait has them:

- Prerequisites: what the site has to provide before the trait's steps can run, such as the core `dblog` module that [Watchdog](watchdog.md) reads.
- Options: the settings the trait reads, with their defaults and the tags that change them for 1 scenario. [Configure a trait](../guides/usage.md#configure-a-trait) shows where to set them.
- Steps: every step of the trait, each under its `Given`, `When` or `Then` keyword. Open a step to see what it does and an example you can copy into a scenario.

## Finding a step

- Search the site with the box at the top of the page.
- Run `vendor/bin/behat --definitions=l` in your project. It lists every step your suite registers, Mink's and your own included.
- Open [STEPS.md](../../../STEPS.md), which holds these steps and the web steps on 1 page. It ships with the package, so an [AI coding agent](../guides/ai-assistants.md) in your project can read it from `vendor/`.

## Every Drupal trait

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
