---
title: "Browser time - Web steps"
sidebar_label: "Browser time"
sidebar_position: 3
description: "Set the time and the timezone a page's JavaScript reads in the browser."
custom_edit_url: null
---

# Browser time

`DrevOps\BehatSteps\Steps\Web\BrowserTimeTrait` - [Source](../../../src/Steps/Web/BrowserTimeTrait.php), [Example](../../../tests/behat/features/browser_time.feature), [Helpers](../../../HELPERS.md#browsertimetrait)

>  Set the time and the timezone a page's JavaScript reads in the browser.
>  - Set the time 'Date' reads in each page loaded after the step.
>  - Set the timezone the browser formats and reports dates in.
>  - Reset both to the real time and the browser's own timezone.
>  
>  The time applies from the next page load, so set it before visiting the
>  page under test. It ticks on from the given value and stays continuous
>  across page loads. The timezone applies at once.
>  <br/><br/>
>  The steps send Chrome DevTools Protocol commands, so they run on a Chromium
>  browser through the Selenium2 or the Chrome browser driver.
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:BrowserTimeTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `browser_time.enabled` | boolean | `TRUE` | `@behat-steps-skip:BrowserTimeTrait` | If `TRUE`, restores the real browser time and timezone after a scenario that set either. If `FALSE`, the browser keeps them. |

## Steps

<details>
  <summary><code>@Given the browser time is set to :value</code></summary>

<br/>
Set the browser time for the pages loaded after this step
<br/><br/>

```gherkin
Given the browser time is set to "2026-12-24 23:59:00"
Given the browser time is set to "1798156740"
```

</details>

<details>
  <summary><code>@Given the browser time is set to :value in the timezone :name</code></summary>

<br/>
Set the browser time and timezone for the pages loaded after this step
<br/><br/>

```gherkin
Given the browser time is set to "2026-12-24 23:59:00" in the timezone "Australia/Melbourne"
```

</details>

<details>
  <summary><code>@Given the browser timezone is set to :name</code></summary>

<br/>
Set the browser timezone, keeping the real time
<br/><br/>

```gherkin
Given the browser timezone is set to "America/New_York"
```

</details>

<details>
  <summary><code>@When the browser time is reset</code></summary>

<br/>
Reset the browser time and timezone to the real ones
<br/><br/>

```gherkin
When the browser time is reset
```

</details>
