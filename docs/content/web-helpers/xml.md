---
title: "XML responses - Web helpers"
sidebar_label: "XML responses"
sidebar_position: 30
description: "Assert XML responses with element and attribute checks."
custom_edit_url: null
---

# XML responses

`DrevOps\BehatSteps\Steps\Web\XmlTrait` - [Source](../../../src/Steps/Web/XmlTrait.php), [Steps](../web-steps/xml.md)

> Assert XML responses with element and attribute checks.

<details>
  <summary><code>public function xmlAssertResponseMatchesDtd(string $dtd): void</code></summary>

<br/>
Assert that the response validates against a DTD
<br/><br/>

</details>

<details>
  <summary><code>public function xmlAssertResponseMatchesRelaxNg(string $schema): void</code></summary>

<br/>
Assert that the response validates against a RelaxNG schema
<br/><br/>

</details>

<details>
  <summary><code>public function xmlAssertResponseMatchesXsd(string $schema): void</code></summary>

<br/>
Assert that the response validates against an XSD schema
<br/><br/>

</details>

<details>
  <summary><code>public function xmlFindMissingChildElement(array $elements, array $names, ?string $namespace = NULL): ?string</code></summary>

<br/>
Find the first required child element that 1 of the elements lacks
<br/><br/>

</details>

<details>
  <summary><code>public function xmlGetAtomFeed(): DOMElement</code></summary>

<br/>
Get the feed element of an Atom response
<br/><br/>

</details>

<details>
  <summary><code>public function xmlGetContent(): string</code></summary>

<br/>
Get the response content, or the content a fixture step set in its place
<br/><br/>

</details>

<details>
  <summary><code>public function xmlGetFirstNode(string $element): DOMNode</code></summary>

<br/>
Return the first node matching an XPath expression
<br/><br/>

</details>

<details>
  <summary><code>public function xmlGetRssChannel(): DOMElement</code></summary>

<br/>
Get the channel element of an RSS 2.0 response
<br/><br/>

</details>

<details>
  <summary><code>public function xmlParse(string $content): array</code></summary>

<br/>
Parse XML content without altering the cached document
<br/><br/>

</details>
