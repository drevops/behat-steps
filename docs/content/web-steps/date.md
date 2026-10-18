---
title: "Relative dates - Web steps"
sidebar_label: "Relative dates"
sidebar_position: 21
description: "Convert relative date expressions into timestamps or formatted dates."
custom_edit_url: null
---

# Relative dates

`DrevOps\BehatSteps\Steps\Web\DateTrait` - [Source](../../../src/Steps/Web/DateTrait.php), [Example](../../../tests/behat/features/date.feature), [Helpers](../web-helpers/date.md)

>  Convert relative date expressions into timestamps or formatted dates.
>  <br/><br/>
>  Supports values and tables.
>  <br/><br/>
>  Possible formats:
>  - `[relative:OFFSET]`
>  - `[relative:OFFSET#FORMAT]`
>  
>  with:
>  - `OFFSET`: any format that can be parsed by `strtotime()`.
>  - `FORMAT`: `date()` format for additional processing.
>  
>  Examples:
>  - `[relative:-1 day]` converted to `1893456000`
>  - `[relative:-1 day#Y-m-d]` converted to `2017-11-05`
>  
>  `dateRelativeProcessValue()` is public API. It and its helpers are static,
>  so a token resolves without a context instance.
>  <br/><br/>
>  Late static binding routes the resolution through a `dateGetNow()` override
>  in the composing context. That override is the supported way to hold the
>  current time constant.
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:DateTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `date.enabled` | boolean | `TRUE` | `@behat-steps-skip:DateTrait` | Replace `[relative:...]` tokens in step arguments and table cells. Turn it off to pass a token through to a step untouched. |
