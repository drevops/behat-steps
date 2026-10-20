---
title: "Failure diagnostics - Web steps"
sidebar_label: "Failure diagnostics"
sidebar_position: 6
description: "Append on-failure diagnostics to the failure message of any failed step."
custom_edit_url: null
---

# Failure diagnostics

`DrevOps\BehatSteps\Steps\Web\DiagnosticsTrait` - [Source](../../../src/Steps/Web/DiagnosticsTrait.php), [Example](../../../tests/behat/features/diagnostics.feature), [Helpers](../../../HELPERS.md#diagnosticstrait)

>  Append on-failure diagnostics to the failure message of any failed step.
>  <br/><br/>
>  The exception message of a failed step is often not enough to diagnose a
>  CI failure. This trait hooks every step and, only when the step failed,
>  appends a compact diagnostics block to the failure message:
>  - `URL` - the current page URL.
>  - `HTTP status` - the last response status code.
>  - `Browser driver` - the class of the browser driver behind the session.
>  - `JS console errors` - collected JavaScript errors, when a JavaScript-capable
>  browser driver is active and errors were captured. Reads the buffer kept by
>  `JavascriptTrait` when the context also uses it, and the live browser buffer.
>  - `Re-run` - a ready-to-paste command that re-runs just the failing scenario.
>  
>  The trait is active with no further configuration once a context composes
>  it. The `enabled` option switches it off.
>  <br/><br/>
>  Every field is individually toggleable through its `show_*` option or by
>  overriding its `diagnosticsGetShow*()` method to return FALSE. Each value
>  source degrades to nothing when the browser driver cannot provide it, so a
>  failed step is never turned into a different failure.
>  <br/><br/>
>  Skip processing with tags: `@behat-steps-skip:DiagnosticsTrait`.
>  <br/><br/>
>  ```
>  Scenario: A failing step prints diagnostics
>    Given I am on "/some-page"
>    Then I should see "text that is not there"
>    # On failure the message gains:
>    # --- Failure diagnostics ---
>    # URL: http://example.com/some-page
>    # HTTP status: 200
>    # Browser driver: Behat\Mink\Driver\BrowserKitDriver
>    # Re-run: vendor/bin/behat features/example.feature:3
>  ```

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `diagnostics.enabled` | boolean | `TRUE` | `@behat-steps-skip:DiagnosticsTrait` | If `TRUE`, appends a diagnostics block to the message of a failing step. If `FALSE`, the message stays as the step threw it. |
| `diagnostics.header` | string | `'--- Failure diagnostics ---'` | - | The line the diagnostics block starts with. |
| `diagnostics.rerun_binary` | string | `'vendor/bin/behat'` | - | The command the block's re-run line starts with, before the failing scenario's file and line. It can carry options too, such as `vendor/bin/behat -p ci`. |
| `diagnostics.show_url` | boolean | `TRUE` | - | If `TRUE`, the block includes the current URL. |
| `diagnostics.show_status_code` | boolean | `TRUE` | - | If `TRUE`, the block includes the HTTP status code of the last response, when the browser driver reports one. |
| `diagnostics.show_browser_driver` | boolean | `TRUE` | - | If `TRUE`, the block includes the class of the session's browser driver. |
| `diagnostics.show_js_errors` | boolean | `TRUE` | - | If `TRUE`, the block includes the JavaScript errors `JavascriptTrait` collected, which it does only in a `@javascript` scenario. |
| `diagnostics.show_rerun` | boolean | `TRUE` | - | If `TRUE`, the block includes the command that re-runs the failing scenario. |
