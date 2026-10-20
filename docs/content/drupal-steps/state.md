---
title: "State API - Drupal steps"
sidebar_label: "State API"
sidebar_position: 23
description: "Manage and assert Drupal State API values with automatic revert."
custom_edit_url: null
---

# State API

`DrevOps\BehatSteps\Steps\Drupal\StateTrait` - [Source](../../../src/Steps/Drupal/StateTrait.php), [Example](../../../tests/behat/features/drupal_state.feature), [Helpers](../../../HELPERS.md#drupalstatetrait)

>  Manage and assert Drupal State API values with automatic revert.
>  <br/><br/>
>  Provides set, delete, and assertion steps for keys in the site's state
>  key-value store. Touched keys are snapshotted on first access and reverted
>  after the scenario finishes.
>  <br/><br/>
>  Skip the revert with `@behat-steps-skip:StateTrait`. The snapshot registry
>  is cleared unconditionally before and after the scenario, so no snapshot
>  persists across scenarios.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `state.enabled` | boolean | `TRUE` | `@behat-steps-skip:StateTrait` | Restore the state values a scenario changed once it finishes. |

## Steps

<details>
  <summary><code>@Given the state :name has the value :value</code></summary>

<br/>
Set a Drupal state value
<br/><br/>

```gherkin
Given the state "my_module.launched" has the value "1"
```

</details>

<details>
  <summary><code>@Given the state :name does not exist</code></summary>

<br/>
Delete a Drupal state value
<br/><br/>

```gherkin
Given the state "my_module.launched" does not exist
```

</details>

<details>
  <summary><code>@Given the following state values exist:</code></summary>

<br/>
Set multiple Drupal state values from a table
<br/><br/>

```gherkin
Given the following state values exist:
  | name                   | value |
  | my_module.launched     | 1     |
  | my_module.feature_flag | 0     |
```

</details>

<details>
  <summary><code>@Then the state :name should have the value :value</code></summary>

<br/>
Assert that a Drupal state value equals an expected value
<br/><br/>

```gherkin
Then the state "my_module.launched" should have the value "1"
```

</details>

<details>
  <summary><code>@Then the state :name should not exist</code></summary>

<br/>
Assert that a Drupal state key does not exist
<br/><br/>

```gherkin
Then the state "my_module.launched" should not exist
```

</details>
