---
title: "Drupal\\EckTrait steps"
sidebar_label: "EckTrait"
sidebar_position: 11
description: "Manage Drupal ECK entities of an existing entity type and bundle."
custom_edit_url: null
---

# Drupal\EckTrait steps

[Source](../../../../src/Steps/Drupal/EckTrait.php), [Example](../../../../tests/behat/features/drupal_eck.feature), [Helpers](../../helpers/drupal/eck.md)

>  Manage Drupal ECK entities of an existing entity type and bundle.
>  - Create structured ECK entities with defined field values.
>  - Visit ECK entity pages and their edit pages.
>  - Created entities are automatically removed at the end of the scenario.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "ContentCapabilityInterface" | `ContentCapabilityInterface` |
| The "eck" module from the "drupal/eck" package is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the following ECK :bundle :entity_type entities exist:</code></summary>

<br/>
Create eck entities
<br/><br/>

```gherkin
Given the following ECK "contact" "contact_type" entities exist:
  | title  | field_marine_animal     | field_fish_type | ... |
  | Snook  | Fish                    | Marine fish     | 10  |
  | ...    | ...                     | ...             | ... |
```

</details>

<details>
  <summary><code>@Given the following ECK :bundle :entity_type entities do not exist:</code></summary>

<br/>
Remove custom entities by field
<br/><br/>

```gherkin
Given the following ECK "contact" "contact_type" entities do not exist:
  | title           |
  | Entity label    |
```

</details>

<details>
  <summary><code>@When I visit the ECK :bundle :entity_type entity page with the title :title</code></summary>

<br/>
Visit the page of the eck entity with the specified type and title
<br/><br/>

```gherkin
When I visit the ECK "contact" "contact_type" entity page with the title "Test contact"
```

</details>

<details>
  <summary><code>@When I visit the ECK :bundle :entity_type entity edit page with the title :title</code></summary>

<br/>
Visit the edit page of the eck entity with the specified type and title
<br/><br/>

```gherkin
When I visit the ECK "contact" "contact_type" entity edit page with the title "Test contact"
```

</details>
