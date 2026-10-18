---
title: "ResponseTrait steps"
sidebar_label: "ResponseTrait"
sidebar_position: 23
description: "Verify HTTP response headers."
custom_edit_url: null
---

# ResponseTrait steps

[Source](../../../../src/Steps/Web/ResponseTrait.php), [Example](../../../../tests/behat/features/response.feature)

>  Verify HTTP response headers.
>  - Assert HTTP header presence and values.

## Steps

<details>
  <summary><code>@Then the response should contain the header :name</code></summary>

<br/>
Assert that a response contains a header with specified name
<br/><br/>

```gherkin
Then the response should contain the header "Connection"
```

</details>

<details>
  <summary><code>@Then the response should not contain the header :name</code></summary>

<br/>
Assert that a response does not contain a header with a specified name
<br/><br/>

```gherkin
Then the response should not contain the header "Connection"
```

</details>

<details>
  <summary><code>@Then the response header :name should contain :value</code></summary>

<br/>
Assert that a response contains a header with a specified name and value
<br/><br/>

```gherkin
Then the response header "Connection" should contain "Keep-Alive"
```

</details>

<details>
  <summary><code>@Then the response header :name should not contain :value</code></summary>

<br/>
Assert a response does not contain a header with a specified name and value
<br/><br/>

```gherkin
Then the response header "Connection" should not contain "Keep-Alive"
```

</details>
