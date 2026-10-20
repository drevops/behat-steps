---
title: "Redirects - Drupal steps"
sidebar_label: "Redirects"
sidebar_position: 21
description: "Manage Drupal redirect entities provided by the contrib redirect module."
custom_edit_url: null
---

# Redirects

`DrevOps\BehatSteps\Steps\Drupal\RedirectTrait` - [Source](../../../src/Steps/Drupal/RedirectTrait.php), [Example](../../../tests/behat/features/drupal_redirect.feature), [Helpers](../../../HELPERS.md#drupalredirecttrait)

>  Manage Drupal redirect entities provided by the contrib `redirect` module.
>  - Create 1 or more redirects from a table of source/destination/status.
>  - Delete redirects by source path.
>  - Assert that redirects do or do not exist for given source paths.
>  - Created redirects are automatically removed at the end of the scenario.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "RedirectCapabilityInterface" | `RedirectCapabilityInterface` |
| The "redirect" module from the "drupal/redirect" package is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the following redirects exist:</code></summary>

<br/>
Create 1 or more redirects
<br/><br/>

```gherkin
Given the following redirects exist:
  | from              | to                        | status_code |
  | /old/about        | /about                    | 301         |
  | /promo            | https://example.com/promo | 302         |
  | /legacy/contact   | /contact                  |             |
```

</details>

<details>
  <summary><code>@Given the following redirects do not exist:</code></summary>

<br/>
Delete redirects by source path
<br/><br/>

```gherkin
Given the following redirects do not exist:
  | /old/about      |
  | /legacy/contact |
```

</details>

<details>
  <summary><code>@Then the following redirects should exist:</code></summary>

<br/>
Assert that 1 or more redirects exist
<br/><br/>

```gherkin
Then the following redirects should exist:
  | from              | to                        | status_code |
  | /old/about        | /about                    | 301         |
  | /promo            | https://example.com/promo |             |
  | /legacy/contact   |                           |             |
```

</details>

<details>
  <summary><code>@Then the following redirects should not exist:</code></summary>

<br/>
Assert that no redirect exists for 1 or more source paths
<br/><br/>

```gherkin
Then the following redirects should not exist:
  | /old/about      |
  | /legacy/contact |
```

</details>
