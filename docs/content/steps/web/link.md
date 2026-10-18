---
title: "LinkTrait steps"
sidebar_label: "LinkTrait"
sidebar_position: 15
description: "Verify link elements with attribute and content assertions."
custom_edit_url: null
---

# LinkTrait steps

[Source](../../../../src/Steps/Web/LinkTrait.php), [Example](../../../../tests/behat/features/link.feature)

>  Verify link elements with attribute and content assertions.
>  - Find links by text, by title, or by text and href, optionally within an
>  element.
>  - Assert link existence and href match.
>  - Assert absolute and relative link paths.

## Steps

<details>
  <summary><code>@When I follow the link with the title :title</code></summary>

<br/>
Click on the link with a title
<br/><br/>

```gherkin
When I follow the link with the title "Return to site content"
```

</details>

<details>
  <summary><code>@Then I should see the link :link</code></summary>

<br/>
Assert that a link exists
<br/><br/>

```gherkin
Then I should see the link "About us"
```

</details>

<details>
  <summary><code>@Then I should not see the link :link</code></summary>

<br/>
Assert that a link does not exist
<br/><br/>

```gherkin
Then I should not see the link "Admin"
```

</details>

<details>
  <summary><code>@Then the link :link with the href :href should exist</code></summary>

<br/>
Assert a link with a href exists
<br/><br/>

```gherkin
Then the link "About us" with the href "/about-us" should exist
Then the link "About us" with the href "/about*" should exist
```

</details>

<details>
  <summary><code>@Then the link :link with the href :href within the element :selector should exist</code></summary>

<br/>
Assert link with a href exists within an element
<br/><br/>

```gherkin
Then the link "About us" with the href "/about-us" within the element ".main-nav" should exist
Then the link "About us" with the href "/about*" within the element ".main-nav" should exist
```

</details>

<details>
  <summary><code>@Then the link :link with the href :href should not exist</code></summary>

<br/>
Assert link with a href does not exist
<br/><br/>

```gherkin
Then the link "About us" with the href "/about-us" should not exist
Then the link "About us" with the href "/about*" should not exist
```

</details>

<details>
  <summary><code>@Then the link :link with the href :href within the element :selector should not exist</code></summary>

<br/>
Assert link with a href does not exist within an element
<br/><br/>

```gherkin
Then the link "About us" with the href "/about-us" within the element ".main-nav" should not exist
Then the link "About us" with the href "/about*" within the element ".main-nav" should not exist
```

</details>

<details>
  <summary><code>@Then the link with the title :title should exist</code></summary>

<br/>
Assert that a link with a title exists
<br/><br/>

```gherkin
Then the link with the title "Return to site content" should exist
```

</details>

<details>
  <summary><code>@Then the link with the title :title should not exist</code></summary>

<br/>
Assert that a link with a title does not exist
<br/><br/>

```gherkin
Then the link with the title "Some non-existing title" should not exist
```

</details>

<details>
  <summary><code>@Then the link :link should be an absolute link</code></summary>

<br/>
Assert that the link with a text is absolute
<br/><br/>

```gherkin
Then the link "my-link-title" should be an absolute link
```

</details>

<details>
  <summary><code>@Then the link :link should not be an absolute link</code></summary>

<br/>
Assert that the link is not absolute
<br/><br/>

```gherkin
Then the link "Return to site content" should not be an absolute link
```

</details>
