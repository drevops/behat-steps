---
title: "TableTrait helpers"
sidebar_label: "TableTrait"
sidebar_position: 23
description: "Interact with HTML table elements and assert their content."
custom_edit_url: null
---

# TableTrait helpers

[Source](../../../../src/Steps/Web/TableTrait.php), [Steps](../../steps/web/table.md)

> Interact with HTML table elements and assert their content.

<details>
  <summary><code>public function tableFindRowByText(string $row_text): ?NodeElement</code></summary>

<br/>
Find a table row containing the given text
<br/><br/>

</details>

<details>
  <summary><code>public function tableFindRowByValues(NodeElement $table, array $values, string $selector): ?NodeElement</code></summary>

<br/>
Find the first body row whose cells hold the given values
<br/><br/>

</details>

<details>
  <summary><code>public function tableGet(string $selector): NodeElement</code></summary>

<br/>
Return the table element matching a CSS selector
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetBodyRowSelector(): string</code></summary>

<br/>
Get the CSS selector for table body rows
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetColumnIndex(NodeElement $table, string $column, string $selector): int</code></summary>

<br/>
Get the index of a column by its header text
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetColumnValues(NodeElement $table, int $column_index): array</code></summary>

<br/>
Get the texts of a column's cells in the body rows of a table
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetHeaderSelector(): string</code></summary>

<br/>
Get the CSS selector for table header cells
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetHeaders(NodeElement $table): array</code></summary>

<br/>
Get the header texts from a table element
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetRowByText(string $row_text): NodeElement</code></summary>

<br/>
Return the first row on the page containing the text
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetRows(NodeElement $table): array</code></summary>

<br/>
Get the body rows from a table element
<br/><br/>

</details>
