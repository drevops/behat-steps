---
title: "Cookies - Web steps"
sidebar_label: "Cookies"
sidebar_position: 3
description: "Verify and inspect browser cookies."
custom_edit_url: null
---

# Cookies

`DrevOps\BehatSteps\Steps\Web\CookieTrait` - [Source](../../../src/Steps/Web/CookieTrait.php), [Example](../../../tests/behat/features/cookie.feature), [Helpers](../../../HELPERS.md#cookietrait)

>  Verify and inspect browser cookies.
>  - Assert cookie existence and values with exact or partial matching.
>  - Read cookies through whichever browser driver provides the cookie
>  capability.

## Steps

<details>
  <summary><code>@Then the cookie :name should exist</code></summary>

<br/>
Assert that a cookie exists
<br/><br/>

```gherkin
Then the cookie "session_id" should exist
```

</details>

<details>
  <summary><code>@Then the cookie :name with the value :value should exist</code></summary>

<br/>
Assert that a cookie exists with a specific value
<br/><br/>

```gherkin
Then the cookie "language" with the value "en" should exist
```

</details>

<details>
  <summary><code>@Then the cookie :name with a value containing :partial_value should exist</code></summary>

<br/>
Assert that a cookie exists with a value containing a partial value
<br/><br/>

```gherkin
Then the cookie "preferences" with a value containing "darkmode" should exist
```

</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name should exist</code></summary>

<br/>
Assert that a cookie with a partial name exists
<br/><br/>

```gherkin
Then a cookie with a name containing "session" should exist
```

</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name and the value :value should exist</code></summary>

<br/>
Assert that a cookie with a partial name and value exists
<br/><br/>

```gherkin
Then a cookie with a name containing "user" and the value "admin" should exist
```

</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name and a value containing :partial_value should exist</code></summary>

<br/>
Assert that a cookie with a partial name and partial value exists
<br/><br/>

```gherkin
Then a cookie with a name containing "user" and a value containing "admin" should exist
```

</details>

<details>
  <summary><code>@Then the cookie :name should not exist</code></summary>

<br/>
Assert that a cookie does not exist
<br/><br/>

```gherkin
Then the cookie "old_session" should not exist
```

</details>

<details>
  <summary><code>@Then the cookie :name with the value :value should not exist</code></summary>

<br/>
Assert that a cookie with a specific value does not exist
<br/><br/>

```gherkin
Then the cookie "language" with the value "fr" should not exist
```

</details>

<details>
  <summary><code>@Then the cookie :name with a value containing :partial_value should not exist</code></summary>

<br/>
Assert that a cookie with a value containing a partial value does not exist
<br/><br/>

```gherkin
Then the cookie "preferences" with a value containing "lightmode" should not exist
```

</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name should not exist</code></summary>

<br/>
Assert that a cookie with a partial name does not exist
<br/><br/>

```gherkin
Then a cookie with a name containing "old" should not exist
```

</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name and the value :value should not exist</code></summary>

<br/>
Assert that a cookie with a partial name and value does not exist
<br/><br/>

```gherkin
Then a cookie with a name containing "user" and the value "guest" should not exist
```

</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name and a value containing :partial_value should not exist</code></summary>

<br/>
Assert that a cookie with a partial name and partial value does not exist
<br/><br/>

```gherkin
Then a cookie with a name containing "user" and a value containing "guest" should not exist
```

</details>
