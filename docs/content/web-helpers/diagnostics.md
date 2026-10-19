---
title: "Failure diagnostics - Web helpers"
sidebar_label: "Failure diagnostics"
sidebar_position: 6
description: "Append on-failure diagnostics to the failure message of any failed step."
custom_edit_url: null
---

# Failure diagnostics

`DrevOps\BehatSteps\Steps\Web\DiagnosticsTrait` - [Source](../../../src/Steps/Web/DiagnosticsTrait.php), [Steps](../web-steps/diagnostics.md)

> Append on-failure diagnostics to the failure message of any failed step.

<details>
  <summary><code>public function diagnosticsFindBrowserDriverName(): ?string</code></summary>

<br/>
Return the browser driver's class, or NULL when it is unavailable
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsFindRerunCommand(): ?string</code></summary>

<br/>
Return the command that re-runs just the failing scenario
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsFindStatusCode(): ?int</code></summary>

<br/>
Return the last response status code, or NULL when it is unavailable
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsFindUrl(): ?string</code></summary>

<br/>
Return the current page URL, or NULL when it cannot be determined
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetHeader(): string</code></summary>

<br/>
Return the header line that precedes the diagnostics block
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetJsErrors(): array</code></summary>

<br/>
Return collected JavaScript console error messages
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetRerunBinary(): string</code></summary>

<br/>
Return the binary used in the re-run command. Override to customize
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowBrowserDriver(): bool</code></summary>

<br/>
Return TRUE to include the browser driver class. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowJsErrors(): bool</code></summary>

<br/>
Return TRUE to include JavaScript console errors. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowRerun(): bool</code></summary>

<br/>
Return TRUE to include the re-run command. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowStatusCode(): bool</code></summary>

<br/>
Return TRUE to include the HTTP status code. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowUrl(): bool</code></summary>

<br/>
Return TRUE to include the current URL. Override to suppress
<br/><br/>

</details>
