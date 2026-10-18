---
title: "Files - Drupal helpers"
sidebar_label: "Files"
sidebar_position: 15
description: "Manage Drupal file entities with upload and storage operations."
custom_edit_url: null
---

# Files

`DrevOps\BehatSteps\Steps\Drupal\FileTrait` - [Source](../../../src/Steps/Drupal/FileTrait.php), [Steps](../drupal-steps/file.md)

> Manage Drupal file entities with upload and storage operations.

<details>
  <summary><code>public function fileCreateEntity(string $path, EntityStubInterface $stub, ?string $uri = NULL): EntityStubInterface</code></summary>

<br/>
Create file entity
<br/><br/>

</details>

<details>
  <summary><code>public function fileCreateManaged(string $path, EntityStubInterface $stub, ?string $uri = NULL): EntityStubInterface</code></summary>

<br/>
Create a single managed file
<br/><br/>

</details>

<details>
  <summary><code>public function fileDeleteManaged(array $conditions): void</code></summary>

<br/>
Delete the managed files that match conditions
<br/><br/>

</details>

<details>
  <summary><code>public function fileGetUnmanagedContent(string $uri): string</code></summary>

<br/>
Get the content of an unmanaged file
<br/><br/>

</details>

<details>
  <summary><code>public function fileLoadMultiple(array $conditions = []): array</code></summary>

<br/>
Load multiple files with specified conditions
<br/><br/>

</details>

<details>
  <summary><code>public function fileWriteUnmanaged(string $uri, string $content): void</code></summary>

<br/>
Write an unmanaged file, creating its directory when it is missing
<br/><br/>

</details>
