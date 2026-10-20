---
title: "Form fields - Web steps"
sidebar_label: "Form fields"
sidebar_position: 8
description: "Manipulate form fields and verify widget functionality."
custom_edit_url: null
---

# Form fields

`DrevOps\BehatSteps\Steps\Web\FieldTrait` - [Source](../../../src/Steps/Web/FieldTrait.php), [Example](../../../tests/behat/features/field.feature), [Helpers](../../../HELPERS.md#fieldtrait)

>  Manipulate form fields and verify widget functionality.
>  - Set field values for various input types including selects and WYSIWYG.
>  - Assert field existence, state, and selected options.
>  - Support for specialized widgets like color pickers and rich text editors.
>  - Disable browser validation for forms with deferred execution.
>  - The @disable-form-validation tag disables validation for all forms.
>  
>  Skip processing with tag: `@behat-steps-skip:FieldTrait`

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `field.enabled` | boolean | `TRUE` | `@behat-steps-skip:FieldTrait` | If `TRUE`, strips HTML5 validation after each step from every form of a `@disable-form-validation` scenario, and from the forms `the browser validation for the form :selector is disabled` names. If `FALSE`, the tag does nothing, and that step strips validation only from the page it runs on. |
| `field.add_more_button_selectors` | map | `[...]` | - | The CSS selectors of the "Add another item" button of a multi-value field, tried in order within the field's wrapper. When none matches, the step looks for a submit input whose name contains `_add_more` or whose value contains `Add another`, or a button whose name contains `_add_more` or whose text contains `Add another`. |
| `field.required_marker_selectors` | map | `[...]` | - | The CSS selectors that mark a field as required, checked on the field, on its label and inside the label. On the field and the label, only a class such as `.form-required` or an attribute such as `[required]` counts. A `*` in the label's text also marks the field as required. |

## Steps

<details>
  <summary><code>@Given the browser validation for the form :selector is disabled</code></summary>

<br/>
Disable browser validation for the form for validating errors
<br/><br/>

```gherkin
Given the browser validation for the form "#node-article-form" is disabled
When I go to "node/add/article"
And I press "Save"
Then I should see "Title field is required"
```

</details>

<details>
  <summary><code>@When I fill in the multi-value field :field with the following values:</code></summary>

<br/>
Fill in a multi-value field widget with a list of values
<br/><br/>

```gherkin
When I fill in the multi-value field "Tags" with the following values:
  | value   |
  | Drupal  |
  | Behat   |
  | Testing |
```

</details>

<details>
  <summary><code>@When I fill in the color field :field with :value</code></summary>

<br/>
Fill value for color field
<br/><br/>

```gherkin
When I fill in the color field "#edit-text-color" with "#3366FF"
```

</details>

<details>
  <summary><code>@When I fill in the WYSIWYG field :field with :value</code></summary>

<br/>
Set value for WYSIWYG field
<br/><br/>

```gherkin
When I fill in the WYSIWYG field "edit-body-0-value" with "<p>This is a <strong>formatted</strong> paragraph.</p>"
```

</details>

<details>
  <summary><code>@When I unselect the option :option from the select :selector</code></summary>

<br/>
Unselect an option from a select field
<br/><br/>

```gherkin
When I unselect the option "Administrator" from the select "edit-roles"
When I unselect the option "Option B" from the select "field_multi_select"
```

</details>

<details>
  <summary><code>@When I clear the select :selector</code></summary>

<br/>
Clear all selections from a select field
<br/><br/>

```gherkin
When I clear the select "edit-roles"
When I clear the select "field_multi_select"
```

</details>

<details>
  <summary><code>@When I check the checkbox :selector</code></summary>

<br/>
Check the checkbox
<br/><br/>

```gherkin
When I check the checkbox "Checkbox label"
When I check the checkbox "edit-field-terms-0-value"
```

</details>

<details>
  <summary><code>@When I uncheck the checkbox :selector</code></summary>

<br/>
Uncheck the checkbox
<br/><br/>

```gherkin
When I uncheck the checkbox "Checkbox label"
When I uncheck the checkbox "edit-field-terms-0-value"
```

</details>

<details>
  <summary><code>@When I choose the radio button :selector</code></summary>

<br/>
Select a radio button
<br/><br/>

```gherkin
When I choose the radio button "Option A"
When I choose the radio button "edit-field-choice-option-a"
```

</details>

<details>
  <summary><code>@When I fill in the field :selector with :value</code></summary>

<br/>
Fill in a field identified by CSS selector
<br/><br/>

```gherkin
When I fill in the field ".field--name-body textarea" with "Hello world"
When I fill in the field "#edit-field-custom-0-value" with "Test value"
```

</details>

<details>
  <summary><code>@When I fill in the datetime field :label with the date :date and the time :time</code></summary>

<br/>
Fill in datetime field with date and optionally time
<br/><br/>

```gherkin
When I fill in the datetime field "Event date" with the date "2024-01-15" and the time "14:30:00"
When I fill in the datetime field "Event date" with the date "2024-01-15" and the time ""
```

</details>

<details>
  <summary><code>@When I fill in the date part of the datetime field :label with :date</code></summary>

<br/>
Fill in the date part of a datetime field
<br/><br/>

```gherkin
When I fill in the date part of the datetime field "Event date" with "2024-01-15"
```

</details>

<details>
  <summary><code>@When I fill in the time part of the datetime field :label with :time</code></summary>

<br/>
Fill in the time part of a datetime field
<br/><br/>

```gherkin
When I fill in the time part of the datetime field "Event date" with "14:30:00"
```

</details>

<details>
  <summary><code>@When I fill in the start datetime field :label with the date :date and the time :time</code></summary>

<br/>
Fill in start datetime field with date and optionally time
<br/><br/>

```gherkin
When I fill in the start datetime field "Event period" with the date "2024-01-15" and the time "14:30:00"
When I fill in the start datetime field "Event period" with the date "2024-01-15" and the time ""
```

</details>

<details>
  <summary><code>@When I fill in the end datetime field :label with the date :date and the time :time</code></summary>

<br/>
Fill in end datetime field with date and optionally time
<br/><br/>

```gherkin
When I fill in the end datetime field "Event period" with the date "2024-01-20" and the time "18:00:00"
When I fill in the end datetime field "Event period" with the date "2024-01-20" and the time ""
```

</details>

<details>
  <summary><code>@Then the field :field should be empty</code></summary>

<br/>
Assert that field is empty
<br/><br/>

```gherkin
Then the field "Name" should be empty
```

</details>

<details>
  <summary><code>@Then the field :field should not be empty</code></summary>

<br/>
Assert that a field is not empty
<br/><br/>

```gherkin
Then the field "Name" should not be empty
```

</details>

<details>
  <summary><code>@Then the field :field should exist</code></summary>

<br/>
Assert that field exists on the page using id,name,label or value
<br/><br/>

```gherkin
Then the field "Body" should exist
Then the field "field_body" should exist
```

</details>

<details>
  <summary><code>@Then the field :field should not exist</code></summary>

<br/>
Assert that field does not exist on the page using id,name,label or value
<br/><br/>

```gherkin
Then the field "Body" should not exist
Then the field "field_body" should not exist
```

</details>

<details>
  <summary><code>@Then the field :field should be enabled</code></summary>

<br/>
Assert that a field is enabled
<br/><br/>

```gherkin
Then the field "Tags" should be enabled
```

</details>

<details>
  <summary><code>@Then the field :field should be disabled</code></summary>

<br/>
Assert that a field is disabled
<br/><br/>

```gherkin
Then the field "Body" should be disabled
```

</details>

<details>
  <summary><code>@Then the field :field should be required</code></summary>

<br/>
Assert that a field is marked as required
<br/><br/>

```gherkin
Then the field "Email" should be required
```

</details>

<details>
  <summary><code>@Then the field :field should not be required</code></summary>

<br/>
Assert that a field is not marked as required
<br/><br/>

```gherkin
Then the field "Nickname" should not be required
```

</details>

<details>
  <summary><code>@Then the color field :field should have the value :value</code></summary>

<br/>
Assert that a color field has a value
<br/><br/>

```gherkin
Then the color field "#edit-background-color" should have the value "#FF5733"
```

</details>

<details>
  <summary><code>@Then the option :option within the select :selector should exist</code></summary>

<br/>
Assert that a select has an option
<br/><br/>

```gherkin
Then the option "Administrator" within the select "edit-roles" should exist
```

</details>

<details>
  <summary><code>@Then the option :option within the select :selector should not exist</code></summary>

<br/>
Assert that a select does not have an option
<br/><br/>

```gherkin
Then the option "Guest" within the select "edit-roles" should not exist
```

</details>

<details>
  <summary><code>@Then the option :option within the select :selector should be selected</code></summary>

<br/>
Assert that a select option is selected
<br/><br/>

```gherkin
Then the option "Administrator" within the select "edit-roles" should be selected
```

</details>

<details>
  <summary><code>@Then the option :option within the select :selector should not be selected</code></summary>

<br/>
Assert that a select option is not selected
<br/><br/>

```gherkin
Then the option "Editor" within the select "edit-roles" should not be selected
```

</details>

<details>
  <summary><code>@Then the radio button :selector should be selected</code></summary>

<br/>
Assert that a radio button is selected
<br/><br/>

```gherkin
Then the radio button "Option A" should be selected
Then the radio button "edit-field-choice-option-a" should be selected
```

</details>

<details>
  <summary><code>@Then the radio button :selector should not be selected</code></summary>

<br/>
Assert that a radio button is not selected
<br/><br/>

```gherkin
Then the radio button "Option B" should not be selected
Then the radio button "edit-field-choice-option-b" should not be selected
```

</details>
