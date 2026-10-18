---
title: "Relative dates - Web helpers"
sidebar_label: "Relative dates"
sidebar_position: 21
description: "Convert relative date expressions into timestamps or formatted dates."
custom_edit_url: null
---

# Relative dates

`DrevOps\BehatSteps\Steps\Web\DateTrait` - [Source](../../../src/Steps/Web/DateTrait.php), [Steps](../web-steps/date.md)

> Convert relative date expressions into timestamps or formatted dates.

<details>
  <summary><code>public static function dateGetNow(): int</code></summary>

<br/>
Get the current timestamp
<br/><br/>

</details>

<details>
  <summary><code>public static function dateRelativeProcessValue(string $value, ?int $now = NULL): string</code></summary>

<br/>
Process date values to convert relative timestamps to actual values
<br/><br/>

```
Given the following "article" content:
  | title        | created           |
  | test article | [relative:-1 day] |
```

</details>

<details>
  <summary><code>public static function dateRelativeStringHasToken(string $string): bool</code></summary>

<br/>
Check whether a string holds a relative date token
<br/><br/>

</details>
