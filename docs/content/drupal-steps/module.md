---
title: "Modules - Drupal steps"
sidebar_label: "Modules"
sidebar_position: 18
description: "Enable and disable Drupal modules with automatic state restoration."
custom_edit_url: null
---

# Modules

`DrevOps\BehatSteps\Steps\Drupal\ModuleTrait` - [Source](../../../src/Steps/Drupal/ModuleTrait.php), [Example](../../../tests/behat/features/drupal_module.feature), [Helpers](../../../HELPERS.md#drupalmoduletrait)

>  Enable and disable Drupal modules with automatic state restoration.
>  <br/><br/>
>  Supports automatic module management via scenario and feature tags. A
>  scenario tag overrides a feature tag naming the same module.
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:ModuleTrait`.
>  <br/><br/>
>  Special tags:
>  - `@module:module_name` - enable module for scenario
>  - `@module:!module_name` - disable module for scenario

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `module.enabled` | boolean | `TRUE` | `@behat-steps-skip:ModuleTrait` | Apply the `@module:` tags of a scenario and restore the original module states afterwards. |

## Steps

<details>
  <summary><code>@Given the module :module is enabled</code></summary>

<br/>
Enable a module
<br/><br/>

```gherkin
Given the module "ctools" is enabled
```

</details>

<details>
  <summary><code>@Given the module :module is disabled</code></summary>

<br/>
Disable a module
<br/><br/>

```gherkin
Given the module "shield" is disabled
```

</details>

<details>
  <summary><code>@Given the following modules are enabled:</code></summary>

<br/>
Enable multiple modules
<br/><br/>

```gherkin
Given the following modules are enabled:
  | ctools |
  | views  |
```

</details>

<details>
  <summary><code>@Given the following modules are disabled:</code></summary>

<br/>
Disable multiple modules
<br/><br/>

```gherkin
Given the following modules are disabled:
  | shield           |
  | stage_file_proxy |
```

</details>

<details>
  <summary><code>@Then the module :module should be enabled</code></summary>

<br/>
Assert that a module is enabled
<br/><br/>

```gherkin
Then the module "ctools" should be enabled
```

</details>

<details>
  <summary><code>@Then the module :module should be disabled</code></summary>

<br/>
Assert that a module is disabled
<br/><br/>

```gherkin
Then the module "shield" should be disabled
```

</details>

<details>
  <summary><code>@Then the following modules should be enabled:</code></summary>

<br/>
Assert that multiple modules are enabled
<br/><br/>

```gherkin
Then the following modules should be enabled:
  | ctools |
  | views  |
```

</details>

<details>
  <summary><code>@Then the following modules should be disabled:</code></summary>

<br/>
Assert that multiple modules are disabled
<br/><br/>

```gherkin
Then the following modules should be disabled:
  | shield           |
  | stage_file_proxy |
```

</details>
