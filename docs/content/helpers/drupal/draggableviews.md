---
title: "Drupal\\DraggableviewsTrait helpers"
sidebar_label: "DraggableviewsTrait"
sidebar_position: 7
description: "Order items in the Drupal Draggable Views."
custom_edit_url: null
---

# Drupal\DraggableviewsTrait helpers

[Source](../../../../src/Steps/Drupal/DraggableviewsTrait.php), [Steps](../../steps/drupal/draggableviews.md)

> Order items in the Drupal Draggable Views.

<details>
  <summary><code>public function draggableviewsFindNode(string $type, array $conditions): ?EntityStubInterface</code></summary>

<br/>
Find a node using provided conditions
<br/><br/>

</details>

<details>
  <summary><code>public function draggableviewsGetNodesByTitle(string $type, array $titles): array</code></summary>

<br/>
Get nodes of a type by title, keyed like the titles
<br/><br/>

</details>

<details>
  <summary><code>public function draggableviewsSetOrder(string $view_id, string $view_display_id, array $nodes): void</code></summary>

<br/>
Set the order of nodes in a Draggable Views display
<br/><br/>

</details>
