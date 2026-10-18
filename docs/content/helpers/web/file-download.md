---
title: "FileDownloadTrait helpers"
sidebar_label: "FileDownloadTrait"
sidebar_position: 10
description: "Test file download functionality with content verification."
custom_edit_url: null
---

# FileDownloadTrait helpers

[Source](../../../../src/Steps/Web/FileDownloadTrait.php), [Steps](../../steps/web/file-download.md)

> Test file download functionality with content verification.

<details>
  <summary><code>public function fileDownloadAssertZipContainsFiles(array $names, bool $is_partial): void</code></summary>

<br/>
Assert that the downloaded ZIP archive holds every named file
<br/><br/>

</details>

<details>
  <summary><code>public function fileDownloadAssertZipNotContainsFiles(array $names, bool $is_partial): void</code></summary>

<br/>
Assert that the downloaded ZIP archive holds none of the named files
<br/><br/>

</details>

<details>
  <summary><code>public function fileDownloadFindLine(string $search): ?string</code></summary>

<br/>
Find the first line of the downloaded file that contains a string
<br/><br/>

</details>

<details>
  <summary><code>public function fileDownloadGetTempDir(): string</code></summary>

<br/>
Get temp download dir
<br/><br/>

</details>

<details>
  <summary><code>public function fileDownloadGetTimeout(): int</code></summary>

<br/>
Get the time, in seconds, a file download may wait for data
<br/><br/>

</details>

<details>
  <summary><code>public function fileDownloadLoad(string $url): void</code></summary>

<br/>
Download a file and keep it as the scenario's download
<br/><br/>

</details>

<details>
  <summary><code>public function fileDownloadOpenZip(): ZipArchive</code></summary>

<br/>
Open downloaded ZIP archive and validate contents
<br/><br/>

</details>

<details>
  <summary><code>public function fileDownloadProcess(string $url, array $options = []): array</code></summary>

<br/>
Download file
<br/><br/>

</details>
