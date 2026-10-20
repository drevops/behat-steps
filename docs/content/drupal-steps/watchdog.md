---
title: "Watchdog - Drupal steps"
sidebar_label: "Watchdog"
sidebar_position: 28
description: "Assert Drupal does not trigger PHP errors during scenarios using Watchdog."
custom_edit_url: null
---

# Watchdog

`DrevOps\BehatSteps\Steps\Drupal\WatchdogTrait` - [Source](../../../src/Steps/Drupal/WatchdogTrait.php), [Example](../../../tests/behat/features/drupal_watchdog.feature), [Helpers](../../../HELPERS.md#drupalwatchdogtrait)

>  Assert Drupal does not trigger PHP errors during scenarios using Watchdog.
>  - Check for Watchdog messages after scenario completion.
>  - Optionally check for more message types than `php`.
>  - Optionally skip error checking for specific scenarios.
>  
>  The check is on by default. An opted-in scenario whose prerequisites do not
>  hold fails at its start.
>  <br/><br/>
>  `watchdog.fail_on_errors` and `@error` determine what happens to errors
>  that were read, so they do not cover an unmet prerequisite.
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:WatchdogTrait`.
>  <br/><br/>
>  Special tags:
>  - `@watchdog:{type}` - track the named message type in addition to `php`,
>  which is always tracked.
>  - `@error` - add to scenarios that are expected to trigger an error. The
>  errors are still read and cleared; the scenario is not failed.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "LogCapabilityInterface" | `LogCapabilityInterface` |
| The core "dblog" module is enabled | `ModuleCapabilityInterface` |

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `watchdog.enabled` | boolean | `TRUE` | `@behat-steps-skip:WatchdogTrait` | If `TRUE`, checks that the `dblog` module is enabled, then reads the PHP warnings and errors, and the entries of the `@watchdog:` types, that every scenario logs. If `FALSE`, nothing is checked or read. |
| `watchdog.fail_on_errors` | boolean | `TRUE` | `@error` | If `TRUE`, a logged warning or error fails the scenario. If `FALSE`, they are still read and cleared without failing it. |
