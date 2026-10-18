---
title: "Watchdog - Drupal helpers"
sidebar_label: "Watchdog"
sidebar_position: 27
description: "Assert Drupal does not trigger PHP errors during scenarios using Watchdog."
custom_edit_url: null
---

# Watchdog

`DrevOps\BehatSteps\Steps\Drupal\WatchdogTrait` - [Source](../../../src/Steps/Drupal/WatchdogTrait.php), [Steps](../drupal-steps/watchdog.md)

> Assert Drupal does not trigger PHP errors during scenarios using Watchdog.

<details>
  <summary><code>public function watchdogAssertErrorsNotExist(string $context): void</code></summary>

<br/>
Assert no errors at or above the severity threshold were logged
<br/><br/>

</details>

<details>
  <summary><code>public function watchdogClearErrors(): array</code></summary>

<br/>
Clear the errors logged since the scenario started, and return them
<br/><br/>

</details>

<details>
  <summary><code>public function watchdogGetFailOnErrors(): bool</code></summary>

<br/>
Return TRUE if a scenario that logged an error should fail
<br/><br/>

</details>
