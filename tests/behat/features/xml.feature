Feature: Check that XmlTrait works
  As Behat Steps library developer
  I want to provide tools to assert XML responses
  So that users can test API endpoints returning XML

  Scenario: Assert "Then the response should be in XML format" works
    When I go to "/sites/default/files/valid.xml"
    Then the response should be in XML format

  Scenario: Assert "Then the response should be in XML format" works with XML that has warnings
    When I go to "/sites/default/files/xml-with-warnings.xml"
    Then the response should be in XML format

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should be in XML format" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/invalid.xml"
      Then the response should be in XML format
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Failed to load XML
      """

  Scenario: Assert "Then the response should not be in XML format" works
    When I go to "/sites/default/files/invalid.xml"
    Then the response should not be in XML format

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the response should not be in XML format" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
      Then the response should not be in XML format
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response is valid XML, but it should not be.
      """

  Scenario: Assert "Then the XML element :element should exist" works with absolute path
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "/library/book" should exist

  Scenario: Assert "Then the XML element :element should exist" works with relative path
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//book" should exist

  Scenario: Assert "Then the XML element :element should exist" works with predicate
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//book[@id='123']" should exist

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
      Then the XML element "//nonexistent" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//nonexistent" was not found.
      """

  Scenario: Assert "Then the XML element :element should not exist" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//nonexistent" should not exist

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
      Then the XML element "//book" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book" was found, but it should not exist.
      """

  Scenario: Assert "Then the XML element :element should be equal to :text" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//book[@id='123']/title" should be equal to "The Great Adventure"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should be equal to :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
      Then the XML element "//book[@id='123']/title" should be equal to "Wrong Title"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book[@id='123']/title" content is "The Great Adventure", but expected "Wrong Title".
      """

  Scenario: Assert "Then the XML element :element should not be equal to :text" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//book[@id='123']/title" should not be equal to "Wrong Title"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should not be equal to :text" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
      Then the XML element "//book[@id='123']/title" should not be equal to "The Great Adventure"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book[@id='123']/title" content is "The Great Adventure", but it should not be.
      """

  Scenario: Assert "Then the XML element :element should contain :text" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//book[@id='123']/description" should contain "sample book"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should contain :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
      Then the XML element "//book[@id='123']/title" should contain "nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book[@id='123']/title" does not contain "nonexistent".
      """

  Scenario: Assert "Then the XML element :element should not contain :text" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//book[@id='123']/title" should not contain "nonexistent"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should not contain :text" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
      Then the XML element "//book[@id='123']/description" should not contain "sample book"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//book[@id='123']/description" contains "sample book", but it should not.
      """

  Scenario: Assert "Then the XML attribute :attribute on element :element should exist" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML attribute "id" on element "//book[@id='123']" should exist

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should exist" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
      Then the XML attribute "nonexistent" on element "//book[@id='123']" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "nonexistent" on element "//book[@id='123']" was not found.
      """

  Scenario: Assert "Then the XML attribute :attribute on element :element should not exist" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML attribute "nonexistent" on element "//book[@id='123']" should not exist

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
      Then the XML attribute "id" on element "//book[@id='123']" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "id" on element "//book[@id='123']" was found, but it should not exist.
      """

  Scenario: Assert "Then the XML attribute :attribute on element :element should be equal to :text" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML attribute "id" on element "//book[@id='123']" should be equal to "123"

  Scenario: Assert "Then the XML attribute :attribute on element :element should be equal to :text" works with category
    When I go to "/sites/default/files/valid.xml"
    Then the XML attribute "category" on element "//book[@id='123']" should be equal to "fiction"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should be equal to :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
      Then the XML attribute "id" on element "//book[@id='123']" should be equal to "999"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "id" on element "//book[@id='123']" is "123", but expected "999".
      """

  Scenario: Assert "Then the XML attribute :attribute on element :element should not be equal to :text" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML attribute "id" on element "//book[@id='123']" should not be equal to "999"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML attribute :attribute on element :element should not be equal to :text" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
      Then the XML attribute "id" on element "//book[@id='123']" should not be equal to "123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "id" on element "//book[@id='123']" is "123", but it should not be.
      """

  Scenario: Assert "Then the XML element :element should have :count element(s)" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//library" should have "3" elements

  Scenario: Assert "Then the XML element :element should have :count element(s)" works with simple.xml
    When I go to "/sites/default/files/simple.xml"
    Then the XML element "//root" should have "5" elements

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML element :element should have :count element(s)" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
      Then the XML element "//library" should have "5" elements
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML element "//library" has 3 child element(s), but expected 5.
      """

  Scenario: Assert "Then the XML should use the namespace :namespace" works
    When I go to "/sites/default/files/namespaced.xml"
    Then the XML should use the namespace "http://example.com/custom"

  Scenario: Assert "Then the XML should use the namespace :namespace" works with test namespace
    When I go to "/sites/default/files/namespaced.xml"
    Then the XML should use the namespace "http://example.com/test"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML should use the namespace :namespace" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/namespaced.xml"
      Then the XML should use the namespace "http://example.com/nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML does not use the namespace "http://example.com/nonexistent".
      """

  Scenario: Assert "Then the XML should not use the namespace :namespace" works
    When I go to "/sites/default/files/valid.xml"
    Then the XML should not use the namespace "http://example.com/nonexistent"

  @trait:XmlTrait
  Scenario: Assert that negative assertion for "Then the XML should not use the namespace :namespace" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/namespaced.xml"
      Then the XML should not use the namespace "http://example.com/custom"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML uses the namespace "http://example.com/custom", but it should not.
      """

  Scenario: Assert that XML document is reloaded when navigating between different XML files
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//book[@id='123']/title" should be equal to "The Great Adventure"
    When I go to "/sites/default/files/simple.xml"
    Then the XML element "//root" should have "5" elements
    And the XML element "//count" should be equal to "3"
    When I go to "/sites/default/files/valid.xml"
    Then the XML element "//book[@id='123']/title" should be equal to "The Great Adventure"

  @trait:XmlTrait
  Scenario: Assert that "Then the XML element :element should not be equal to :text" fails with an error for missing element
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
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
      When I go to "/sites/default/files/valid.xml"
      Then the XML attribute "nonexistent" on element "//book[@id='123']" should not be equal to "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The XML attribute "nonexistent" on element "//book[@id='123']" was not found.
      """
