Feature: Check that RegionTrait works
  As Behat Steps library developer
  I want to provide tools to interact with named page regions
  So that users can scope actions and assertions to part of a page

  @phpserver
  Scenario: Assert "Then the region :region should contain the value :value" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    Then the region "content" should contain the value "Welcome to the content region."
    And the region "content" should not contain the value "Sidebar copy."
    And the region "sidebar" should contain the value "Sidebar copy."

  @phpserver
  Scenario: Assert "Then the region :region should contain the heading :heading" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    Then the region "content" should contain the heading "Latest news"
    And the region "content" should not contain the heading "Sidebar heading"
    And the region "sidebar" should contain the heading "Sidebar heading"

  @phpserver
  Scenario: Assert "Then the link :link should exist in the region :region" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    Then the link "About us" should exist in the region "footer"
    And the link "About us" should not exist in the region "content"
    And the link "Read more" should exist in the region "content"

  @phpserver
  Scenario: Assert "Then the button :button should exist in the region :region" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    Then the button "Save" should exist in the region "content"
    And the button "Save" should not exist in the region "footer"

  @phpserver
  Scenario: Assert "Then the element :selector should exist in the region :region" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    Then the element "img" should exist in the region "content"
    And the element "img" should not exist in the region "footer"
    And the element "h2" in the region "content" should have the value "Latest news"
    And the element "h2" in the region "content" should not have the value "Sidebar heading"
    And the element "img" in the region "content" should have the attribute "alt" with the value "Logo"
    And the element "a" with the text "About us" in the region "footer" should have the attribute "href" with the value "/about"

  @phpserver
  Scenario: Assert "When I click on the link :link in the region :region" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    And I click on the link "About us" in the region "footer"
    Then the path should be "/about"

  @phpserver
  Scenario: Assert "When I press the button :button in the region :region" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    And I press the button "Subscribe" in the region "sidebar"
    Then the current URL should have the query parameter "op" with the value "Subscribe"

  @javascript @phpserver
  Scenario: Assert "Then the element :selector with the text :text in the region :region should have the CSS property :property with the value :value" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    Then the element "span" with the text "New" in the region "content" should have the CSS property "color" with the value "rgb(255, 0, 0)"

  @phpserver
  Scenario: Assert "When I fill in the field :field with the value :value in the region :region" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    And I fill in the field "Search" with the value "behat" in the region "content"
    And I check the checkbox "Published" in the region "content"
    Then the "search" field should contain "behat"
    And I uncheck the checkbox "Published" in the region "content"

  @trait:RegionTrait @phpserver
  Scenario: Assert "Then the region :region should contain the value :value" fails for an unmapped region
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then the region "nonexistent" should contain the value "Anything"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "nonexistent" region is not configured.
      """
