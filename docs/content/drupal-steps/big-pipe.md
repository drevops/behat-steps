---
title: "BigPipe - Drupal steps"
sidebar_label: "BigPipe"
sidebar_position: 2
description: "Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios."
custom_edit_url: null
---

# BigPipe

`DrevOps\BehatSteps\Steps\Drupal\BigPipeTrait` - [Source](../../../src/Steps/Drupal/BigPipeTrait.php), [Example](../../../tests/behat/features/drupal_big_pipe.feature), [Helpers](../../../HELPERS.md#drupalbigpipetrait)

>  Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios.
>  <br/><br/>
>  Drupal BigPipe streams parts of a page in after the initial response and
>  replaces its `<span data-big-pipe-placeholder-id="...">` markers with the
>  real markup using JavaScript. An assertion that runs before those
>  replacements complete fails intermittently with "element not found".
>  <br/><br/>
>  With this trait included, every `@javascript` scenario waits before each
>  step until no BigPipe placeholder marker remains in the DOM. The wait
>  removes the race without an explicit step.
>  <br/><br/>
>  The wait is best-effort: on timeout the step still runs, so a placeholder
>  that is never replaced fails the following assertion rather than the wait.
>  <br/><br/>
>  A browser driver that runs no JavaScript never replaces those placeholders,
>  and does not follow the `http-equiv=refresh` fallback either. An
>  authenticated-user assertion on such a browser driver silently misses the
>  content BigPipe deferred.
>  <br/><br/>
>  A scenario tagged `@bigpipe` on such a browser driver gets the
>  `big_pipe_nojs` cookie, so Drupal renders the page in full server-side.
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:BigPipeTrait`.
>  <br/><br/>
>  Special tags:
>  - `@bigpipe` - render server-side on a browser driver without JavaScript.
>  
>  Set the `big_pipe.wait_timeout` option to change the maximum wait, or assign
>  `$bigPipeWaitTimeout` to override it for 1 scenario.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `big_pipe.enabled` | boolean | `TRUE` | `@behat-steps-skip:BigPipeTrait` | Wait for BigPipe placeholders to be replaced before each step of a `@javascript` scenario. |
| `big_pipe.wait_timeout` | integer | `10000` | - | Maximum time, in milliseconds, to wait for BigPipe placeholders to be replaced. |
