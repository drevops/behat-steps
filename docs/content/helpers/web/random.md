---
title: "RandomTrait helpers"
sidebar_label: "RandomTrait"
sidebar_position: 19
description: "Replace random-value tokens in step arguments and table cells."
custom_edit_url: null
---

# RandomTrait helpers

[Source](../../../../src/Steps/Web/RandomTrait.php), [Steps](../../steps/web/random.md)

> Replace random-value tokens in step arguments and table cells.

<details>
  <summary><code>public function randomGenerate(string $type, array $args): string|int</code></summary>

<br/>
Dispatches to the type-specific generator
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateEmail(): string</code></summary>

<br/>
Generates a syntactically valid email at the reserved '.test' TLD
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateInt(int $min, int $max): int</code></summary>

<br/>
Generates an integer in '[min, max]' inclusive
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateMachineName(int $length): string</code></summary>

<br/>
Generates a lowercase alphanumeric machine name starting with a letter
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateName(int $length): string</code></summary>

<br/>
Generates a letters-and-digits name with original case preserved
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateString(int $length): string</code></summary>

<br/>
Generates a lowercase string, the default token type
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateUuid(): string</code></summary>

<br/>
Generates a UUID v4 string
<br/><br/>

</details>

<details>
  <summary><code>public function randomResolveLiteral(string $literal): string|int</code></summary>

<br/>
Resolves a token literal to its generated value
<br/><br/>

</details>

<details>
  <summary><code>public function randomSubstitute(string $message): string</code></summary>

<br/>
Substitutes every token match in '$message' via 'randomResolveLiteral()'
<br/><br/>

</details>

<details>
  <summary><code>public function randomSubstituteTable(TableNode $table): TableNode</code></summary>

<br/>
Applies 'randomSubstitute()' across every cell in '$table'
<br/><br/>

</details>
