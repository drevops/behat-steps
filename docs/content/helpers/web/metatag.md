---
title: "MetatagTrait helpers"
sidebar_label: "MetatagTrait"
sidebar_position: 16
description: "Assert <meta> tags and head/SEO markup in page markup."
custom_edit_url: null
---

# MetatagTrait helpers

[Source](../../../../src/Steps/Web/MetatagTrait.php), [Steps](../../steps/web/metatag.md)

> Assert `<meta>` tags and head/SEO markup in page markup.

<details>
  <summary><code>public function metatagAssertHreflangAlternatesReciprocal(array $alternates): void</code></summary>

<br/>
Assert that every hreflang alternate page links back to the current URL
<br/><br/>

</details>

<details>
  <summary><code>public function metatagAssertHreflangAlternatesValid(array $alternates): void</code></summary>

<br/>
Assert that hreflang alternates are valid
<br/><br/>

</details>

<details>
  <summary><code>public function metatagAssertMetaSetExists(array $names, string $label): void</code></summary>

<br/>
Assert that a set of meta tags is present and non-empty
<br/><br/>

</details>

<details>
  <summary><code>public function metatagFindCanonicalHref(): ?string</code></summary>

<br/>
Find the canonical URL href
<br/><br/>

</details>

<details>
  <summary><code>public function metatagFindMeta(string $meta_name): ?NodeElement</code></summary>

<br/>
Find a meta tag by its "name" or "property" attribute
<br/><br/>

</details>

<details>
  <summary><code>public function metatagFindMetaContent(string $meta_name): ?string</code></summary>

<br/>
Find the content of a meta tag by its "name" or "property" attribute
<br/><br/>

</details>

<details>
  <summary><code>public function metatagFindMetaWithAttributes(array $attributes): ?NodeElement</code></summary>

<br/>
Find the first meta tag carrying every given attribute value
<br/><br/>

</details>

<details>
  <summary><code>public function metatagGetHreflangAlternates(): array</code></summary>

<br/>
Get the hreflang alternates present on the current page
<br/><br/>

</details>

<details>
  <summary><code>public function metatagGetRequiredOpenGraphTags(): array</code></summary>

<br/>
The Open Graph meta tags required by "the Open Graph tags should be valid"
<br/><br/>

</details>

<details>
  <summary><code>public function metatagGetRequiredTwitterCardTags(): array</code></summary>

<br/>
The Twitter Card tags required by "the Twitter Card tags should be valid"
<br/><br/>

</details>

<details>
  <summary><code>public function metatagGetRobotsDirectives(): array</code></summary>

<br/>
Get the robots meta tag directives as lower-cased tokens
<br/><br/>

</details>

<details>
  <summary><code>public function metatagIsIndexable(): bool</code></summary>

<br/>
Determine whether the current page is indexable
<br/><br/>

</details>

<details>
  <summary><code>public function metatagIsValidHreflang(string $value): bool</code></summary>

<br/>
Determine whether a hreflang value is a well-formed language code
<br/><br/>

</details>

<details>
  <summary><code>public function metatagResponseHasNoindexHeader(): bool</code></summary>

<br/>
Determine whether the X-Robots-Tag response header carries "noindex"
<br/><br/>

</details>
