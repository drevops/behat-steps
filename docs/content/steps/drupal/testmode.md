---
title: "Drupal\\TestmodeTrait steps"
sidebar_label: "TestmodeTrait"
sidebar_position: 25
description: "Configure Drupal Testmode module for controlled testing scenarios."
custom_edit_url: null
---

# Drupal\TestmodeTrait steps

[Source](../../../../src/Steps/Drupal/TestmodeTrait.php), [Example](../../../../tests/behat/features/drupal_testmode.feature), [Helpers](../../helpers/drupal/testmode.md)

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
| `testmode.enabled` | boolean | `TRUE` | `@behat-steps-skip:TestmodeTrait` | Enable the Testmode module for a `@testmode` scenario and disable it afterwards. |
