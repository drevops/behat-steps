---
title: "Content - Drupal steps"
sidebar_label: "Content"
sidebar_position: 7
description: "Manage Drupal content with workflow and moderation support."
custom_edit_url: null
---

# Content

`DrevOps\BehatSteps\Steps\Drupal\ContentTrait` - [Source](../../../src/Steps/Drupal/ContentTrait.php), [Example](../../../tests/behat/features/drupal_content.feature), [Helpers](../drupal-helpers/content.md)

>  Manage Drupal content with workflow and moderation support.
>  - Create, find, and manipulate nodes with structured field data.
>  - Navigate to node pages by title and manage editorial workflows.
>  - Support content moderation transitions and scheduled publishing.
>  - Set path aliases and assert the published state of content.
>  
>  Several nodes of the same type can share a title. Steps that visit, modify
>  or assert the published state of content by title resolve to the most
>  recently created of them. Steps that delete content or assert it does not
>  exist match every such node.
>  <br/><br/>
>  When the contrib `pathauto` module is enabled, the path alias step switches
>  automatic alias generation off for the content, so the provided alias is
>  preserved.

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "PathAliasCapabilityInterface" | `PathAliasCapabilityInterface` |
| The core "path" module is enabled, for the path alias step | `ModuleCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the content type :content_type does not exist</code></summary>

<br/>
Delete content type
<br/><br/>

```gherkin
Given the content type "article" does not exist
```

</details>

<details>
  <summary><code>@Given the following :content_type content does not exist:</code></summary>

<br/>
Remove content defined by provided properties
<br/><br/>

```gherkin
Given the following "article" content does not exist:
  | title                |
  | Test article         |
  | Another test article |
```

</details>

<details>
  <summary><code>@Given the following :content_type content with fields exists:</code></summary>

<br/>
Create content with vertical field format
<br/><br/>

```gherkin
Given the following page content with fields exists:
  | title  | [TEST] Page 1        | [TEST] Page 2        |
  | body   | First page content   | Second page content  |
  | status | 1                    | 1                    |
```

</details>

<details>
  <summary><code>@Given the following :content_type content exists:</code></summary>

<br/>
Create content of a type from a table of field values
<br/><br/>

```gherkin
Given the following page content exists:
  | title         | status |
  | [TEST] Page 1 | 1      |
  | [TEST] Page 2 | 0      |
```

</details>

<details>
  <summary><code>@When I visit the :content_type content page with the title :title</code></summary>

<br/>
Visit a page of a type with a specified title
<br/><br/>

```gherkin
When I visit the "article" content page with the title "Test article"
```

</details>

<details>
  <summary><code>@When I visit the :content_type content edit page with the title :title</code></summary>

<br/>
Visit an edit page of a type with a specified title
<br/><br/>

```gherkin
When I visit the "article" content edit page with the title "Test article"
```

</details>

<details>
  <summary><code>@When I visit the :content_type content delete page with the title :title</code></summary>

<br/>
Visit a delete page of a type with a specified title
<br/><br/>

```gherkin
When I visit the "article" content delete page with the title "Test article"
```

</details>

<details>
  <summary><code>@When I visit the :content_type content scheduled transitions page with the title :title</code></summary>

<br/>
Visit a scheduled transitions page of a type with a specified title
<br/><br/>

```gherkin
When I visit the "article" content scheduled transitions page with the title "Test article"
```

</details>

<details>
  <summary><code>@When I visit the :content_type content revisions page with the title :title</code></summary>

<br/>
Visit a revisions page of a type with a specified title
<br/><br/>

```gherkin
When I visit the "article" content revisions page with the title "Test article"
```

</details>

<details>
  <summary><code>@When the moderation state of the :content_type content with the title :title changes to the state :new_state</code></summary>

<br/>
Change moderation state of a content with the specified title
<br/><br/>

```gherkin
When the moderation state of the "article" content with the title "Test article" changes to the state "published"
```

</details>

<details>
  <summary><code>@When the access grants for the :content_type content with the title :title are rebuilt</code></summary>

<br/>
Rebuild node access grants for a content with the specified title
<br/><br/>

```gherkin
When the access grants for the "article" content with the title "My article" are rebuilt
```

</details>

<details>
  <summary><code>@When the access grants for all content are rebuilt</code></summary>

<br/>
Rebuild node access grants for all content
<br/><br/>

```gherkin
When the access grants for all content are rebuilt
```

</details>

<details>
  <summary><code>@When the path alias of the :content_type content with the title :title is set to :alias</code></summary>

<br/>
Set the path alias of a content with the specified title
<br/><br/>

```gherkin
When the path alias of the "article" content with the title "Test article" is set to "/my-test-article"
```

</details>

<details>
  <summary><code>@Then the :content_type content with the title :title should not exist</code></summary>

<br/>
Assert content with specified type and title does not exist
<br/><br/>

```gherkin
Then the "page" content with the title "Test page" should not exist
Then the "article" content with the title "Test article" should not exist
```

</details>

<details>
  <summary><code>@Then the :content_type content with the title :title should be published</code></summary>

<br/>
Assert content with specified type and title is published
<br/><br/>

```gherkin
Then the "page" content with the title "Test page" should be published
```

</details>

<details>
  <summary><code>@Then the :content_type content with the title :title should not be published</code></summary>

<br/>
Assert content with specified type and title is not published
<br/><br/>

```gherkin
Then the "page" content with the title "Test page" should not be published
```

</details>
