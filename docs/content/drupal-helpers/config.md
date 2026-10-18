---
title: "Configuration - Drupal helpers"
sidebar_label: "Configuration"
sidebar_position: 5
description: "Assert and set stored Drupal configuration values with automatic revert."
custom_edit_url: null
---

# Configuration

`DrevOps\BehatSteps\Steps\Drupal\ConfigTrait` - [Source](../../../src/Steps/Drupal/ConfigTrait.php), [Steps](../drupal-steps/config.md)

> Assert and set stored Drupal configuration values with automatic revert.

<details>
  <summary><code>public function configFindEffectiveValue(string $name, string $key): mixed</code></summary>

<br/>
Find an effective configuration value, with overrides applied
<br/><br/>

</details>

<details>
  <summary><code>public function configFindStoredValue(string $name, string $key): mixed</code></summary>

<br/>
Find a stored configuration value, ignoring runtime overrides
<br/><br/>

</details>

<details>
  <summary><code>public function configSetValue(string $name, string $key, mixed $value): void</code></summary>

<br/>
Set a stored configuration value, restored after the scenario
<br/><br/>

</details>
