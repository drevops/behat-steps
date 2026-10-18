---
title: "JSON responses - Web helpers"
sidebar_label: "JSON responses"
sidebar_position: 13
description: "Assert JSON responses with path and schema checks."
custom_edit_url: null
---

# JSON responses

`DrevOps\BehatSteps\Steps\Web\JsonTrait` - [Source](../../../src/Steps/Web/JsonTrait.php), [Steps](../web-steps/json.md)

> Assert JSON responses with path and schema checks.

<details>
  <summary><code>public function jsonAssertResponseMatchesSchema(string $schema_json): void</code></summary>

<br/>
Assert that the response validates against a JSON schema
<br/><br/>

</details>

<details>
  <summary><code>public function jsonDecode(string $content): array</code></summary>

<br/>
Decode a JSON string into an array
<br/><br/>

</details>

<details>
  <summary><code>public function jsonDecodeLoose(string $content): mixed</code></summary>

<br/>
Decode JSON content as loosely-typed data
<br/><br/>

</details>

<details>
  <summary><code>public function jsonGetContent(): string</code></summary>

<br/>
Get the response content, or the content a fixture step set in its place
<br/><br/>

</details>

<details>
  <summary><code>public function jsonGetScalar(string $path): mixed</code></summary>

<br/>
Get the scalar value a JSONPath expression matches
<br/><br/>

</details>

<details>
  <summary><code>public function jsonGetValue(string $path): mixed</code></summary>

<br/>
Get the value a JSONPath expression matches
<br/><br/>

</details>

<details>
  <summary><code>public function jsonQuery(string $path): array</code></summary>

<br/>
Run a JSONPath expression against the decoded response
<br/><br/>

</details>
