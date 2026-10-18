---
title: "Drupal\\BatchTrait steps"
sidebar_label: "BatchTrait"
sidebar_position: 1
description: "Wait for Drupal's Batch API to finish."
custom_edit_url: null
---

# Drupal\BatchTrait steps

[Source](../../../../src/Steps/Drupal/BatchTrait.php), [Example](../../../../tests/behat/features/drupal_batch.feature)

>  Wait for Drupal's Batch API to finish.
>  - Poll the batch progress element until the page no longer contains it.
>  
>  A batch page reloads itself until the operation completes, so a following
>  assertion would otherwise read the progress screen rather than the result.

## Steps

<details>
  <summary><code>@When I wait for the batch job to finish</code></summary>

<br/>
Wait for the batch job to finish
<br/><br/>

```gherkin
When I wait for the batch job to finish
```

</details>
