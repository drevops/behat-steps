---
title: "Accessibility - Web steps"
sidebar_label: "Accessibility"
sidebar_position: 1
description: "Assess accessibility of rendered pages."
custom_edit_url: null
---

# Accessibility

`DrevOps\BehatSteps\Steps\Web\AccessibilityTrait` - [Source](../../../src/Steps/Web/AccessibilityTrait.php), [Example](../../../tests/behat/features/accessibility.feature), [Helpers](../web-helpers/accessibility.md)

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
| `accessibility.enabled` | boolean | `TRUE` | `@behat-steps-skip:AccessibilityTrait` | Assess every page an `@accessibility` scenario visits. |
| `accessibility.auto_tag` | string | `'accessibility'` | - | Base tag name, without its `@`, that puts a scenario into automatic mode. |
| `accessibility.default_rules` | string | `'wcag2a,wcag2aa'` | - | Rule identifier passed to the engine when a scenario names none. |
| `accessibility.failure_threshold` | string | `'any'` | - | Impact level at which a violation fails the scenario: `any`, `never`, or one impact identifier. |
| `accessibility.fail_on_incomplete` | boolean | `FALSE` | - | Fail the scenario on a finding the engine could not decide. |
| `accessibility.cdn_url` | string | `'https://cdn.jsdelivr.net/npm/axe-core@4.11.4/axe.min.js'` | - | Location the engine source is read from. |
| `accessibility.fetch_timeout` | integer | `10` | - | Per-attempt timeout, in seconds, for the engine fetch. |
| `accessibility.fetch_attempts` | integer | `3` | - | How many times the engine fetch is attempted before failing. |
| `accessibility.report_dir` | string | `'.logs/test_results/accessibility'` | - | Directory the per-scenario reports are written to. A relative path resolves against the directory the run was launched from. |

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
