---
title: "Menus - Drupal steps"
sidebar_label: "Menus"
sidebar_position: 17
description: "Manage Drupal menus and menu links."
custom_edit_url: null
---

# Menus

`DrevOps\BehatSteps\Steps\Drupal\MenuTrait` - [Source](../../../src/Steps/Drupal/MenuTrait.php), [Example](../../../tests/behat/features/drupal_menu.feature), [Helpers](../drupal-helpers/menu.md)

>  Manage Drupal menus and menu links.
>  - Create and remove menus by label.
>  - Create and remove menu links, including parent-child hierarchies.
>  - Created menus and menu links are automatically removed at the end of the scenario.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "ContentCapabilityInterface" | `ContentCapabilityInterface` |
| The core "menu_link_content" module is enabled, for the menu link steps | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the menu :menu_name does not exist</code></summary>

<br/>
Remove every menu with a label
<br/><br/>

```gherkin
Given the menu "Test Menu" does not exist
```

</details>

<details>
  <summary><code>@Given the following menus exist:</code></summary>

<br/>
Create menus
<br/><br/>

```gherkin
Given the following menus exist:
  | label            | description                    |
  | Footer Menu     | Links displayed in the footer  |
  | Secondary Menu  | Secondary navigation menu      |
```

</details>

<details>
  <summary><code>@Given the following menu links do not exist in the menu :menu_name:</code></summary>

<br/>
Remove every menu link with each of the titles
<br/><br/>

```gherkin
Given the following menu links do not exist in the menu "Main navigation":
  | About Us     |
  | Contact      |
```

</details>

<details>
  <summary><code>@Given the following menu links exist in the menu :menu_name:</code></summary>

<br/>
Create menu links
<br/><br/>

```gherkin
Given the following menu links exist in the menu "Main navigation":
  | title           | enabled | uri                     | parent       |
  | Products        | 1       | /products               |              |
  | Latest Products | 1       | /products/latest        | Products     |
```

</details>
