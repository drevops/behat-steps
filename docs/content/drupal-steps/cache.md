---
title: "Caches and cron - Drupal steps"
sidebar_label: "Caches and cron"
sidebar_position: 4
description: "Invalidate Drupal caches and run cron from within a scenario."
custom_edit_url: null
---

# Caches and cron

`DrevOps\BehatSteps\Steps\Drupal\CacheTrait` - [Source](../../../src/Steps/Drupal/CacheTrait.php), [Example](../../../tests/behat/features/drupal_cache.feature), [Helpers](../drupal-helpers/cache.md)

>  Invalidate Drupal caches and run cron from within a scenario.
>  - Clear every cache bin, or target a single path, a path pattern, or the
>  render cache.
>  - Run cron, which also flushes the caches cron itself invalidates.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "CacheCapabilityInterface" | `CacheCapabilityInterface` |

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `cache.page_cache_bin` | string | `'page'` | - | Name of the cache bin holding the internal page cache. |

## Steps

<details>
  <summary><code>@Given the cache has been cleared</code></summary>

<br/>
Clear every cache bin
<br/><br/>

```gherkin
Given the cache has been cleared
```

</details>

<details>
  <summary><code>@Given the page cache for the path :path has been cleared</code></summary>

<br/>
Clear the page cache for a single path
<br/><br/>

```gherkin
Given the page cache for the path "/about" has been cleared
```

</details>

<details>
  <summary><code>@Given the page cache for the paths matching :path_pattern has been cleared</code></summary>

<br/>
Clear the page cache for all paths matching a glob-style pattern
<br/><br/>

```gherkin
Given the page cache for the paths matching "/news*" has been cleared
```

</details>

<details>
  <summary><code>@Given the render cache has been cleared</code></summary>

<br/>
Clear the render cache
<br/><br/>

```gherkin
Given the render cache has been cleared
```

</details>

<details>
  <summary><code>@When cron runs</code></summary>

<br/>
Run cron
<br/><br/>

```gherkin
When cron runs
```

</details>
