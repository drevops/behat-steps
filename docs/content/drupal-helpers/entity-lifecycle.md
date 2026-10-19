---
title: "Entity lifecycle - Drupal helpers"
sidebar_label: "Entity lifecycle"
sidebar_position: 12
description: "Creates Drupal entities and removes them when the scenario ends."
custom_edit_url: null
---

# Entity lifecycle

`DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait` - [Source](../../../src/Steps/Helper/Drupal/EntityLifecycleTrait.php)

> Creates Drupal entities and removes them when the scenario ends.

<details>
  <summary><code>public function entityLifecycleCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates an entity of a type that has no dedicated method
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleCreateLanguage(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a language
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleCreateNode(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a node
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleCreateTerm(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a taxonomy term
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleFindNewest(array $stubs, string $id_key): ?EntityStubInterface</code></summary>

<br/>
Find the newest of several entities whose ids carry no creation order
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleParseFields(EntityStubInterface $stub, array $ignored_properties = [], ?EntityDefinitionCapabilityInterface $backend = NULL): void</code></summary>

<br/>
Expands a stub's raw Gherkin values into the storage field shape
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleRegister(EntityStubInterface $stub, string $id_key): void</code></summary>

<br/>
Registers an entity created outside the create pipeline for cleanup
<br/><br/>

</details>
