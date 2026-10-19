---
title: "Cookies - Web helpers"
sidebar_label: "Cookies"
sidebar_position: 3
description: "Verify and inspect browser cookies."
custom_edit_url: null
---

# Cookies

`DrevOps\BehatSteps\Steps\Web\CookieTrait` - [Source](../../../src/Steps/Web/CookieTrait.php), [Steps](../web-steps/cookie.md)

> Verify and inspect browser cookies.

<details>
  <summary><code>public function cookieAssertExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void</code></summary>

<br/>
Assert that a cookie exists
<br/><br/>

</details>

<details>
  <summary><code>public function cookieAssertNotExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void</code></summary>

<br/>
Assert that a cookie does not exist
<br/><br/>

</details>

<details>
  <summary><code>public function cookieFindByName(string $name, bool $is_partial = FALSE): ?array</code></summary>

<br/>
Find a cookie by exact or partial name
<br/><br/>

</details>

<details>
  <summary><code>public function cookieGetAll(): array</code></summary>

<br/>
Get all cookies
<br/><br/>

</details>
