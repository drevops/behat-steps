---
title: "Files - Drupal steps"
sidebar_label: "Files"
sidebar_position: 14
description: "Manage Drupal file entities with upload and storage operations."
custom_edit_url: null
---

# Files

`DrevOps\BehatSteps\Steps\Drupal\FileTrait` - [Source](../../../src/Steps/Drupal/FileTrait.php), [Example](../../../tests/behat/features/drupal_file.feature), [Helpers](../../../HELPERS.md#drupalfiletrait)

>  Manage Drupal file entities with upload and storage operations.
>  - Create managed and unmanaged files with specific URIs and content.
>  - Verify file existence, content, and proper storage locations.
>  - Set up file system directories and clean up created files.
>  
>  Skip processing with tag: `@behat-steps-skip:FileTrait`.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "FileCapabilityInterface" | `FileCapabilityInterface` |
| The core "file" module is enabled, for the managed file steps | `ModuleCapabilityInterface` |

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `file.enabled` | boolean | `TRUE` | `@behat-steps-skip:FileTrait` | If `TRUE`, creates the `private://` and `temporary://` directories before each scenario, and deletes the unmanaged files the file steps wrote once it finishes. If `FALSE`, the steps still work and their files stay. |

## Steps

<details>
  <summary><code>@Given the following managed files exist:</code></summary>

<br/>
Create managed files with properties provided in the table
<br/><br/>

```gherkin
Given the following managed files exist:
  | path         | uri                    | status |
  | document.pdf | public://document.pdf  | 1      |
  | image.jpg    | public://images/pic.jpg| 1      |
```

</details>

<details>
  <summary><code>@Given the following managed files do not exist:</code></summary>

<br/>
Delete managed files defined by provided properties/fields
<br/><br/>

```gherkin
Given the following managed files do not exist:
  | filename      |
  | myfile.jpg    |
  | otherfile.jpg |
Given the following managed files do not exist:
  | uri                    |
  | public://myfile.jpg    |
  | public://otherfile.jpg |
```

</details>

<details>
  <summary><code>@Given the unmanaged file at the URI :uri exists</code></summary>

<br/>
Create an unmanaged file
<br/><br/>

```gherkin
Given the unmanaged file at the URI "public://sample.txt" exists
```

</details>

<details>
  <summary><code>@Given the unmanaged file at the URI :uri exists with the content :content</code></summary>

<br/>
Create an unmanaged file with specified content
<br/><br/>

```gherkin
Given the unmanaged file at the URI "public://data.txt" exists with the content "Sample content"
```

</details>

<details>
  <summary><code>@Then the unmanaged file at the URI :uri should exist</code></summary>

<br/>
Assert that an unmanaged file with specified URI exists
<br/><br/>

```gherkin
Then the unmanaged file at the URI "public://sample.txt" should exist
```

</details>

<details>
  <summary><code>@Then the unmanaged file at the URI :uri should not exist</code></summary>

<br/>
Assert that an unmanaged file with specified URI does not exist
<br/><br/>

```gherkin
Then the unmanaged file at the URI "public://temp.txt" should not exist
```

</details>

<details>
  <summary><code>@Then the unmanaged file at the URI :uri should contain :content</code></summary>

<br/>
Assert that an unmanaged file exists and has specified content
<br/><br/>

```gherkin
Then the unmanaged file at the URI "public://config.txt" should contain "debug=true"
```

</details>

<details>
  <summary><code>@Then the unmanaged file at the URI :uri should not contain :content</code></summary>

<br/>
Assert that an unmanaged file exists and does not have specified content
<br/><br/>

```gherkin
Then the unmanaged file at the URI "public://config.txt" should not contain "debug=false"
```

</details>
