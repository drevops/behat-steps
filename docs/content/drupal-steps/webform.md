---
title: "Webforms - Drupal steps"
sidebar_label: "Webforms"
sidebar_position: 29
description: "Manage Drupal webforms."
custom_edit_url: null
---

# Webforms

`DrevOps\BehatSteps\Steps\Drupal\WebformTrait` - [Source](../../../src/Steps/Drupal/WebformTrait.php), [Example](../../../tests/behat/features/drupal_webform.feature), [Helpers](../../../HELPERS.md#drupalwebformtrait)

>  Manage Drupal webforms.
>  - Delete webforms matching a given title for test isolation.
>  - Clone webform templates into new webforms for scenario setup.
>  - Cloned webforms are automatically removed at the end of the scenario.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "ConfigEntityCapabilityInterface" | `ConfigEntityCapabilityInterface` |
| The "webform" module from the "drupal/webform" package is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the webform :title does not exist</code></summary>

<br/>
Remove all webforms with a title containing the given string
<br/><br/>

```gherkin
Given the webform "Test form" does not exist
```

</details>

<details>
  <summary><code>@Given the webform :title has been created from the template :template</code></summary>

<br/>
Clone a webform template into a new webform with the given title
<br/><br/>

```gherkin
Given the webform "My contact form" has been created from the template "Contact"
```

</details>
