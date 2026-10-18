---
title: "Search API - Drupal helpers"
sidebar_label: "Search API"
sidebar_position: 22
description: "Run Drupal Search API indexing and cron hooks."
custom_edit_url: null
---

# Search API

`DrevOps\BehatSteps\Steps\Drupal\SearchApiTrait` - [Source](../../../src/Steps/Drupal/SearchApiTrait.php), [Steps](../drupal-steps/search-api.md)

> Run Drupal Search API indexing and cron hooks.

<details>
  <summary><code>public function searchApiFindNodeByTitle(string $content_type, string $title): ?EntityStubInterface</code></summary>

<br/>
Find a node of a content type by title
<br/><br/>

</details>

<details>
  <summary><code>public function searchApiIndexItems(int $count): void</code></summary>

<br/>
Index items on every active search index
<br/><br/>

</details>

<details>
  <summary><code>public function searchApiIndexNode(EntityStubInterface $node): void</code></summary>

<br/>
Track a node in the search indexes, then index it on each
<br/><br/>

</details>
