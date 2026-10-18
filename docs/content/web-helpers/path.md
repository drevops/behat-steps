---
title: "Paths - Web helpers"
sidebar_label: "Paths"
sidebar_position: 19
description: "Navigate and verify paths with URL validation."
custom_edit_url: null
---

# Paths

`DrevOps\BehatSteps\Steps\Web\PathTrait` - [Source](../../../src/Steps/Web/PathTrait.php), [Steps](../web-steps/path.md)

> Navigate and verify paths with URL validation.

<details>
  <summary><code>public function pathGetCurrent(): string</code></summary>

<br/>
Get the path of the current URL
<br/><br/>

</details>

<details>
  <summary><code>public function pathGetCurrentUrlQuery(): array</code></summary>

<br/>
Get the query parameters of the current URL
<br/><br/>

</details>

<details>
  <summary><code>public function pathGetUrlParameter(string $name): array|string</code></summary>

<br/>
Get a query parameter of the current URL
<br/><br/>

</details>

<details>
  <summary><code>public function pathIsCurrent(string $path): bool</code></summary>

<br/>
Check whether the current URL is at a path
<br/><br/>

</details>
