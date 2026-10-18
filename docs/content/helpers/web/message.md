---
title: "MessageTrait helpers"
sidebar_label: "MessageTrait"
sidebar_position: 15
description: "Assert status, error, warning and success messages rendered on the page."
custom_edit_url: null
---

# MessageTrait helpers

[Source](../../../../src/Steps/Web/MessageTrait.php), [Steps](../../steps/web/message.md)

> Assert status, error, warning and success messages rendered on the page.

<details>
  <summary><code>public function messageAssertExistsOfType(string $message, string $type): void</code></summary>

<br/>
Assert that a message of the given type contains the expected text
<br/><br/>

</details>

<details>
  <summary><code>public function messageAssertNotExistsOfType(string $message, string $type): void</code></summary>

<br/>
Assert that no message of the given type contains the expected text
<br/><br/>

</details>

<details>
  <summary><code>public function messageGetSelector(string $type): string</code></summary>

<br/>
Return the configured CSS selector for a message type
<br/><br/>

</details>
