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
>  - Set HTTP headers for subsequent requests.
>  - Send requests with any HTTP method (GET, POST, PUT, PATCH, DELETE).
>  - Assert response status codes and body content.
>  
>  Skip processing with tag: `@behat-steps-skip:RestTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `rest.enabled` | boolean | `TRUE` | `@behat-steps-skip:RestTrait` | If `TRUE`, clears the context's request headers before each scenario. Behat builds a new context for each scenario, so the headers start empty either way. |

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
