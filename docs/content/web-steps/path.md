---
title: "Paths - Web steps"
sidebar_label: "Paths"
sidebar_position: 19
description: "Navigate and verify paths with URL validation."
custom_edit_url: null
---

# Paths

`DrevOps\BehatSteps\Steps\Web\PathTrait` - [Source](../../../src/Steps/Web/PathTrait.php), [Example](../../../tests/behat/features/path.feature), [Helpers](../web-helpers/path.md)

>  Navigate and verify paths with URL validation.
>  - Assert current page location with front page special handling.
>  - Configure basic authentication for protected path access.
>  - Validate URL query parameters with expected values.

## Steps

<details>
  <summary><code>@Given I am using basic authentication with the username :username and the password :password</code></summary>

<br/>
Set basic authentication for the current session
<br/><br/>

```gherkin
Given I am using basic authentication with the username "myusername" and the password "mypassword"
```

</details>

<details>
  <summary><code>@When I visit :path</code></summary>

<br/>
Navigate to a path
<br/><br/>

```gherkin
When I visit "/about-us"
When I visit "https://example.com/about-us"
```

</details>

<details>
  <summary><code>@When I go back</code></summary>

<br/>
Navigate back in browser history
<br/><br/>

```gherkin
When I go back
```

</details>

<details>
  <summary><code>@Then the path should be :path</code></summary>

<br/>
Assert that the current page is a specified path
<br/><br/>

```gherkin
Then the path should be "/about-us"
Then the path should be "/"
Then the path should be "<front>"
```

</details>

<details>
  <summary><code>@Then the path should not be :path</code></summary>

<br/>
Assert that the current page is not a specified path
<br/><br/>

```gherkin
Then the path should not be "/about-us"
Then the path should not be "/"
Then the path should not be "<front>"
```

</details>

<details>
  <summary><code>@Then the URL should have the query parameter :name</code></summary>

<br/>
Assert that current URL has a query parameter
<br/><br/>

```gherkin
Then the URL should have the query parameter "filter"
```

</details>

<details>
  <summary><code>@Then the URL should have the query parameter :name with the value :value</code></summary>

<br/>
Assert that current URL has a query parameter with a specific value
<br/><br/>

```gherkin
Then the URL should have the query parameter "filter" with the value "recent"
```

</details>

<details>
  <summary><code>@Then the URL should not have the query parameter :name</code></summary>

<br/>
Assert that current URL has no query parameter
<br/><br/>

```gherkin
Then the URL should not have the query parameter "filter"
```

</details>

<details>
  <summary><code>@Then the URL should not have the query parameter :name with the value :value</code></summary>

<br/>
Assert that current URL does not have a query parameter with a value
<br/><br/>

```gherkin
Then the URL should not have the query parameter "filter" with the value "recent"
```

</details>
