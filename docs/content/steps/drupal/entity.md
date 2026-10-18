---
title: "Drupal\\EntityTrait steps"
sidebar_label: "EntityTrait"
sidebar_position: 13
description: "Create entities of a type that has no dedicated trait."
custom_edit_url: null
---

# Drupal\EntityTrait steps

[Source](../../../../src/Steps/Drupal/EntityTrait.php), [Example](../../../../tests/behat/features/drupal_entity.feature)

>  Create entities of a type that has no dedicated trait.
>  - Create entities of any type from a table of field values.
>  
>  Covers types such as `commerce_product` or `group`, where a dedicated trait
>  would add vocabulary without adding behavior. Entities created here are
>  removed after the scenario along with every other entity the scenario
>  created.
>  <br/><br/>
>  Skip cleanup for 1 type with tag:
>  `@behat-steps-entity-cleanup-skip:commerce_product`.

## Steps

<details>
  <summary><code>@Given the following :entity_type entities exist:</code></summary>

<br/>
Create entities of a type from a table of field values
<br/><br/>

```gherkin
Given the following "commerce_product" entities exist:
  | title | type    | status |
  | TNT   | product | 1      |
  | Anvil | product | 1      |
```

</details>
