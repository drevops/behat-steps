---
title: "Content blocks - Drupal steps"
sidebar_label: "Content blocks"
sidebar_position: 8
description: "Manage Drupal content blocks."
custom_edit_url: null
---

# Content blocks

`DrevOps\BehatSteps\Steps\Drupal\ContentBlockTrait` - [Source](../../../src/Steps/Drupal/ContentBlockTrait.php), [Example](../../../tests/behat/features/drupal_content_block.feature), [Helpers](../../../HELPERS.md#drupalcontentblocktrait)

>  Manage Drupal content blocks.
>  - Define reusable custom block content with structured field data.
>  - Create and verify block_content entities by type and description, and
>  visit their edit pages.
>  - Created entities are automatically removed at the end of the scenario.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "BlockCapabilityInterface" | `BlockCapabilityInterface` |
| The core "block_content" module is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the following :content_block_type content blocks do not exist:</code></summary>

<br/>
Remove content blocks of a specified type with the given descriptions
<br/><br/>

```gherkin
Given the following "basic" content blocks do not exist:
  | [TEST] Footer Block  |
  | [TEST] Contact Form  |
```

</details>

<details>
  <summary><code>@Given the following :content_block_type content blocks exist:</code></summary>

<br/>
Create content blocks of the specified type with the given field values
<br/><br/>

```gherkin
Given the following "basic" content blocks exist:
  | info                  | status | body                   |
  | [TEST] Footer Contact | 1      | Call us at 555-1234    |
  | [TEST] Copyright      | 1      | © 2023 Example Company |
```

</details>

<details>
  <summary><code>@Given the following :content_block_type content blocks with fields exist:</code></summary>

<br/>
Create content blocks with vertical field format
<br/><br/>

```gherkin
Given the following basic content blocks with fields exist:
  | info   | [TEST] Block 1        | [TEST] Block 2        |
  | body   | First block content   | Second block content  |
  | status | 1                     | 1                     |
```

</details>

<details>
  <summary><code>@When I visit the :content_block_type content block edit page with the description :description</code></summary>

<br/>
Visit the edit page of the content block with the specified description
<br/><br/>

```gherkin
When I visit the "basic" content block edit page with the description "[TEST] Footer Block"
```

</details>

<details>
  <summary><code>@Then the content block type :content_block_type should exist</code></summary>

<br/>
Assert that a content block type exists
<br/><br/>

```gherkin
Then the content block type "Search" should exist
```

</details>
