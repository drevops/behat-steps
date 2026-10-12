Feature: Check that RegionTrait works
  As Behat Steps library developer
  I want to provide tools to interact with named page regions
  So that users can scope actions and assertions to part of a page

  @phpserver
  Scenario: Assert "Then I should see :text in the :region region" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/regions.html"
    Then I should see "Welcome to the content region." in the "content" region
    And I should not see "Sidebar copy." in the "content" region
    And I should see "Sidebar copy." in the "sidebar" region

  @phpserver
  Scenario: Assert "Then I should see the heading :heading in the :region region" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/regions.html"
    Then I should see the heading "Latest news" in the "content" region
    And I should not see the heading "Sidebar heading" in the "content" region
    And I should see the heading "Sidebar heading" in the "sidebar" region

  @phpserver
  Scenario: Assert "Then I should see the link :link in the :region region" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/regions.html"
    Then I should see the link "About us" in the "footer" region
    And I should not see the link "About us" in the "content" region
    And I should see the link "Read more" in the "content" region

  @phpserver
  Scenario: Assert "Then I should see the button :button in the :region region" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/regions.html"
    Then I should see the button "Save" in the "content" region
    And I should not see the button "Save" in the "footer" region

  @phpserver
  Scenario: Assert "Then the element :selector in the :region region should exist" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/regions.html"
    Then the element "img" in the "content" region should exist
    And the element "img" in the "footer" region should not exist
    And the element "h2" in the "content" region should have the value "Latest news"
    And the element "h2" in the "content" region should not have the value "Sidebar heading"
    And the element "img" in the "content" region should have the attribute "alt" with the value "Logo"
    And the element "a" with the text "About us" in the "footer" region should have the attribute "href" with the value "/about"

  @phpserver
  Scenario: Assert "When I follow the link :link in the :region region" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/regions.html"
    And I follow the link "About us" in the "footer" region
    Then the path should be "/about"

  @phpserver
  Scenario: Assert "When I press the button :button in the :region region" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/regions.html"
    And I press the button "Subscribe" in the "sidebar" region
    Then the URL should have the query parameter "op" with the value "Subscribe"

  @javascript @phpserver
  Scenario: Assert "Then the element :selector with the text :text in the :region region should have the CSS property :property with the value :value" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/regions.html"
    Then the element "span" with the text "New" in the "content" region should have the CSS property "color" with the value "rgb(255, 0, 0)"

  @phpserver
  Scenario: Assert "When I fill in the field :field with :value in the :region region" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/regions.html"
    And I fill in the field "Search" with "behat" in the "content" region
    And I check the checkbox "Published" in the "content" region
    Then the "search" field should contain "behat"
    And I uncheck the checkbox "Published" in the "content" region

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then I should see :text in the :region region" fails for an unmapped region
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then I should see "Anything" in the "nonexistent" region
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The region "nonexistent" is not configured.
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then I should see :text in the :region region" fails when the region lacks the value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then I should see "Sidebar copy." in the "content" region
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The text "Sidebar copy." was not found in the region "content" on the page "http://cli:8888/regions.html".
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then I should not see :text in the :region region" fails when the region has the value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then I should not see "Welcome to the content region." in the "content" region
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The text "Welcome to the content region." was found in the region "content" on the page "http://cli:8888/regions.html".
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then I should not see the heading :heading in the :region region" fails when the region has the heading
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then I should not see the heading "Latest news" in the "content" region
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The heading "Latest news" was found in the region "content" on the page "http://cli:8888/regions.html".
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then I should not see the link :link in the :region region" fails when the region has the link
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then I should not see the link "Read more" in the "content" region
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link "Read more" was found in the region "content" on the page "http://cli:8888/regions.html".
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then I should not see the button :button in the :region region" fails when the region has the button
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then I should not see the button "Save" in the "content" region
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The button "Save" was found in the region "content" on the page "http://cli:8888/regions.html".
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then the element :selector in the :region region should not exist" fails when the region has the element
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then the element "img" in the "content" region should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The element "img" was found in the region "content" on the page "http://cli:8888/regions.html".
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then the element :selector in the :region region should have the value :value" fails when no element has the value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then the element "h2" in the "content" region should have the value "Sidebar heading"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The text "Sidebar heading" was not found in the element "h2" in the region "content" on the page "http://cli:8888/regions.html".
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then the element :selector in the :region region should not have the value :value" fails when an element has the value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then the element "h2" in the "content" region should not have the value "Latest news"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The text "Latest news" was found in the element "h2" in the region "content" on the page "http://cli:8888/regions.html".
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then the element :selector in the :region region should have the attribute :attribute with the value :value" fails when no element has the attribute value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then the element "img" in the "content" region should have the attribute "alt" with the value "Banner"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The element "img" in the region "content" does not have the attribute "alt" with the value "Banner" on the page "http://cli:8888/regions.html".
      """

  @test-trait:RegionTrait @phpserver
  Scenario: Assert "Then the element :selector with the text :text in the :region region should have the attribute :attribute with the value :value" fails when the attribute differs
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then the element "a" with the text "About us" in the "footer" region should have the attribute "href" with the value "/contact"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The element "a" with the text "About us" in the region "footer" does not have the attribute "href" with the value "/contact" on the page "http://cli:8888/regions.html".
      """
