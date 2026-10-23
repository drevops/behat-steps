---
title: "Relative dates - Web steps"
sidebar_label: "Relative dates"
sidebar_position: 21
description: "Convert relative date expressions into timestamps or formatted dates."
custom_edit_url: null
---

# Relative dates

`DrevOps\BehatSteps\Steps\Web\DateTrait` - [Source](../../../src/Steps/Web/DateTrait.php), [Example](../../../tests/behat/features/date.feature), [Helpers](../../../HELPERS.md#datetrait)

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
| `date.enabled` | boolean | `TRUE` | `@behat-steps-skip:DateTrait` | If `TRUE`, replaces `[relative:...]` tokens in table cells and in the `:datetime`, `:value`, `:partial_value`, `:expected_value`, `:text`, `:content`, `:title`, `:name` and `:label` arguments of a step. If `FALSE`, tokens stay as written. |
