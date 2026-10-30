---
title: Extending the extension layer
sidebar_label: Extension layer
sidebar_position: 2
description: Replace the HTTP client factory, a registry, an authenticator or option resolution for every context at once from a Behat extension of your own, and give another browser driver a page client.
---

# Extending the extension layer

The extension layer is `src/Extension`: `BehatStepsExtension` and the services it builds into Behat's container, which the context initializer injects into the contexts. You extend it from a Behat extension of your own, for a change that has to reach every context at once. This page covers 3 changes to the container:

- Replacing the HTTP client factory.
- Swapping a registry or an authenticator through its `*.class` parameter.
- Replacing option resolution.

It also covers registering a browser adapter, which a context does.

The container sits outside the 5 surfaces semantic versioning covers, so check [what it does cover](README.md#what-semantic-versioning-covers) before you build on any of these.

## From a Behat extension of your own

Each container change on this page runs in the `process()` method of a Behat extension of your own. Behat calls the `load()` method of every extension before it calls any `process()`, and Behat Steps defines its services in `load()`. So the changes on this page hold whichever order the profile lists the extensions in. Put them in an extension class such as this:

```php
<?php

declare(strict_types=1);

namespace Acme\Behat;

use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AcmeExtension implements ExtensionInterface {

  public function getConfigKey(): string {
    return 'acme';
  }

  public function initialize(ExtensionManager $extension_manager): void {}

  public function configure(ArrayNodeDefinition $builder): void {}

  public function load(ContainerBuilder $container, array $config): void {}

  public function process(ContainerBuilder $container): void {
    // Replace a Behat Steps service here.
  }

}
```

Register it on the profile beside `BehatStepsExtension`:

```php
$profile->withExtension(new Extension(AcmeExtension::class));
```

## The HTTP client factory and browser adapters

[A context override](steps.md#a-context-override-a-flaky-staging-server) decorates the transport for 1 context. The 2 extension points in this section are meant for package authors.

Replace `behat_steps.http_client_factory` from a Behat extension to change every context at once, for example with a tracing client for debugging or a mock client for offline tests. Your class implements `HttpClientFactoryInterface`, and the container builds it with the shared transport and the base URL. Extend `HttpClientFactory` to keep the rules [HTTP clients](../guides/http-clients.md#the-rules-the-clients-follow) describes:

```php
public function process(ContainerBuilder $container): void {
  $container->getDefinition('behat_steps.http_client_factory')
    ->setClass(AcmeHttpClientFactory::class);
}
```

To give another browser driver a page client, register an adapter that implements `HttpClientCapabilityInterface` from your context, such as in its constructor. Each context keeps its own resolver, which tries your adapter ahead of the shipped ones:

```php
$this->getBrowserCapabilityResolver()
  ->registerAdapter(AcmeDriverAdapter::class);
```

[Capabilities of the browser driver](../../../MIGRATION.md#capabilities-of-the-browser-driver) covers writing the adapter itself.

## Swap a registry or an authenticator

The extension builds its 3 registries and 2 authenticators from the classes that `*.class` container parameters name. A Behat extension of your own sets a parameter to put a class of its own in place of each. The shipped classes are final, so yours implements the interface instead. The registry interfaces sit in `DrevOps\BehatSteps\Extension\Registry`, and the authenticator ones in `DrevOps\BehatSteps\Extension\Auth`:

| Parameter | Shipped class | Your class implements | The container passes |
| --- | --- | --- | --- |
| `behat_steps.backend_registry.class` | `BackendRegistry` | `BackendRegistryInterface` | An empty array, followed by 1 `registerBackend()` call for each backend the extension registers |
| `behat_steps.scenario_tag_registry.class` | `ScenarioTagRegistry` | `ScenarioTagRegistryInterface` | Nothing |
| `behat_steps.user_registry.class` | `UserRegistry` | `UserRegistryInterface` | Nothing |
| `behat_steps.authenticator.class` | `Authenticator` | `AuthenticatorInterface`, and optionally `FastLogoutInterface` | `@mink`, `@behat_steps.user_registry`, `@behat_steps.backend_registry`, `@behat_steps.basic_authenticator`, `%mink.parameters%` and `%behat_steps.parameters%` |
| `behat_steps.basic_authenticator.class` | `BasicAuthenticator` | `BasicAuthenticatorInterface` | `@mink` and `%mink.parameters%` |

Set the parameter in `process()`:

```php
public function process(ContainerBuilder $container): void {
  $container->setParameter(
    'behat_steps.user_registry.class',
    AcmeUserRegistry::class,
  );
}
```

Only the class changes: the container builds yours with the arguments in the table, in that order. Everything that receives 1 of these services takes it by its interface, the contexts included, so nothing else needs replacing.

`FastLogoutInterface` changes how `AuthTrait` logs out in the teardown after each scenario. With it, `AuthTrait` calls `fastLogout()`. The shipped `Authenticator::fastLogout()` resets the browser session and logs out of any backend the scenario already reached, so the site runs no logout hook. Without it, `AuthTrait` calls `logout()`, and only while a user is still logged in.

## Replace option resolution

[Trait options](../guides/configuration.md#trait-options) lists the layers a trait option resolves through. The `behat_steps.config.resolver_factory` service builds the resolver every option read goes through. To resolve options your own way, register a `TraitOptionResolverFactoryInterface` of your own under that ID:

```php
public function process(ContainerBuilder $container): void {
  $definition = new Definition(AcmeOptionResolverFactory::class);
  $container->setDefinition('behat_steps.config.resolver_factory', $definition);
}
```

The factory's `create()` takes the context class, the context's `config` argument and the extension's `steps` section. It returns the `TraitOptionResolverInterface` that `getOption()`, `getOptionBool()` and the other typed readers go through. The shipped `TraitOptionResolverFactory` is final, so a factory of your own implements the interface. To keep the container's arguments, set the `behat_steps.config.resolver_factory.class` parameter instead of calling `setDefinition()`. The container then passes `@behat_steps.config.schema_reader`, `@behat_steps.scenario_tag_registry` and `@behat_steps.config.tag_override_resolver`.

Behat builds a context before the initializer injects the service, so the context's constructor still checks its `config` argument through the shipped factory. A `config` value the shipped resolution rejects fails there, whatever factory the service holds.

## Where to next

- [Extending the step vocabulary](steps.md) covers what a single context of yours overrides without a Behat extension.
- [Data flow](../architecture/data-flow.md) shows when the services the extension builds run and how they reach a context.
