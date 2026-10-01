Feature: Check that the backend order resolves as documented
  As Behat Steps library developer
  I want a scenario to resolve its backend by capability over the suite's ordered list
  So that a step never names a backend and a scenario can still promote one

  Scenario: The suite's list is the order when no tag promotes a backend
    Then the scenario backend order should be "drupal, drush, blackbox"
    And the Cache capability should resolve to the "DrevOps\BehatSteps\Backend\DrupalBackend" backend

  @backend:drush
  Scenario: A scenario tag moves its backend to the front
    Then the scenario backend order should be "drush, drupal, blackbox"
    And the Cache capability should resolve to the "DrevOps\BehatSteps\Backend\DrushBackend" backend

  @backend:blackbox @backend:drush
  Scenario: Repeated tags keep the configured order
    Then the scenario backend order should be "drush, blackbox, drupal"

  @backend:drush @backend:blackbox
  Scenario: Repeated tags give the same order whichever is written first
    Then the scenario backend order should be "drush, blackbox, drupal"

  @backend:blackbox
  Scenario Outline: An example ranks the outline's tags and its table's tags together
    Then the scenario backend order should be "<order>"

    @backend:drush
    Examples:
      | order                   |
      | drush, blackbox, drupal |

    Examples:
      | order                   |
      | blackbox, drupal, drush |

  @backend:blackbox
  Scenario: Resolution falls through a promoted backend that lacks the capability
    Then the scenario backend order should be "blackbox, drupal, drush"
    And the Cache capability should resolve to the "DrevOps\BehatSteps\Backend\DrupalBackend" backend

  @backend:drush
  Scenario: A capability only one backend provides ignores the order
    Then the Drush capability should resolve to the "DrevOps\BehatSteps\Backend\DrushBackend" backend
    And the Core capability should resolve to the "DrevOps\BehatSteps\Backend\DrupalBackend" backend

  Scenario: The config, module and state capabilities resolve in-process by default
    Then the Config capability should resolve to the "DrevOps\BehatSteps\Backend\DrupalBackend" backend
    And the Module capability should resolve to the "DrevOps\BehatSteps\Backend\DrupalBackend" backend
    And the State capability should resolve to the "DrevOps\BehatSteps\Backend\DrupalBackend" backend

  # The config, module and state steps name these capabilities rather than Core,
  # so a scenario tag is enough to run them against a site over Drush.
  @backend:drush
  Scenario: The config, module and state capabilities follow a promoted backend
    Then the Config capability should resolve to the "DrevOps\BehatSteps\Backend\DrushBackend" backend
    And the Module capability should resolve to the "DrevOps\BehatSteps\Backend\DrushBackend" backend
    And the State capability should resolve to the "DrevOps\BehatSteps\Backend\DrushBackend" backend
