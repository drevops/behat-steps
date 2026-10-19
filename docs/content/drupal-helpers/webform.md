---
title: "Webforms - Drupal helpers"
sidebar_label: "Webforms"
sidebar_position: 28
description: "Manage Drupal webforms."
custom_edit_url: null
---

# Webforms

`DrevOps\BehatSteps\Steps\Drupal\WebformTrait` - [Source](../../../src/Steps/Drupal/WebformTrait.php), [Steps](../drupal-steps/webform.md)

> Manage Drupal webforms.

<details>
  <summary><code>public function webformCreateFromTemplate(string $title, EntityStubInterface $template): EntityStubInterface</code></summary>

<br/>
Create a webform as a copy of a template
<br/><br/>

</details>

<details>
  <summary><code>public function webformFindTemplateByTitle(string $title): ?EntityStubInterface</code></summary>

<br/>
Find the newest webform template whose title contains the given string
<br/><br/>

</details>

<details>
  <summary><code>public function webformLoadMultiple(string $title): array</code></summary>

<br/>
Load all webforms whose title contains the given string
<br/><br/>

</details>

<details>
  <summary><code>public function webformLoadTemplateMultiple(string $title): array</code></summary>

<br/>
Load all webform templates whose title contains the given string
<br/><br/>

</details>
