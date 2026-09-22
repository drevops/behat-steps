Feature: Check that the driver order resolves as documented
  As Behat Steps library developer
  I want a scenario to resolve its driver by capability over the suite's ordered list
  So that a step never names a driver and a scenario can still promote one

  Scenario: The suite's list is the order when no tag promotes a driver
    Then the scenario driver order should be "drupal, drush, blackbox"
    And the Cache capability should resolve to the "DrevOps\BehatSteps\Driver\DrupalDriver" driver

  @driver:drush
  Scenario: A scenario tag moves its driver to the front
    Then the scenario driver order should be "drush, drupal, blackbox"
    And the Cache capability should resolve to the "DrevOps\BehatSteps\Driver\DrushDriver" driver

  @driver:blackbox @driver:drush
  Scenario: Repeated tags keep the order they were written in
    Then the scenario driver order should be "blackbox, drush, drupal"

  @driver:blackbox
  Scenario: Resolution falls through a promoted driver that lacks the capability
    Then the scenario driver order should be "blackbox, drupal, drush"
    And the Cache capability should resolve to the "DrevOps\BehatSteps\Driver\DrupalDriver" driver

  @driver:drush
  Scenario: A capability only one driver provides ignores the order
    Then the Drush capability should resolve to the "DrevOps\BehatSteps\Driver\DrushDriver" driver
    And the Core capability should resolve to the "DrevOps\BehatSteps\Driver\DrupalDriver" driver
