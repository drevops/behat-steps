---
title: "Content - Drupal helpers"
sidebar_label: "Content"
sidebar_position: 6
description: "Manage Drupal content with workflow and moderation support."
custom_edit_url: null
---

# Content

`DrevOps\BehatSteps\Steps\Drupal\ContentTrait` - [Source](../../../src/Steps/Drupal/ContentTrait.php), [Steps](../drupal-steps/content.md)

> Manage Drupal content with workflow and moderation support.

<details>
  <summary><code>public function contentCreate(string $content_type, array $values): EntityStubInterface</code></summary>

<br/>
Create a node of a content type
<br/><br/>

</details>

<details>
  <summary><code>public function contentDelete(string $content_type, array $conditions): void</code></summary>

<br/>
Delete the nodes of a content type that match conditions
<br/><br/>

</details>

<details>
  <summary><code>public function contentGetNidByTitle(string $content_type, string $title): int</code></summary>

<br/>
Return the ID of the node with the specified type and title
<br/><br/>

</details>

<details>
  <summary><code>public function contentGetNodeByTitle(string $content_type, string $title): EntityStubInterface</code></summary>

<br/>
Return the node with the specified type and title
<br/><br/>

</details>

<details>
  <summary><code>public function contentRebuildAccessGrants(EntityStubInterface $node): void</code></summary>

<br/>
Rebuild the access grants of a node
<br/><br/>

</details>

<details>
  <summary><code>public function contentSetModerationState(EntityStubInterface $node, string $state): void</code></summary>

<br/>
Set the moderation state of a node and save it
<br/><br/>

</details>

<details>
  <summary><code>public function contentSetPathAlias(EntityStubInterface $node, string $alias): void</code></summary>

<br/>
Set the path alias of a node, replacing any existing alias
<br/><br/>

</details>

<details>
  <summary><code>public function contentVisitActionPageWithTitle(string $content_type, string $title, string $rel = 'canonical'): void</code></summary>

<br/>
Visit a page of the content with a specified title
<br/><br/>

</details>
