---
title: "JavascriptTrait steps"
sidebar_label: "JavascriptTrait"
sidebar_position: 12
description: "Automatically detect JavaScript errors during test execution."
custom_edit_url: null
---

# JavascriptTrait steps

[Source](../../../../src/Steps/Web/JavascriptTrait.php), [Example](../../../../tests/behat/features/javascript.feature), [Helpers](../../helpers/web/javascript.md)

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
| `javascript.enabled` | boolean | `TRUE` | `@behat-steps-skip:JavascriptTrait` | Collect JavaScript console errors on a `@javascript` scenario. |
| `javascript.fail_on_errors` | boolean | `TRUE` | `@js-errors` | Fail a scenario that collected a console error. Errors are still collected when this is off. |
