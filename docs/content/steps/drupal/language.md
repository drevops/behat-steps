---
title: "Drupal\\LanguageTrait steps"
sidebar_label: "LanguageTrait"
sidebar_position: 15
description: "Create the languages a scenario needs."
custom_edit_url: null
---

# Drupal\LanguageTrait steps

[Source](../../../../src/Steps/Drupal/LanguageTrait.php), [Example](../../../../tests/behat/features/drupal_language.feature)

>  Create the languages a scenario needs.
>  - Add languages by their ISO code, skipping ones already installed.
>  
>  Languages created here are removed after the scenario along with every other
>  entity the scenario created.
>  <br/><br/>
>  The 2 teardown hooks run in no guaranteed order. A scenario that also
>  installs the 'language' module therefore leaves that removal to the module
>  uninstall, with '@behat-steps-entity-cleanup-skip:language'.

## Steps

<details>
  <summary><code>@Given the following languages exist:</code></summary>

<br/>
Create the listed languages
<br/><br/>

```gherkin
Given the following languages exist:
  | langcode |
  | fr       |
  | de       |
```

</details>
