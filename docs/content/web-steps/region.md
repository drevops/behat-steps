---
title: "Page regions - Web steps"
sidebar_label: "Page regions"
sidebar_position: 18
description: "Interact with and assert against named page regions."
custom_edit_url: null
---

# Page regions

`DrevOps\BehatSteps\Steps\Web\RegionTrait` - [Source](../../../src/Steps/Web/RegionTrait.php), [Example](../../../tests/behat/features/region.feature), [Helpers](../../../HELPERS.md#regiontrait)

>  Interact with and assert against named page regions.
>  - Click links, press buttons, fill fields and toggle checkboxes in a region.
>  - Assert text, headings, links, buttons and elements within a region.
>  
>  A region name resolves through the `region` Mink selector, which reads the
>  `regions:` map in the extension configuration. Every step throws when the
>  name is not mapped or the mapped selector matches nothing on the page.

## Steps

<details>
  <summary><code>@When I follow the link :link in the region :region</code></summary>

<br/>
Click a link within a region
<br/><br/>

```gherkin
When I follow the link "Read more" in the region "content"
```

</details>

<details>
  <summary><code>@When I press the button :button in the region :region</code></summary>

<br/>
Press a button within a region
<br/><br/>

```gherkin
When I press the button "Save" in the region "sidebar"
```

</details>

<details>
  <summary><code>@When I fill in the field :field with :value in the region :region</code></summary>

<br/>
Fill a field within a region
<br/><br/>

```gherkin
When I fill in the field "Search" with "test" in the region "header"
```

</details>

<details>
  <summary><code>@When I check the checkbox :checkbox in the region :region</code></summary>

<br/>
Check a checkbox within a region
<br/><br/>

```gherkin
When I check the checkbox "Published" in the region "content"
```

</details>

<details>
  <summary><code>@When I uncheck the checkbox :checkbox in the region :region</code></summary>

<br/>
Uncheck a checkbox within a region
<br/><br/>

```gherkin
When I uncheck the checkbox "Promoted" in the region "content"
```

</details>

<details>
  <summary><code>@Then I should see the text :text in the region :region</code></summary>

<br/>
Assert that the text of a region contains a string
<br/><br/>

```gherkin
Then I should see the text "Welcome" in the region "content"
```

</details>

<details>
  <summary><code>@Then I should not see the text :text in the region :region</code></summary>

<br/>
Assert that the text of a region does not contain a string
<br/><br/>

```gherkin
Then I should not see the text "Error" in the region "content"
```

</details>

<details>
  <summary><code>@Then I should see the heading :heading in the region :region</code></summary>

<br/>
Assert that a region contains the heading
<br/><br/>

```gherkin
Then I should see the heading "Latest news" in the region "sidebar"
```

</details>

<details>
  <summary><code>@Then I should not see the heading :heading in the region :region</code></summary>

<br/>
Assert that a region does not contain the heading
<br/><br/>

```gherkin
Then I should not see the heading "Admin" in the region "sidebar"
```

</details>

<details>
  <summary><code>@Then I should see the link :link in the region :region</code></summary>

<br/>
Assert that a region contains the link
<br/><br/>

```gherkin
Then I should see the link "About us" in the region "footer"
```

</details>

<details>
  <summary><code>@Then I should not see the link :link in the region :region</code></summary>

<br/>
Assert that a region does not contain the link
<br/><br/>

```gherkin
Then I should not see the link "Admin" in the region "footer"
```

</details>

<details>
  <summary><code>@Then I should see the button :button in the region :region</code></summary>

<br/>
Assert that a region contains the button
<br/><br/>

```gherkin
Then I should see the button "Save" in the region "content"
```

</details>

<details>
  <summary><code>@Then I should not see the button :button in the region :region</code></summary>

<br/>
Assert that a region does not contain the button
<br/><br/>

```gherkin
Then I should not see the button "Delete" in the region "content"
```

</details>

<details>
  <summary><code>@Then the element :selector in the region :region should exist</code></summary>

<br/>
Assert that a region contains an element matching the selector
<br/><br/>

```gherkin
Then the element "blockquote" in the region "content" should exist
```

</details>

<details>
  <summary><code>@Then the element :selector in the region :region should not exist</code></summary>

<br/>
Assert that a region contains no element matching the selector
<br/><br/>

```gherkin
Then the element "blockquote" in the region "content" should not exist
```

</details>

<details>
  <summary><code>@Then the element :selector in the region :region should have the value :value</code></summary>

<br/>
Assert that an element in a region has text exactly equal to a value
<br/><br/>

```gherkin
Then the element "h2" in the region "content" should have the value "News"
```

</details>

<details>
  <summary><code>@Then the element :selector in the region :region should not have the value :value</code></summary>

<br/>
Assert that no element in a region has text exactly equal to a value
<br/><br/>

```gherkin
Then the element "h2" in the region "content" should not have the value "News"
```

</details>

<details>
  <summary><code>@Then the element :selector in the region :region should have the attribute :attribute with the value :value</code></summary>

<br/>
Assert that an element in a region has the attribute value
<br/><br/>

```gherkin
Then the element "img" in the region "content" should have the attribute "alt" with the value "Logo"
```

</details>

<details>
  <summary><code>@Then the element :selector with the text :text in the region :region should have the attribute :attribute with the value :value</code></summary>

<br/>
Assert that an element in a region with the text has the attribute value
<br/><br/>

```gherkin
Then the element "a" with the text "Home" in the region "header" should have the attribute "href" with the value "/"
```

</details>

<details>
  <summary><code>@Then the element :selector with the text :text in the region :region should have the CSS property :property with the value :value</code></summary>

<br/>
Assert that an element in a region with the text has the CSS value
<br/><br/>

```gherkin
Then the element "span" with the text "New" in the region "content" should have the CSS property "color" with the value "rgb(255, 0, 0)"
```

</details>
