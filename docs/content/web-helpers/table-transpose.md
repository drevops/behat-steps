---
title: "Vertical tables - Web helpers"
sidebar_label: "Vertical tables"
sidebar_position: 28
description: "Reads a vertical Gherkin table as 1 set of values per entity."
custom_edit_url: null
---

# Vertical tables

`DrevOps\BehatSteps\Steps\Helper\Web\TableTransposeTrait` - [Source](../../../src/Steps/Helper/Web/TableTransposeTrait.php)

> Reads a vertical Gherkin table as 1 set of values per entity.

<details>
  <summary><code>public function tableTransposeHorizontal(array $entities): TableNode</code></summary>

<br/>
Convert vertical format entities to horizontal TableNode
<br/><br/>

</details>

<details>
  <summary><code>public function tableTransposeVertical(TableNode $table): array</code></summary>

<br/>
Transpose a vertical table format (field/value columns) to entity arrays
<br/><br/>

</details>
