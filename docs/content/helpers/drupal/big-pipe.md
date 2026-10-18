---
title: "Drupal\\BigPipeTrait helpers"
sidebar_label: "BigPipeTrait"
sidebar_position: 1
description: "Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios."
custom_edit_url: null
---

# Drupal\BigPipeTrait helpers

[Source](../../../../src/Steps/Drupal/BigPipeTrait.php), [Steps](../../steps/drupal/big-pipe.md)

> Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios.

<details>
  <summary><code>public function bigPipeGetWaitTimeout(): int</code></summary>

<br/>
Maximum time to wait for BigPipe placeholders, in milliseconds
<br/><br/>

</details>

<details>
  <summary><code>public function bigPipeWaitForPlaceholders(int $timeout_ms): void</code></summary>

<br/>
Wait until no BigPipe placeholder markers remain in the DOM
<br/><br/>

</details>
