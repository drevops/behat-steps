Feature: Check that QueueTrait works
  As Behat Steps library developer
  I want to provide tools to manage Drupal queue state
  So that users can clear, process, and assert queue items in their tests

  @api @queue
  Scenario: Assert "Given the :queue queue is empty" clears a queue
    Given I add 3 items to the "behat_test" queue
    And the "behat_test" queue should have 3 items
    When the "behat_test" queue is empty
    Then the "behat_test" queue should be empty

  @api @queue
  Scenario: Assert "Then the :queue queue should have :count items" counts items
    Given the "behat_test" queue is empty
    And I add 5 items to the "behat_test" queue
    Then the "behat_test" queue should have 5 items

  @api @queue
  Scenario: Assert "Then the :queue queue should be empty" passes for empty queue
    Given the "behat_test" queue is empty
    Then the "behat_test" queue should be empty

  @api @queue
  Scenario: Assert "Then the :queue queue should have :count item" works with singular
    Given the "behat_test" queue is empty
    And I add 1 items to the "behat_test" queue
    Then the "behat_test" queue should have 1 item

  @api @trait:Drupal\QueueTrait
  Scenario: Assert negative assertion for "Then the :queue queue should have :count items" works with wrong count
    Given some behat configuration
    And scenario steps tagged with "@api @queue":
      """
      Given I go to "/"
      Then the "behat_test" queue should have 5 items
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected queue "behat_test" to have 5 items, but it has 0.
      """

  @api @trait:Drupal\QueueTrait
  Scenario: Assert negative assertion for "Then the :queue queue should be empty" works with non-empty queue
    Given some behat configuration
    And scenario steps tagged with "@api @queue":
      """
      Given I add 2 items to the "behat_test" queue
      Then the "behat_test" queue should be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected queue "behat_test" to be empty, but it has 2 items.
      """
