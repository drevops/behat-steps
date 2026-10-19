---
title: "Meta tags - Web steps"
sidebar_label: "Meta tags"
sidebar_position: 16
description: "Assert <meta> tags and head/SEO markup in page markup."
custom_edit_url: null
---

# Meta tags

`DrevOps\BehatSteps\Steps\Web\MetatagTrait` - [Source](../../../src/Steps/Web/MetatagTrait.php), [Example](../../../tests/behat/features/metatag.feature), [Helpers](../web-helpers/metatag.md)

>  Assert `<meta>` tags and head/SEO markup in page markup.
>  - Assert presence and content of meta tags.
>  - Verify meta tag content is free of HTML markup.
>  - Assert canonical URL, robots directives and indexability.
>  - Assert hreflang alternates are valid and reciprocal.
>  - Assert Open Graph and Twitter Card completeness.

## Steps

<details>
  <summary><code>@Then the meta tag with the following attributes should exist:</code></summary>

<br/>
Assert that a meta tag with specific attributes and values exists
<br/><br/>

```gherkin
Then the meta tag with the following attributes should exist:
  | name    | description          |
  | content | My page description  |
```

</details>

<details>
  <summary><code>@Then the meta tag with the following attributes should not exist:</code></summary>

<br/>
Assert that a meta tag with specific attributes and values does not exist
<br/><br/>

```gherkin
Then the meta tag with the following attributes should not exist:
  | name    | nonexistent          |
  | content | Some content         |
```

</details>

<details>
  <summary><code>@Then the meta tag :name should not contain any HTML tags</code></summary>

<br/>
Assert a meta tag does not contain HTML tags
<br/><br/>

```gherkin
Then the meta tag "og:description" should not contain any HTML tags
Then the meta tag "description" should not contain any HTML tags
```

</details>

<details>
  <summary><code>@Then the canonical URL should be :url</code></summary>

<br/>
Assert the canonical URL equals a value
<br/><br/>

```gherkin
Then the canonical URL should be "https://example.com/about"
Then the canonical URL should be "/about"
```

</details>

<details>
  <summary><code>@Then the canonical URL should exist</code></summary>

<br/>
Assert the canonical URL is present
<br/><br/>

```gherkin
Then the canonical URL should exist
```

</details>

<details>
  <summary><code>@Then the canonical URL should not exist</code></summary>

<br/>
Assert the canonical URL is absent
<br/><br/>

```gherkin
Then the canonical URL should not exist
```

</details>

<details>
  <summary><code>@Then the page should be indexable</code></summary>

<br/>
Assert the page is indexable
<br/><br/>

```gherkin
Then the page should be indexable
```

</details>

<details>
  <summary><code>@Then the page should not be indexable</code></summary>

<br/>
Assert the page is not indexable
<br/><br/>

```gherkin
Then the page should not be indexable
```

</details>

<details>
  <summary><code>@Then the robots meta tag should contain :directive</code></summary>

<br/>
Assert the robots meta tag contains a directive
<br/><br/>

```gherkin
Then the robots meta tag should contain "noindex"
Then the robots meta tag should contain "nofollow"
```

</details>

<details>
  <summary><code>@Then the robots meta tag should not contain :directive</code></summary>

<br/>
Assert the robots meta tag does not contain a directive
<br/><br/>

```gherkin
Then the robots meta tag should not contain "noindex"
Then the robots meta tag should not contain "nofollow"
```

</details>

<details>
  <summary><code>@Then the hreflang alternates should be valid</code></summary>

<br/>
Assert hreflang alternates are valid
<br/><br/>

```gherkin
Then the hreflang alternates should be valid
```

</details>

<details>
  <summary><code>@Then the hreflang alternates should have reciprocal return links</code></summary>

<br/>
Assert hreflang alternates have reciprocal return links
<br/><br/>

```gherkin
Then the hreflang alternates should have reciprocal return links
```

</details>

<details>
  <summary><code>@Then the Open Graph tags should be valid</code></summary>

<br/>
Assert the required Open Graph meta tags are present and non-empty
<br/><br/>

```gherkin
Then the Open Graph tags should be valid
```

</details>

<details>
  <summary><code>@Then the following Open Graph tags should exist:</code></summary>

<br/>
Assert the listed Open Graph meta tags are present and non-empty
<br/><br/>

```gherkin
Then the following Open Graph tags should exist:
  | og:title |
  | og:image |
  | og:url   |
```

</details>

<details>
  <summary><code>@Then the Twitter Card tags should be valid</code></summary>

<br/>
Assert the required Twitter Card meta tags are present and non-empty
<br/><br/>

```gherkin
Then the Twitter Card tags should be valid
```

</details>

<details>
  <summary><code>@Then the following Twitter Card tags should exist:</code></summary>

<br/>
Assert the listed Twitter Card meta tags are present and non-empty
<br/><br/>

```gherkin
Then the following Twitter Card tags should exist:
  | twitter:card  |
  | twitter:title |
```

</details>
