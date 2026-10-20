---
title: "Tables - Web steps"
sidebar_label: "Tables"
sidebar_position: 26
description: "Interact with HTML table elements and assert their content."
custom_edit_url: null
---

# Tables

`DrevOps\BehatSteps\Steps\Web\TableTrait` - [Source](../../../src/Steps/Web/TableTrait.php), [Example](../../../tests/behat/features/table.feature), [Helpers](../../../HELPERS.md#tabletrait)

>  Interact with HTML table elements and assert their content.
>  - Assert table row and column counts.
>  - Assert table column headers.
>  - Assert table empty and non-empty states.
>  - Assert table sort order by column.
>  - Assert text values present in a specific table row.
>  - Assert bulk row content against expected values.
>  - Click links and press buttons within a row identified by part of its
>  text.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `table.header_selector` | string | `'thead tr th'` | - | CSS selector of a table header cell, relative to the table. |
| `table.body_row_selector` | string | `'tbody tr'` | - | CSS selector of a table body row, relative to the table. |

## Steps

<details>
  <summary><code>@When I follow the link :link in the row containing :partial_text</code></summary>

<br/>
Click a link within a row
<br/><br/>

```gherkin
When I follow the link "Edit" in the row containing "Article title"
```

</details>

<details>
  <summary><code>@When I press the button :button in the row containing :partial_text</code></summary>

<br/>
Press a button within a row
<br/><br/>

```gherkin
When I press the button "Remove" in the row containing "Article title"
```

</details>

<details>
  <summary><code>@Then the table :selector should have :count row(s)</code></summary>

<br/>
Assert that a table has the expected number of body rows
<br/><br/>

```gherkin
Then the table ".mytable" should have 5 rows
```

</details>

<details>
  <summary><code>@Then the table :selector should have :count column(s)</code></summary>

<br/>
Assert that a table has the expected number of columns
<br/><br/>

```gherkin
Then the table ".mytable" should have 5 columns
```

</details>

<details>
  <summary><code>@Then the table :selector should contain the following columns:</code></summary>

<br/>
Assert that a table contains the expected column headers
<br/><br/>

```gherkin
Then the table ".mytable" should contain the following columns:
  | Title  |
  | Author |
  | Status |
```

</details>

<details>
  <summary><code>@Then the table :selector should be empty</code></summary>

<br/>
Assert that a table is empty (has no body rows)
<br/><br/>

```gherkin
Then the table ".mytable" should be empty
```

</details>

<details>
  <summary><code>@Then the table :selector should not be empty</code></summary>

<br/>
Assert that a table is not empty (has body rows)
<br/><br/>

```gherkin
Then the table ".mytable" should not be empty
```

</details>

<details>
  <summary><code>@Then the table :selector should be sorted by :column in :direction order</code></summary>

<br/>
Assert that a table is sorted by a column in a specific direction
<br/><br/>

```gherkin
Then the table ".mytable" should be sorted by "Title" in "ascending" order
Then the table ".mytable" should be sorted by "Date" in "descending" order
```

</details>

<details>
  <summary><code>@Then the table :selector should contain the following rows:</code></summary>

<br/>
Assert that a table contains the expected rows
<br/><br/>

```gherkin
Then the table ".mytable" should contain the following rows:
  | Title     | Status    |
  | Article 1 | Published |
  | Article 2 | Draft     |
```

</details>

<details>
  <summary><code>@Then I should see the following text in the row containing :partial_text:</code></summary>

<br/>
Assert that a table row containing a text has the expected values
<br/><br/>

```gherkin
Then I should see the following text in the row containing "Article title":
  | Published |
  | admin     |
```

</details>

<details>
  <summary><code>@Then I should see the text :text in the row containing :partial_text</code></summary>

<br/>
Assert that a row contains a string
<br/><br/>

```gherkin
Then I should see the text "Published" in the row containing "Article title"
```

</details>

<details>
  <summary><code>@Then I should not see the text :text in the row containing :partial_text</code></summary>

<br/>
Assert that a row does not contain a string
<br/><br/>

```gherkin
Then I should not see the text "Unpublished" in the row containing "Article title"
```

</details>

<details>
  <summary><code>@Then I should see the link :link in the row containing :partial_text</code></summary>

<br/>
Assert that a row contains the link
<br/><br/>

```gherkin
Then I should see the link "Edit" in the row containing "Article title"
```

</details>

<details>
  <summary><code>@Then I should not see the link :link in the row containing :partial_text</code></summary>

<br/>
Assert that a row does not contain the link
<br/><br/>

```gherkin
Then I should not see the link "Delete" in the row containing "Article title"
```

</details>
