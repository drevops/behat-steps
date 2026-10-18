---
title: "Drupal\\TaxonomyTrait helpers"
sidebar_label: "TaxonomyTrait"
sidebar_position: 20
description: "Manage Drupal taxonomy terms with vocabulary organization."
custom_edit_url: null
---

# Drupal\TaxonomyTrait helpers

[Source](../../../../src/Steps/Drupal/TaxonomyTrait.php), [Steps](../../steps/drupal/taxonomy.md)

> Manage Drupal taxonomy terms with vocabulary organization.

<details>
  <summary><code>public function taxonomyCreate(string $vocabulary, array $values): EntityStubInterface</code></summary>

<br/>
Create a term in a vocabulary
<br/><br/>

</details>

<details>
  <summary><code>public function taxonomyDelete(string $vocabulary, array $conditions): void</code></summary>

<br/>
Delete the terms of a vocabulary that match conditions
<br/><br/>

</details>

<details>
  <summary><code>public function taxonomyFindVocabulary(string $vocabulary): ?EntityStubInterface</code></summary>

<br/>
Find a vocabulary by machine name
<br/><br/>

</details>

<details>
  <summary><code>public function taxonomyGetVocabulary(string $vocabulary): EntityStubInterface</code></summary>

<br/>
Get a vocabulary by machine name
<br/><br/>

</details>

<details>
  <summary><code>public function taxonomyLoadMultiple(string $vocabulary, array $conditions = []): array</code></summary>

<br/>
Load multiple terms with specified vocabulary and conditions
<br/><br/>

</details>

<details>
  <summary><code>public function taxonomyVisitActionPageWithName(string $vocabulary, string $name, string $rel = 'canonical'): void</code></summary>

<br/>
Visit a page of the term with a specified name
<br/><br/>

</details>
