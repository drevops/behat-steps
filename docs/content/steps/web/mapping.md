---
title: "MappingTrait steps"
sidebar_label: "MappingTrait"
sidebar_position: 16
description: "Replace {{ Key }} tokens in step arguments and table cells."
custom_edit_url: null
---

# MappingTrait steps

[Source](../../../../src/Steps/Web/MappingTrait.php), [Example](../../../../tests/behat/features/mapping.feature), [Helpers](../../helpers/web/mapping.md)

>  Replace `{{ Key }}` tokens in step arguments and table cells.
>  - Resolve a token against the `mapping.groups` option.
>  - Fail the step when a key is not mapped.
>  
>  Whitespace inside the braces is ignored, so `{{ Key }}` and `{{Key}}`
>  resolve identically. Keys are unique across groups, so the group a key was
>  declared in does not take part in the lookup.
>  <br/><br/>
>  The transform matches the token's braces rather than a placeholder name, so
>  1 map covers every string argument without the step opting in.
>  <br/><br/>
>  Operates on Gherkin text alone: no Mink session and no backend, so the trait
>  works in any suite.
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:MappingTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `mapping.enabled` | boolean | `TRUE` | `@behat-steps-skip:MappingTrait` | Replace `{{ Key }}` tokens in step arguments and table cells. Turn it off to pass a token through to a step untouched. |
| `mapping.groups` | map | `[]` | - | Named value mappings grouped for organization. Group names take no part in the lookup, so a key must be unique across all groups. |
