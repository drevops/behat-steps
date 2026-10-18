---
title: "Mapped values - Web helpers"
sidebar_label: "Mapped values"
sidebar_position: 14
description: "Replace {{ Key }} tokens in step arguments and table cells."
custom_edit_url: null
---

# Mapped values

`DrevOps\BehatSteps\Steps\Web\MappingTrait` - [Source](../../../src/Steps/Web/MappingTrait.php), [Steps](../web-steps/mapping.md)

> Replace `{{ Key }}` tokens in step arguments and table cells.

<details>
  <summary><code>public function mappingGetGroups(): array</code></summary>

<br/>
Return the configured mapping groups
<br/><br/>

</details>

<details>
  <summary><code>public function mappingGetValue(string $name): string</code></summary>

<br/>
Returns a mapped value by its key
<br/><br/>

</details>

<details>
  <summary><code>public function mappingSubstitute(string $value): string</code></summary>

<br/>
Substitutes every mapping token found in a single string
<br/><br/>

</details>
