---
title: "Drupal\\ParagraphsTrait helpers"
sidebar_label: "ParagraphsTrait"
sidebar_position: 15
description: "Manage Drupal paragraphs entities with structured field data."
custom_edit_url: null
---

# Drupal\ParagraphsTrait helpers

[Source](../../../../src/Steps/Drupal/ParagraphsTrait.php), [Steps](../../steps/drupal/paragraphs.md)

> Manage Drupal paragraphs entities with structured field data.

<details>
  <summary><code>public function paragraphsAttachFromStubToEntity(EntityStubInterface $parent_entity, string $parent_field, string $paragraph_type, EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Create a paragraphs item from a stub and attach it to an entity
<br/><br/>

</details>

<details>
  <summary><code>public function paragraphsBuildStub(string $paragraph_type, array $fields): EntityStub</code></summary>

<br/>
Build a paragraph stub from field values
<br/><br/>

</details>

<details>
  <summary><code>public function paragraphsFindEntity(string $entity_type, string $bundle, string $field_name, string $field_value): ?EntityStubInterface</code></summary>

<br/>
Find entity
<br/><br/>

</details>

<details>
  <summary><code>public function paragraphsGetParentEntity(string $entity_type, string $bundle, string $field_name, string $lookup_field, string $lookup_value): EntityStubInterface</code></summary>

<br/>
Get the entity to attach a paragraph to
<br/><br/>

</details>
