---
title: "Entities - Drupal steps"
sidebar_label: "Entities"
sidebar_position: 13
description: "Create entities of a type that has no dedicated trait, and reference them."
custom_edit_url: null
---

# Entities

`DrevOps\BehatSteps\Steps\Drupal\EntityTrait` - [Source](../../../src/Steps/Drupal/EntityTrait.php), [Example](../../../tests/behat/features/drupal_entity.feature), [Helpers](../../../HELPERS.md#drupalentitytrait)

>  Create entities of a type that has no dedicated trait, and reference them.
>  - Create entities of any type from a table of field values.
>  - Fill in an entity reference autocomplete field with an entity's label.
>  - Replace `[id:...]` tokens in step arguments and table cells with the ID
>  of an entity looked up by its label.
>  <br/><br/>
>  Covers types such as `commerce_product` or `group`, where a dedicated trait
>  would add vocabulary without adding behavior. Entities created here are
>  removed after the scenario along with every other entity the scenario
>  created.
>  <br/><br/>
>  Possible token formats:
>  - `[id:ENTITY_TYPE:LABEL]`
>  - `[id:ENTITY_TYPE.BUNDLE:LABEL]`
>  
>  with:
>  - `ENTITY_TYPE`: the entity type ID, such as `node`, `user` or
>  `taxonomy_term`.
>  - `BUNDLE`: the bundle to restrict the lookup to, such as `article`.
>  - `LABEL`: the label to match exactly, such as a title or a user name. It
>  may hold balanced brackets, as in `[TEST] Page`, but no other token.
>  <br/><br/>
>  Examples:
>  - `/print/pdf/user/[id:user:jane]` converted to `/print/pdf/user/12`
>  - `[id:taxonomy_term.topics:Jazz]` converted to `7`
>  
>  When several entities share the label, the newest is used. A token whose
>  entity does not exist fails the step.
>  <br/><br/>
>  Skip cleanup for 1 type with tag:
>  `@behat-steps-entity-cleanup-skip:commerce_product`.
>  <br/><br/>
>  Skip token processing with tag: `@behat-steps-skip:EntityTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `entity.enabled` | boolean | `TRUE` | `@behat-steps-skip:EntityTrait` | If `TRUE`, replaces `[id:...]` tokens in step arguments and table cells with the ID of the newest entity with the token's label, though not in multiline text. If `FALSE`, tokens stay as written. |

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

<details>
  <summary><code>@When I fill in the entity reference field :field with the :entity_type entity with the label :label</code></summary>

<br/>
Fill in an entity reference autocomplete field with an entity's label
<br/><br/>

```gherkin
When I fill in the entity reference field "Authored by" with the "user" entity with the label "jane"
```

</details>

<details>
  <summary><code>@When I fill in the entity reference field :field with the :bundle :entity_type entity with the label :label</code></summary>

<br/>
Fill in an entity reference autocomplete field with a bundle entity
<br/><br/>

```gherkin
When I fill in the entity reference field "Program" with the "program" "node" entity with the label "Morning show"
When I fill in the entity reference field "Tags" with the "tags" "taxonomy_term" entity with the label "Jazz"
```

</details>
