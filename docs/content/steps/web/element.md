---
title: "ElementTrait steps"
sidebar_label: "ElementTrait"
sidebar_position: 8
description: "Interact with HTML elements using CSS selectors and DOM attributes."
custom_edit_url: null
---

# ElementTrait steps

[Source](../../../../src/Steps/Web/ElementTrait.php), [Example](../../../../tests/behat/features/element.feature), [Helpers](../../helpers/web/element.md)

>  Interact with HTML elements using CSS selectors and DOM attributes.
>  - Assert element visibility, attribute values, and viewport positioning.
>  - Execute JavaScript-based interactions with element state verification.
>  - Handle confirmation dialogs and scrolling operations.
>  - A top offset of N pixels scrolls the top of the element to N pixels
>  below the top of the viewport. A negative offset scrolls it above the
>  viewport.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `element.scroll_into_view_center` | boolean | `TRUE` | - | Center an element in the viewport when scrolling to it, rather than aligning it to the top. |

## Steps

<details>
  <summary><code>@Given I accept all confirmation dialogs</code></summary>

<br/>
Accept confirmation dialogs appearing on the page
<br/><br/>

```gherkin
Given I accept all confirmation dialogs
```

</details>

<details>
  <summary><code>@Given I decline all confirmation dialogs</code></summary>

<br/>
Do not accept confirmation dialogs appearing on the page
<br/><br/>

```gherkin
Given I decline all confirmation dialogs
```

</details>

<details>
  <summary><code>@When I click on the element :selector</code></summary>

<br/>
Click on the element defined by the selector
<br/><br/>

```gherkin
When I click on the element ".button"
```

</details>

<details>
  <summary><code>@When I click on the element :selector with the index :index</code></summary>

<br/>
Click on the element at the 1-based index among all selector matches
<br/><br/>

```gherkin
When I click on the element ".card" with the index 2
```

</details>

<details>
  <summary><code>@When I follow the link :link with the index :index</code></summary>

<br/>
Follow the link at the 1-based index among all links with the text
<br/><br/>

```gherkin
When I follow the link "Read more" with the index 2
```

</details>

<details>
  <summary><code>@When I press the button :button with the index :index</code></summary>

<br/>
Press the button at the 1-based index among all buttons with the label
<br/><br/>

```gherkin
When I press the button "Delete" with the index 2
```

</details>

<details>
  <summary><code>@When the JS event :event is triggered on the element :selector</code></summary>

<br/>
Trigger a JS event on the element defined by the selector
<br/><br/>

```gherkin
When the JS event "click" is triggered on the element "#submit-button"
```

</details>

<details>
  <summary><code>@When I scroll to the element :selector</code></summary>

<br/>
Scroll to the element matching a CSS selector
<br/><br/>

```gherkin
When I scroll to the element "#footer"
```

</details>

<details>
  <summary><code>@When I hover over the element :selector</code></summary>

<br/>
Hover over an element identified by CSS selector
<br/><br/>

```gherkin
When I hover over the element ".menu-item"
When I hover over the element "#tooltip-trigger"
```

</details>

<details>
  <summary><code>@When I focus on the element :selector</code></summary>

<br/>
Focus on an element by CSS selector
<br/><br/>

```gherkin
When I focus on the element "#edit-name"
When I focus on the element ".form-text"
```

</details>

<details>
  <summary><code>@Then I should see the heading :heading</code></summary>

<br/>
Assert that a heading with the text exists
<br/><br/>

```gherkin
Then I should see the heading "Latest news"
```

</details>

<details>
  <summary><code>@Then I should not see the heading :heading</code></summary>

<br/>
Assert that no heading with the text exists
<br/><br/>

```gherkin
Then I should not see the heading "Admin"
```

</details>

<details>
  <summary><code>@Then I should see the button :button</code></summary>

<br/>
Assert that a button exists
<br/><br/>

```gherkin
Then I should see the button "Save"
```

</details>

<details>
  <summary><code>@Then I should not see the button :button</code></summary>

<br/>
Assert that a button does not exist
<br/><br/>

```gherkin
Then I should not see the button "Delete"
```

</details>

<details>
  <summary><code>@Then the element :selector1 should appear after the element :selector2</code></summary>

<br/>
Assert that one element appears after another on the page
<br/><br/>

```gherkin
Then the element "body" should appear after the element "head"
```

</details>

<details>
  <summary><code>@Then I should see the text :text1 after the text :text2</code></summary>

<br/>
Assert that one text string appears after another on the page
<br/><br/>

```gherkin
Then I should see the text "Welcome" after the text "Home"
```

</details>

<details>
  <summary><code>@Then the element :selector with the attribute :attribute and the value :value should exist</code></summary>

<br/>
Assert an element with selector and attribute with a value exists
<br/><br/>

```gherkin
Then the element "#main-content" with the attribute "class" and the value "content-wrapper" should exist
```

</details>

<details>
  <summary><code>@Then the element :selector with the attribute :attribute and a value containing :partial_value should exist</code></summary>

<br/>
Assert an element with selector and attribute containing a value exists
<br/><br/>

```gherkin
Then the element "#main-content" with the attribute "class" and a value containing "content" should exist
```

</details>

<details>
  <summary><code>@Then the element :selector with the attribute :attribute and the value :value should not exist</code></summary>

<br/>
Assert an element with selector and attribute with a value does not exist
<br/><br/>

```gherkin
Then the element "#main-content" with the attribute "class" and the value "hidden" should not exist
```

</details>

<details>
  <summary><code>@Then the element :selector with the attribute :attribute and a value containing :partial_value should not exist</code></summary>

<br/>
Assert an element with selector and attribute containing a value does not exist
<br/><br/>

```gherkin
Then the element "#main-content" with the attribute "class" and a value containing "hidden" should not exist
```

</details>

<details>
  <summary><code>@Then the element :selector should have the CSS property :property with the value :value</code></summary>

<br/>
Assert an element has a computed CSS property with a value
<br/><br/>

```gherkin
Then the element ".button" should have the CSS property "background-color" with the value "rgb(0, 0, 255)"
```

</details>

<details>
  <summary><code>@Then the element :selector should have the CSS property :property with a value containing :partial_value</code></summary>

<br/>
Assert an element has a computed CSS property containing a value
<br/><br/>

```gherkin
Then the element ".card" should have the CSS property "box-shadow" with a value containing "rgb(0, 0, 0)"
```

</details>

<details>
  <summary><code>@Then the element :selector should not have the CSS property :property with the value :value</code></summary>

<br/>
Assert an element does not have a computed CSS property with a value
<br/><br/>

```gherkin
Then the element ".button" should not have the CSS property "display" with the value "none"
```

</details>

<details>
  <summary><code>@Then the element :selector should not have the CSS property :property with a value containing :partial_value</code></summary>

<br/>
Assert an element does not have a computed CSS property containing a value
<br/><br/>

```gherkin
Then the element ".card" should not have the CSS property "box-shadow" with a value containing "inset"
```

</details>

<details>
  <summary><code>@Then the element :selector1 should stack above the element :selector2</code></summary>

<br/>
Assert that one element stacks above another
<br/><br/>

```gherkin
Then the element "#modal" should stack above the element "#page-header"
```

</details>

<details>
  <summary><code>@Then the element :selector1 should stack below the element :selector2</code></summary>

<br/>
Assert that one element stacks below another
<br/><br/>

```gherkin
Then the element "#page-header" should stack below the element "#modal"
```

</details>

<details>
  <summary><code>@Then the element :selector should be at the top of the viewport</code></summary>

<br/>
Assert that the element is at the top of the viewport
<br/><br/>

```gherkin
Then the element "#header" should be at the top of the viewport
```

</details>

<details>
  <summary><code>@Then the element :selector should be centered in the viewport</code></summary>

<br/>
Assert that the element is centered in the viewport
<br/><br/>

```gherkin
Then the element "#content" should be centered in the viewport
```

</details>

<details>
  <summary><code>@Then the element :selector should be pinned to the top of the viewport</code></summary>

<br/>
Assert that an element is pinned to the top of the viewport
<br/><br/>

```gherkin
When I scroll to the element "#footer"
Then the element "#header" should be pinned to the top of the viewport
```

</details>

<details>
  <summary><code>@Then the element :selector should be pinned to the top of the viewport within :tolerance pixels</code></summary>

<br/>
Assert that an element is pinned to the top of the viewport within a tolerance
<br/><br/>

```gherkin
Then the element "#header" should be pinned to the top of the viewport within 10 pixels
```

</details>

<details>
  <summary><code>@Then the element :selector should not be pinned to the top of the viewport</code></summary>

<br/>
Assert that an element is not pinned to the top of the viewport
<br/><br/>

```gherkin
When I scroll to the element "#footer"
Then the element "#header" should not be pinned to the top of the viewport
```

</details>

<details>
  <summary><code>@Then the element :selector should have keyboard focus</code></summary>

<br/>
Assert that the element has keyboard focus
<br/><br/>

```gherkin
Then the element "#edit-name" should have keyboard focus
```

</details>

<details>
  <summary><code>@Then the element :selector should not have keyboard focus</code></summary>

<br/>
Assert that the element does not have keyboard focus
<br/><br/>

```gherkin
Then the element "#edit-name" should not have keyboard focus
```

</details>

<details>
  <summary><code>@Then the element :selector should have a visible focus outline</code></summary>

<br/>
Assert that the element has a visible focus indicator
<br/><br/>

```gherkin
Then the element "#edit-name" should have a visible focus outline
```

</details>

<details>
  <summary><code>@Then the element :selector should not have a visible focus outline</code></summary>

<br/>
Assert that the element does not have a visible focus indicator
<br/><br/>

```gherkin
Then the element "#decorative-icon" should not have a visible focus outline
```

</details>

<details>
  <summary><code>@Then the element :selector should be displayed</code></summary>

<br/>
Assert that element with specified CSS is visible on page
<br/><br/>

```gherkin
Then the element ".alert-success" should be displayed
```

</details>

<details>
  <summary><code>@Then the element :selector should not be displayed</code></summary>

<br/>
Assert that element with specified CSS is not visible on page
<br/><br/>

```gherkin
Then the element ".error-message" should not be displayed
```

</details>

<details>
  <summary><code>@Then the element :selector should be displayed within the viewport</code></summary>

<br/>
Assert that element with specified CSS is displayed within the viewport
<br/><br/>

```gherkin
Then the element ".hero-banner" should be displayed within the viewport
```

</details>

<details>
  <summary><code>@Then the element :selector should be displayed within the viewport with a top offset of :offset pixels</code></summary>

<br/>
Assert that element with specified CSS is displayed within the viewport with a top offset
<br/><br/>

```gherkin
Then the element "#main-content" should be displayed within the viewport with a top offset of 80 pixels
Then the element ".sticky-header" should be displayed within the viewport with a top offset of -200 pixels
```

</details>

<details>
  <summary><code>@Then the element :selector should not be displayed within the viewport with a top offset of :offset pixels</code></summary>

<br/>
Assert that element with specified CSS is not displayed within the viewport with a top offset
<br/><br/>

```gherkin
Then the element ".announcement-bar" should not be displayed within the viewport with a top offset of -200 pixels
```

</details>

<details>
  <summary><code>@Then the element :selector should not be displayed within the viewport</code></summary>

<br/>
Assert that element with specified CSS is visually hidden on page
<br/><br/>

```gherkin
Then the element ".visually-hidden" should not be displayed within the viewport
```

</details>

<details>
  <summary><code>@Then the element :parent should have :count element(s) matching :selector</code></summary>

<br/>
Assert the number of elements matching a selector within a parent element
<br/><br/>

```gherkin
Then the element "#main-nav" should have 3 elements matching ".menu-item"
```

</details>
