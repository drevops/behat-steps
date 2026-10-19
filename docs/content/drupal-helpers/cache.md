---
title: "Caches and cron - Drupal helpers"
sidebar_label: "Caches and cron"
sidebar_position: 4
description: "Invalidate Drupal caches and run cron from within a scenario."
custom_edit_url: null
---

# Caches and cron

`DrevOps\BehatSteps\Steps\Drupal\CacheTrait` - [Source](../../../src/Steps/Drupal/CacheTrait.php), [Steps](../drupal-steps/cache.md)

> Invalidate Drupal caches and run cron from within a scenario.

<details>
  <summary><code>public function cacheDeletePagePath(string $path, bool $is_pattern = FALSE): void</code></summary>

<br/>
Delete the internal page cache entries stored for a path
<br/><br/>

</details>

<details>
  <summary><code>public function cacheGetPageCacheBin(): string</code></summary>

<br/>
Get the cache bin used for the page cache
<br/><br/>

</details>
