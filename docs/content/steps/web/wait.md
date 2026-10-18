---
title: "WaitTrait steps"
sidebar_label: "WaitTrait"
sidebar_position: 27
description: "Wait for a period of time or for AJAX to finish."
custom_edit_url: null
---

# WaitTrait steps

[Source](../../../../src/Steps/Web/WaitTrait.php), [Example](../../../../tests/behat/features/wait.feature), [Helpers](../../helpers/web/wait.md)

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
| `wait.enabled` | boolean | `TRUE` | `@behat-steps-skip:WaitTrait` | Wait for AJAX around every navigating or submitting step of a `@javascript` scenario. |
| `wait.ajax_timeout` | integer | `5` | - | Maximum time, in seconds, to wait for AJAX calls to complete. |

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
