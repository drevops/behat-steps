---
title: "Page regions - Web helpers"
sidebar_label: "Page regions"
sidebar_position: 18
description: "Interact with and assert against named page regions."
custom_edit_url: null
---

# Page regions

`DrevOps\BehatSteps\Steps\Web\RegionTrait` - [Source](../../../src/Steps/Web/RegionTrait.php), [Steps](../web-steps/region.md)

> Interact with and assert against named page regions.

<details>
  <summary><code>public function regionFindElementByText(string $region, string $selector, string $text): ?NodeElement</code></summary>

<br/>
Find an element in a region whose text matches exactly
<br/><br/>

</details>

<details>
  <summary><code>public function regionFindElementWithAttribute(string $region, string $selector, string $attribute, string $value): ?NodeElement</code></summary>

<br/>
Find an element in a region whose attribute holds a value
<br/><br/>

</details>

<details>
  <summary><code>public function regionGet(string $region): NodeElement</code></summary>

<br/>
Return a named region on the current page
<br/><br/>

</details>

<details>
  <summary><code>public function regionGetElementByText(string $region, string $selector, string $text): NodeElement</code></summary>

<br/>
Return an element in a region whose text matches exactly
<br/><br/>

</details>
