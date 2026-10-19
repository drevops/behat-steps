---
title: "JavaScript errors - Web helpers"
sidebar_label: "JavaScript errors"
sidebar_position: 12
description: "Automatically detect JavaScript errors during test execution."
custom_edit_url: null
---

# JavaScript errors

`DrevOps\BehatSteps\Steps\Web\JavascriptTrait` - [Source](../../../src/Steps/Web/JavascriptTrait.php), [Steps](../web-steps/javascript.md)

> Automatically detect JavaScript errors during test execution.

<details>
  <summary><code>public function javascriptAssertErrorsNotExist(): void</code></summary>

<br/>
Assert that no JavaScript errors were collected
<br/><br/>

</details>

<details>
  <summary><code>public function javascriptGetFailOnErrors(): bool</code></summary>

<br/>
Return TRUE if a scenario that collected a console error should fail
<br/><br/>

</details>
