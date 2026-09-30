Feature: Check that QueueTrait works
  As Behat Steps library developer
  I want to provide tools to manage Drupal queue state
  So that users can clear, process, and assert queue items in their tests

  @queue
  Scenario: Assert "Given the queue :queue is empty" clears a queue
    Given I add 3 items to the "behat_test" queue
    And the queue "behat_test" should have 3 items
    When the queue "behat_test" is empty
    Then the queue "behat_test" should be empty

  @queue
  Scenario: Assert "Given the following item is in the queue :queue:" adds an item
    Given the queue "behat_test" is empty
    And the following item is in the queue "behat_test":
      | data | {"nid":1} |
    Then the queue "behat_test" should have 1 item

  @queue
  Scenario: Assert "Then the queue :queue should have :count items" counts items
    Given the queue "behat_test" is empty
    And I add 5 items to the "behat_test" queue
    Then the queue "behat_test" should have 5 items

  @queue
  Scenario: Assert "Then the queue :queue should be empty" passes for empty queue
    Given the queue "behat_test" is empty
    Then the queue "behat_test" should be empty

  @queue
  Scenario: Assert "Then the queue :queue should have :count item" works with singular
    Given the queue "behat_test" is empty
    And I add 1 item to the "behat_test" queue
    Then the queue "behat_test" should have 1 item

  @queue
  Scenario: Assert "When I process :count item(s) from the queue :queue" processes the requested number of items
    Given the config "mysite_core.settings" key "queue_budget" has the value "20"
    And the queue "behat_test" is empty
    And I add 7 items to the "behat_test" queue
    When I process 3 items from the queue "behat_test"
    Then the queue "behat_test" should have 4 items
    And the config "mysite_core.settings" key "queue_budget" should have the value "17"

  @queue
  Scenario: Assert "When I process :count item(s) from the queue :queue" works with singular
    Given the config "mysite_core.settings" key "queue_budget" has the value "20"
    And the queue "behat_test" is empty
    And I add 6 items to the "behat_test" queue
    When I process 1 item from the queue "behat_test"
    Then the queue "behat_test" should have 5 items
    And the config "mysite_core.settings" key "queue_budget" should have the value "19"

  @queue
  Scenario: Assert "When I process the queue :queue" processes all items
    Given the config "mysite_core.settings" key "queue_budget" has the value "20"
    And the queue "behat_test" is empty
    And I add 4 items to the "behat_test" queue
    When I process the queue "behat_test"
    Then the queue "behat_test" should be empty
    And the config "mysite_core.settings" key "queue_budget" should have the value "16"

  @trait:Drupal\QueueTrait
  Scenario: Assert negative assertion for "Then the queue :queue should have :count items" works with wrong count
    Given some behat configuration
    And scenario steps tagged with "@queue":
      """
      Given I go to "/"
      Then the queue "behat_test" should have 5 items
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected queue "behat_test" to have 5 items, but it has 0.
      """

  @trait:Drupal\QueueTrait
  Scenario: Assert negative assertion for "Then the queue :queue should be empty" works with non-empty queue
    Given some behat configuration
    And scenario steps tagged with "@queue":
      """
      Given I add 2 items to the "behat_test" queue
      Then the queue "behat_test" should be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected queue "behat_test" to be empty, but it has 2 items.
      """

  @trait:Drupal\QueueTrait
  Scenario: Assert negative assertion for "When I process :count item(s) from the queue :queue" works with too few items
    Given some behat configuration
    And scenario steps tagged with "@queue":
      """
      Given the queue "behat_test" is empty
      And I add 1 item to the "behat_test" queue
      When I process 3 items from the queue "behat_test"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Queue "behat_test" has no more items to process. Processed 1 of 3 requested items.
      """

  @trait:Drupal\QueueTrait
  Scenario: Assert negative "Given the following item is in the queue :queue:" fails for invalid JSON
    Given some behat configuration
    And scenario steps tagged with "@queue":
      """
      Given the following item is in the queue "behat_test":
        | data | {"nid": |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "data" value is not valid JSON: Syntax error.
      """

  @trait:Drupal\QueueTrait
  Scenario: Assert negative "Given the following item is in the queue :queue:" fails for more than one data value
    Given some behat configuration
    And scenario steps tagged with "@queue":
      """
      Given the following item is in the queue "behat_test":
        | data | {"nid":1} | {"nid":2} |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "data" value must be a single JSON string.
      """
