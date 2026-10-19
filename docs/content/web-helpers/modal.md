---
title: "Modals - Web helpers"
sidebar_label: "Modals"
sidebar_position: 17
description: "Interact with and assert modals."
custom_edit_url: null
---

# Modals

`DrevOps\BehatSteps\Steps\Web\ModalTrait` - [Source](../../../src/Steps/Web/ModalTrait.php), [Steps](../web-steps/modal.md)

> Interact with and assert modals.

<details>
  <summary><code>public function modalFind(): ?NodeElement</code></summary>

<br/>
Find the first visible modal, or fall back to the first DOM match
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetCloseSelectors(): array</code></summary>

<br/>
Get the CSS selectors for the modal close button
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetContent(): NodeElement</code></summary>

<br/>
Get the content element of the visible modal
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetContentSelectors(): array</code></summary>

<br/>
Get the CSS selectors for the modal content
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetElement(string $selector): NodeElement</code></summary>

<br/>
Get a visible element in the visible modal
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetSelectors(): array</code></summary>

<br/>
Get the CSS selectors for the modal container
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetVisible(): NodeElement</code></summary>

<br/>
Return the first visible modal
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetWaitTimeout(): int</code></summary>

<br/>
Get the timeout in seconds for waiting for the modal to appear
<br/><br/>

</details>

<details>
  <summary><code>public function modalWaitForAppearWithin(int $seconds): void</code></summary>

<br/>
Wait for the modal to appear within a number of seconds
<br/><br/>

</details>
