@driver:drush
Feature: Check that a feature-level driver tag promotes for every scenario
  As Behat Steps library developer
  I want a driver tag on the Feature line to apply to every scenario below it
  So that a whole feature can run against one driver without repeating the tag

  Scenario: A scenario inherits the feature's promotion
    Then the scenario driver order should be "drush, drupal, blackbox"

  @driver:blackbox
  Scenario: A scenario tag is promoted ahead of the feature tag
    Then the scenario driver order should be "blackbox, drush, drupal"
