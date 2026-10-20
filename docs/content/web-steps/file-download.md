---
title: "File downloads - Web steps"
sidebar_label: "File downloads"
sidebar_position: 7
description: "Test file download functionality with content verification."
custom_edit_url: null
---

# File downloads

`DrevOps\BehatSteps\Steps\Web\FileDownloadTrait` - [Source](../../../src/Steps/Web/FileDownloadTrait.php), [Example](../../../tests/behat/features/file_download.feature), [Helpers](../../../HELPERS.md#filedownloadtrait)

>  Test file download functionality with content verification.
>  - Download files through links and URLs with session cookie handling.
>  - Verify file names, content, and zip archive entries.
>  - Set up download directories and handle file cleanup.
>  
>  Skip processing with tag: `@behat-steps-skip:FileDownloadTrait`.
>  <br/><br/>
>  Special tags:
>  - `@download` - enable download handling

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `file_download.enabled` | boolean | `TRUE` | `@behat-steps-skip:FileDownloadTrait` | If `TRUE`, empties and creates the `temp_dir` directory before a `@download` scenario and deletes it afterwards. If `FALSE`, the download steps need that directory to exist already. |
| `file_download.temp_dir` | string | `'/tmp/behat_downloads'` | - | The directory the download steps write downloaded files into. A `@download` scenario deletes it, with everything in it, before and after the scenario. A relative path resolves against the working directory, which bootstrapping Drupal in-process changes, so an absolute path is safer. |
| `file_download.timeout` | integer | `120` | - | The time, in seconds, a download can go without receiving data before it fails. |

## Steps

<details>
  <summary><code>@When I download the file from the URL :url</code></summary>

<br/>
Download a file from the specified URL
<br/><br/>

```gherkin
When I download the file from the URL "/files/document.pdf"
When I download the file from the URL "http://example.com/files/report.xlsx"
```

</details>

<details>
  <summary><code>@When I download the file from the link :link</code></summary>

<br/>
Download the file from the specified HTML link
<br/><br/>

```gherkin
When I download the file from the link "Download PDF"
When I download the file from the link "Get Report"
```

</details>

<details>
  <summary><code>@Then the downloaded file should contain:</code></summary>

<br/>
Assert the contents of the download file
<br/><br/>

```gherkin
Then the downloaded file should contain:
"""
Financial Report 2023
"""
```

</details>

<details>
  <summary><code>@Then the downloaded file name should be :name</code></summary>

<br/>
Assert the file name of the downloaded file
<br/><br/>

```gherkin
Then the downloaded file name should be "report.pdf"
```

</details>

<details>
  <summary><code>@Then the downloaded file name should contain :partial_name</code></summary>

<br/>
Assert the downloaded file name contains a specific string
<br/><br/>

```gherkin
Then the downloaded file name should contain "report"
```

</details>

<details>
  <summary><code>@Then the downloaded file should be a zip archive containing files with the following names:</code></summary>

<br/>
Assert the downloaded file should be a zip archive containing specific files
<br/><br/>

```gherkin
Then the downloaded file should be a zip archive containing files with the following names:
  | document.pdf |
  | image.jpg    |
  | data.csv     |
```

</details>

<details>
  <summary><code>@Then the downloaded file should be a zip archive containing files with the following partial names:</code></summary>

<br/>
Assert the downloaded file should be a zip archive containing files with partial names
<br/><br/>

```gherkin
Then the downloaded file should be a zip archive containing files with the following partial names:
  | report |
  | data   |
  | image  |
```

</details>

<details>
  <summary><code>@Then the downloaded file should be a zip archive not containing files with the following partial names:</code></summary>

<br/>
Assert the downloaded file is a zip archive not containing files with partial names
<br/><br/>

```gherkin
Then the downloaded file should be a zip archive not containing files with the following partial names:
  | confidential |
  | private      |
  | draft        |
```

</details>
