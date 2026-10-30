# 📊 Field handler truth table

This page is for contributors who change how the Drupal backend turns a scenario's field values into Drupal's storage shape. It lists every kind of field the backend meets and what the backend does with each. Every legal combination of origin, storage profile and writability has its own row, with an ID that code and tests cite.

## Axes

The primary table sorts fields on the first 3 of these columns, and the fourth gives the result for each combination:

- **Origin** - where the field definition is declared.
- **Storage profile** - how the value is stored, if at all.
- **Writable?** - whether a value assigned to a stub has an observable effect at save.
- **Resolution** - what the backend's expansion pipeline does with the field.

Cardinality (1 value or several) and value shape (scalar, compound or entity reference) don't decide whether a field enters the pipeline. Every handler accepts any cardinality, as [Cardinality](#cardinality) describes. Value shape decides only whether `DefaultHandler` may take a field with no handler of its own, as [DefaultHandler classification policy](#defaulthandler-classification-policy) describes.

## Primary table: which fields enter the pipeline

The following table gives the resolution for each combination:

| ID | Origin                                                          | Storage profile | Writable?              | Example field                                                                        | Resolution         | Notes                                                                                                                                                                                                                                                                                                             |
|----|-----------------------------------------------------------------|-----------------|------------------------|--------------------------------------------------------------------------------------|--------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| F1 | `baseFieldDefinitions()`                                        | standard        | yes                    | `node.title`, `node.uid`, `node.status`, `commerce_product.variations`, `user.roles` | Expand via handler | Identified by `fieldIsBaseStandard()`. Iterated by `getEntityFieldTypes()` and routed through `getFieldHandler()`, which picks the handler by field type (see the sub-table). `DefaultHandler` relays plain-scalar fields. Before that fallback, `Core` throws on an unhandled reference or complex field, which the shape classifier detects. |
| F2 | `baseFieldDefinitions()`                                        | computed        | read-only              | None listed (a pure derived accessor)                                                | Skip entirely      | Identified by `fieldIsBaseComputedReadOnly()`. Rejected by `getEntityFieldTypes()`. A stub value, if set, reaches the entity constructor untouched, and Drupal discards it on save because the field has no storage. |
| F3 | `baseFieldDefinitions()`                                        | computed        | writable (side-effect) | `node.moderation_state`                                                              | Skip entirely      | Identified by `fieldIsBaseComputedWritable()`. Rejected by `getEntityFieldTypes()`. The stub value reaches `Node::create($stub->getValues())` untouched. The field's item list captures the raw value and acts on it at save: `moderation_state`, for example, writes a `ContentModerationState` revision. |
| F4 | `baseFieldDefinitions()`                                        | custom storage  | yes                    | None listed                                                                          | Skip entirely      | Identified by `fieldIsBaseCustomStorage()`. Rejected by `getEntityFieldTypes()`. The stub value reaches the entity constructor untouched, and the declaring module's custom storage handles it from there. |
| F5 | `FieldStorageConfig` + `FieldConfig`                            | standard        | yes                    | `node.field_tags`, `node.body`, `node.field_image`                                   | Expand via handler | Identified by `fieldIsConfigurable()`. Iterated by `getEntityFieldTypes()` through `getFieldStorageDefinitions()` and routed through `getFieldHandler()`. The common configurable-field path, with the handler picked by field type (see the sub-table). |
| F6 | `bundleFieldDefinitions()` alone (no storage-info sibling)      | computed        | read-only              | None listed                                                                          | Skip entirely      | Identified by `fieldIsBundleComputedReadOnly()`. Absent from `getFieldStorageDefinitions()` and `getBaseFieldDefinitions()`. No expand predicate matches it, so `getEntityFieldTypes()` rejects it. A stub value set on the field reaches the entity constructor, and Drupal discards it. |
| F7 | `bundleFieldDefinitions()` alone                                | computed        | writable (side-effect) | None listed                                                                          | Skip entirely      | Identified by `fieldIsBundleComputedWritable()`. Absent from `getFieldStorageDefinitions()` and `getBaseFieldDefinitions()`. No expand predicate matches it, so `getEntityFieldTypes()` rejects it. The stub value reaches the entity constructor untouched, and the bundle-scoped field class captures it at save. The bundle-scoped form of F3. |
| F8 | `bundleFieldDefinitions()` alone                                | custom storage  | yes                    | None listed                                                                          | Skip entirely      | Identified by `fieldIsBundleCustomStorage()`. Rejected by `getEntityFieldTypes()`. The stub value reaches the entity constructor untouched, and the module's custom storage handles it from there. The bundle-scoped form of F4. |
| F9 | `hook_entity_field_storage_info()` + `bundleFieldDefinitions()` | standard        | yes                    | None listed                                                                          | Expand via handler | Identified by `fieldIsBundleStorageBacked()`. Present in `getFieldStorageDefinitions()` through `hook_entity_field_storage_info()`, but not as a `FieldStorageConfig`. `getEntityFieldTypes()` iterates the field and admits it through `fieldIsBundleStorageBacked()`, reading handler metadata from the bundle's field definitions. The handler is picked by field type (see the sub-table). |

## Handler selection sub-table

When a field enters the pipeline (F1, F5 and F9), the field type string that `FieldDefinitionInterface::getType()` returns picks its handler. The following table lists every handler, and representative types that `DefaultHandler` relays:

| ID  | Field-type shape                   | Representative types                                                                                               | Handler                                                                                                | Rationale                                                                                                                  |
|-----|------------------------------------|--------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|
| H1  | Plain-scalar columns               | `string`, `integer`, `float`, `decimal`, `email`, `telephone`, `uri`                                               | `DefaultHandler`                                                                                       | Relayed verbatim by `DefaultHandler`.                                                                                      |
| H2  | Multi-column compound              | `link`, `address`, `daterange`, `text`, `text_long`, `text_with_summary`                                           | `LinkHandler`, `AddressHandler`, `DaterangeHandler`; `DefaultHandler` for the `text*` types             | `LinkHandler`, `AddressHandler` and `DaterangeHandler` transform the compound value. The `text*` columns are plain scalars that `DefaultHandler` relays (see [DefaultHandler classification policy](#defaulthandler-classification-policy)). |
| H3  | Simple datetime                    | `datetime`                                                                                                         | `DatetimeHandler`                                                                                      | Parses a date string into the ISO 8601 storage format.                                                                     |
| H4  | Entity reference (single target)   | `entity_reference`, `file`, `image`                                                                                | `EntityReferenceHandler`, `FileHandler`, `ImageHandler`                                                | Resolves a label, path or filename to a `target_id`. `FileHandler` and `ImageHandler` first look for an existing managed file at the given URI, or at the bare basename in `public://` and `private://`. Without one, they upload a new file as `public://<uniqid>.<ext>`. |
| H5  | Entity reference with revision     | `entity_reference_revisions` (paragraphs)                                                                          | `EntityReferenceRevisionsHandler`                                                                      | A composite `target_id` and `target_revision_id`. Resolves the target and fills in its current revision ID.               |
| H6  | Typed list (allowed values)        | `list_string`, `list_integer`, `list_float`                                                                        | `ListStringHandler`, `ListIntegerHandler`, `ListFloatHandler`                                          | Matches on label or key, and stores the key.                                                                              |
| H7  | Boolean                            | `boolean`                                                                                                          | `BooleanHandler`                                                                                       | Matches the field's on or off label, or `1`/`0`, `true`/`false`, `yes`/`no` or `on`/`off`, case-insensitive. Stores `1` or `0`, and throws on any other value. |
| H8  | Name field (contrib)               | `name`                                                                                                             | `NameHandler`                                                                                          | Composite name components.                                                                                                 |
| H9  | Organic Groups reference (contrib) | `og_standard_reference`                                                                                            | `OgStandardReferenceHandler`                                                                           | Organic Groups lookup.                                                                                                     |
| H10 | Embedded asset reference (contrib) | `embridge_asset_item`                                                                                              | `EmbridgeAssetItemHandler`                                                                             | Embridge value shape.                                                                                                      |
| H11 | Smart date range (contrib)         | `smartdate`                                                                                                        | `SmartdateHandler`                                                                                     | 6-column timestamp range with a derived duration. Accepts numeric Unix timestamps or `strtotime()` strings.               |
| H12 | Color with opacity (contrib)       | `color_field_type`                                                                                                 | `DefaultHandler`                                                                                       | 2 plain-scalar columns that `DefaultHandler` relays: a `color` hex value and an optional `opacity` float. `preSave()` formats the hex value. |
| H13 | Recurring date (contrib)           | `date_recur`                                                                                                       | `DateRecurHandler`                                                                                     | 5 columns: `value`, `end_value`, `rrule`, `timezone` and `infinite`. Relays `value` and `end_value` as written, so a record holds the stored UTC values and names its `timezone`. Rejects a record with a date but no valid `timezone`, and a date outside the storage format. `preSave()` derives `infinite`, so `rrule` and `infinite` are relayed unchanged. |
| H14 | Captioned image (contrib)          | `supported_image`                                                                                                  | `SupportedImageHandler`                                                                                | An `image` item plus `caption_value`, `caption_format`, `attribution_value` and `attribution_format`. Extends `ImageHandler`, so it reuses an existing managed file before uploading, as H4 does. |
| H15 | Redirect source (contrib)          | `redirect_source`                                                                                                  | `RedirectSourceHandler`                                                                                | A `path` plus a `query` map, which `DefaultHandler` rejects as a complex value. Stores the path without its leading slash, as `Redirect::setSource()` does. Parses a query string into the map. |
| H16 | Unix timestamp                     | `timestamp`, `created`, `changed`                                                                                  | `TimestampHandler`, `CreatedHandler`, `ChangedHandler`                                                 | Parses a date `strtotime()` reads, such as `2014-10-17 8:00am` or `-1 hour`, to a Unix timestamp. Stores a numeric, empty or NULL value as given. |
| H17 | Time of day (contrib)              | `time`                                                                                                             | `TimeHandler`                                                                                          | Parses a time `strtotime()` reads to the number of seconds after midnight. Stores a numeric value as given.               |

## Cardinality

Cardinality doesn't change the resolution of any row in the primary table. Every handler accepts a scalar or an array. Before the handler builds the storage shape, `AbstractHandler::normalize()` wraps a bare scalar as a list of 1 record keyed by the field's main property. For a main property of `value`, that's `[['value' => $scalar]]`.

## DefaultHandler classification policy

`DefaultHandler` takes a field whose type has no registered handler. It relays the normalized records to storage verbatim. `Core` decides whether that's safe by consulting the field shape classifier first.

Value shape is independent of the origin and storage axes that `FieldClassifier` owns, so a second classifier reads it. Before `getFieldHandler()` falls back to `DefaultHandler`, `Core` passes the field's storage definition to `FieldShapeClassifierInterface`. The classifier reads only the stored property definitions and skips computed ones. It names no field type or data type string, so it works on any field type. `Core` throws when either predicate reports 1 of these 2 shapes, which Drupal's typed data system can't express as a plain scalar:

- **Entity-reference target** (`fieldIsEntityReference()`): a stored property is a `DataReferenceTargetDefinition`, such as `target_id`. The scenario names the target by label, path or name. Only a dedicated handler can resolve that to an ID.
- **Complex or nested value** (`fieldIsComplexValue()`): a stored property is a `ListDataDefinitionInterface` or a `ComplexDataDefinitionInterface`, such as a `map`. The value has no single scalar shape to relay.

Every other shape passes, datetime strings, booleans and list keys included. A dedicated handler translates a value that a scenario writes differently from the stored scalar. Such values include a date relative to a time zone, a boolean label, an allowed-value label and name or address components given separately. That knowledge lives only in the handlers.

Relayed verbatim, both rejected shapes would fail silently at save: a label stored as a bogus ID, or a nested value flattened. So `Core` refuses the field when it picks the handler, before any save. The exception names the field, its type, the entity type, the bundle and why `DefaultHandler` can't relay the value. To fix it, map the field type to a dedicated handler under the `drupal.field_handlers` option, then run the scenario again.

## Handler-coverage safety net

`FieldTypeCoverageKernelTest` reads every field type plugin that its enabled modules provide. It fails on a type that matches none of these:

- A registered handler.
- A shape the field shape classifier passes, with no entity-reference target and no complex or nested property.
- An entry in the test's `SKIP` map, with a reason such as a computed, write-only or composite-lifecycle field.

A new field type the classifier rejects therefore fails the test in this package's CI until it gets a handler or a `SKIP` entry.

## What each resolution means in code

Each resolution in the primary table runs this code:

- **Expand via handler**: `getEntityFieldTypes()` keeps the field because 1 of the 3 expand predicates matches. `getFieldHandler()` picks a handler by field type, and `$stub->setValue($field_name, $handler->expand($stub->getValue($field_name)))` runs.
- **Skip entirely**: `getEntityFieldTypes()` leaves the field out of its map. The stub value stays as the caller set it and reaches the entity through the storage's `create($stub->getValues())`. The field class, Drupal's storage layer or module hooks handle it from there.

For F2-F4 and F6-F8, the backend treats computed and custom-storage fields as *transparent* by design. It leaves persistence to the field's own mechanism, and it has no schema to normalize the value against. The scenario's raw value reaches the entity, and Drupal and the declaring module own what happens next.

## Predicate design

`FieldClassifierInterface` has 1 predicate per F-row, and `Core\Field\FieldClassifier` implements it. The following table lists when each predicate returns `TRUE`:

| F-row | Predicate                                              | Returns TRUE when                                                                                                             |
|-------|--------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| F1    | `fieldIsBaseStandard($type, $name)`                    | field is in `getBaseFieldDefinitions($type)`, not computed, not custom-storage                                                |
| F2    | `fieldIsBaseComputedReadOnly($type, $name)`            | in `getBaseFieldDefinitions($type)`, computed, `isReadOnly()` returns TRUE                                                    |
| F3    | `fieldIsBaseComputedWritable($type, $name)`            | in `getBaseFieldDefinitions($type)`, computed, `isReadOnly()` returns FALSE                                                   |
| F4    | `fieldIsBaseCustomStorage($type, $name)`               | in `getBaseFieldDefinitions($type)`, not computed, `hasCustomStorage()` returns TRUE                                          |
| F5    | `fieldIsConfigurable($type, $name)`                    | present in `getFieldStorageDefinitions($type)` as `FieldStorageConfig` instance                                               |
| F6    | `fieldIsBundleComputedReadOnly($type, $name, $bundle)` | in `getFieldDefinitions($type, $bundle)`, computed, read-only, not in base definitions                                        |
| F7    | `fieldIsBundleComputedWritable($type, $name, $bundle)` | in `getFieldDefinitions($type, $bundle)`, computed, writable, not in base definitions                                         |
| F8    | `fieldIsBundleCustomStorage($type, $name, $bundle)`    | in `getFieldDefinitions($type, $bundle)`, not computed, custom storage, not in base definitions                               |
| F9    | `fieldIsBundleStorageBacked($type, $name, $bundle)`    | present in `getFieldStorageDefinitions($type)` (through `hook_entity_field_storage_info()`) and in `getFieldDefinitions($type, $bundle)`, not a `FieldStorageConfig`, not base |

The interface has no aggregate predicate. Code that decides whether a field enters the expansion pipeline ORs the 3 expand-row predicates inline:

```php
$classifier = $this->getFieldClassifier();

if ($classifier->fieldIsBaseStandard($type, $name)
  || $classifier->fieldIsConfigurable($type, $name)
  || $classifier->fieldIsBundleStorageBacked($type, $name, $bundle)) {
  // Expand the field.
}
```

Each call site then shows exactly which F-rows it admits. A new F-row that should enter the pipeline gets added at each call site, where every reader of that code sees it.

## Version-specific classifiers

Unless the `drupal.core` option names a class, `DrupalBackend` builds `DrevOps\BehatSteps\Backend\Drupal\Core{N}\Core` for the newest major, at or below the detected one, that has such a class. When no major has one, it builds the base `Core`. A version-specific classifier lives in that subclass's `Field` namespace, as `Core{N}\Field\FieldClassifier`.

`Core` creates its classifier in the protected factory `createFieldClassifier()`. A `Core{N}\Core` in this package overrides the factory only when it needs a version-specific classifier class:

```php
namespace DrevOps\BehatSteps\Backend\Drupal\Core12;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core as BaseCore;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldClassifierInterface;

class Core extends BaseCore {

  protected function createFieldClassifier(): FieldClassifierInterface {
    return new Field\FieldClassifier($this->getEntityFieldManager());
  }

}
```

The base factory returns the base `FieldClassifier`. The value-shape classifier follows the same pattern: `Core::createFieldShapeClassifier()` returns the base `FieldShapeClassifier`, and a version core can return a `Core{N}\Field\FieldShapeClassifier` in its place. A version core registers its own handlers the same way, by extending `registerDefaultFieldHandlers()` with a scan of its own `Field/` directory. Each scan registers only the types its directory holds, so a version core re-registers the types it changes and keeps the parent's handlers for the rest.

These methods are protected, so semantic versioning doesn't cover them: they exist for a version core inside this package. A project adds or replaces a field handler through the `drupal.field_handlers` option or `FieldHandlerCapabilityInterface`, as [Field handlers](../../../../../docs/content/extending/backend.md#field-handlers) describes.

## Pipeline walk-through

When `createEntity()` receives a stub, the backend runs these steps:

1. `createEntity($stub)` runs the pre-create aliases for the values the stub carries, then calls `expandEntityFields($stub)`.
2. `expandEntityFields()` resolves the bundle from the stub and calls `getEntityFieldTypes($entity_type, $bundle)`.
3. `getEntityFieldTypes()` iterates `getFieldStorageDefinitions()`, `getBaseFieldDefinitions()` and, when a bundle is known, the bundle's field definitions. It keeps a field when `fieldIsBaseStandard()`, `fieldIsConfigurable()` or `fieldIsBundleStorageBacked()` returns TRUE. When a bundle is known, it keeps a configurable field only if that bundle carries it.
4. `expandEntityFields()` iterates the returned map, skipping the entity type's ID and bundle keys. For each field the stub holds a value for, it calls `getFieldHandler()`, which picks a handler by field type string and falls back to `DefaultHandler` when the shape classifier allows it. The handler's `expand()` turns the stub value into Drupal's storage shape.
5. Fields left out of the map (F2, F3, F4, F6, F7 and F8) keep their original stub values. The entity storage's `create()` receives all of the stub's values, and Drupal's field classes and storage layer handle them from there. After the save, `createEntity()` runs the post-create aliases for the values the stub carries.
