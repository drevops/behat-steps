---
title: "REST APIs - Web steps"
sidebar_label: "REST APIs"
sidebar_position: 24
description: "Test REST APIs with lightweight steps."
custom_edit_url: null
---

# REST APIs

`DrevOps\BehatSteps\Steps\Web\RestTrait` - [Source](../../../src/Steps/Web/RestTrait.php), [Example](../../../tests/behat/features/rest.feature), [Helpers](../../../HELPERS.md#resttrait)

>  Test REST APIs with lightweight steps.
>  - Set HTTP headers for subsequent requests, literally or from a JSON path
>  in the last response.
>  - Send requests with any HTTP method (GET, POST, PUT, PATCH, DELETE), with
>  a body written inline or read from a fixture file.
>  - Assert response status codes, headers and body content.
>  
>  Requests go through the `rest` Mink session, apart from the browser's: they
>  carry no browser cookies, and the browser keeps its page. The JSON and XML
>  steps read the same session.
>  <br/><br/>
>  The JSON path header steps require the optional `softcreatr/jsonpath`
>  package: `composer require --dev softcreatr/jsonpath`.
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:RestTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `rest.enabled` | boolean | `TRUE` | `@behat-steps-skip:RestTrait` | If `TRUE`, clears the REST headers before each scenario. If `FALSE`, they aren't cleared, which changes nothing, because Behat builds a new context for each scenario and its headers start empty. |

## Steps

<details>
  <summary><code>@Given the REST header :name has the value :value</code></summary>

<br/>
Set a REST header for subsequent requests
<br/><br/>

```gherkin
Given the REST header "Accept" has the value "application/json"
Given the REST header "Authorization" has the value "Bearer abc123"
```

</details>

<details>
  <summary><code>@Given the REST header :name has the value from the JSON path :path</code></summary>

<br/>
Set a REST header to the value at a JSON path in the last REST response
<br/><br/>

```gherkin
Given the REST header "X-Session-Id" has the value from the JSON path "$.session_id"
```

</details>

<details>
  <summary><code>@Given the REST header :name has the value from the JSON path :path with the prefix :prefix</code></summary>

<br/>
Set a REST header to a prefix and the value at a JSON path
<br/><br/>

```gherkin
Given the REST header "Authorization" has the value from the JSON path "$.access_token" with the prefix "Bearer "
```

</details>

<details>
  <summary><code>@When I send a REST :method request to :url</code></summary>

<br/>
Send a REST request to a URL
<br/><br/>

```gherkin
When I send a REST "GET" request to "/api/resource"
When I send a REST "DELETE" request to "/api/resource/1"
```

</details>

<details>
  <summary><code>@When I send a REST :method request to :url with the body:</code></summary>

<br/>
Send a REST request to a URL with a body
<br/><br/>

```gherkin
When I send a REST "POST" request to "/api/resource" with the body:
  """
  {"name": "example"}
  """
```

</details>

<details>
  <summary><code>@When I send a REST :method request to :url with the body from the file :filename</code></summary>

<br/>
Send a REST request to a URL with a body read from a fixture file
<br/><br/>

```gherkin
When I send a REST "POST" request to "/api/import" with the body from the file "items.xml"
```

</details>

<details>
  <summary><code>@When I print the last REST response</code></summary>

<br/>
Print the last REST response
<br/><br/>

```gherkin
When I print the last REST response
```

</details>

<details>
  <summary><code>@Then the REST response status code should be :code</code></summary>

<br/>
Assert the REST response status code
<br/><br/>

```gherkin
Then the REST response status code should be 200
Then the REST response status code should be 404
```

</details>

<details>
  <summary><code>@Then the REST response should contain :value</code></summary>

<br/>
Assert the REST response contains text
<br/><br/>

```gherkin
Then the REST response should contain "success"
```

</details>

<details>
  <summary><code>@Then the REST response should contain the header :name</code></summary>

<br/>
Assert the REST response contains a header
<br/><br/>

```gherkin
Then the REST response should contain the header "Content-Type"
```

</details>

<details>
  <summary><code>@Then the REST response should not contain the header :name</code></summary>

<br/>
Assert the REST response does not contain a header
<br/><br/>

```gherkin
Then the REST response should not contain the header "Set-Cookie"
```

</details>

<details>
  <summary><code>@Then the REST response header :name should contain :value</code></summary>

<br/>
Assert a header of the REST response contains a value
<br/><br/>

```gherkin
Then the REST response header "Content-Type" should contain "application/json"
```

</details>

<details>
  <summary><code>@Then the REST response header :name should not contain :value</code></summary>

<br/>
Assert a header of the REST response does not contain a value
<br/><br/>

```gherkin
Then the REST response header "Cache-Control" should not contain "public"
```

</details>
