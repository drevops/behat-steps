---
title: "Drupal\\ConfigTrait steps"
sidebar_label: "ConfigTrait"
sidebar_position: 6
description: "Assert and set stored Drupal configuration values with automatic revert."
custom_edit_url: null
---

# Drupal\ConfigTrait steps

[Source](../../../../src/Steps/Drupal/ConfigTrait.php), [Example](../../../../tests/behat/features/drupal_config.feature), [Helpers](../../helpers/drupal/config.md)

>  Assert and set stored Drupal configuration values with automatic revert.
>  <br/><br/>
>  Set a configuration value for test setup and assert that a configuration
>  object's key holds, or contains, an expected value. Nested keys are
>  addressable with dotted notation (for example `page.front`).
>  <br/><br/>
>  2 families of assertions read the value differently:
>  - The default steps read the stored value, with module and `settings.php`
>  overrides left unapplied. This is symmetric with the set steps and is
>  what most setup-and-assert scenarios need.
>  - The `effective` steps read the value with module and `settings.php`
>  overrides applied: the value the running site uses.
>  <br/><br/>
>  Values are compared by their stringified form, so `true`, `42` and JSON
>  arrays written in a step match their typed configuration counterparts. The
>  `contain` steps match a substring for string values and membership for
>  array values, searched recursively.
>  <br/><br/>
>  Configuration objects touched by the set steps are snapshotted on first
>  write and restored after the scenario. An existing object is reset to its
>  original data, and an object that did not exist is deleted.
>  <br/><br/>
>  Skip the revert with `@behat-steps-skip:ConfigTrait`.
>  <br/><br/>
>  ```
>  Scenario: Assert configured values
>    Given the config "mymodule.settings" with the key "api.endpoint" has the value "https://api.example.com"
>    Then the config "mymodule.settings" with the key "api.endpoint" should have the value "https://api.example.com"
>    And the config "system.site" with the key "name" should have the effective value "My overridden site"
>  ```

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `config.enabled` | boolean | `TRUE` | `@behat-steps-skip:ConfigTrait` | Restore the configuration values a scenario changed once it finishes. |

## Steps

<details>
  <summary><code>@Given the config :name with the key :key has the value :value</code></summary>

<br/>
Set a stored Drupal configuration value
<br/><br/>

```gherkin
Given the config "system.site" with the key "page.front" has the value "/node"
```

</details>

<details>
  <summary><code>@Given the following config values exist:</code></summary>

<br/>
Set multiple stored Drupal configuration values from a table
<br/><br/>

```gherkin
Given the following config values exist:
  | name              | key          | value                   |
  | system.site       | name         | My site                 |
  | mymodule.settings | api.endpoint | https://api.example.com |
  | mymodule.settings | roles        | ["editor","reviewer"]   |
```

</details>

<details>
  <summary><code>@Then the config :name with the key :key should have the value :value</code></summary>

<br/>
Assert that a stored configuration value equals an expected value
<br/><br/>

```gherkin
Then the config "system.site" with the key "name" should have the value "My site"
```

</details>

<details>
  <summary><code>@Then the config :name with the key :key should not have the value :value</code></summary>

<br/>
Assert that a stored configuration value does not equal a value
<br/><br/>

```gherkin
Then the config "system.site" with the key "name" should not have the value "Wrong"
```

</details>

<details>
  <summary><code>@Then the config :name with the key :key should contain :value</code></summary>

<br/>
Assert that a stored configuration value contains an expected value
<br/><br/>

```gherkin
Then the config "system.site" with the key "name" should contain "site"
```

</details>

<details>
  <summary><code>@Then the config :name with the key :key should not contain :value</code></summary>

<br/>
Assert that a stored configuration value does not contain a value
<br/><br/>

```gherkin
Then the config "system.site" with the key "name" should not contain "xyz"
```

</details>

<details>
  <summary><code>@Then the config :name with the key :key should have the effective value :value</code></summary>

<br/>
Assert that an effective configuration value equals an expected value
<br/><br/>

```gherkin
Then the config "system.site" with the key "name" should have the effective value "Overridden"
```

</details>

<details>
  <summary><code>@Then the config :name with the key :key should not have the effective value :value</code></summary>

<br/>
Assert that an effective configuration value does not equal a value
<br/><br/>

```gherkin
Then the config "system.site" with the key "name" should not have the effective value "Wrong"
```

</details>

<details>
  <summary><code>@Then the config :name with the key :key should contain the effective value :value</code></summary>

<br/>
Assert that an effective configuration value contains an expected value
<br/><br/>

```gherkin
Then the config "system.site" with the key "name" should contain the effective value "Over"
```

</details>

<details>
  <summary><code>@Then the config :name with the key :key should not contain the effective value :value</code></summary>

<br/>
Assert that an effective configuration value does not contain a value
<br/><br/>

```gherkin
Then the config "system.site" with the key "name" should not contain the effective value "xyz"
```

</details>
