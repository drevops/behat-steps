---
title: "Messages - Web steps"
sidebar_label: "Messages"
sidebar_position: 15
description: "Assert status, error, warning and success messages rendered on the page."
custom_edit_url: null
---

# Messages

`DrevOps\BehatSteps\Steps\Web\MessageTrait` - [Source](../../../src/Steps/Web/MessageTrait.php), [Example](../../../tests/behat/features/message.feature), [Helpers](../../../HELPERS.md#messagetrait)

>  Assert status, error, warning and success messages rendered on the page.
>  - Match a single message by substring, per message type.
>  - Match a table of messages in 1 step.
>  
>  Each message type resolves to a CSS selector from the `message.selectors`
>  option, keyed `default`, `error`, `success` and `warning`. A message
>  matches when the text of any element found by that selector contains the
>  expected string.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `message.selectors` | map | `[]` | - | Selectors of the message regions the message steps assert against, one per severity: `default`, `error`, `success` and `warning`. |

## Steps

<details>
  <summary><code>@Then I should see the message :message</code></summary>

<br/>
Assert that a status message is present
<br/><br/>

```gherkin
Then I should see the message "Changes saved"
```

</details>

<details>
  <summary><code>@Then I should not see the message :message</code></summary>

<br/>
Assert that a status message is absent
<br/><br/>

```gherkin
Then I should not see the message "Access denied"
```

</details>

<details>
  <summary><code>@Then I should see the error message :message</code></summary>

<br/>
Assert that an error message is present
<br/><br/>

```gherkin
Then I should see the error message "Username field is required"
```

</details>

<details>
  <summary><code>@Then I should not see the error message :message</code></summary>

<br/>
Assert that an error message is absent
<br/><br/>

```gherkin
Then I should not see the error message "Access denied"
```

</details>

<details>
  <summary><code>@Then I should see the success message :message</code></summary>

<br/>
Assert that a success message is present
<br/><br/>

```gherkin
Then I should see the success message "Article has been created"
```

</details>

<details>
  <summary><code>@Then I should not see the success message :message</code></summary>

<br/>
Assert that a success message is absent
<br/><br/>

```gherkin
Then I should not see the success message "Changes saved"
```

</details>

<details>
  <summary><code>@Then I should see the warning message :message</code></summary>

<br/>
Assert that a warning message is present
<br/><br/>

```gherkin
Then I should see the warning message "This action cannot be undone"
```

</details>

<details>
  <summary><code>@Then I should not see the warning message :message</code></summary>

<br/>
Assert that a warning message is absent
<br/><br/>

```gherkin
Then I should not see the warning message "deprecated"
```

</details>

<details>
  <summary><code>@Then I should see the following error messages:</code></summary>

<br/>
Assert that every error message in the table is present
<br/><br/>

```gherkin
Then I should see the following error messages:
  | Username field is required |
  | Password field is required |
```

</details>

<details>
  <summary><code>@Then I should not see the following error messages:</code></summary>

<br/>
Assert that no error message in the table is present
<br/><br/>

```gherkin
Then I should not see the following error messages:
  | Access denied |
```

</details>

<details>
  <summary><code>@Then I should see the following success messages:</code></summary>

<br/>
Assert that every success message in the table is present
<br/><br/>

```gherkin
Then I should see the following success messages:
  | Article has been created |
```

</details>

<details>
  <summary><code>@Then I should not see the following success messages:</code></summary>

<br/>
Assert that no success message in the table is present
<br/><br/>

```gherkin
Then I should not see the following success messages:
  | Changes saved |
```

</details>

<details>
  <summary><code>@Then I should see the following warning messages:</code></summary>

<br/>
Assert that every warning message in the table is present
<br/><br/>

```gherkin
Then I should see the following warning messages:
  | This action cannot be undone |
```

</details>

<details>
  <summary><code>@Then I should not see the following warning messages:</code></summary>

<br/>
Assert that no warning message in the table is present
<br/><br/>

```gherkin
Then I should not see the following warning messages:
  | deprecated |
```

</details>
