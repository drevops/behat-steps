# Migration

A migration map of the step definitions available in v2 to v3.

| V2                                                                                                                           | V3                                                                                                                                                                                                                             |
|------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **[`ParagraphsTrait`](src/ParagraphsTrait.php) ([example](tests/behat/features/paragraphs.feature))**                        |                                                                                                                                                                                                                                |
| `When :field_name in :bundle :entity_type with :entity_field_name of :entity_field_identifer has :paragraph_type paragraph:` | `Given the following fields for the paragraph :paragraph_type exist in the field :parent_field within the :parent_bundle :parent_entity_type identified by the field :parent_lookup_field and the value :parent_lookup_value:` |
