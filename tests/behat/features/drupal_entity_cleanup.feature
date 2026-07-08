Feature: Check that automatic entity cleanup works
  As a Behat Steps library developer
  I want entities created during a scenario to be tracked and removed at teardown
  So that a test database stays clean across long suites without manual cleanup

  # Entities created through the library's creation steps are registered in a
  # shared registry and deleted in reverse creation order at scenario teardown.
  # These paired scenarios rely on Behat running scenarios in file order: the
  # first scenario of each pair creates an entity, the second asserts what the
  # teardown of the first scenario did with it.

  @api
  Scenario: Registered entities exist during the scenario that creates them
    Given the following redirects exist:
      | from                 | to          |
      | /entity-cleanup-auto | /user/login |
    Then the following redirects should exist:
      | from                 |
      | /entity-cleanup-auto |

  @api
  Scenario: Registered entities are deleted at teardown of the creating scenario
    Then the following redirects should not exist:
      | /entity-cleanup-auto |

  @api @behat-steps-skip:entityCleanupAfterScenario
  Scenario: The cleanup skip tag keeps all registered entities
    Given the following redirects exist:
      | from                     | to          |
      | /entity-cleanup-kept-all | /user/login |
    Then the following redirects should exist:
      | from                     |
      | /entity-cleanup-kept-all |

  @api
  Scenario: An entity kept by the skip tag survives teardown and is removed manually
    Then the following redirects should exist:
      | from                     |
      | /entity-cleanup-kept-all |
    Given the following redirects do not exist:
      | /entity-cleanup-kept-all |

  @api @behat-steps-entity-cleanup-skip:redirect
  Scenario: The per-type skip tag keeps entities of the named type
    Given the following redirects exist:
      | from                      | to          |
      | /entity-cleanup-kept-type | /user/login |
    Then the following redirects should exist:
      | from                      |
      | /entity-cleanup-kept-type |

  @api
  Scenario: An entity kept by the per-type skip tag survives teardown and is removed manually
    Then the following redirects should exist:
      | from                      |
      | /entity-cleanup-kept-type |
    Given the following redirects do not exist:
      | /entity-cleanup-kept-type |
