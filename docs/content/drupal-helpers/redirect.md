---
title: "Redirects - Drupal helpers"
sidebar_label: "Redirects"
sidebar_position: 21
description: "Manage Drupal redirect entities provided by the contrib redirect module."
custom_edit_url: null
---

# Redirects

`DrevOps\BehatSteps\Steps\Drupal\RedirectTrait` - [Source](../../../src/Steps/Drupal/RedirectTrait.php), [Steps](../drupal-steps/redirect.md)

> Manage Drupal redirect entities provided by the contrib `redirect` module.

<details>
  <summary><code>public function redirectCreate(string $from, string $to, int $status_code = 301): EntityStubInterface</code></summary>

<br/>
Create a redirect
<br/><br/>

</details>

<details>
  <summary><code>public function redirectDelete(string $from): void</code></summary>

<br/>
Delete the redirects from a source path
<br/><br/>

</details>

<details>
  <summary><code>public function redirectExists(string $from, ?string $to = NULL, ?int $status_code = NULL): bool</code></summary>

<br/>
Check whether a redirect exists
<br/><br/>

</details>
