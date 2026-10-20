---
title: "Queues - Drupal steps"
sidebar_label: "Queues"
sidebar_position: 20
description: "Manage and assert Drupal queue state."
custom_edit_url: null
---

# Queues

`DrevOps\BehatSteps\Steps\Drupal\QueueTrait` - [Source](../../../src/Steps/Drupal/QueueTrait.php), [Example](../../../tests/behat/features/drupal_queue.feature), [Helpers](../../../HELPERS.md#drupalqueuetrait)

>  Manage and assert Drupal queue state.
>  - Add items to a queue and empty queues.
>  - Process queue items during tests.
>  - Assert queue item counts.
>  
>  Every queue a step names is deleted once the scenario finishes. Skip the
>  deletion with `@behat-steps-skip:QueueTrait`.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "QueueCapabilityInterface" | `QueueCapabilityInterface` |

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `queue.enabled` | boolean | `TRUE` | `@behat-steps-skip:QueueTrait` | Delete the queues a scenario used once it finishes. |
| `queue.process_limit` | integer | `1000` | - | Maximum number of items a single queue-processing step handles. |
| `queue.lease_time` | integer | `30` | - | Time, in seconds, a claimed queue item stays leased. |

## Steps

<details>
  <summary><code>@Given the following item is in the queue :queue:</code></summary>

<br/>
Add an item to a queue
<br/><br/>

```gherkin
Given the following item is in the queue "myqueue":
  | data | {"nid":1} |
```

</details>

<details>
  <summary><code>@Given the queue :queue is empty</code></summary>

<br/>
Empty a queue
<br/><br/>

```gherkin
Given the queue "myqueue" is empty
```

</details>

<details>
  <summary><code>@When the queue :queue processes :count item(s)</code></summary>

<br/>
Process a specific number of items from a queue
<br/><br/>

```gherkin
When the queue "myqueue" processes 5 items
When the queue "myqueue" processes 1 item
```

</details>

<details>
  <summary><code>@When the queue :queue is processed</code></summary>

<br/>
Process all items from a queue
<br/><br/>

```gherkin
When the queue "myqueue" is processed
```

</details>

<details>
  <summary><code>@Then the queue :queue should have :count item(s)</code></summary>

<br/>
Assert that a queue has a specific number of items
<br/><br/>

```gherkin
Then the queue "myqueue" should have 5 items
Then the queue "myqueue" should have 1 item
```

</details>

<details>
  <summary><code>@Then the queue :queue should be empty</code></summary>

<br/>
Assert that a queue is empty
<br/><br/>

```gherkin
Then the queue "myqueue" should be empty
```

</details>
