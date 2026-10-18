---
title: "ElementTrait helpers"
sidebar_label: "ElementTrait"
sidebar_position: 8
description: "Interact with HTML elements using CSS selectors and DOM attributes."
custom_edit_url: null
---

# ElementTrait helpers

[Source](../../../../src/Steps/Web/ElementTrait.php), [Steps](../../steps/web/element.md)

> Interact with HTML elements using CSS selectors and DOM attributes.

<details>
  <summary><code>public function elementExecuteJs(string $selector, string $script)</code></summary>

<br/>
Execute JS on an element provided by the selector
<br/><br/>

</details>

<details>
  <summary><code>public function elementFindVisible(string $selector): ?NodeElement</code></summary>

<br/>
Find the first visible element matching a CSS selector
<br/><br/>

</details>

<details>
  <summary><code>public function elementGet(string $selector): NodeElement</code></summary>

<br/>
Get the first element matching a CSS selector
<br/><br/>

</details>

<details>
  <summary><code>public function elementGetNth(array $elements, int $index, string $subject): NodeElement</code></summary>

<br/>
Return the element at a 1-based index or throw a clear error
<br/><br/>

</details>

<details>
  <summary><code>public function elementGetPosition(string $selector): int</code></summary>

<br/>
Get where an element's markup starts within the markup of the page
<br/><br/>

</details>

<details>
  <summary><code>public function elementGetScrollIntoViewCenter(): bool</code></summary>

<br/>
Whether to scroll elements to the center of the viewport
<br/><br/>

```
class FeatureContext extends WebContext {
  public function elementGetScrollIntoViewCenter(): bool {
    return FALSE;
  }
}
```

</details>

<details>
  <summary><code>public function elementGetVisible(string $selector): NodeElement</code></summary>

<br/>
Get the first visible element matching a CSS selector
<br/><br/>

</details>

<details>
  <summary><code>public function elementIsVisuallyVisible(string $selector, int $offset)</code></summary>

<br/>
Check whether an element is displayed within the viewport
<br/><br/>

</details>
