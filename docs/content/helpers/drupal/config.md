---
title: "Drupal\\ConfigTrait helpers"
sidebar_label: "ConfigTrait"
sidebar_position: 4
description: "Assert and set stored Drupal configuration values with automatic revert."
custom_edit_url: null
---

# Drupal\ConfigTrait helpers

[Source](../../../../src/Steps/Drupal/ConfigTrait.php), [Steps](../../steps/drupal/config.md)

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
