---
title: "State API - Drupal helpers"
sidebar_label: "State API"
sidebar_position: 23
description: "Manage and assert Drupal State API values with automatic revert."
custom_edit_url: null
---

# State API

`DrevOps\BehatSteps\Steps\Drupal\StateTrait` - [Source](../../../src/Steps/Drupal/StateTrait.php), [Steps](../drupal-steps/state.md)

> Manage and assert Drupal State API values with automatic revert.

<details>
  <summary><code>public function stateExists(string $name): bool</code></summary>

<br/>
Determine whether a state key exists, a key holding NULL included
<br/><br/>

</details>

<details>
  <summary><code>public function stateFindValue(string $name): mixed</code></summary>

<br/>
Find a state value
<br/><br/>

</details>

<details>
  <summary><code>public function stateSetValue(string $name, mixed $value): void</code></summary>

<br/>
Set a state value, restored after the scenario
<br/><br/>

</details>
