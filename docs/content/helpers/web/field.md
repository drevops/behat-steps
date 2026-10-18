---
title: "FieldTrait helpers"
sidebar_label: "FieldTrait"
sidebar_position: 9
description: "Manipulate form fields and verify widget functionality."
custom_edit_url: null
---

# FieldTrait helpers

[Source](../../../../src/Steps/Web/FieldTrait.php), [Steps](../../steps/web/field.md)

> Manipulate form fields and verify widget functionality.

<details>
  <summary><code>public function fieldDisableFormValidation(?string $selector = NULL): void</code></summary>

<br/>
Disable browser validation for forms
<br/><br/>

</details>

<details>
  <summary><code>public function fieldFillMultiValueItems(string $field, array $values): void</code></summary>

<br/>
Fill in the items of a multi-value field widget with a list of values
<br/><br/>

</details>

<details>
  <summary><code>public function fieldFind(string $field): ?NodeElement</code></summary>

<br/>
Find a field by its id, name, label or value
<br/><br/>

</details>

<details>
  <summary><code>public function fieldFindSelectOption(string $selector, string $option): ?NodeElement</code></summary>

<br/>
Find an option of a select by its value or text
<br/><br/>

</details>

<details>
  <summary><code>public function fieldGet(string $field): NodeElement</code></summary>

<br/>
Get a field by its id, name, label or value
<br/><br/>

</details>

<details>
  <summary><code>public function fieldGetAddMoreButtonSelectors(): array</code></summary>

<br/>
CSS selectors for the "Add another item" button
<br/><br/>

</details>

<details>
  <summary><code>public function fieldGetRadioButton(string $selector): NodeElement</code></summary>

<br/>
Get a radio button by its id, name, label or value
<br/><br/>

</details>

<details>
  <summary><code>public function fieldGetRequiredMarkerSelectors(): array</code></summary>

<br/>
CSS selectors that indicate a required-field marker
<br/><br/>

</details>

<details>
  <summary><code>public function fieldGetSelect(string $selector): NodeElement</code></summary>

<br/>
Get a select by its id, name or label
<br/><br/>

</details>

<details>
  <summary><code>public function fieldGetSelectOption(string $selector, string $option): NodeElement</code></summary>

<br/>
Get an option of a select by its value or text
<br/><br/>

</details>

<details>
  <summary><code>public function fieldIsMarkedRequired(NodeElement $field_element): bool</code></summary>

<br/>
Check if a given field element is marked as required
<br/><br/>

</details>

<details>
  <summary><code>public function fieldSetWysiwygValue(NodeElement $element, string $value): void</code></summary>

<br/>
Set the value of a field that carries a WYSIWYG editor
<br/><br/>

</details>

<details>
  <summary><code>public function fieldUnselectValue(NodeElement $select_field, string $value): void</code></summary>

<br/>
Remove a value from the selection of a select
<br/><br/>

</details>
