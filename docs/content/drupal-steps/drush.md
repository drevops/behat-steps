---
title: "Drush - Drupal steps"
sidebar_label: "Drush"
sidebar_position: 10
description: "Run Drush commands and assert their output."
custom_edit_url: null
---

# Drush

`DrevOps\BehatSteps\Steps\Drupal\DrushTrait` - [Source](../../../src/Steps/Drupal/DrushTrait.php), [Example](../../../tests/behat/features/drupal_drush.feature), [Helpers](../drupal-helpers/drush.md)

>  Run Drush commands and assert their output.
>  - Run a command with or without arguments, through the Drush backend.
>  - Run a command that is expected to fail and keep its output.
>  - Assert the last command's output by substring or regular expression.
>  
>  Steps resolve the backend that can run Drush commands, not the first one in
>  the scenario's order. They work in a scenario driven by any other backend
>  as long as the suite lists a Drush-capable one.

## Steps

<details>
  <summary><code>@When I run the drush command :command</code></summary>

<br/>
Run a Drush command
<br/><br/>

```gherkin
When I run the drush command "status"
When I run the drush command "config:get system.site name"
```

</details>

<details>
  <summary><code>@When I run the drush command :command with the arguments :arguments</code></summary>

<br/>
Run a Drush command with arguments
<br/><br/>

```gherkin
When I run the drush command "pm:list" with the arguments "--status=enabled"
When I run the drush command "config:get" with the arguments "system.site uuid"
```

</details>

<details>
  <summary><code>@When I run the failing drush command :command</code></summary>

<br/>
Run a Drush command that is expected to fail
<br/><br/>

```gherkin
When I run the failing drush command "pm:uninstall no_such_module"
```

</details>

<details>
  <summary><code>@When I run the failing drush command :command with the arguments :arguments</code></summary>

<br/>
Run a Drush command with arguments that is expected to fail
<br/><br/>

```gherkin
When I run the failing drush command "pm:uninstall" with the arguments "no_such_module"
```

</details>

<details>
  <summary><code>@When I print the last drush output</code></summary>

<br/>
Print the output of the most recent Drush command
<br/><br/>

```gherkin
When I print the last drush output
```

</details>

<details>
  <summary><code>@Then the drush output should contain :value</code></summary>

<br/>
Assert that the last Drush output contains the text
<br/><br/>

```gherkin
Then the drush output should contain "Drupal version"
```

</details>

<details>
  <summary><code>@Then the drush output should not contain :value</code></summary>

<br/>
Assert that the last Drush output does not contain the text
<br/><br/>

```gherkin
Then the drush output should not contain "error"
```

</details>

<details>
  <summary><code>@Then the drush output should match :pattern</code></summary>

<br/>
Assert that the last Drush output matches the pattern
<br/><br/>

```gherkin
Then the drush output should match "/Drupal [0-9]+/"
```

</details>
