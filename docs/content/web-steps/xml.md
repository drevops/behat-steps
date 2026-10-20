---
title: "XML responses - Web steps"
sidebar_label: "XML responses"
sidebar_position: 28
description: "Assert XML responses with element and attribute checks."
custom_edit_url: null
---

# XML responses

`DrevOps\BehatSteps\Steps\Web\XmlTrait` - [Source](../../../src/Steps/Web/XmlTrait.php), [Example](../../../tests/behat/features/xml.feature), [Helpers](../../../HELPERS.md#xmltrait)

>  Assert XML responses with element and attribute checks.
>  - Assert response is valid XML format.
>  - Assert XML element existence and content.
>  - Assert XML attribute values.
>  - Assert XML structure and namespace usage.

## Steps

<details>
  <summary><code>@Given the response XML is loaded from the file :filename</code></summary>

<br/>
Set the response XML content from a fixture file
<br/><br/>

```gherkin
Given the response XML is loaded from the file "xml_valid.xml"
```

</details>

<details>
  <summary><code>@Given the response XML is the following:</code></summary>

<br/>
Set the response XML content directly from a PyString
<br/><br/>

```gherkin
Given the response XML is the following:
  """
  <?xml version="1.0"?><root><item>value</item></root>
  """
```

</details>

<details>
  <summary><code>@When I print the last XML response</code></summary>

<br/>
Print the last XML response
<br/><br/>

```gherkin
When I print the last XML response
```

</details>

<details>
  <summary><code>@Then the response should be in XML format</code></summary>

<br/>
Assert that a response is valid XML
<br/><br/>

```gherkin
Then the response should be in XML format

# Content set by a fixture step is validated instead of the page content.
Given the response XML is loaded from the file "xml_valid.xml"
Then the response should be in XML format
```

</details>

<details>
  <summary><code>@Then the response should not be in XML format</code></summary>

<br/>
Assert that a response is not valid XML
<br/><br/>

```gherkin
Then the response should not be in XML format

# Content set by a fixture step is validated instead of the page content.
Given the response XML is loaded from the file "xml_invalid.xml"
Then the response should not be in XML format
```

</details>

<details>
  <summary><code>@Then the XML element :element should exist</code></summary>

<br/>
Assert that an XML element exists
<br/><br/>

```gherkin
Then the XML element "//book" should exist
Then the XML element "/library/book[@id='123']" should exist
```

</details>

<details>
  <summary><code>@Then the XML element :element should not exist</code></summary>

<br/>
Assert that an XML element does not exist
<br/><br/>

```gherkin
Then the XML element "//nonexistent" should not exist
Then the XML element "/library/book[@id='999']" should not exist
```

</details>

<details>
  <summary><code>@Then the XML element :element should have the value :value</code></summary>

<br/>
Assert that an XML element content equals specified text
<br/><br/>

```gherkin
Then the XML element "//title" should have the value "The Great Adventure"
Then the XML element "/library/book[1]/author" should have the value "John Doe"
```

</details>

<details>
  <summary><code>@Then the XML element :element should not have the value :value</code></summary>

<br/>
Assert that an XML element content does not equal specified text
<br/><br/>

```gherkin
Then the XML element "//title" should not have the value "Wrong Title"
Then the XML element "/library/book[1]/author" should not have the value "Wrong Author"
```

</details>

<details>
  <summary><code>@Then the XML element :element should contain :value</code></summary>

<br/>
Assert that an XML element contains specified text
<br/><br/>

```gherkin
Then the XML element "//description" should contain "sample book"
Then the XML element "/library/book[1]/description" should contain "detailed"
```

</details>

<details>
  <summary><code>@Then the XML element :element should not contain :value</code></summary>

<br/>
Assert that an XML element does not contain specified text
<br/><br/>

```gherkin
Then the XML element "//description" should not contain "nonexistent"
Then the XML element "/library/book[1]/title" should not contain "wrong"
```

</details>

<details>
  <summary><code>@Then the XML attribute :attribute on the element :element should exist</code></summary>

<br/>
Assert that an XML attribute exists on an element
<br/><br/>

```gherkin
Then the XML attribute "id" on the element "//book" should exist
Then the XML attribute "category" on the element "/library/book[1]" should exist
```

</details>

<details>
  <summary><code>@Then the XML attribute :attribute on the element :element should not exist</code></summary>

<br/>
Assert that an XML attribute does not exist on an element
<br/><br/>

```gherkin
Then the XML attribute "nonexistent" on the element "//book" should not exist
Then the XML attribute "missing" on the element "/library/book[1]" should not exist
```

</details>

<details>
  <summary><code>@Then the XML attribute :attribute on the element :element should have the value :value</code></summary>

<br/>
Assert that an XML attribute value equals specified text
<br/><br/>

```gherkin
Then the XML attribute "id" on the element "//book" should have the value "123"
Then the XML attribute "category" on the element "/library/book[1]" should have the value "fiction"
```

</details>

<details>
  <summary><code>@Then the XML attribute :attribute on the element :element should not have the value :value</code></summary>

<br/>
Assert that an XML attribute value does not equal specified text
<br/><br/>

```gherkin
Then the XML attribute "id" on the element "//book" should not have the value "999"
Then the XML attribute "category" on the element "/library/book[1]" should not have the value "science"
```

</details>

<details>
  <summary><code>@Then the XML attribute :attribute on the element :element should contain :value</code></summary>

<br/>
Assert that an XML attribute value contains specified text
<br/><br/>

```gherkin
Then the XML attribute "category" on the element "//book" should contain "fic"
Then the XML attribute "id" on the element "/library/book[1]" should contain "12"
```

</details>

<details>
  <summary><code>@Then the XML attribute :attribute on the element :element should not contain :value</code></summary>

<br/>
Assert that an XML attribute value does not contain specified text
<br/><br/>

```gherkin
Then the XML attribute "category" on the element "//book" should not contain "science"
Then the XML attribute "id" on the element "/library/book[1]" should not contain "999"
```

</details>

<details>
  <summary><code>@Then the XML element :element should have :count element(s)</code></summary>

<br/>
Assert that an XML element has a specific number of child elements
<br/><br/>

```gherkin
Then the XML element "//library" should have "3" elements
Then the XML element "/library" should have "3" elements
```

</details>

<details>
  <summary><code>@Then the XML should use the namespace :namespace</code></summary>

<br/>
Assert that the XML uses a specific namespace
<br/><br/>

```gherkin
Then the XML should use the namespace "http://example.com/custom"
```

</details>

<details>
  <summary><code>@Then the XML should not use the namespace :namespace</code></summary>

<br/>
Assert that the XML does not use a specific namespace
<br/><br/>

```gherkin
Then the XML should not use the namespace "http://example.com/nonexistent"
```

</details>

<details>
  <summary><code>@Then the response should match the following XSD schema:</code></summary>

<br/>
Assert that the response validates against an inline XSD schema
<br/><br/>

```gherkin
Then the response should match the following XSD schema:
  """
  <?xml version="1.0"?>
  <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="note" type="xs:string"/>
  </xs:schema>
  """
```

</details>

<details>
  <summary><code>@Then the response should match the XSD schema in the file :filename</code></summary>

<br/>
Assert that the response validates against an XSD schema from a file
<br/><br/>

```gherkin
Then the response should match the XSD schema in the file "xml_schema.xsd"
```

</details>

<details>
  <summary><code>@Then the response should match the following DTD:</code></summary>

<br/>
Assert that the response validates against an inline DTD
<br/><br/>

```gherkin
Then the response should match the following DTD:
  """
  <!ELEMENT note (#PCDATA)>
  """
```

</details>

<details>
  <summary><code>@Then the response should match the DTD in the file :filename</code></summary>

<br/>
Assert that the response validates against a DTD from a file
<br/><br/>

```gherkin
Then the response should match the DTD in the file "xml_schema.dtd"
```

</details>

<details>
  <summary><code>@Then the response should match the following RelaxNG schema:</code></summary>

<br/>
Assert that the response validates against an inline RelaxNG schema
<br/><br/>

```gherkin
Then the response should match the following RelaxNG schema:
  """
  <element name="note" xmlns="http://relaxng.org/ns/structure/1.0">
    <text/>
  </element>
  """
```

</details>

<details>
  <summary><code>@Then the response should match the RelaxNG schema in the file :filename</code></summary>

<br/>
Assert that the response validates against a RelaxNG schema from a file
<br/><br/>

```gherkin
Then the response should match the RelaxNG schema in the file "xml_schema.rng"
```

</details>

<details>
  <summary><code>@Then the response should be a valid RSS feed</code></summary>

<br/>
Assert that the response is a valid RSS 2.0 feed
<br/><br/>

```gherkin
Then the response should be a valid RSS feed
```

</details>

<details>
  <summary><code>@Then the response should be a valid Atom feed</code></summary>

<br/>
Assert that the response is a valid Atom feed
<br/><br/>

```gherkin
Then the response should be a valid Atom feed
```

</details>
