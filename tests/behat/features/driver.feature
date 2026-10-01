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
  Scenario: Repeated tags keep the configured order
    Then the scenario driver order should be "drush, blackbox, drupal"

  @driver:drush @driver:blackbox
  Scenario: Repeated tags give the same order whichever is written first
    Then the scenario driver order should be "drush, blackbox, drupal"

  @driver:blackbox
  Scenario Outline: An example ranks the outline's tags and its table's tags together
    Then the scenario driver order should be "<order>"

    @driver:drush
    Examples:
      | order                   |
      | drush, blackbox, drupal |

    Examples:
      | order                   |
      | blackbox, drupal, drush |

  @driver:blackbox
  Scenario: Resolution falls through a promoted driver that lacks the capability
    Then the scenario driver order should be "blackbox, drupal, drush"
    And the Cache capability should resolve to the "DrevOps\BehatSteps\Driver\DrupalDriver" driver

  @driver:drush
  Scenario: A capability only one driver provides ignores the order
    Then the Drush capability should resolve to the "DrevOps\BehatSteps\Driver\DrushDriver" driver
    And the Core capability should resolve to the "DrevOps\BehatSteps\Driver\DrupalDriver" driver

  Scenario: The config, module and state capabilities resolve in-process by default
    Then the Config capability should resolve to the "DrevOps\BehatSteps\Driver\DrupalDriver" driver
    And the Module capability should resolve to the "DrevOps\BehatSteps\Driver\DrupalDriver" driver
    And the State capability should resolve to the "DrevOps\BehatSteps\Driver\DrupalDriver" driver

  # The config, module and state steps name these capabilities rather than Core,
  # so a scenario tag is enough to run them against a site over Drush.
  @driver:drush
  Scenario: The config, module and state capabilities follow a promoted driver
    Then the Config capability should resolve to the "DrevOps\BehatSteps\Driver\DrushDriver" driver
    And the Module capability should resolve to the "DrevOps\BehatSteps\Driver\DrushDriver" driver
    And the State capability should resolve to the "DrevOps\BehatSteps\Driver\DrushDriver" driver
