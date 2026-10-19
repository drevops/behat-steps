---
title: "Entity queries - Drupal helpers"
sidebar_label: "Entity queries"
sidebar_position: 13
description: "Finds the entities a step reads, asserts on or deletes."
custom_edit_url: null
---

# Entity queries

`DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait` - [Source](../../../src/Steps/Helper/Drupal/QueryTrait.php)

> Finds the entities a step reads, asserts on or deletes.

<details>
  <summary><code>public function queryEntityIds(string $entity_type, array $conditions = [], ?string $bundle = NULL): array</code></summary>

<br/>
Load the ids of the entities of a type matching the conditions
<br/><br/>

</details>

<details>
  <summary><code>public function queryEntityStubs(string $entity_type, string $id_key, array $conditions = [], ?string $bundle = NULL): array</code></summary>

<br/>
Load a stub for each entity of a type matching the conditions
<br/><br/>

</details>

<details>
  <summary><code>public function queryFindNewestEntityId(string $entity_type, array $conditions = [], ?string $bundle = NULL): ?string</code></summary>

<br/>
Find the id of the newest entity of a type matching the conditions
<br/><br/>

</details>

<details>
  <summary><code>public function queryGetEntityId(EntityStubInterface $stub, string $id_key): string</code></summary>

<br/>
Get the id a stub carries
<br/><br/>

</details>

<details>
  <summary><code>public function queryNodeIds(string $content_type, array $conditions = []): array</code></summary>

<br/>
Load the ids of the nodes of a content type matching the conditions
<br/><br/>

</details>
