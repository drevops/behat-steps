---
title: "Drupal\\MediaTrait steps"
sidebar_label: "MediaTrait"
sidebar_position: 16
description: "Manage Drupal media entities with type-specific field handling."
custom_edit_url: null
---

# Drupal\MediaTrait steps

[Source](../../../../src/Steps/Drupal/MediaTrait.php), [Example](../../../../tests/behat/features/drupal_media.feature), [Helpers](../../helpers/drupal/media.md)

>  Manage Drupal media entities with type-specific field handling.
>  - Create structured media items with proper file reference handling.
>  - Assert media type and media item existence.
>  - Visit media view, edit, delete and revision pages.
>  - Support for multiple media types with field value expansion handling.
>  - Created entities are automatically removed at the end of the scenario.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "ContentCapabilityInterface" | `ContentCapabilityInterface` |
| The core "media" module is enabled | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the media type :media_type does not exist</code></summary>

<br/>
Remove media type
<br/><br/>

```gherkin
Given the media type "video" does not exist
```

</details>

<details>
  <summary><code>@Given the following :media_type media exist:</code></summary>

<br/>
Create media of a given type
<br/><br/>

```gherkin
Given the following "video" media exist:
  | name     | field1   | field2 | field3           |
  | My media | file.jpg | value  | value            |
  | ...      | ...      | ...    | ...              |
```

</details>

<details>
  <summary><code>@Given the following :media_type media with fields exist:</code></summary>

<br/>
Create media entities with vertical field format
<br/><br/>

```gherkin
Given the following image media with fields exist:
  | name              | [TEST] Image 1       | [TEST] Image 2       |
  | field_media_image | image1.jpg           | image2.jpg           |
```

</details>

<details>
  <summary><code>@Given the following :media_type media do not exist:</code></summary>

<br/>
Remove media defined by provided properties
<br/><br/>

```gherkin
Given the following "image" media do not exist:
  | name               |
  | Media item         |
  | Another media item |
```

</details>

<details>
  <summary><code>@When I visit the :media_type media page with the name :name</code></summary>

<br/>
Visit the page of the media with the specified type and name
<br/><br/>

```gherkin
When I visit the "image" media page with the name "Test media image"
```

</details>

<details>
  <summary><code>@When I visit the :media_type media edit page with the name :name</code></summary>

<br/>
Visit the edit page of the media with the specified type and name
<br/><br/>

```gherkin
When I visit the "document" media edit page with the name "Test document"
```

</details>

<details>
  <summary><code>@When I visit the :media_type media delete page with the name :name</code></summary>

<br/>
Visit the delete page of the media with the specified type and name
<br/><br/>

```gherkin
When I visit the "image" media delete page with the name "Test media image"
```

</details>

<details>
  <summary><code>@When I visit the :media_type media revisions page with the name :name</code></summary>

<br/>
Visit the revisions page of the media with the specified type and name
<br/><br/>

```gherkin
When I visit the "image" media revisions page with the name "Test media image"
```

</details>

<details>
  <summary><code>@Then the media type :media_type should exist</code></summary>

<br/>
Assert that a media type exists
<br/><br/>

```gherkin
Then the media type "image" should exist
```

</details>

<details>
  <summary><code>@Then the media type :media_type should not exist</code></summary>

<br/>
Assert that a media type does not exist
<br/><br/>

```gherkin
Then the media type "test_type" should not exist
```

</details>

<details>
  <summary><code>@Then the :media_type media with the name :name should exist</code></summary>

<br/>
Assert that a media entity with a specific type and name exists
<br/><br/>

```gherkin
Then the "image" media with the name "Test media image" should exist
```

</details>

<details>
  <summary><code>@Then the :media_type media with the name :name should not exist</code></summary>

<br/>
Assert that a media entity with a specific type and name does not exist
<br/><br/>

```gherkin
Then the "image" media with the name "Test media image" should not exist
```

</details>
