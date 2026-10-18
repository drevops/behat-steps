---
title: "Drupal\\DrushTrait helpers"
sidebar_label: "DrushTrait"
sidebar_position: 8
description: "Run Drush commands and assert their output."
custom_edit_url: null
---

# Drupal\DrushTrait helpers

[Source](../../../../src/Steps/Drupal/DrushTrait.php), [Steps](../../steps/drupal/drush.md)

> Run Drush commands and assert their output.

<details>
  <summary><code>public function drushGetBackend(): DrushCapabilityInterface</code></summary>

<br/>
Return the backend that runs Drush commands
<br/><br/>

</details>

<details>
  <summary><code>public function drushGetOutput(): string</code></summary>

<br/>
Return the output of the most recent Drush command
<br/><br/>

</details>

<details>
  <summary><code>public function drushRunExpectingFailure(string $command, ?string $arguments = NULL): void</code></summary>

<br/>
Run a Drush command expecting a non-zero exit, keeping its output
<br/><br/>

</details>
