---
title: Extending the backend layer
sidebar_label: Backend layer
sidebar_position: 3
description: Add or replace the field handlers and creation aliases the Drupal backends use, name a Core of your own for the in-process backend, and register a backend of your own.
---

# Extending the backend layer

Follow this page when your suite needs the backend layer to do something the package doesn't ship. The backend layer is `src/Backend`, the part of the package that talks to the site. You extend it with classes of your own:

- A field handler covers a field type the package ships no handler for, or replaces a shipped one.
- A creation alias lets your scenarios fill in a column the way a person would.
- A Core of your own serves a site whose modules change what an operation has to do.
- A backend of your own reaches the site in a way none of the shipped backends does, such as over the site's own API.

The `drupal` backend takes the first 3 through `drupal.field_handlers`, `drupal.creation_aliases` and `drupal.core`. The `drush` backend takes aliases through `drush.creation_aliases`. These 4 options sit under the `behat_steps` key. [Configuration reference](../guides/configuration-reference.md#extension-options) lists them with every other option of the extension.

## Field handlers

A step that creates or updates an entity on the `drupal` backend passes each field's value through a field handler. The handler turns the value a scenario writes into the value Drupal stores. A date in the site's timezone becomes UTC, a term name becomes its ID and a file path becomes a managed file. The package ships a handler for each field type it supports. `DefaultHandler` relays any other plain-scalar value as written. When a field whose type has no handler holds an entity reference or a complex value, the step fails until you register a handler for that type.

`drupal.field_handlers` maps a field type to a handler class of your own. An entry for a new field type adds a handler. An entry for a shipped type, such as `datetime`, replaces the shipped handler.

To add the `RatingHandler` class for a `rating` field type:

1. Write a class that implements `FieldHandlerInterface`. Its constructor takes the stub of the entity, the entity type and the field name. `expand()` returns the field's values the way Drupal's entity storage takes them, 1 array of columns per delta:

   ```php
   namespace Acme\Behat\Field;

   use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
   use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

   final class RatingHandler implements FieldHandlerInterface {

     public function __construct(
       EntityStubInterface $stub,
       string $entity_type,
       string $field_name,
     ) {}

     public function expand(mixed $values): array {
       $deltas = [];

       // A keyed array is 1 record of columns, so it makes 1 delta.
       $records = is_array($values) && !array_is_list($values)
         ? [$values]
         : (array) $values;

       // A delta arrives as a bare value or as a record of columns, and
       // "4 stars" stores 4.
       foreach ($records as $record) {
         $rating = is_array($record) ? ($record['value'] ?? '') : $record;
         $deltas[] = ['value' => (int) $rating];
       }

       return $deltas;
     }

   }
   ```

2. Put the class where your suite loads classes from: an `autoload-dev` entry in Composer, or a path in Behat's `autoload` setting such as `tests/behat/bootstrap`. The package builds the `drupal` backend the first time a scenario uses it, after Behat has added those paths.

3. Map the field type to the class under `drupal.field_handlers`:

   ```php
   $profile->withExtension(new Extension(BehatStepsExtension::class, [
     'drupal' => [
       'drupal_root' => 'web',
       'field_handlers' => [
         'rating' => \Acme\Behat\Field\RatingHandler::class,
       ],
     ],
   ]));
   ```

A class that doesn't exist, doesn't implement `FieldHandlerInterface` or is abstract fails each scenario that uses the backend. The `\RuntimeException` names the class.

The package registers each class named in the option through `FieldHandlerCapabilityInterface`, which the `drupal` backend provides. To register a handler from a context of your own, call `registerFieldHandler()` on the same interface:

```php
$this->backendFor(FieldHandlerCapabilityInterface::class)
  ->registerFieldHandler('rating', \Acme\Behat\Field\RatingHandler::class);
```

The registration lasts until the run ends, because the package keeps 1 backend for the whole run.

Semantic versioning covers the option, `FieldHandlerCapabilityInterface` and `FieldHandlerInterface`. A handler can extend a shipped handler such as `DatetimeHandler` and change only what differs. Semantic versioning leaves out the shipped handlers' protected methods, so a minor release can rename 1 of them.

## Creation aliases

A creation alias resolves a column of a creation step that isn't a field of the entity, such as `author` on a node. It runs while the backend creates the entity. The `author` column names a user, and its alias sets the node's `uid` to that user's ID.

The package ships 4 aliases, each registered on the backends that run it:

| Alias | Entity type | Backends | What it does |
| --- | --- | --- | --- |
| `author` | `node` | `drupal` | Resolves a username into the node's `uid`, and fails when no user has that name |
| `vocabulary_machine_name` | `taxonomy_term` | `drupal` | Sets the term's `vid` from a vocabulary machine name, when the step gives no bundle or `vid` |
| `parent` | `taxonomy_term` | `drupal` | Resolves a parent term's name into its ID, within the term's vocabulary |
| `roles` | `user` | `drupal`, `drush` | Assigns roles, by machine name or label, after the user is saved |

`drupal.creation_aliases` and `drush.creation_aliases` list alias classes of your own. Each backend keeps its own list, because an alias that queries Drupal in the Behat process can't run on Drush. The backend registers your classes after the aliases it ships. So an entry with the name and entity type of a shipped alias replaces that alias.

An alias implements 1 of 2 interfaces, and it runs only when the step gives its column:

- `PreCreateAliasInterface`, whose `applyToStub()` changes the stub's values before the entity is saved.
- `PostCreateAliasInterface`, whose `applyAfterCreate()` receives the stub and the saved entity.

Both interfaces extend `CreationAliasInterface`, which declares 3 more methods:

- `getName()` returns the column.
- `getEntityType()` returns the entity type the alias applies to.
- `getDescription()` returns a sentence for anyone listing a backend's aliases through `CreationAliasCapabilityInterface::getCreationAliases()`.

To add the `OrganizationAlias` class, which resolves an `organization` column on a node:

1. Write a class that implements `PreCreateAliasInterface`. In `applyToStub()`, remove the alias's own column after resolving it, so the stub holds only fields when Drupal's entity storage saves it:

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
       return "Resolves an organization code supplied via 'organization' "
         . 'to the organization that carries it.';
     }

     public function applyToStub(EntityStubInterface $stub): void {
       $code = (string) $stub->getValue('organization');

       $ids = \Drupal::entityQuery('node')
         ->accessCheck(FALSE)
         ->condition('type', 'organization')
         ->condition('field_code', $code)
         ->execute();

       if ($ids === []) {
         throw new CreationAliasResolutionException(
           sprintf('No organization has the code "%s".', $code),
         );
       }

       // "ACME" stores the ID of the organization with that code.
       $stub->setValue('field_organization', (int) reset($ids));
       $stub->removeValue('organization');
     }

   }
   ```

2. List the class under `drupal.creation_aliases`:

   ```php
   $profile->withExtension(new Extension(BehatStepsExtension::class, [
     'drupal' => [
       'drupal_root' => 'web',
       'creation_aliases' => [
         \Acme\Behat\Alias\OrganizationAlias::class,
       ],
     ],
   ]));
   ```

3. Name the organization by its code in a scenario:

   ```gherkin
   Given the following article content exists:
     | title      | organization |
     | Lab report | ACME         |
   ```

The package builds each class with no constructor arguments when it builds the backend, so the class can sit in the same places as a [field handler](#field-handlers). A class that doesn't exist, doesn't implement `CreationAliasInterface`, can't be instantiated or requires a constructor argument fails with an `InvalidConfigurationException`. The message names the option and the class. An alias that implements neither `PreCreateAliasInterface` nor `PostCreateAliasInterface` fails with a `\RuntimeException`, because it would never run.

The package registers each class on the backend whose option names it. It does so through `CreationAliasCapabilityInterface`, which the `drupal` and `drush` backends provide. To register an alias whose constructor requires arguments, build it in a context of your own and register it through the same interface. To register `OrganizationLookupAlias`, a class of your own whose constructor requires a lookup service:

```php
$this->backendFor(CreationAliasCapabilityInterface::class)
  ->registerCreationAlias(new OrganizationLookupAlias($lookup));
```

This registration also lasts until the run ends. `backendFor()` returns the first backend in the scenario's order that provides the capability. With `drupal` first in the `backends` list, the alias goes to `drupal`. To reach the Drush backend by name, call `$this->getBackend('drush')`.

Where an alias runs depends on the backend:

- `drupal` runs an alias whenever a step creates an entity of the alias's type. The steps that create nodes, terms, users, files and block content run it, as do the media, paragraph, menu link, ECK and `the following :entity_type entities exist:` steps. The field parser of `drupal` passes the alias's column through unchanged. Any other column that isn't a field fails the step with `The field "..." does not exist on entity type "...".` A `Core` of your own that doesn't implement `CreationAliasCapabilityInterface` keeps no aliases. Registering an alias on the `drupal` backend then fails with an `UnsupportedBackendActionException`.
- `drush` creates only users, so it runs only the aliases for `user`. A pre-create alias runs before `drush user:create`, which takes only the name, the password and the email. A post-create alias runs after it, with an object carrying only the new user's `uid` in place of the entity. Drupal isn't loaded in the Behat process there. So an alias that reaches the site takes the backend in its constructor and is registered from a context. The shipped `roles` alias takes `UserCapabilityInterface` that way.

Semantic versioning covers the options, `CreationAliasCapabilityInterface`, `CreationAliasInterface`, `PreCreateAliasInterface` and `PostCreateAliasInterface`. The shipped aliases are final, so an alias of your own implements 1 of the interfaces.

## Core

The `drupal` backend works through a Core. A Core is the class that runs every in-process operation through Drupal's API, such as creating entities, clearing caches and collecting mail. The package ships a version core for each Drupal major that changes something the base `Core` relies on, named `DrevOps\BehatSteps\Backend\Drupal\Core<N>\Core`. The backend builds the newest version core at or below the major it detects. When no version core applies, it builds the base `Core`.

`drupal.core` names a class of your own in place of the shipped Core, for a site whose modules change what an operation has to do. The class implements `CoreInterface`.

To use a Core of your own, `AcmeCore`:

1. Write a class that extends the Core the package ships for your site's major, here the base `Core`. Your class keeps everything it doesn't override:

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

   The backend builds the class with the Drupal root, the base URL and the random generator, as it builds the package's Core. A Core that needs other collaborators creates them in its constructor.

2. Name the class in `drupal.core`:

   ```php
   $profile->withExtension(new Extension(BehatStepsExtension::class, [
     'drupal' => [
       'drupal_root' => 'web',
       'core' => \Acme\Behat\AcmeCore::class,
     ],
   ]));
   ```

The class can sit in the same places as a [field handler](#field-handlers), because the backend builds its Core when the backend itself is built. A class that doesn't exist, doesn't implement `CoreInterface` or can't be instantiated fails with a `BootstrapException` naming it, when the package builds the `drupal` backend.

A class built on a Core of the package has to extend the Core for the site's major, directly or through classes of your own. Otherwise, building the backend fails with `Core class "..." extends "...", which is not the Core for Drupal N. Extend "..." instead.` This happens when a site moves to a major whose Core is another class. It also happens when a release of the package adds a version core for the major a site already runs. In both cases, change `AcmeCore` to extend the class the message names.

Naming a Core of the package fails the same way when it isn't the Core for the site's major. The base `Core` fails like this on a major with a version core. The message reads `Core class "..." is not the Core for Drupal N. Use "..." instead.` For a suite running on 2 majors whose Cores differ, pick the class per environment in `behat.php`. A class that implements `CoreInterface` without extending a Core of the package skips this check.

Semantic versioning covers the option, and leaves out `CoreInterface` and the Core classes. A minor release can add a method to the interface, or rename a protected method that `AcmeCore` overrides. A new interface method breaks a class built from scratch, and leaves a class that extends a Core of the package working. To change how a field's value is stored, register a [field handler](#field-handlers) instead.

## A backend of your own

A step asks for the capability it needs, and the first backend in the scenario's order that provides it runs the step. So a shipped step runs on your backend when your backend implements the capability and comes first among those. [Backend resolution](../guides/configuration.md#backend-resolution) describes that order.

Every backend implements `BackendInterface`, which declares 3 methods:

- `getRandom()` returns the `RandomGeneratorInterface` that a context's `getRandom()` returns when your backend comes first in the scenario's order.
- `bootstrap()` bootstraps the backend.
- `isBootstrapped()` reports whether `bootstrap()` has run.

The registry calls `bootstrap()` the first time a scenario resolves the backend, unless `isBootstrapped()` already returns `TRUE`. It bootstraps only the backends it resolves.

Everything else a backend does sits on a capability interface named `<Name>CapabilityInterface`. The following table lists them by namespace:

| Namespace | Provided by | Capabilities |
| --- | --- | --- |
| `DrevOps\BehatSteps\Backend\Capability` | Any backend | 11: `Authentication`, `Cache`, `Content`, `CreationAlias`, `Cron`, `EntityLookup`, `Language`, `Mail`, `RequestEnvironment`, `Role` and `User` |
| `DrevOps\BehatSteps\Backend\Drupal\Capability` | A backend with a Drupal site behind it | 24: `Batch`, `Block`, `Config`, `ConfigEntity`, `Core`, `Draggableviews`, `Drush`, `EntityDefinition`, `FieldHandler`, `File`, `Log`, `MenuLink`, `Module`, `Node`, `PathAlias`, `Queue`, `QueueItem`, `Redirect`, `SearchApi`, `SearchIndex`, `State`, `Term`, `UserActivity` and `Workflow` |

No step resolves `CoreCapabilityInterface`, which returns the in-process backend's Core.

A step that resolves a capability can call any of its methods. So declare a capability only when your backend serves all of it, as the shipped backends do. Follow 2 more rules of the contract:

- A create returns the stub it was given.
- A delete does nothing when its target doesn't exist, so teardown can delete whatever a scenario created, with no check first.

[What a trait needs from the backend](../../../CONTRIBUTING.md#what-a-trait-needs-from-the-backend) covers the rest of that contract, including a backend outside the test process.

`BlackboxBackend` shows the minimum. It returns the shipped `RandomGenerator`, has nothing to bootstrap and reports itself bootstrapped. It implements `BackendInterface` through `BlackboxBackendInterface`, which promises no capability at all. So a backend with capabilities implements `BackendInterface` itself and doesn't extend `BlackboxBackend`. A backend can extend `DrupalBackend` or `DrushBackend`. Such a subclass relies on protected methods that a minor release can rename or stop calling.

To add the `AcmeJsonApiBackend` class, which runs cron through an API client of the project's own:

1. Write the backend class:

   ```php
   namespace Acme\Behat\Backend;

   use DrevOps\BehatSteps\Backend\BackendInterface;
   use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
   use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
   use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;

   final class AcmeJsonApiBackend implements
     BackendInterface, CronCapabilityInterface {

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

2. Register the backend from the `load()` method of a Behat extension of your own, and import `Symfony\Component\DependencyInjection\Definition` there. Tag its service `behat_steps.backend`, with the name it answers to as the `alias`:

   ```php
   public function load(ContainerBuilder $container, array $config): void {
     $definition = new Definition(AcmeJsonApiBackend::class, ['%mink.base_url%']);
     $definition->addTag('behat_steps.backend', ['alias' => 'acme-jsonapi']);
     $container->setDefinition('acme.backend.jsonapi', $definition);
   }
   ```

   To define the service in a services file that `load()` reads, give it the same tag, as the shipped [`blackbox.yml`](../../../src/Extension/ServiceContainer/config/backends/blackbox.yml) does. [From a Behat extension of your own](extension.md#from-a-behat-extension-of-your-own) shows the extension class and how a profile registers it.

3. Name the backend in the `backends` list, which is both the allow-list and the precedence order:

   ```php
   $profile->withExtension(new Extension(BehatStepsExtension::class, [
     'drupal' => ['drupal_root' => 'web'],
     'backends' => ['drupal', 'acme-jsonapi', 'blackbox'],
   ]));
   ```

   With that list, `When cron runs` still runs on `drupal`, which comes first and provides cron as well. A scenario tagged `@backend:acme-jsonapi` runs it on your backend.

Behat Steps collects the tagged services in its own `process()`. It registers each one on the backend registry under its alias, lowercased. Behat calls every `load()` before any `process()`, so a backend defined in `load()` is registered whatever order the profile lists the extensions in. A backend defined in a `process()` that runs after Behat Steps' own stays unregistered.

`BehatStepsExtension` builds its backend providers in its own constructor, so `BackendProviderInterface` can't add yours. The `behat_steps` key has no place for your backend's settings either. Take them under your extension's own config key, and pass them to the backend's constructor.

A bare entry in the `backends` list names a backend by its alias. So an alias you list bare holds only letters, digits, `_` and `-`, which also keeps `@backend:acme-jsonapi` a valid tag. A keyed entry such as `'api' => 'acme-jsonapi'` gives the backend a name of its own for `@backend:` tags and `getBackend()`. A list that names an alias no service carries fails the container build with `The "backends" list under "behat_steps" names the backend "acme-jsonapi", which is not registered.` The message goes on to list the registered names. Without a `backends` list, a scenario gets every registered backend in registration order, so list them to choose the order.

[Service container](../../../MIGRATION.md#service-container) maps the 3.x service names and the `drupal.driver` tag to their 4.0 names.

Semantic versioning covers the `backends` option, and the `backendFor()` and `getBackend()` helpers that reach a backend. It leaves out the `behat_steps.backend` tag. Only 3 capability interfaces appear in a helper's signature: `UserCapabilityInterface`, `EntityDefinitionCapabilityInterface` and `DrushCapabilityInterface`. Every other capability interface apart from `FieldHandlerCapabilityInterface` and `CreationAliasCapabilityInterface` sits on none of the 5 surfaces that [Public API and versioning](../architecture/versioning.md) lists. So a minor release can add a method to any of those other interfaces, and your backend then has to implement it.

## Where to next

- A step of your own that creates an entity goes through the same handlers and aliases, and [Extending the step vocabulary](steps.md#writing-a-step-of-your-own) covers writing one.
- [Class inheritance](../architecture/class-inheritance.md) shows the backends, their capabilities and the Core in its diagram.
