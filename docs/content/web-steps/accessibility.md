---
title: "Accessibility - Web steps"
sidebar_label: "Accessibility"
sidebar_position: 1
description: "Assess accessibility of rendered pages."
custom_edit_url: null
---

# Accessibility

`DrevOps\BehatSteps\Steps\Web\AccessibilityTrait` - [Source](../../../src/Steps/Web/AccessibilityTrait.php), [Example](../../../tests/behat/features/accessibility.feature), [Helpers](../../../HELPERS.md#accessibilitytrait)

>  Assess accessibility of rendered pages.
>  <br/><br/>
>  Supported tags:
>  - `@accessibility`                          Auto-mode, default threshold.
>  - `@accessibility:critical`                 Auto-mode, fail only on critical impact.
>  - `@accessibility:serious`                  Auto-mode, fail on critical or serious.
>  - `@accessibility:moderate`                 Auto-mode, fail on critical / serious / moderate.
>  - `@accessibility:minor`                    Auto-mode, fail on any impact.
>  - `@accessibility:any`                      Auto-mode, fail on any impact (alias).
>  - `@accessibility:warning`                  Auto-mode, never fail (advisory).
>  - `@accessibility:strict`                   Also fail on "incomplete" findings.
>  - `@behat-steps-skip:AccessibilityTrait`    Opt the scenario or feature out entirely.
>  
>  Tool-agnostic. Any engine that runs inside the existing Mink session can
>  be plugged in by overriding `accessibilityRunEngine()` and
>  `accessibilityNormalizeResults()`. The first performs the assessment and
>  returns raw results; the second remaps raw output into the canonical shape
>  the rest of the trait reads.
>  <br/><br/>
>  Reporting. Each scenario writes its own HTML and JUnit report. After the
>  whole suite, a single cross-page `accessibility_report_<timestamp>.html`
>  (timestamp `YYYYMMDD_HHMMSS`) is written to the same directory,
>  de-duplicating every assessed page and rolling violations up by rule.
>  <br/><br/>
>  1 file is written per run, so a run never overwrites a previous one. The
>  aggregate accumulates in process-global state, so under parallel Behat each
>  process writes its own report.
>  <br/><br/>
>  Console output. A 1-line per-page summary can be printed to the console
>  as pages are assessed. Printing is off by default; set the
>  `BEHAT_ACCESSIBILITY_PRINT` environment variable to a non-empty value other
>  than `0`, or override `accessibilityGetPrintCli()`, to enable it.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `accessibility.enabled` | boolean | `TRUE` | `@behat-steps-skip:AccessibilityTrait` | If `TRUE`, assesses every page an `@accessibility` scenario visits and writes its reports. If `FALSE`, no page is assessed automatically and no report is written, while the accessibility steps still assess the page they run on. |
| `accessibility.auto_tag` | string | `'accessibility'` | - | The name, without the `@`, of the tag that switches on automatic assessment for a scenario and carries its threshold, as in `@accessibility:serious`. |
| `accessibility.default_rules` | string | `'wcag2a,wcag2aa'` | - | The comma-separated axe-core tags that select the rules to check, in automatic mode and in the step that names no tags. |
| `accessibility.failure_threshold` | string | `'any'` | - | The impact at which a violation fails the scenario: `critical`, `serious`, `moderate` or `minor` fails on that impact or a worse one, `any` on every violation and `never` on none. Any other value fails on every violation, like `any`. An `@accessibility:VALUE` tag overrides it for 1 scenario. |
| `accessibility.fail_on_incomplete` | boolean | `FALSE` | - | If `TRUE`, a finding axe-core can't decide fails the scenario, whatever the threshold. If `FALSE`, it doesn't. `@accessibility:strict` sets it to `TRUE` for 1 scenario. |
| `accessibility.cdn_url` | string | `'https://cdn.jsdelivr.net/npm/axe-core@4.11.4/axe.min.js'` | - | The URL of the axe-core script the trait loads into each page it assesses, or the absolute path of a local copy. The script is read once per run. |
| `accessibility.fetch_timeout` | integer | `10` | - | The time, in seconds, an attempt to fetch the axe-core script over HTTP can go without receiving data before it fails. |
| `accessibility.fetch_attempts` | integer | `3` | - | The number of attempts to fetch the axe-core script before the step fails, with a pause after each failed attempt that grows by half a second. A value below 1 counts as 1. |
| `accessibility.report_dir` | string | `'.logs/test_results/accessibility'` | - | The directory each scenario's HTML and JUnit reports and the run's summary report are written to. A relative path resolves against the directory the run was launched from. |

## Steps

<details>
  <summary><code>@Then the page should pass accessibility checks</code></summary>

<br/>
Assert that the current page passes accessibility checks
<br/><br/>

```gherkin
Then the page should pass accessibility checks
```

</details>

<details>
  <summary><code>@Then the page should pass accessibility checks for the tags :tags</code></summary>

<br/>
Assert that the current page passes accessibility checks for given tags
<br/><br/>

```gherkin
Then the page should pass accessibility checks for the tags "wcag2a"
```

</details>
