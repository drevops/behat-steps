Feature: Check that XmlTrait works
  As Behat Steps library developer
  I want to provide tools to assert XML responses
  So that users can test API endpoints returning XML

  Scenario: Assert "Then the response should be in XML format" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the response should be in XML format

  Scenario: Assert "Then the response should be in XML format" works with XML that has warnings
    When I go to "/sites/default/files/xml_with_warnings.xml"
    Then the response should be in XML format

  Scenario: Assert "Then the response should be in XML format" honours content set from a fixture file over the page content
    When I go to "/sites/default/files/xml_invalid.xml"
    And the response content from the file "xml_valid.xml"
    Then the response should be in XML format

  Scenario: Assert "Then the response should be in XML format" honours content set from a PyString over the page content
    When I go to "/sites/default/files/xml_invalid.xml"
    And the response content is the following:
      """
      <?xml version="1.0" encoding="UTF-8"?>
      <catalog>
        <product id="p1">Blue Widget</product>
      </catalog>
      """
    Then the response should be in XML format

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be in XML format" fails with an exception
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_invalid.xml"
      Then the response should be in XML format
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Failed to load XML
      """

  Scenario: Assert "Then the response should not be in XML format" works
    When I go to "/sites/default/files/xml_invalid.xml"
    Then the response should not be in XML format

  Scenario: Assert "Then the response should not be in XML format" honours content set from a fixture file over the page content
    When I go to "/sites/default/files/xml_valid.xml"
    And the response content from the file "xml_invalid.xml"
    Then the response should not be in XML format

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should not be in XML format" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the response should not be in XML format
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response is valid XML, but it should not be.
      """

  Scenario: Assert "Then the XML element :element should exist" works with absolute path
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "/library/book" should exist

  Scenario: Assert "Then the XML element :element should exist" works with relative path
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//book" should exist

  Scenario: Assert "Then the XML element :element should exist" works with predicate
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//book[@id='123']" should exist

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//nonexistent" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  Scenario: Assert "Then the XML element :element should not exist" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//nonexistent" should not exist

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//book" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book" was found, but it should not exist.
      """

  Scenario: Assert "Then the XML element :element should be equal to :text" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//book[@id='123']/title" should be equal to "The Great Adventure"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should be equal to :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//nonexistent" should be equal to "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should be equal to :text" fails with an error for wrong content
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//book[@id='123']/title" should be equal to "Wrong Title"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book[@id='123']/title" content is "The Great Adventure", but expected "Wrong Title".
      """

  Scenario: Assert "Then the XML element :element should not be equal to :text" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//book[@id='123']/title" should not be equal to "Wrong Title"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should not be equal to :text" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//book[@id='123']/title" should not be equal to "The Great Adventure"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book[@id='123']/title" content is "The Great Adventure", but it should not be.
      """

  Scenario: Assert "Then the XML element :element should contain :text" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//book[@id='123']/description" should contain "sample book"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should contain :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//nonexistent" should contain "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should contain :text" fails with an error for missing text
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//book[@id='123']/title" should contain "nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book[@id='123']/title" does not contain "nonexistent".
      """

  Scenario: Assert "Then the XML element :element should not contain :text" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//book[@id='123']/title" should not contain "nonexistent"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should not contain :text" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//book[@id='123']/description" should not contain "sample book"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book[@id='123']/description" contains "sample book", but it should not.
      """

  Scenario: Assert "Then the XML attribute :attribute on element :element should exist" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML attribute "id" on element "//book[@id='123']" should exist

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should exist" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "id" on element "//nonexistent" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should exist" fails with an error for missing attribute
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "nonexistent" on element "//book[@id='123']" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "nonexistent" on element "//book[@id='123']" was not found.
      """

  Scenario: Assert "Then the XML attribute :attribute on element :element should not exist" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML attribute "nonexistent" on element "//book[@id='123']" should not exist

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "id" on element "//book[@id='123']" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "id" on element "//book[@id='123']" was found, but it should not exist.
      """

  Scenario: Assert "Then the XML attribute :attribute on element :element should be equal to :text" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML attribute "id" on element "//book[@id='123']" should be equal to "123"

  Scenario: Assert "Then the XML attribute :attribute on element :element should be equal to :text" works with category
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML attribute "category" on element "//book[@id='123']" should be equal to "fiction"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should be equal to :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "id" on element "//nonexistent" should be equal to "123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should be equal to :text" fails with an error for missing attribute
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "nonexistent" on element "//book[@id='123']" should be equal to "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "nonexistent" on element "//book[@id='123']" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should be equal to :text" fails with an error for wrong value
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "id" on element "//book[@id='123']" should be equal to "999"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "id" on element "//book[@id='123']" is "123", but expected "999".
      """

  Scenario: Assert "Then the XML attribute :attribute on element :element should not be equal to :text" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML attribute "id" on element "//book[@id='123']" should not be equal to "999"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should not be equal to :text" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "id" on element "//book[@id='123']" should not be equal to "123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "id" on element "//book[@id='123']" is "123", but it should not be.
      """

  Scenario: Assert "Then the XML element :element should have :count element(s)" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//library" should have "3" elements

  Scenario: Assert "Then the XML element :element should have :count element(s)" works with simple.xml
    When I go to "/sites/default/files/xml_simple.xml"
    Then the XML element "//root" should have "5" elements

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should have :count element(s)" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//nonexistent" should have "3" elements
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should have :count element(s)" fails with an error for wrong count
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//library" should have "5" elements
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//library" has 3 child element(s), but expected 5.
      """

  Scenario: Assert "Then the XML should use the namespace :namespace" works
    When I go to "/sites/default/files/xml_namespaced.xml"
    Then the XML should use the namespace "http://example.com/custom"

  Scenario: Assert "Then the XML should use the namespace :namespace" works with test namespace
    When I go to "/sites/default/files/xml_namespaced.xml"
    Then the XML should use the namespace "http://example.com/test"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML should use the namespace :namespace" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_namespaced.xml"
      Then the XML should use the namespace "http://example.com/nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML does not use the namespace "http://example.com/nonexistent".
      """

  Scenario: Assert "Then the XML should not use the namespace :namespace" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML should not use the namespace "http://example.com/nonexistent"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML should not use the namespace :namespace" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_namespaced.xml"
      Then the XML should not use the namespace "http://example.com/custom"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML uses the namespace "http://example.com/custom", but it should not.
      """

  Scenario: Assert that XML document is reloaded when navigating between different XML files
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//book[@id='123']/title" should be equal to "The Great Adventure"
    When I go to "/sites/default/files/xml_simple.xml"
    Then the XML element "//root" should have "5" elements
    And the XML element "//count" should be equal to "3"
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML element "//book[@id='123']/title" should be equal to "The Great Adventure"

  @trait:XmlTrait
  Scenario: Assert that "Then the XML element :element should not be equal to :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//nonexistent" should not be equal to "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that "Then the XML element :element should not contain :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML element "//nonexistent" should not contain "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that "Then the XML attribute :attribute on element :element should not exist" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "id" on element "//nonexistent" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that "Then the XML attribute :attribute on element :element should not be equal to :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "id" on element "//nonexistent" should not be equal to "123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that "Then the XML attribute :attribute on element :element should not be equal to :text" fails with an error for missing attribute
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "nonexistent" on element "//book[@id='123']" should not be equal to "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "nonexistent" on element "//book[@id='123']" was not found.
      """

  Scenario: Assert "Given the response content from the file :filename" works
    Given the response content from the file "xml_valid.xml"
    Then the XML element "//book[@id='123']/title" should be equal to "The Great Adventure"

  Scenario: Assert "Given the response content from the file :filename" works with attribute assertions
    Given the response content from the file "xml_valid.xml"
    Then the XML attribute "category" on element "//book[@id='123']" should be equal to "fiction"

  @trait:XmlTrait
  Scenario: Assert that "Given the response content from the file :filename" fails with an exception for missing file
    Given some behat configuration
    And scenario steps:
      """
      Given the response content from the file "nonexistent.xml"
      Then the XML element "//book" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      does not exist
      """

  Scenario: Assert "Given the response content is the following:" works with direct PyString content
    Given the response content is the following:
      """
      <?xml version="1.0" encoding="UTF-8"?>
      <catalog>
        <product id="p1" type="widget">
          <name>Blue Widget</name>
          <price>9.99</price>
        </product>
      </catalog>
      """
    Then the XML element "//product[@id='p1']/name" should be equal to "Blue Widget"
    And the XML element "//product[@id='p1']/price" should be equal to "9.99"
    And the XML attribute "type" on element "//product[@id='p1']" should be equal to "widget"

  @trait:XmlTrait
  Scenario: Assert that "Given the response content is the following:" fails with an exception for invalid XML
    Given some behat configuration
    And scenario steps:
      """
      Given the response content is the following:
        '''
        this is not valid xml <<<
        '''
      Then the XML element "//anything" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Failed to load XML
      """

  Scenario: Assert "Then the XML attribute :attribute_name on element :element should contain :text" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML attribute "category" on element "//book[@id='123']" should contain "fic"

  Scenario: Assert "Then the XML attribute :attribute_name on element :element should contain :text" works with id attribute
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML attribute "id" on element "//book[@id='123']" should contain "12"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute_name on element :element should contain :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "id" on element "//nonexistent" should contain "123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute_name on element :element should contain :text" fails with an error for missing attribute
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "nonexistent" on element "//book[@id='123']" should contain "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "nonexistent" on element "//book[@id='123']" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute_name on element :element should contain :text" fails with an error for text not found
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "category" on element "//book[@id='123']" should contain "science"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "category" on element "//book[@id='123']" does not contain "science".
      """

  Scenario: Assert "Then the XML attribute :attribute_name on element :element should not contain :text" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the XML attribute "category" on element "//book[@id='123']" should not contain "science"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute_name on element :element should not contain :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "id" on element "//nonexistent" should not contain "123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute_name on element :element should not contain :text" fails with an error for missing attribute
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "nonexistent" on element "//book[@id='123']" should not contain "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "nonexistent" on element "//book[@id='123']" was not found.
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute_name on element :element should not contain :text" fails with an error when text is found
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the XML attribute "category" on element "//book[@id='123']" should not contain "fic"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "category" on element "//book[@id='123']" contains "fic", but it should not.
      """

  Scenario: Assert "When I print last XML response" works
    Given the response content from the file "xml_valid.xml"
    When I print last XML response

  @trait:XmlTrait
  Scenario: Assert that "When I print last XML response" fails with an error when no XML is loaded
    Given some behat configuration
    And scenario steps:
      """
      When I print last XML response
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\DriverException" exception:
      """
      Unable to access the response before visiting a page
      """

  Scenario: Assert "Then the response should match the following XSD schema:" works
    Given the response content is the following:
      """
      <?xml version="1.0"?><note><to>World</to></note>
      """
    Then the response should match the following XSD schema:
      """
      <?xml version="1.0"?>
      <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
        <xs:element name="note">
          <xs:complexType>
            <xs:sequence>
              <xs:element name="to" type="xs:string"/>
            </xs:sequence>
          </xs:complexType>
        </xs:element>
      </xs:schema>
      """

  Scenario: Assert "Then the response should match the XSD schema in the file :filename" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the response should match the XSD schema in the file "xml_schema.xsd"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should match the XSD schema in the file :filename" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_simple.xml"
      Then the response should match the XSD schema in the file "xml_schema.xsd"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response does not match the XSD schema
      """

  @trait:XmlTrait
  Scenario: Assert that "Then the response should match the XSD schema in the file :filename" fails with an exception for missing file
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      Then the response should match the XSD schema in the file "nonexistent.xsd"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      does not exist
      """

  Scenario: Assert "Then the response should match the following DTD:" works
    Given the response content is the following:
      """
      <?xml version="1.0"?><note>Hello</note>
      """
    Then the response should match the following DTD:
      """
      <!ELEMENT note (#PCDATA)>
      """

  Scenario: Assert "Then the response should match the DTD in the file :filename" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the response should match the DTD in the file "xml_schema.dtd"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should match the DTD in the file :filename" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_simple.xml"
      Then the response should match the DTD in the file "xml_schema.dtd"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response does not match the DTD
      """

  Scenario: Assert "Then the response should match the following RelaxNG schema:" works
    Given the response content is the following:
      """
      <?xml version="1.0"?><note>Hello</note>
      """
    Then the response should match the following RelaxNG schema:
      """
      <element name="note" xmlns="http://relaxng.org/ns/structure/1.0">
        <text/>
      </element>
      """

  Scenario: Assert "Then the response should match the RelaxNG schema in the file :filename" works
    When I go to "/sites/default/files/xml_valid.xml"
    Then the response should match the RelaxNG schema in the file "xml_schema.rng"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should match the RelaxNG schema in the file :filename" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_simple.xml"
      Then the response should match the RelaxNG schema in the file "xml_schema.rng"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response does not match the RelaxNG schema
      """

  Scenario: Assert "Then the response should be a valid RSS feed" works
    When I go to "/sites/default/files/rss_valid.xml"
    Then the response should be a valid RSS feed

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be a valid RSS feed" fails with an error for a non-rss root
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      And the response content is the following:
        '''
        <?xml version="1.0"?><catalog></catalog>
        '''
      Then the response should be a valid RSS feed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      the root element must be "rss"
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be a valid RSS feed" fails with an error for a wrong version
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      And the response content is the following:
        '''
        <?xml version="1.0"?><rss version="1.0"><channel><title>t</title><link>l</link><description>d</description></channel></rss>
        '''
      Then the response should be a valid RSS feed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      must have a "version" attribute of "2.0"
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be a valid RSS feed" fails with an error for a missing channel
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      And the response content is the following:
        '''
        <?xml version="1.0"?><rss version="2.0"></rss>
        '''
      Then the response should be a valid RSS feed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      must contain exactly one "channel" element
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be a valid RSS feed" fails with an error for a missing required channel element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      And the response content is the following:
        '''
        <?xml version="1.0"?><rss version="2.0"><channel><title>t</title><link>l</link></channel></rss>
        '''
      Then the response should be a valid RSS feed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      is missing the required "description" element
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be a valid RSS feed" fails with an error for an item without a title or description
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      And the response content is the following:
        '''
        <?xml version="1.0"?><rss version="2.0"><channel><title>t</title><link>l</link><description>d</description><item><link>x</link></item></channel></rss>
        '''
      Then the response should be a valid RSS feed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      each "item" element must contain a "title" or a "description"
      """

  Scenario: Assert "Then the response should be a valid Atom feed" works
    When I go to "/sites/default/files/atom_valid.xml"
    Then the response should be a valid Atom feed

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be a valid Atom feed" fails with an error for a non-atom root
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      And the response content is the following:
        '''
        <?xml version="1.0"?><feed><id>x</id><title>t</title><updated>u</updated></feed>
        '''
      Then the response should be a valid Atom feed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      the root element must be "feed" in the Atom namespace
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be a valid Atom feed" fails with an error for a missing required feed element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      And the response content is the following:
        '''
        <?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><id>x</id><title>t</title></feed>
        '''
      Then the response should be a valid Atom feed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      the "feed" element is missing the required "updated" element
      """

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be a valid Atom feed" fails with an error for an entry missing a required element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/xml_valid.xml"
      And the response content is the following:
        '''
        <?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:other="http://example.com/other"><id>x</id><title>t</title><updated>u</updated><entry><id>e</id><title>et</title><other:updated>2024</other:updated></entry></feed>
        '''
      Then the response should be a valid Atom feed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      an "entry" element is missing the required "updated" element
      """
