---
title: "Mapped values - Web steps"
sidebar_label: "Mapped values"
sidebar_position: 14
description: "Replace {{ Key }} tokens in step arguments and table cells."
custom_edit_url: null
---

# Mapped values

`DrevOps\BehatSteps\Steps\Web\MappingTrait` - [Source](../../../src/Steps/Web/MappingTrait.php), [Example](../../../tests/behat/features/mapping.feature), [Helpers](../../../HELPERS.md#mappingtrait)

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
| `mapping.enabled` | boolean | `TRUE` | `@behat-steps-skip:MappingTrait` | If `TRUE`, replaces `{{ Key }}` tokens in step arguments and table cells with values from `groups`, though not in multiline text. If `FALSE`, tokens stay as written. |
| `mapping.groups` | map | `[]` | - | The values `{{ Key }}` tokens resolve to, as a map of group names to maps of keys and their values. Group names only organize the keys, so a key has to be unique across all groups, and it matches as written, case included. A token whose key no group holds fails the step. |
