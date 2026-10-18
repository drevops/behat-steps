---
title: "JsonTrait steps"
sidebar_label: "JsonTrait"
sidebar_position: 13
description: "Assert JSON responses with path and schema checks."
custom_edit_url: null
---

# JsonTrait steps

[Source](../../../../src/Steps/Web/JsonTrait.php), [Example](../../../../tests/behat/features/json.feature), [Helpers](../../helpers/web/json.md)

>  Assert JSON responses with path and schema checks.
>  - Assert response is valid JSON format.
>  - Assert values at JSONPath expressions.
>  - Assert JSONPath existence, type, and element counts.
>  - Validate the response against a JSON Schema.
>  
>  JSONPath expressions use the standard `$.path.to[0].value` syntax.
>  <br/><br/>
>  The JSON Schema steps require the optional `justinrainbow/json-schema`
>  package: `composer require --dev justinrainbow/json-schema`.

## Steps

<details>
  <summary><code>@Given the response JSON is loaded from the file :filename</code></summary>

<br/>
Set the response JSON content from a fixture file
<br/><br/>

```gherkin
Given the response JSON is loaded from the file "json_valid.json"
```

</details>

<details>
  <summary><code>@Given the response JSON is the following:</code></summary>

<br/>
Set the response JSON content directly from a PyString
<br/><br/>

```gherkin
Given the response JSON is the following:
  """
  {"name": "John Doe", "roles": ["admin", "editor"]}
  """
```

</details>

<details>
  <summary><code>@When I print the last JSON response</code></summary>

<br/>
Print the last JSON response
<br/><br/>

```gherkin
When I print the last JSON response
```

</details>

<details>
  <summary><code>@Then the response should be in JSON format</code></summary>

<br/>
Assert that a response is valid JSON
<br/><br/>

```gherkin
Then the response should be in JSON format

# Content set by a fixture step is validated instead of the page content.
Given the response JSON is loaded from the file "json_valid.json"
Then the response should be in JSON format
```

</details>

<details>
  <summary><code>@Then the response should not be in JSON format</code></summary>

<br/>
Assert that a response is not valid JSON
<br/><br/>

```gherkin
Then the response should not be in JSON format
```

</details>

<details>
  <summary><code>@Then the JSON path :path should exist</code></summary>

<br/>
Assert that a JSONPath expression matches at least 1 value
<br/><br/>

```gherkin
Then the JSON path "$.name" should exist
Then the JSON path "$.user.roles[0]" should exist
```

</details>

<details>
  <summary><code>@Then the JSON path :path should not exist</code></summary>

<br/>
Assert that a JSONPath expression matches no values
<br/><br/>

```gherkin
Then the JSON path "$.nonexistent" should not exist
```

</details>

<details>
  <summary><code>@Then the JSON path :path should have the value :value</code></summary>

<br/>
Assert that the value at a JSONPath equals the expected value
<br/><br/>

```gherkin
Then the JSON path "$.name" should have the value "John Doe"
Then the JSON path "$.age" should have the value "42"
```

</details>

<details>
  <summary><code>@Then the JSON path :path should not have the value :value</code></summary>

<br/>
Assert that the value at a JSONPath does not equal the expected value
<br/><br/>

```gherkin
Then the JSON path "$.name" should not have the value "Jane Doe"
```

</details>

<details>
  <summary><code>@Then the JSON path :path should contain :value</code></summary>

<br/>
Assert that the value at a JSONPath contains the expected text
<br/><br/>

```gherkin
Then the JSON path "$.name" should contain "John"
```

</details>

<details>
  <summary><code>@Then the JSON path :path should not contain :value</code></summary>

<br/>
Assert that the value at a JSONPath does not contain the expected text
<br/><br/>

```gherkin
Then the JSON path "$.name" should not contain "Jane"
```

</details>

<details>
  <summary><code>@Then the JSON path :path should match :pattern</code></summary>

<br/>
Assert that the value at a JSONPath matches a regular expression
<br/><br/>

```gherkin
Then the JSON path "$.email" should match "/^[^@]+@example\.com$/"
```

</details>

<details>
  <summary><code>@Then the JSON path :path should not match :pattern</code></summary>

<br/>
Assert that the value at a JSONPath does not match a regular expression
<br/><br/>

```gherkin
Then the JSON path "$.email" should not match "/^admin@/"
```

</details>

<details>
  <summary><code>@Then the JSON path :path should be null</code></summary>

<br/>
Assert that the value at a JSONPath is null
<br/><br/>

```gherkin
Then the JSON path "$.deleted_at" should be null
```

</details>

<details>
  <summary><code>@Then the JSON path :path should be true</code></summary>

<br/>
Assert that the value at a JSONPath is boolean true
<br/><br/>

```gherkin
Then the JSON path "$.active" should be true
```

</details>

<details>
  <summary><code>@Then the JSON path :path should be false</code></summary>

<br/>
Assert that the value at a JSONPath is boolean false
<br/><br/>

```gherkin
Then the JSON path "$.disabled" should be false
```

</details>

<details>
  <summary><code>@Then the JSON path :path should have :count element(s)</code></summary>

<br/>
Assert that the array or object at a JSONPath has a number of elements
<br/><br/>

```gherkin
Then the JSON path "$.items" should have "3" elements
Then the JSON path "$.user" should have "2" elements
```

</details>

<details>
  <summary><code>@Then the response should match the following JSON schema:</code></summary>

<br/>
Assert that the response validates against an inline JSON Schema
<br/><br/>

```gherkin
Then the response should match the following JSON schema:
  """
  {"type": "object", "required": ["name"], "properties": {"name": {"type": "string"}}}
  """
```

</details>

<details>
  <summary><code>@Then the response should match the JSON schema in the file :filename</code></summary>

<br/>
Assert that the response validates against a JSON Schema from a file
<br/><br/>

```gherkin
Then the response should match the JSON schema in the file "json_schema.json"
```

</details>
