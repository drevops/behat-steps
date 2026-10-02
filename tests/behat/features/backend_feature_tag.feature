@backend:drush
Feature: Check that a feature-level backend tag promotes for every scenario
  As Behat Steps library developer
  I want a backend tag on the Feature line to apply to every scenario below it
  So that a whole feature can run against one backend without repeating the tag

  Scenario: A scenario inherits the feature's promotion
    Then the scenario backend order should be "drush, drupal, blackbox"

  @backend:blackbox
  Scenario: A scenario tag is promoted ahead of the feature tag
    Then the scenario backend order should be "blackbox, drush, drupal"

  @backend:blackbox @backend:drupal
  Scenario: Repeated scenario tags keep the configured order ahead of the feature tag
    Then the scenario backend order should be "drupal, blackbox, drush"
