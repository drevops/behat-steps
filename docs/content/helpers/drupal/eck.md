---
title: "Drupal\\EckTrait helpers"
sidebar_label: "EckTrait"
sidebar_position: 9
description: "Manage Drupal ECK entities of an existing entity type and bundle."
custom_edit_url: null
---

# Drupal\EckTrait helpers

[Source](../../../../src/Steps/Drupal/EckTrait.php), [Steps](../../steps/drupal/eck.md)

> Manage Drupal ECK entities of an existing entity type and bundle.

<details>
  <summary><code>public function eckCreate(EntityStub $stub): void</code></summary>

<br/>
Create a single content entity
<br/><br/>

</details>

<details>
  <summary><code>public function eckDelete(string $entity_type, string $bundle, array $conditions): void</code></summary>

<br/>
Delete the entities of a type and bundle that match conditions
<br/><br/>

</details>

<details>
  <summary><code>public function eckGetEntityByTitle(string $entity_type, string $bundle, string $title): EntityStubInterface</code></summary>

<br/>
Get an entity of a type and bundle by title
<br/><br/>

</details>

<details>
  <summary><code>public function eckGetEntityUrlByTitle(string $entity_type, string $bundle, string $title, string $rel = 'canonical'): string</code></summary>

<br/>
Get the URL of a page of the entity of a type and bundle with a title
<br/><br/>

</details>

<details>
  <summary><code>public function eckLoadMultiple(string $entity_type, string $bundle, array $conditions = []): array</code></summary>

<br/>
Load multiple entities with specified type and conditions
<br/><br/>

</details>
