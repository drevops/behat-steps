---
title: "Search API - Drupal steps"
sidebar_label: "Search API"
sidebar_position: 22
description: "Run Drupal Search API indexing and cron hooks."
custom_edit_url: null
---

# Search API

`DrevOps\BehatSteps\Steps\Drupal\SearchApiTrait` - [Source](../../../src/Steps/Drupal/SearchApiTrait.php), [Example](../../../tests/behat/features/drupal_search_api.feature), [Helpers](../drupal-helpers/search-api.md)

>  Run Drupal Search API indexing and cron hooks.
>  - Add content to an index.
>  - Run indexing for a specific number of items.
>  - Run the Search API and Search API Solr cron hooks.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "SearchIndexCapabilityInterface" | `SearchIndexCapabilityInterface` |
| The "search_api" module from the "drupal/search_api" package is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@When the :content_type content with the title :title is indexed</code></summary>

<br/>
Index a node of a specific content type with a specific title
<br/><br/>

```gherkin
When the "article" content with the title "Test Article" is indexed
```

</details>

<details>
  <summary><code>@When search indexing runs for :count item(s)</code></summary>

<br/>
Run indexing for a specific number of items
<br/><br/>

```gherkin
When search indexing runs for 5 items
When search indexing runs for 1 item
```

</details>

<details>
  <summary><code>@When the Search API cron runs</code></summary>

<br/>
Run the Search API module cron hook
<br/><br/>

```gherkin
When the Search API cron runs
```

</details>

<details>
  <summary><code>@When the Search API Solr cron runs</code></summary>

<br/>
Run the Search API Solr module cron hook
<br/><br/>

```gherkin
When the Search API Solr cron runs
```

</details>
