---
title: "Blocks - Drupal steps"
sidebar_label: "Blocks"
sidebar_position: 3
description: "Manage Drupal blocks."
custom_edit_url: null
---

# Blocks

`DrevOps\BehatSteps\Steps\Drupal\BlockTrait` - [Source](../../../src/Steps/Drupal/BlockTrait.php), [Example](../../../tests/behat/features/drupal_block.feature), [Helpers](../drupal-helpers/block.md)

>  Manage Drupal blocks.
>  - Create and configure blocks with custom visibility conditions.
>  - Place blocks in regions and assert their configured region.
>  - Created blocks are automatically removed at the end of the scenario.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "BlockCapabilityInterface" | `BlockCapabilityInterface` |
| The core "block" module is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the instance of the block :admin_label with the following configuration exists:</code></summary>

<br/>
Create a block instance
<br/><br/>

```gherkin
Given the instance of the block "My block" with the following configuration exists:
  | label         | My block |
  | label_display | 1        |
  | region        | content  |
  | status        | 1        |
```

</details>

<details>
  <summary><code>@Given the block :label has the following configuration:</code></summary>

<br/>
Configure an existing block identified by label
<br/><br/>

```gherkin
Given the block "My block" has the following configuration:
  | label_display | 1       |
  | region        | content |
  | status        | 1       |
```

</details>

<details>
  <summary><code>@Given the block :label does not exist</code></summary>

<br/>
Remove every block in the default theme with the label
<br/><br/>

```gherkin
Given the block "My block" does not exist
```

</details>

<details>
  <summary><code>@Given the block :label is enabled</code></summary>

<br/>
Enable a block specified by label
<br/><br/>

```gherkin
Given the block "My block" is enabled
```

</details>

<details>
  <summary><code>@Given the block :label is disabled</code></summary>

<br/>
Disable a block specified by label
<br/><br/>

```gherkin
Given the block "My block" is disabled
```

</details>

<details>
  <summary><code>@Given the block :label has the condition :condition with the following configuration:</code></summary>

<br/>
Set a visibility condition for a block
<br/><br/>

```gherkin
Given the block "My block" has the condition "request_path" with the following configuration:
  | pages  | /node/1\r\n/about |
  | negate | 0                 |
```

</details>

<details>
  <summary><code>@Given the block :label has the condition :condition removed</code></summary>

<br/>
Remove a visibility condition from the specified block
<br/><br/>

```gherkin
Given the block "My block" has the condition "request_path" removed
```

</details>

<details>
  <summary><code>@Then the block :label should exist</code></summary>

<br/>
Assert that a block with the specified label exists
<br/><br/>

```gherkin
Then the block "My block" should exist
```

</details>

<details>
  <summary><code>@Then the block :label should not exist</code></summary>

<br/>
Assert that a block with the specified label does not exist
<br/><br/>

```gherkin
Then the block "My block" should not exist
```

</details>

<details>
  <summary><code>@Then the block :label in the region :region should exist</code></summary>

<br/>
Assert that a block with the specified label exists in a region
<br/><br/>

```gherkin
Then the block "My block" in the region "content" should exist
```

</details>

<details>
  <summary><code>@Then the block :label in the region :region should not exist</code></summary>

<br/>
Assert that a block with the specified label does not exist in a region
<br/><br/>

```gherkin
Then the block "My block" in the region "content" should not exist
```

</details>
