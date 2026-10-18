---
title: "Drupal\\MediaTrait helpers"
sidebar_label: "MediaTrait"
sidebar_position: 12
description: "Manage Drupal media entities with type-specific field handling."
custom_edit_url: null
---

# Drupal\MediaTrait helpers

[Source](../../../../src/Steps/Drupal/MediaTrait.php), [Steps](../../steps/drupal/media.md)

> Manage Drupal media entities with type-specific field handling.

<details>
  <summary><code>public function mediaCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Create a single media item
<br/><br/>

</details>

<details>
  <summary><code>public function mediaCreateEntity(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Create media entity
<br/><br/>

</details>

<details>
  <summary><code>public function mediaDelete(string $media_type, array $conditions): void</code></summary>

<br/>
Delete the media of a type that match conditions
<br/><br/>

</details>

<details>
  <summary><code>public function mediaLoadMultiple(string $media_type, array $conditions = []): array</code></summary>

<br/>
Load multiple media entities with specified type and conditions
<br/><br/>

</details>

<details>
  <summary><code>public function mediaVisitActionPageWithName(string $media_type, string $name, string $rel = 'canonical'): void</code></summary>

<br/>
Visit a page of the media with a specified name
<br/><br/>

</details>
