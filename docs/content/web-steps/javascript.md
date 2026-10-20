---
title: "JavaScript errors - Web steps"
sidebar_label: "JavaScript errors"
sidebar_position: 10
description: "Automatically detect JavaScript errors during test execution."
custom_edit_url: null
---

# JavaScript errors

`DrevOps\BehatSteps\Steps\Web\JavascriptTrait` - [Source](../../../src/Steps/Web/JavascriptTrait.php), [Example](../../../tests/behat/features/javascript.feature), [Helpers](../../../HELPERS.md#javascripttrait)

>  Automatically detect JavaScript errors during test execution.
>  - Collects JavaScript errors from `window.onerror`, `unhandledrejection`
>  and `console.error`.
>  - Automatically asserts no errors at end of scenarios with `@javascript` tag.
>  - Collects errors after every step and re-injects the collector when the
>  URL changes.
>  - The `@js-errors` tag bypasses error checking when errors are expected.
>  
>  Skip processing with tags: `@behat-steps-skip:JavascriptTrait`
>  <br/><br/>
>  Special tags:
>  - `@js-errors` - bypasses error assertion for a scenario.
>  
>  Automatic error detection:
>  ```
>  @javascript
>  Scenario: Navigation without JS errors (will fail if errors occur)
>    Given I visit "/home"
>    When I click on "About"
>    Then I should see "About Us"
>  ```
>  <br/><br/>
>  Bypassing error detection:
>  ```
>  @javascript @js-errors
>  Scenario: Legacy page with known errors (will not fail)
>    Given I visit "/legacy-page"
>  ```

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `javascript.enabled` | boolean | `TRUE` | `@behat-steps-skip:JavascriptTrait` | If `TRUE`, collects the uncaught errors, unhandled promise rejections and `console.error()` calls of the pages a `@javascript` scenario visits. The collector goes in after a page loads, so errors thrown while it loads aren't caught. If `FALSE`, nothing is collected and `fail_on_errors` has no effect. |
| `javascript.fail_on_errors` | boolean | `TRUE` | `@js-errors` | If `TRUE`, a collected error fails the scenario. If `FALSE`, errors are still collected and don't fail it. |
