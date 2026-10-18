---
title: "Menus - Drupal helpers"
sidebar_label: "Menus"
sidebar_position: 17
description: "Manage Drupal menus and menu links."
custom_edit_url: null
---

# Menus

`DrevOps\BehatSteps\Steps\Drupal\MenuTrait` - [Source](../../../src/Steps/Drupal/MenuTrait.php), [Steps](../drupal-steps/menu.md)

> Manage Drupal menus and menu links.

<details>
  <summary><code>public function menuCreate(array $values): EntityStubInterface</code></summary>

<br/>
Create a menu
<br/><br/>

</details>

<details>
  <summary><code>public function menuCreateLink(EntityStubInterface $menu, array $values): EntityStubInterface</code></summary>

<br/>
Create a link in a menu
<br/><br/>

</details>

<details>
  <summary><code>public function menuDeleteLink(string $menu_name, array $conditions): void</code></summary>

<br/>
Delete the links of a menu that match conditions
<br/><br/>

</details>

<details>
  <summary><code>public function menuFindByLabel(string $label): ?EntityStubInterface</code></summary>

<br/>
Find a menu by its label
<br/><br/>

</details>

<details>
  <summary><code>public function menuFindLinkByTitle(string $title, string $menu_name): ?EntityStubInterface</code></summary>

<br/>
Find a menu link by title and menu label
<br/><br/>

</details>

<details>
  <summary><code>public function menuLoadMultiple(array $conditions = []): array</code></summary>

<br/>
Load multiple menus with specified conditions
<br/><br/>

</details>
