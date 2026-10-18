---
title: "Responsive layouts - Web helpers"
sidebar_label: "Responsive layouts"
sidebar_position: 23
description: "Test responsive layouts with viewport control."
custom_edit_url: null
---

# Responsive layouts

`DrevOps\BehatSteps\Steps\Web\ResponsiveTrait` - [Source](../../../src/Steps/Web/ResponsiveTrait.php), [Steps](../web-steps/responsive.md)

> Test responsive layouts with viewport control.

<details>
  <summary><code>public function responsiveGetAllBreakpoints(): array</code></summary>

<br/>
Get all available breakpoints
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveGetBreakpoint(string $breakpoint): string</code></summary>

<br/>
Get breakpoint dimensions by name
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveGetCurrentDimensions(): array</code></summary>

<br/>
Get current viewport dimensions
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveResize(int $width, int $height): void</code></summary>

<br/>
Resize the browser window
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveResizeToBreakpoint(string $breakpoint): void</code></summary>

<br/>
Resize viewport to a named breakpoint
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveSetBreakpoint(string $name, string $dimensions): void</code></summary>

<br/>
Set a custom breakpoint
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveSetBreakpoints(array $breakpoints): void</code></summary>

<br/>
Set custom breakpoints
<br/><br/>

</details>
