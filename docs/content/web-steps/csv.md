---
title: "CSV files - Web steps"
sidebar_label: "CSV files"
sidebar_position: 5
description: "Assert the columns, rows and values of CSV files."
custom_edit_url: null
---

# CSV files

`DrevOps\BehatSteps\Steps\Web\CsvTrait` - [Source](../../../src/Steps/Web/CsvTrait.php), [Example](../../../tests/behat/features/csv.feature), [Helpers](../../../HELPERS.md#csvtrait)

>  Assert the columns, rows and values of CSV files.
>  - Assert the columns the header row names.
>  - Assert the number of data rows.
>  - Assert that rows holding given values exist or do not exist.
>  - Assert every data row of a file, in order.
>  - Assert that a value appears in a field, or in none.
>  
>  A CSV file is read with a header row naming its columns, `,` as the
>  separator and `"` as the enclosure. A row step compares values exactly,
>  and a column it does not list may hold anything.
>  <br/><br/>
>  A relative path is resolved against the directory the run was launched
>  from. A path with a scheme, such as `temporary://report.csv`, is read
>  through the stream wrapper PHP has registered for that scheme.

## Steps

<details>
  <summary><code>@Then the CSV file :path should have the following columns:</code></summary>

<br/>
Assert that a CSV file has exactly the given columns, in order
<br/><br/>

```gherkin
Then the CSV file "report.csv" should have the following columns:
  | clip_id | title | result |
```

</details>

<details>
  <summary><code>@Then the CSV file :path should have the column :name</code></summary>

<br/>
Assert that a CSV file has a column
<br/><br/>

```gherkin
Then the CSV file "report.csv" should have the column "result"
```

</details>

<details>
  <summary><code>@Then the CSV file :path should not have the column :name</code></summary>

<br/>
Assert that a CSV file does not have a column
<br/><br/>

```gherkin
Then the CSV file "report.csv" should not have the column "password"
```

</details>

<details>
  <summary><code>@Then the CSV file :path should have :count row(s)</code></summary>

<br/>
Assert that a CSV file has a number of data rows
<br/><br/>

```gherkin
Then the CSV file "report.csv" should have 3 rows
Then the CSV file "empty-report.csv" should have 0 rows
```

</details>

<details>
  <summary><code>@Then the CSV file :path should contain the following row:</code></summary>

<br/>
Assert that a CSV file contains a row holding the given values
<br/><br/>

```gherkin
Then the CSV file "report.csv" should contain the following row:
  | clip_id | clip-1  |
  | result  | updated |
```

</details>

<details>
  <summary><code>@Then the CSV file :path should not contain the following row:</code></summary>

<br/>
Assert that a CSV file does not contain a row holding the given values
<br/><br/>

```gherkin
Then the CSV file "report.csv" should not contain the following row:
  | clip_id | clip-1 |
  | result  | failed |
```

</details>

<details>
  <summary><code>@Then the CSV file :path should contain the following rows:</code></summary>

<br/>
Assert that a CSV file contains a row for each row of the table
<br/><br/>

```gherkin
Then the CSV file "report.csv" should contain the following rows:
  | clip_id | result  |
  | clip-1  | updated |
  | clip-2  | created |
```

</details>

<details>
  <summary><code>@Then the CSV file :path should not contain the following rows:</code></summary>

<br/>
Assert that a CSV file contains no row matching any row of the table
<br/><br/>

```gherkin
Then the CSV file "report.csv" should not contain the following rows:
  | clip_id | result |
  | clip-1  | failed |
  | clip-2  | failed |
```

</details>

<details>
  <summary><code>@Then the CSV file :path should have the following rows:</code></summary>

<br/>
Assert that the data rows of a CSV file are exactly the table's rows
<br/><br/>

```gherkin
Then the CSV file "report.csv" should have the following rows:
  | clip_id | result  |
  | clip-1  | updated |
  | clip-2  | created |
```

</details>

<details>
  <summary><code>@Then the CSV file :path should contain :value</code></summary>

<br/>
Assert that a value appears in a field of a CSV file
<br/><br/>

```gherkin
Then the CSV file "report.csv" should contain "clip-1"
```

</details>

<details>
  <summary><code>@Then the CSV file :path should not contain :value</code></summary>

<br/>
Assert that a value appears in no field of a CSV file
<br/><br/>

```gherkin
Then the CSV file "report.csv" should not contain "error"
```

</details>
