---
title: "Dropzone uploads - Web steps"
sidebar_label: "Dropzone uploads"
sidebar_position: 4
description: "Simulate a real multi-file drag-and-drop gesture onto a Dropzone target."
custom_edit_url: null
---

# Dropzone uploads

`DrevOps\BehatSteps\Steps\Web\DropzoneTrait` - [Source](../../../src/Steps/Web/DropzoneTrait.php), [Example](../../../tests/behat/features/dropzone.feature), [Helpers](../../../HELPERS.md#dropzonetrait)

>  Simulate a real multi-file drag-and-drop gesture onto a Dropzone target.
>  - Drop 1 or more files on a CSS-selected target in a single native event.
>  - Fixture paths resolve against the Mink `files_path` parameter.
>  - Works on any element that handles native `drop` events (Dropzone.js,
>  custom drop targets, framework widgets).
>  <br/><br/>
>  Mink's `attachFile` writes each file to a hidden `<input type="file">`
>  sequentially, so file A finishes uploading before file B starts. Real users
>  release multiple files together, so a single `drop` event carries all of
>  them in `dataTransfer.files` and triggers concurrent uploads.
>  <br/><br/>
>  Race conditions in dedup maps, status indicators, error handlers and
>  server-side queues reproduce only under the multi-file path.
>  <br/><br/>
>  `@javascript`-only: requires a headless browser session.

## Steps

<details>
  <summary><code>@When I drop the file :filename on the dropzone :selector</code></summary>

<br/>
Drop a single file on the target element
<br/><br/>

```gherkin
When I drop the file "document.pdf" on the dropzone ".dropzone"
```

</details>

<details>
  <summary><code>@When I drop the following files on the dropzone :selector:</code></summary>

<br/>
Drop 1 or more files on the target element in a single native event
<br/><br/>

```gherkin
When I drop the following files on the dropzone ".dropzone":
  | document.pdf |
  | image.png    |
  | text.txt     |
```

</details>
