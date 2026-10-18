---
title: "RandomTrait steps"
sidebar_label: "RandomTrait"
sidebar_position: 21
description: "Replace random-value tokens in step arguments and table cells."
custom_edit_url: null
---

# RandomTrait steps

[Source](../../../../src/Steps/Web/RandomTrait.php), [Example](../../../../tests/behat/features/random.feature), [Helpers](../../helpers/web/random.md)

>  Replace random-value tokens in step arguments and table cells.
>  - Resolve `[?<name>:<type>[,<args>]]` tokens to generated values.
>  - Return 1 value per token for the whole scenario.
>  
>  Built-in types are `string`, `name`, `machine_name`, `int`, `email` and
>  `uuid`. The default is `string` with length `10`, so `[?title]`,
>  `[?title:string]` and `[?title:string,10]` share 1 value.
>  <br/><br/>
>  Operates on Gherkin text alone: no Mink session and no backend, so the trait
>  works in any suite.
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:RandomTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `random.enabled` | boolean | `TRUE` | `@behat-steps-skip:RandomTrait` | Replace `[?name:type]` tokens in step arguments and table cells. Turn it off to pass a token through to a step untouched. |
