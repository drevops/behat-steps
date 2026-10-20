---
title: "Paragraphs - Drupal steps"
sidebar_label: "Paragraphs"
sidebar_position: 19
description: "Manage Drupal paragraphs entities with structured field data."
custom_edit_url: null
---

# Paragraphs

`DrevOps\BehatSteps\Steps\Drupal\ParagraphsTrait` - [Source](../../../src/Steps/Drupal/ParagraphsTrait.php), [Example](../../../tests/behat/features/drupal_paragraphs.feature), [Helpers](../../../HELPERS.md#drupalparagraphstrait)

>  Manage Drupal paragraphs entities with structured field data.
>  - Create paragraph items with type-specific field values.
>  - Test nested paragraph structures and reference field handling.
>  - Attach paragraphs to various entity types with parent-child relationships.
>  - Created paragraph items are automatically removed at the end of the scenario.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "ContentCapabilityInterface" | `ContentCapabilityInterface` |
| The "paragraphs" module from the "drupal/paragraphs" package is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the following fields for the :paragraph_type paragraph exist in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:</code></summary>

<br/>
Create a paragraph of the given type with fields within an existing entity
<br/><br/>

```gherkin
Given the following fields for the "text" paragraph exist in the field "field_component" within the "landing_page" "node" entity with the value "My landing page" in the field "title":
  | field_paragraph_title           | My paragraph title   |
  | field_paragraph_longtext:value  | My paragraph message |
  | field_paragraph_longtext:format | full_html            |
  | ...                             | ...                  |
```

</details>
