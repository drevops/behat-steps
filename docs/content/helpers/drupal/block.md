---
title: "Drupal\\BlockTrait helpers"
sidebar_label: "BlockTrait"
sidebar_position: 2
description: "Manage Drupal blocks."
custom_edit_url: null
---

# Drupal\BlockTrait helpers

[Source](../../../../src/Steps/Drupal/BlockTrait.php), [Steps](../../steps/drupal/block.md)

> Manage Drupal blocks.

<details>
  <summary><code>public function blockApplyConfiguration(EntityStubInterface $block, array $configuration): void</code></summary>

<br/>
Apply a configuration to a block and save it
<br/><br/>

</details>

<details>
  <summary><code>public function blockCreate(string $admin_label): EntityStubInterface</code></summary>

<br/>
Create a block in the default theme from the plugin with an admin label
<br/><br/>

</details>

<details>
  <summary><code>public function blockFindByLabel(string $label): ?EntityStubInterface</code></summary>

<br/>
Find a block by its label
<br/><br/>

</details>

<details>
  <summary><code>public function blockGetByLabel(string $label): EntityStubInterface</code></summary>

<br/>
Return the block carrying a label
<br/><br/>

</details>

<details>
  <summary><code>public function blockSetVisibilityCondition(EntityStubInterface $block, string $condition, array $configuration): void</code></summary>

<br/>
Set a visibility condition on a block and save it
<br/><br/>

</details>

<details>
  <summary><code>public function blockUnsetVisibilityCondition(EntityStubInterface $block, string $condition): void</code></summary>

<br/>
Remove a visibility condition from a block and save it
<br/><br/>

</details>
