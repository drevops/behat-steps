---
title: "Drupal\\QueueTrait helpers"
sidebar_label: "QueueTrait"
sidebar_position: 16
description: "Manage and assert Drupal queue state."
custom_edit_url: null
---

# Drupal\QueueTrait helpers

[Source](../../../../src/Steps/Drupal/QueueTrait.php), [Steps](../../steps/drupal/queue.md)

> Manage and assert Drupal queue state.

<details>
  <summary><code>public function queueClear(string $queue): void</code></summary>

<br/>
Delete every item of a queue
<br/><br/>

</details>

<details>
  <summary><code>public function queueCountItems(string $queue): int</code></summary>

<br/>
Count the items in a queue
<br/><br/>

</details>

<details>
  <summary><code>public function queueGetLeaseTime(): int</code></summary>

<br/>
Get the lease time for claiming queue items
<br/><br/>

</details>

<details>
  <summary><code>public function queueGetProcessLimit(): int</code></summary>

<br/>
Get the maximum number of items to process
<br/><br/>

</details>

<details>
  <summary><code>public function queueProcess(string $queue, int $limit): int</code></summary>

<br/>
Process items from a queue with the queue's worker
<br/><br/>

</details>
