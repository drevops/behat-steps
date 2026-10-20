---
title: "Email - Drupal steps"
sidebar_label: "Email"
sidebar_position: 12
description: "Test Drupal email functionality with content verification."
custom_edit_url: null
---

# Email

`DrevOps\BehatSteps\Steps\Drupal\EmailTrait` - [Source](../../../src/Steps/Drupal/EmailTrait.php), [Example](../../../tests/behat/features/drupal_email.feature), [Helpers](../../../HELPERS.md#drupalemailtrait)

>  Test Drupal email functionality with content verification.
>  - Capture and examine outgoing emails with header and body validation.
>  - Follow links and test attachments within email content.
>  - Configure mail handler systems for proper test isolation.
>  
>  Skip processing with tag: `@behat-steps-skip:EmailTrait`.
>  <br/><br/>
>  Special tags:
>  - `@email` - enable email tracking using a default handler
>  - `@email:{type}` - enable email tracking using a `{type}` handler
>  - `@debug` (enable detailed logs)

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "MailCapabilityInterface" | `MailCapabilityInterface` |

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `email.enabled` | boolean | `TRUE` | `@behat-steps-skip:EmailTrait` | If `TRUE`, points the mail system of an `@email` scenario at a test collector, empties the collector and restores the mail system afterwards. If `FALSE`, the tag does nothing, and a collector the email steps switch on stays on after the scenario. |

## Steps

<details>
  <summary><code>@When the test email system queue is cleared</code></summary>

<br/>
Clear test email system queue
<br/><br/>

```gherkin
When the test email system queue is cleared
```

</details>

<details>
  <summary><code>@When I follow the link with the index :index in the email with the subject :subject</code></summary>

<br/>
Follow the link at the 1-based index in an email with the given subject
<br/><br/>

```gherkin
When I follow the link with the index "1" in the email with the subject "Account Verification"
```

</details>

<details>
  <summary><code>@When I follow the link with a URL containing :partial_url in the email</code></summary>

<br/>
Follow the first link whose URL contains a fragment in an email
<br/><br/>

```gherkin
When I follow the link with a URL containing "user/reset" in the email
```

</details>

<details>
  <summary><code>@When I follow the link with the index :index in the email with a subject containing :partial_subject</code></summary>

<br/>
Follow the link at the 1-based index in an email whose subject contains the given substring
<br/><br/>

```gherkin
When I follow the link with the index "1" in the email with a subject containing "Verification"
```

</details>

<details>
  <summary><code>@When the test email system is enabled</code></summary>

<br/>
Enable the test email system
<br/><br/>

```gherkin
When the test email system is enabled
```

</details>

<details>
  <summary><code>@When the test email system is disabled</code></summary>

<br/>
Disable test email system
<br/><br/>

```gherkin
When the test email system is disabled
```

</details>

<details>
  <summary><code>@Then an email should have been sent to the address :address</code></summary>

<br/>
Assert that an email was sent to an address
<br/><br/>

```gherkin
Then an email should have been sent to the address "user@example.com"
```

</details>

<details>
  <summary><code>@Then the number of emails sent should be :count</code></summary>

<br/>
Assert the number of emails sent
<br/><br/>

```gherkin
Then the number of emails sent should be 2
```

</details>

<details>
  <summary><code>@Then the number of emails sent to the address :address should be :count</code></summary>

<br/>
Assert the number of emails sent to an address
<br/><br/>

```gherkin
Then the number of emails sent to the address "user@example.com" should be 2
```

</details>

<details>
  <summary><code>@Then the number of emails sent with the subject :subject should be :count</code></summary>

<br/>
Assert the number of emails sent with a subject
<br/><br/>

```gherkin
Then the number of emails sent with the subject "Welcome" should be 1
```

</details>

<details>
  <summary><code>@Then no emails should have been sent</code></summary>

<br/>
Assert that no email was sent
<br/><br/>

```gherkin
Then no emails should have been sent
```

</details>

<details>
  <summary><code>@Then no emails should have been sent to the address :address</code></summary>

<br/>
Assert that no email was sent to an address
<br/><br/>

```gherkin
Then no emails should have been sent to the address "user@example.com"
```

</details>

<details>
  <summary><code>@Then the email header :header should contain:</code></summary>

<br/>
Assert that the email message header should contain specified content
<br/><br/>

```gherkin
Then the email header "Subject" should contain:
"""
Account details
"""
```

</details>

<details>
  <summary><code>@Then the email header :header should be:</code></summary>

<br/>
Assert that the email message header should be the exact specified content
<br/><br/>

```gherkin
Then the email header "Subject" should be:
"""
Your Account Details
"""
```

</details>

<details>
  <summary><code>@Then an email should have been sent to the address :address with the content:</code></summary>

<br/>
Assert that an email was sent to an address with the exact content in the body
<br/><br/>

```gherkin
Then an email should have been sent to the address "user@example.com" with the content:
"""
Welcome to our site!
Click the link below to verify your account.
"""
```

</details>

<details>
  <summary><code>@Then an email should have been sent to the address :address with the content containing:</code></summary>

<br/>
Assert that an email was sent to an address with the body containing specific content
<br/><br/>

```gherkin
Then an email should have been sent to the address "user@example.com" with the content containing:
"""
verification link
"""
```

</details>

<details>
  <summary><code>@Then an email should have been sent to the address :address with the content not containing:</code></summary>

<br/>
Assert that an email was sent to an address with the body not containing specific content
<br/><br/>

```gherkin
Then an email should have been sent to the address "user@example.com" with the content not containing:
"""
password
"""
```

</details>

<details>
  <summary><code>@Then no emails should have been sent to the address :address with the content:</code></summary>

<br/>
Assert that no email was sent to an address with the exact content in the body
<br/><br/>

```gherkin
Then no emails should have been sent to the address "wrong@example.com" with the content:
"""
Welcome to our site!
"""
```

</details>

<details>
  <summary><code>@Then no emails should have been sent to the address :address with the content containing:</code></summary>

<br/>
Assert that no email was sent to an address with the body containing specific content
<br/><br/>

```gherkin
Then no emails should have been sent to the address "wrong@example.com" with the content containing:
"""
verification link
"""
```

</details>

<details>
  <summary><code>@Then the email field :field should contain:</code></summary>

<br/>
Assert that the email field should contain a value
<br/><br/>

```gherkin
Then the email field "body" should contain:
"""
Please verify your account
"""
```

</details>

<details>
  <summary><code>@Then the email field :field should be:</code></summary>

<br/>
Assert that the email field should exactly match a value
<br/><br/>

```gherkin
Then the email field "subject" should be:
"""
Account Verification
"""
```

</details>

<details>
  <summary><code>@Then the email field :field should not contain:</code></summary>

<br/>
Assert that the email field should not contain a value
<br/><br/>

```gherkin
Then the email field "body" should not contain:
"""
password
"""
```

</details>

<details>
  <summary><code>@Then the email field :field should not be:</code></summary>

<br/>
Assert that the email field should not exactly match a value
<br/><br/>

```gherkin
Then the email field "subject" should not be:
"""
Password Reset
"""
```

</details>

<details>
  <summary><code>@Then the file :filename should be attached to the email with the subject :subject</code></summary>

<br/>
Assert that a file is attached to an email message with specified subject
<br/><br/>

```gherkin
Then the file "document.pdf" should be attached to the email with the subject "Your document"
```

</details>

<details>
  <summary><code>@Then the file :filename should be attached to the email with a subject containing :partial_subject</code></summary>

<br/>
Assert that a file is attached to an email message with a subject containing the specified substring
<br/><br/>

```gherkin
Then the file "report.xlsx" should be attached to the email with a subject containing "Monthly Report"
```

</details>
