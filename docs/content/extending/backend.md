---
title: Extending the backend layer
sidebar_label: Backend layer
sidebar_position: 3
description: Add or replace the field handlers and creation aliases the Drupal backends use, name a Core of your own for the in-process backend, and register a backend of your own.
---

<!-- cspell:ignore autoloadable -->

# Extending the backend layer

The backend layer is `src/Backend`, the part that talks to the site. You extend it with classes of your own, which the `drupal` and `drush` backends take through options under the `behat_steps` key. A field handler covers a field type the package ships no handler for, or replaces a shipped one. A creation alias lets your scenarios fill in a column the way a person would, and a Core of your own serves a site whose modules change what an operation has to do. When none of the shipped backends reaches the site the way your suite needs, a backend of your own can, registered from a Behat extension of yours.

[Configuration](../guides/configuration.md#2-the-behat_steps-extension-key) lists the 4 options, `drupal.field_handlers`, `drupal.creation_aliases`, `drush.creation_aliases` and `drupal.core`, with every other option of the extension.

## Field handlers

A step that creates or updates an entity on the `drupal` backend passes each field's value through a field handler, which turns the value a scenario writes into the value Drupal stores: a date in the site's timezone into UTC, a term name into its ID, or a file path into a managed file. The package ships a handler for each field type it supports, and `DefaultHandler` relays any other plain-scalar value as written.

`drupal.field_handlers` maps a field type to a handler class of your own. An entry for a field type the package ships no handler for adds one, and an entry for a shipped type replaces the shipped handler:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'drupal' => [
    'drupal_root' => 'web',
    'field_handlers' => [
      'rating' => RatingHandler::class,
      'datetime' => FiscalDatetimeHandler::class,
    ],
  ],
]));
```

A handler implements `FieldHandlerInterface`. Its constructor takes the stub of the entity, the entity type and the field name, and `expand()` returns the field's values the way Drupal's entity storage takes them, 1 array of columns per delta:

```php
namespace Acme\Behat\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

final class RatingHandler implements FieldHandlerInterface {

  public function __construct(EntityStubInterface $stub, string $entity_type, string $field_name) {}

  public function expand(mixed $values): array {
    $deltas = [];

    // A delta arrives as a bare value or as a record of columns, and
    // "4 stars" stores 4.
    foreach ((array) $values as $value) {
      $rating = is_array($value) ? ($value['value'] ?? '') : $value;
      $deltas[] = ['value' => (int) $rating];
    }

    return $deltas;
  }

}
```

The class has to be autoloadable through Composer, for example from an `autoload-dev` entry. The package builds the `drupal` backend before Behat registers the paths in its `autoload` setting, so a class only those paths can load isn't found. A class that doesn't exist, doesn't implement `FieldHandlerInterface` or is abstract fails with a `\RuntimeException` naming it when the package builds the `drupal` backend.

The option registers each class through `FieldHandlerCapabilityInterface`, which the `drupal` backend provides. A context of your own registers a handler the same way, and the registration lasts for the rest of the run, because the backend does:

```php
$this->backendFor(FieldHandlerCapabilityInterface::class)->registerFieldHandler('rating', RatingHandler::class);
```

The option, `FieldHandlerCapabilityInterface` and `FieldHandlerInterface` are covered by semantic versioning. A handler can extend a shipped one, such as `DatetimeHandler`, and change only what differs, but the shipped handlers' protected methods aren't covered, so a minor release can rename 1 of them.

## Creation aliases

A creation step can take a column that isn't a field of the entity, such as `author` on a node, and a creation alias resolves it while the entity is created: `author` names a user, and the alias sets the node's `uid` to that user's ID. Your scenarios name things the way a person would, and the backend works out the IDs.

The package ships 4, each registered on the backends that run it:

| Alias | Entity type | Backends | What it does |
| --- | --- | --- | --- |
| `author` | `node` | `drupal` | Resolves a username into the node's `uid`, and fails when no user has that name |
| `vocabulary_machine_name` | `taxonomy_term` | `drupal` | Sets the term's `vid` from a vocabulary machine name, when the step gives no bundle or `vid` |
| `parent` | `taxonomy_term` | `drupal` | Resolves a parent term's name into its ID, within the term's vocabulary |
| `roles` | `user` | `drupal`, `drush` | Assigns roles, by machine name or label, after the user is saved |

`drupal.creation_aliases` and `drush.creation_aliases` list alias classes of your own. There's 1 list per backend because each backend keeps its own aliases: an alias that queries Drupal in the Behat process can't run on Drush. The backend registers each class after the aliases it ships, so an entry with the name and entity type of a shipped alias replaces it:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'drupal' => [
    'drupal_root' => 'web',
    'creation_aliases' => [
      OrganizationAlias::class,
    ],
  ],
]));
```

An alias implements `PreCreateAliasInterface`, which changes the stub's values before the entity is saved, or `PostCreateAliasInterface`, which acts on the entity after it's saved. It runs only when the step gives its column. `getName()` returns the column, `getEntityType()` the entity type it applies to, and `getDescription()` a sentence for anyone listing a backend's aliases through `CreationAliasCapabilityInterface::getCreationAliases()`. A pre-create alias removes its own column once it has resolved it, so Drupal's entity storage sees fields only:

```php
namespace Acme\Behat\Alias;

use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException;

final class OrganizationAlias implements PreCreateAliasInterface {

  public function getName(): string {
    return 'organization';
  }

  public function getEntityType(): string {
    return 'node';
  }

  public function getDescription(): string {
    return "Resolves an organization code supplied via 'organization' to the organization that carries it.";
  }

  public function applyToStub(EntityStubInterface $stub): void {
    $code = (string) $stub->getValue('organization');

    $ids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'organization')
      ->condition('field_code', $code)
      ->execute();

    if ($ids === []) {
      throw new CreationAliasResolutionException(sprintf('No organization has the code "%s".', $code));
    }

    // "ACME" stores the ID of the organization with that code.
    $stub->setValue('field_organization', (int) reset($ids));
    $stub->removeValue('organization');
  }

}
```

With it registered, a scenario names the organization by its code:

```gherkin
Given the following article content exists:
  | title      | organization |
  | Lab report | ACME         |
```

The package builds each class with no constructor arguments when it builds the backend, so the class has to be autoloadable through Composer, for the same reason a [field handler](#field-handlers) does. A class that doesn't exist, doesn't implement `CreationAliasInterface`, can't be instantiated or requires a constructor argument fails with an `InvalidConfigurationException` naming the option and the class. An alias that implements neither `PreCreateAliasInterface` nor `PostCreateAliasInterface` fails with a `\RuntimeException`, because it would never run.

The options register each class through `CreationAliasCapabilityInterface`, which both Drupal backends provide. A context of your own builds an alias whose constructor takes arguments, such as a lookup service, and registers it the same way. The registration lasts for the rest of the run, because the backend does:

```php
$this->backendFor(CreationAliasCapabilityInterface::class)->registerCreationAlias(new OrganizationAlias($lookup));
```

`backendFor()` returns the first backend in the scenario's order that provides the capability, so with `drupal` listed first, that's where the alias lands. `$this->getBackend('drush')` reaches the Drush backend by name instead.

Where an alias runs depends on the backend:

- `drupal` runs an alias whenever a step creates an entity of its type: a node, a term, a user, a file, block content, or an entity the media, paragraph, menu link, ECK and `the following :entity_type entities exist:` steps create. Its field parser leaves the alias's column as written, while any other column that isn't a field fails the step with `The field "..." does not exist on entity type "...".` A `Core` of your own that doesn't implement `CreationAliasCapabilityInterface` keeps no aliases, so registering an alias on the `drupal` backend fails with an `UnsupportedBackendActionException`.
- `drush` creates only users, so it runs only the aliases for `user`. A pre-create alias runs before `drush user:create`, which takes only the name, the password and the email. A post-create alias runs after it, with an object carrying only the new user's `uid` in place of the entity. Drupal isn't loaded in the Behat process there, so an alias that reaches the site takes the backend in its constructor, the way the shipped `roles` alias takes `UserCapabilityInterface`, and is registered from a context.

The options, `CreationAliasCapabilityInterface`, `CreationAliasInterface`, `PreCreateAliasInterface` and `PostCreateAliasInterface` are covered by semantic versioning. The shipped aliases are final, so an alias of your own implements an interface rather than extending 1 of them.

## Core

The `drupal` backend works through a Core, the class that creates entities, clears caches, collects mail and runs every other in-process operation through Drupal's API. The base `Core` serves every supported Drupal major that has no version core of its own. A major that changes something the base relies on gets a version core of its own, such as `DrevOps\BehatSteps\Backend\Drupal\Core13\Core`. The backend builds the newest version core at or below the major it detects, or the base `Core` when none applies.

`drupal.core` names a class of your own instead, for a site whose modules change what an operation has to do:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'drupal' => [
    'drupal_root' => 'web',
    'core' => AcmeCore::class,
  ],
]));
```

The class implements `CoreInterface` and is built with the Drupal root, the base URL and the random generator, as the package's Core is. The backend builds it, so a Core that needs other collaborators creates them in its constructor. Extending the Core the package ships for your site's major keeps everything you don't override:

```php
namespace Acme\Behat;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;

class AcmeCore extends Core {

  public function cacheClear(): void {
    parent::cacheClear();
    // Purge the site's reverse proxy as well.
  }

}
```

The class has to be autoloadable through Composer, for example from an `autoload-dev` entry, for the same reason a [field handler](#field-handlers) does. A class that doesn't exist, doesn't implement `CoreInterface` or can't be instantiated fails with a `BootstrapException` naming it when the package builds the `drupal` backend.

When a class builds on a Core of the package, the nearest one among its parents has to be the Core for the site's major. Otherwise, building the backend fails with `Core class "..." extends "...", which is not the Core for Drupal N. Extend "..." instead.` That happens when a site moves to a major whose Core is another class, and when a release of the package adds a version core for the major a site already runs. In both cases, change `AcmeCore` to extend the class the message names.

Naming a Core of the package itself, such as the base `Core` on a major with a version core, fails the same way with `Core class "..." is not the Core for Drupal N. Use "..." instead.` A suite running on 2 majors whose Cores differ picks its class per environment in `behat.php`. A class implementing `CoreInterface` without extending a Core of the package is built as it is.

The option is covered by semantic versioning. `CoreInterface` and the Core classes aren't: a minor release can add a method to the interface or rename a protected method `AcmeCore` overrides. A new interface method breaks a class built from scratch, but not one extending a Core of the package. To change how a field's value is stored, register a [field handler](#field-handlers) rather than a Core of your own.

## A backend of your own

A backend of your own reaches the site in a way none of the shipped ones does, such as over the site's own API. A step names the capability it needs and never a backend, so a shipped step runs on your backend once it implements that capability and comes first among the backends in the scenario's order that do. [Backend resolution](../guides/configuration.md#backend-resolution) describes that order.

Every backend implements `BackendInterface`, which declares 3 methods. `getRandom()` returns the `RandomGeneratorInterface` that a context's `getRandom()` hands out when your backend comes first in the order. `bootstrap()` bootstraps the backend, and `isBootstrapped()` reports whether that has happened. The registry calls `bootstrap()` the first time a scenario resolves the backend, unless `isBootstrapped()` already returns `TRUE`, and it bootstraps only the backend it resolves.

Everything else a backend does sits on a capability interface, named `<Name>CapabilityInterface`. The 11 that any backend may provide sit in `DrevOps\BehatSteps\Backend\Capability`: `Authentication`, `Cache`, `Content`, `CreationAlias`, `Cron`, `EntityLookup`, `Language`, `Mail`, `RequestEnvironment`, `Role` and `User`. The 24 that need a Drupal site behind the backend sit in `DrevOps\BehatSteps\Backend\Drupal\Capability`: `Batch`, `Block`, `Config`, `ConfigEntity`, `Core`, `Draggableviews`, `Drush`, `EntityDefinition`, `FieldHandler`, `File`, `Log`, `MenuLink`, `Module`, `Node`, `PathAlias`, `Queue`, `QueueItem`, `Redirect`, `SearchApi`, `SearchIndex`, `State`, `Term`, `UserActivity` and `Workflow`. No step resolves `CoreCapabilityInterface`, which hands out the in-process backend's Core.

A step that resolves a capability can call any of its methods, so declare a capability only when your backend serves all of it, as the shipped backends do. A create returns the stub it was given, and a delete does nothing when its target doesn't exist, so teardown deletes whatever a scenario created without checking first. [What a trait needs from the backend](../../../CONTRIBUTING.md#what-a-trait-needs-from-the-backend) covers the rest of that contract, a backend outside the test process included.

`BlackboxBackend` shows the minimum: it returns the shipped `RandomGenerator`, has nothing to bootstrap and reports itself bootstrapped. It implements `BackendInterface` through `BlackboxBackendInterface`, which promises no capability at all, so a backend with capabilities implements `BackendInterface` itself instead of extending `BlackboxBackend`. A backend can extend `DrupalBackend` or `DrushBackend`, but a subclass relies on protected methods that a minor release can rename or stop calling.

This backend runs cron through an API client of the project's own:

```php
namespace Acme\Behat\Backend;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;

final class AcmeJsonApiBackend implements BackendInterface, CronCapabilityInterface {

  protected RandomGeneratorInterface $random;

  protected ?AcmeJsonApiClient $client = NULL;

  public function __construct(protected string $baseUrl) {
    $this->random = new RandomGenerator();
  }

  public function getRandom(): RandomGeneratorInterface {
    return $this->random;
  }

  public function bootstrap(): void {
    $this->client = new AcmeJsonApiClient($this->baseUrl);
  }

  public function isBootstrapped(): bool {
    return $this->client instanceof AcmeJsonApiClient;
  }

  public function cronRun(): bool {
    return $this->client->runCron();
  }

}
```

Register the backend from the `load()` method of a Behat extension of your own, by tagging its service `behat_steps.backend` with the name it answers to as the `alias`:

```php
public function load(ContainerBuilder $container, array $config): void {
  $definition = new Definition(AcmeJsonApiBackend::class, ['%mink.base_url%']);
  $definition->addTag('behat_steps.backend', ['alias' => 'acme-jsonapi']);
  $container->setDefinition('acme.backend.jsonapi', $definition);
}
```

Behat Steps collects the tagged services in its own `process()` and registers each one on the backend registry under its alias, lowercased. Behat calls every `load()` before any `process()`, so a backend defined in `load()` is registered whichever order the profile lists the extensions in. A backend defined in a `process()` that runs after Behat Steps' own isn't registered. A services file that `load()` reads carries the same tag, as the shipped [`blackbox.yml`](../../../src/Extension/ServiceContainer/config/backends/blackbox.yml) does. [From a Behat extension of your own](extension.md#from-a-behat-extension-of-your-own) shows the extension class and how a profile registers it.

`BehatStepsExtension` builds its backend providers in its own constructor, so `BackendProviderInterface` can't add yours, and the `behat_steps` key has no place for your backend's settings. Your extension takes them under its own config key and passes them to the backend's constructor.

The `backends` list is both the allow-list and the precedence order, so name the backend there:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'drupal' => ['drupal_root' => 'web'],
  'backends' => ['drupal', 'acme-jsonapi', 'blackbox'],
]));
```

With that list, `When cron runs` still runs on `drupal`, which comes first and provides cron as well, while a scenario tagged `@backend:acme-jsonapi` runs it on your backend.

A bare entry names a backend by its alias, so that alias holds only letters, digits, `_` and `-`, which also makes `@backend:acme-jsonapi` a valid tag. A keyed entry such as `'api' => 'acme-jsonapi'` gives the backend a name of its own for `@backend:` tags and `getBackend()`. A list that names an alias no service carries fails the container build with `The "backends" list under "behat_steps" names the backend "acme-jsonapi", which is not registered.`, followed by the names that are. Without a `backends` list, a scenario gets every registered backend in the order they were registered, so list them to choose the order.

[Service container](../../../MIGRATION.md#service-container) maps the 3.x service names and the `drupal.driver` tag to their 4.0 names.

Semantic versioning covers the `backends` option, and the `backendFor()` and `getBackend()` helpers that reach a backend. It doesn't cover the `behat_steps.backend` tag. Only 3 capability interfaces appear in a helper's signature: `UserCapabilityInterface`, `EntityDefinitionCapabilityInterface` and `DrushCapabilityInterface`. The rest sit on none of the 5 surfaces [Public API and versioning](../versioning.md) lists, so a minor release can add a method to 1 of them that your backend then has to implement.

## Where to next

- A step of your own that creates an entity goes through the same handlers and aliases, and [Extending the step vocabulary](steps.md#writing-a-step-of-your-own) covers writing one.
- [Architecture](../architecture/README.md#the-backend-layer) walks through the backends, their capabilities and the Core.
