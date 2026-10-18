---
title: "DiagnosticsTrait steps"
sidebar_label: "DiagnosticsTrait"
sidebar_position: 6
description: "Append on-failure diagnostics to the failure message of any failed step."
custom_edit_url: null
---

# DiagnosticsTrait steps

[Source](../../../../src/Steps/Web/DiagnosticsTrait.php), [Example](../../../../tests/behat/features/diagnostics.feature), [Helpers](../../helpers/web/diagnostics.md)

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
| `diagnostics.enabled` | boolean | `TRUE` | `@behat-steps-skip:DiagnosticsTrait` | Append a diagnostics block to the message of a failing step. |
| `diagnostics.header` | string | `'--- Failure diagnostics ---'` | - | Header line that precedes the diagnostics block. |
| `diagnostics.rerun_binary` | string | `'vendor/bin/behat'` | - | Binary named in the re-run command the block prints. |
| `diagnostics.show_url` | boolean | `TRUE` | - | Include the current URL in the block. |
| `diagnostics.show_status_code` | boolean | `TRUE` | - | Include the HTTP status code in the block. |
| `diagnostics.show_browser_driver` | boolean | `TRUE` | - | Include the browser driver class in the block. |
| `diagnostics.show_js_errors` | boolean | `TRUE` | - | Include the JavaScript console errors in the block. |
| `diagnostics.show_rerun` | boolean | `TRUE` | - | Include the command that re-runs the failing scenario in the block. |
