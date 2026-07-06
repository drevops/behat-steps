Feature: Check that BigPipeTrait works
  As Behat Steps library developer
  I want the library to wait for Drupal BigPipe placeholders to be replaced
  So that JavaScript assertions do not race progressive rendering

  @javascript
  Scenario: BigPipe placeholders are awaited automatically before assertions
    When I visit "/sites/default/files/bigpipe_wait.html"
    Then I should see "BigPipe streamed content"

  @javascript @behat-steps-skip:BigPipeTrait
  Scenario: The automatic BigPipe wait can be skipped with a tag
    When I visit "/sites/default/files/bigpipe_wait.html"
    Then I should see an "span[data-big-pipe-placeholder-id]" element
    And I should not see "BigPipe streamed content"
