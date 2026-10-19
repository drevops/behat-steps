---
title: "Content blocks - Drupal helpers"
sidebar_label: "Content blocks"
sidebar_position: 7
description: "Manage Drupal content blocks."
custom_edit_url: null
---

# Content blocks

`DrevOps\BehatSteps\Steps\Drupal\ContentBlockTrait` - [Source](../../../src/Steps/Drupal/ContentBlockTrait.php), [Steps](../drupal-steps/content-block.md)

> Manage Drupal content blocks.

<details>
  <summary><code>public function contentBlockCreate(string $content_block_type, array $values): EntityStubInterface</code></summary>

<br/>
Create a block content entity with the specified type and field values
<br/><br/>

</details>

<details>
  <summary><code>public function contentBlockDelete(string $content_block_type, array $conditions): void</code></summary>

<br/>
Delete the content blocks of a type that match conditions
<br/><br/>

</details>

<details>
  <summary><code>public function contentBlockGetByDescription(string $content_block_type, string $description): EntityStubInterface</code></summary>

<br/>
Get the newest content block with a description
<br/><br/>

</details>

<details>
  <summary><code>public function contentBlockLoadMultiple(string $content_block_type, array $conditions = []): array</code></summary>

<br/>
Load multiple content blocks with specified type and conditions
<br/><br/>

</details>
