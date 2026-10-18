---
title: "Drupal\\DraggableviewsTrait steps"
sidebar_label: "DraggableviewsTrait"
sidebar_position: 9
description: "Order items in the Drupal Draggable Views."
custom_edit_url: null
---

# Drupal\DraggableviewsTrait steps

[Source](../../../../src/Steps/Drupal/DraggableviewsTrait.php), [Example](../../../../tests/behat/features/drupal_draggableviews.feature), [Helpers](../../helpers/drupal/draggableviews.md)

>  Order items in the Drupal Draggable Views.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "DraggableviewsCapabilityInterface" | `DraggableviewsCapabilityInterface` |
| The "draggableviews" module from the "drupal/draggableviews" package is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@When the draggable views items of the view :view_id and the display :view_display_id for the :content_type content are saved in the following order:</code></summary>

<br/>
Save the order of the Draggable Views items
<br/><br/>

```gherkin
When the draggable views items of the view "draggableviews_demo" and the display "page_1" for the "article" content are saved in the following order:
  | First Article  |
  | Second Article |
  | Third Article  |
```

</details>
