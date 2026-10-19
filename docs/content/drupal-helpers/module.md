---
title: "Modules - Drupal helpers"
sidebar_label: "Modules"
sidebar_position: 18
description: "Enable and disable Drupal modules with automatic state restoration."
custom_edit_url: null
---

# Modules

`DrevOps\BehatSteps\Steps\Drupal\ModuleTrait` - [Source](../../../src/Steps/Drupal/ModuleTrait.php), [Steps](../drupal-steps/module.md)

> Enable and disable Drupal modules with automatic state restoration.

<details>
  <summary><code>public function moduleDisable(string $module): void</code></summary>

<br/>
Disable a module
<br/><br/>

</details>

<details>
  <summary><code>public function moduleEnable(string $module): void</code></summary>

<br/>
Enable a module
<br/><br/>

</details>

<details>
  <summary><code>public function moduleIsEnabled(string $module): bool</code></summary>

<br/>
Check if a module is enabled
<br/><br/>

</details>

<details>
  <summary><code>public function moduleIsPresent(string $module): bool</code></summary>

<br/>
Check if a module's code is present
<br/><br/>

</details>

<details>
  <summary><code>public function moduleSetEnabled(string $module, bool $is_enabled): void</code></summary>

<br/>
Enable or disable a module, restored after the scenario
<br/><br/>

</details>
