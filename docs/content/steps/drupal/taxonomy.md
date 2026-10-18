---
title: "Drupal\\TaxonomyTrait steps"
sidebar_label: "TaxonomyTrait"
sidebar_position: 24
description: "Manage Drupal taxonomy terms with vocabulary organization."
custom_edit_url: null
---

# Drupal\TaxonomyTrait steps

[Source](../../../../src/Steps/Drupal/TaxonomyTrait.php), [Example](../../../../tests/behat/features/drupal_taxonomy.feature), [Helpers](../../helpers/drupal/taxonomy.md)

>  Manage Drupal taxonomy terms with vocabulary organization.
>  - Create terms in an existing vocabulary, with field values.
>  - Navigate to term pages.
>  - Verify vocabulary configurations.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "TermCapabilityInterface" | `TermCapabilityInterface` |
| The core "taxonomy" module is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the following :vocabulary terms with fields exist:</code></summary>

<br/>
Create taxonomy terms with vertical field format
<br/><br/>

```gherkin
Given the following tags terms with fields exist:
  | name        | [TEST] Behat    | [TEST] Testing  |
  | description | Testing tag     | QA tag          |
```

</details>

<details>
  <summary><code>@Given the following :vocabulary terms exist:</code></summary>

<br/>
Create taxonomy terms in a vocabulary from a table of field values
<br/><br/>

```gherkin
Given the following tags terms exist:
  | name         | description |
  | [TEST] Behat | Testing tag |
```

</details>

<details>
  <summary><code>@Given the following :vocabulary terms do not exist:</code></summary>

<br/>
Remove terms from a specified vocabulary
<br/><br/>

```gherkin
Given the following "fruits" terms do not exist:
  | Apple |
  | Pear  |
```

</details>

<details>
  <summary><code>@When I visit the :vocabulary term page with the name :name</code></summary>

<br/>
Visit specified vocabulary term page
<br/><br/>

```gherkin
When I visit the "fruits" term page with the name "Apple"
```

</details>

<details>
  <summary><code>@When I visit the :vocabulary term edit page with the name :name</code></summary>

<br/>
Visit specified vocabulary term edit page
<br/><br/>

```gherkin
When I visit the "fruits" term edit page with the name "Apple"
```

</details>

<details>
  <summary><code>@When I visit the :vocabulary term delete page with the name :name</code></summary>

<br/>
Visit specified vocabulary term delete page
<br/><br/>

```gherkin
When I visit the "tags" term delete page with the name "[TEST] Remove"
```

</details>

<details>
  <summary><code>@Then the vocabulary :vocabulary with the name :name should exist</code></summary>

<br/>
Assert that a vocabulary with a specific name exists
<br/><br/>

```gherkin
Then the vocabulary "topics" with the name "Topics" should exist
```

</details>

<details>
  <summary><code>@Then the vocabulary :vocabulary should not exist</code></summary>

<br/>
Assert that a vocabulary does not exist by machine name
<br/><br/>

```gherkin
Then the vocabulary "topics" should not exist
```

</details>

<details>
  <summary><code>@Then the :vocabulary term with the name :name should exist</code></summary>

<br/>
Assert that a taxonomy term exists by name
<br/><br/>

```gherkin
Then the "Fruits" term with the name "Apple" should exist
```

</details>

<details>
  <summary><code>@Then the :vocabulary term with the name :name should not exist</code></summary>

<br/>
Assert that a taxonomy term does not exist by name
<br/><br/>

```gherkin
Then the "Fruits" term with the name "Apple" should not exist
```

</details>
