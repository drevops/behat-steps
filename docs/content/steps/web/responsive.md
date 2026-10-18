---
title: "ResponsiveTrait steps"
sidebar_label: "ResponsiveTrait"
sidebar_position: 24
description: "Test responsive layouts with viewport control."
custom_edit_url: null
---

# ResponsiveTrait steps

[Source](../../../../src/Steps/Web/ResponsiveTrait.php), [Example](../../../../tests/behat/features/responsive.feature), [Helpers](../../helpers/web/responsive.md)

>  Test responsive layouts with viewport control.
>  - Default breakpoints: mobile_portrait, tablet_landscape, desktop, etc.
>  - Custom breakpoint registration via `responsiveSetBreakpoints()`
>  - Tag-based viewport control using `@breakpoint:NAME` tag
>  - Step-based viewport control during scenario execution
>  - Individual width/height control or combined dimensions.
>  - Every viewport step needs a browser driver that can resize the window,
>  such as the one a `@javascript` scenario runs.
>  <br/><br/>
>  Tag-based viewport control:
>  ```
>  @javascript @breakpoint:mobile_portrait
>  Scenario: Mobile navigation test
>    When I am on the homepage
>    Then I should see the mobile menu
>  ```
>  <br/><br/>
>  Step-based viewport control:
>  ```
>  @javascript
>  Scenario: Responsive layout test
>    When I am on the homepage
>    And I set the viewport to the breakpoint "tablet_landscape"
>    Then I should see the tablet layout
>    When I set the viewport to "1920" by "1080"
>    Then I should see the desktop layout
>  ```
>  <br/><br/>
>  Custom breakpoints:
>  ```
>  class FeatureContext extends WebContext {
>    use ResponsiveTrait;
>  
>    #[BeforeScenario]
>    public function setupCustomBreakpoints(): void {
>      $this->responsiveSetBreakpoints([
>        'iphone_12' => '390x844',
>        '4k' => '3840x2160',
>      ]);
>    }
>  }
>  ```

## Steps

<details>
  <summary><code>@Given the following responsive breakpoints exist:</code></summary>

<br/>
Set custom responsive breakpoints from a table
<br/><br/>

```gherkin
Given the following responsive breakpoints exist:
  | name       | dimensions |
  | iphone_12  | 390x844    |
  | 4k_display | 3840x2160  |
```

</details>

<details>
  <summary><code>@When I set the viewport to the breakpoint :breakpoint</code></summary>

<br/>
Set the viewport to a specific breakpoint
<br/><br/>

```gherkin
When I set the viewport to the breakpoint "mobile_portrait"
When I set the viewport to the breakpoint "desktop"
```

</details>

<details>
  <summary><code>@When I set the viewport width to :width</code></summary>

<br/>
Set the viewport width
<br/><br/>

```gherkin
When I set the viewport width to "1920"
When I set the viewport width to "768"
```

</details>

<details>
  <summary><code>@When I set the viewport height to :height</code></summary>

<br/>
Set the viewport height
<br/><br/>

```gherkin
When I set the viewport height to "1080"
When I set the viewport height to "900"
```

</details>

<details>
  <summary><code>@When I set the viewport to :width by :height</code></summary>

<br/>
Set the viewport to specific dimensions
<br/><br/>

```gherkin
When I set the viewport to "1920" by "1080"
When I set the viewport to "375" by "667"
```

</details>
