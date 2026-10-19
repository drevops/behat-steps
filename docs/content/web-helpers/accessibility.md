---
title: "Accessibility - Web helpers"
sidebar_label: "Accessibility"
sidebar_position: 1
description: "Assess accessibility of rendered pages."
custom_edit_url: null
---

# Accessibility

`DrevOps\BehatSteps\Steps\Web\AccessibilityTrait` - [Source](../../../src/Steps/Web/AccessibilityTrait.php), [Steps](../web-steps/accessibility.md)

> Assess accessibility of rendered pages.

<details>
  <summary><code>public function accessibilityAssertResultPasses(array $result, string $rules): void</code></summary>

<br/>
Assert that an assessment result passes the effective failure threshold
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityAssess(string $rules): array</code></summary>

<br/>
Run the engine, normalize the result, record it for the scenario
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityFetchJs(string $url, int $timeout): string|false</code></summary>

<br/>
Read the engine source once from the given location
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityFormatUrl(string $url): string</code></summary>

<br/>
Format a page URL for display in reports and gate messages
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetAutoTag(): string</code></summary>

<br/>
Return the base tag name that enables automatic mode (no `@` prefix)
<br/><br/>

</details>

<details>
  <summary><code>public static function accessibilityGetBlankUrls(): array</code></summary>

<br/>
Return URL values that represent a blank tab rather than a real page
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetCdnUrl(): string</code></summary>

<br/>
Return the URL used by the default accessibilityGetJs() implementation
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetDefaultRules(): string</code></summary>

<br/>
Return the default rule identifier passed to the engine
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetFailOnIncomplete(): bool</code></summary>

<br/>
Return TRUE if "incomplete" findings should fail the gate by default
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetFailureThreshold(): string</code></summary>

<br/>
Return the default failure threshold
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetFetchAttempts(): int</code></summary>

<br/>
Return how many times the engine fetch is attempted before failing
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetFetchTimeout(): int</code></summary>

<br/>
Return the per-attempt timeout, in seconds, for the engine fetch
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetImpacts(): array</code></summary>

<br/>
Return the canonical impact levels in descending severity order
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetJs(): string</code></summary>

<br/>
Return the JavaScript source to inject into the page
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetPrintCli(): bool</code></summary>

<br/>
Return TRUE to print a 1-line per-page summary to the console
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetReportDir(): string</code></summary>

<br/>
Return the absolute directory used to write per-scenario reports
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityNormalizeResults(array $raw): array</code></summary>

<br/>
Normalize raw engine output into the canonical shape used by the trait
<br/><br/>

</details>

<details>
  <summary><code>public static function accessibilityRenderAggregate(array $data): string</code></summary>

<br/>
Render the entire aggregate report from prepared data
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityRenderHtmlPage(string $sections): string</code></summary>

<br/>
Wrap the per-URL sections in a standalone HTML page
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityRenderHtmlSections(): string</code></summary>

<br/>
Render the per-URL section markup (1 `<section>` per visited URL)
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityRunEngine(string $rules): array</code></summary>

<br/>
Execute the engine against the current page and return raw results
<br/><br/>

</details>
