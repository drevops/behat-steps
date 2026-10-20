---
title: "Waits - Web steps"
sidebar_label: "Waits"
sidebar_position: 27
description: "Wait for a period of time or for AJAX to finish."
custom_edit_url: null
---

# Waits

`DrevOps\BehatSteps\Steps\Web\WaitTrait` - [Source](../../../src/Steps/Web/WaitTrait.php), [Example](../../../tests/behat/features/wait.feature), [Helpers](../../../HELPERS.md#waittrait)

>  Wait for a period of time or for AJAX to finish.
>  - Wait a fixed number of seconds.
>  - Wait for jQuery and Drupal AJAX activity to settle, on demand or around
>  every step that navigates or submits.
>  <br/><br/>
>  A wait on `jQuery.active` alone misses `Drupal.ajax` updates, so an
>  assertion following a click can read the page before the update applies.
>  The wait here watches both, and a page without `Drupal` takes the jQuery
>  check alone.
>  <br/><br/>
>  Skip the automatic waits with tag: `@behat-steps-skip:WaitTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `wait.enabled` | boolean | `TRUE` | `@behat-steps-skip:WaitTrait` | If `TRUE`, waits for AJAX before and after every step of a `@javascript` scenario whose text, outside its quoted values, holds `follow`, `press`, `click`, `submit` or `attach`. If `FALSE`, only the wait steps wait. |
| `wait.ajax_timeout` | integer | `5` | - | The longest time, in seconds, to wait for jQuery's AJAX calls and animations and Drupal's AJAX requests to finish, in the automatic waits and in `I wait for AJAX to finish`. |

## Steps

<details>
  <summary><code>@When I wait for AJAX to finish</code></summary>

<br/>
Wait for the AJAX calls to finish, using the configured timeout
<br/><br/>

```gherkin
When I wait for AJAX to finish
```

</details>

<details>
  <summary><code>@When I wait for :seconds second(s)</code></summary>

<br/>
Wait for a specified number of seconds
<br/><br/>

```gherkin
When I wait for 5 seconds
When I wait for 1 second
```

</details>

<details>
  <summary><code>@When I wait for :seconds second(s) for AJAX to finish</code></summary>

<br/>
Wait for the AJAX calls to finish
<br/><br/>

```gherkin
When I wait for 5 seconds for AJAX to finish
When I wait for 1 second for AJAX to finish
```

</details>
