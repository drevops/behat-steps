# Migration guide

## Optional dependencies moved to `require-dev` and `suggest`

Trait-specific packages are no longer hard `require` dependencies. They now live in `require-dev` (so this library's own test suite still runs) and `suggest`, matching the existing treatment of `justinrainbow/json-schema`. Projects that relied on transitive installation must add the packages they use to their own `composer.json`.

| Package | Add it to your `require-dev` when you use | Example |
| --- | --- | --- |
| `drupal/drupal-extension` | any Drupal trait (`DrevOps\BehatSteps\Drupal\*`) | all Drupal steps |
| `lullabot/mink-selenium2-driver` | `@javascript` scenarios driven by a Selenium/WebDriver server | `JavascriptTrait`, `KeyboardTrait`, `ConfigOverrideTrait` |
| `dmore/behat-chrome-extension` | `@javascript` scenarios driven by headless Chrome over the DevTools Protocol (no Selenium) | selenium-less profile |
| `softcreatr/jsonpath` | `JsonTrait` JSON path assertions | `Then the JSON path :path should exist` |

`behat/behat` and `behat/mink` remain hard `require` dependencies. For example, a project that uses the Drupal traits and runs JavaScript scenarios with Selenium adds:

```bash
composer require --dev drupal/drupal-extension lullabot/mink-selenium2-driver
```

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
