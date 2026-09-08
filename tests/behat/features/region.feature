Feature: Check that RegionTrait works
  As Behat Steps library developer
  I want to provide tools to interact with named page regions
  So that users can scope actions and assertions to part of a page

  @phpserver
  Scenario: Assert "Then the region :region should contain the text :text" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    Then the region "content" should contain the text "Welcome to the content region."
    And the region "content" should not contain the text "Sidebar copy."
    And the region "sidebar" should contain the text "Sidebar copy."

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
    And the element "h2" in the region "content" should have the text "Latest news"
    And the element "h2" in the region "content" should not have the text "Sidebar heading"
    And the element "img" in the region "content" should have the attribute "alt" with the value "Logo"
    And the element "a" with the text "About us" in the region "footer" should have the attribute "href" with the value "/about"

  @phpserver
  Scenario: Assert "When I click the link :link in the region :region" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    And I click the link "About us" in the region "footer"
    Then the path should be "/about"

  @phpserver
  Scenario: Assert "When I fill in the field :field with :value in the region :region" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/regions.html"
    And I fill in the field "Search" with "behat" in the region "content"
    And I check the checkbox "Published" in the region "content"
    Then the "search" field should contain "behat"
    And I uncheck the checkbox "Published" in the region "content"

  @trait:RegionTrait @phpserver
  Scenario: Assert "Then the region :region should contain the text :text" fails for an unmapped region
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then the region "nonexistent" should contain the text "Anything"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      region
      """
