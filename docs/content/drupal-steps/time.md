---
title: "System time - Drupal steps"
sidebar_label: "System time"
sidebar_position: 24
description: "Control system time in tests using Drupal state overrides."
custom_edit_url: null
---

# System time

`DrevOps\BehatSteps\Steps\Drupal\TimeTrait` - [Source](../../../src/Steps/Drupal/TimeTrait.php), [Example](../../../tests/behat/features/drupal_time.feature)

>  Control system time in tests using Drupal state overrides.
>  <br/><br/>
>  This trait requires the consuming application to use a mockable time
>  service that checks Drupal state for time overrides.
>  <br/><br/>
>  Example implementation:
>  - Time service: https://github.com/drevops/behat-steps/blob/main/tests/behat/fixtures_drupal/d11/web/modules/custom/mysite_core/src/Time/Time.php
>  - Time interface: https://github.com/drevops/behat-steps/blob/main/tests/behat/fixtures_drupal/d11/web/modules/custom/mysite_core/src/Time/TimeInterface.php
>  - Service registration: https://github.com/drevops/behat-steps/blob/main/tests/behat/fixtures_drupal/d11/web/modules/custom/mysite_core/mysite_core.services.yml
>  
>  Skip processing with tag: `@behat-steps-skip:TimeTrait`.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "StateCapabilityInterface" | `StateCapabilityInterface` |

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `time.enabled` | boolean | `TRUE` | `@behat-steps-skip:TimeTrait` | Restore the site clock after a scenario that moved it. |

## Steps

<details>
  <summary><code>@When the system time is set to :value</code></summary>

<br/>
Sets the system time for testing
<br/><br/>

```gherkin
When the system time is set to "1737849900"
```

</details>

<details>
  <summary><code>@When the system time is reset</code></summary>

<br/>
Resets the system time to real time
<br/><br/>

```gherkin
When the system time is reset
```

</details>
