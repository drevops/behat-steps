---
title: "Testmode - Drupal steps"
sidebar_label: "Testmode"
sidebar_position: 26
description: "Configure Drupal Testmode module for controlled testing scenarios."
custom_edit_url: null
---

# Testmode

`DrevOps\BehatSteps\Steps\Drupal\TestmodeTrait` - [Source](../../../src/Steps/Drupal/TestmodeTrait.php), [Example](../../../tests/behat/features/drupal_testmode.feature), [Helpers](../../../HELPERS.md#drupaltestmodetrait)

>  Configure Drupal Testmode module for controlled testing scenarios.
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:TestmodeTrait`.
>  <br/><br/>
>  Special tags:
>  - `@testmode` - enable for scenario

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "StateCapabilityInterface" | `StateCapabilityInterface` |
| A backend in the scenario's list provides "CacheCapabilityInterface" | `CacheCapabilityInterface` |
| The "testmode" module from the "drupal/testmode" package is enabled | `ModuleCapabilityInterface` |

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `testmode.enabled` | boolean | `TRUE` | `@behat-steps-skip:TestmodeTrait` | If `TRUE`, switches the Testmode module's test mode on for a `@testmode` scenario and off after it finishes. If `FALSE`, the tag does nothing. |
