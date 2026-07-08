# Migration guide

## Unified entity cleanup

Traits that create Drupal entities now register them in a single shared registry and delete them in reverse creation order through one `entityCleanupAfterScenario` hook, instead of each trait running its own after-scenario cleanup.

The per-trait cleanup skip tags have been removed. Replace them as follows:

| Removed tag                                    | Replacement                                                                                          |
| ---------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `@behat-steps-skip:mediaAfterScenario`         | `@behat-steps-entity-cleanup-skip:media`                                                             |
| `@behat-steps-skip:contentBlockAfterScenario`  | `@behat-steps-entity-cleanup-skip:block_content`                                                     |
| `@behat-steps-skip:paragraphsAfterScenario`    | `@behat-steps-entity-cleanup-skip:paragraph`                                                         |
| `@behat-steps-skip:eckAfterScenario`           | `@behat-steps-entity-cleanup-skip:ENTITY_TYPE_ID`                                                    |
| `@behat-steps-skip:menuAfterScenario`          | `@behat-steps-entity-cleanup-skip:menu` and/or `@behat-steps-entity-cleanup-skip:menu_link_content`  |
| `@behat-steps-skip:redirectAfterScenario`      | `@behat-steps-entity-cleanup-skip:redirect`                                                          |
| `@behat-steps-skip:blockAfterScenario`         | `@behat-steps-entity-cleanup-skip:block`                                                             |
| `@behat-steps-skip:webformAfterScenario`       | `@behat-steps-entity-cleanup-skip:webform`                                                           |

To skip cleanup of every registered entity at once, use `@behat-steps-skip:entityCleanupAfterScenario`.

`FileTrait` keeps its own `@behat-steps-skip:fileAfterScenario` tag, which now covers only unmanaged files; managed file entities it creates are cleaned up by the shared registry and can be kept with `@behat-steps-entity-cleanup-skip:file`.
