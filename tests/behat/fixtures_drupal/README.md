# Drupal Fixtures

This directory contains Drupal fixture sites used for testing the Behat Steps library with actual Drupal installations. Each subdirectory represents a different Drupal major version.

## Directory Structure

```
fixtures_drupal/
├── d10/          # Drupal 10 fixture
│   ├── composer.json
│   ├── config/
│   │   └── sync/  # Exported Drupal configuration
│   ├── scripts/
│   │   └── composer/
│   │       └── ScriptHandler.php
│   └── web/
│       └── modules/
│           └── custom/
│               └── mysite_core/
└── d11/          # Drupal 11 fixture
    └── (same structure as d10)
```

## Purpose

These fixtures provide:
- **Drupal-dependent test scenarios**: Content types, fields, forms, and entities that require Drupal's database and API
- **Configuration management testing**: Exported configuration for reproducible site builds
- **Custom module hooks**: Test-specific functionality (e.g., cookies, emails) that can't be replicated with static HTML

## Required Drupal Modules

Each fixture's `composer.json` includes these contrib modules required for comprehensive testing:

### Core Testing Modules
- **drupal/eck**: Entity Construction Kit for custom entity testing
- **drupal/paragraphs**: Paragraph entity reference testing
- **drupal/webform**: Form building and submission testing

### Content & Workflow Modules
- **drupal/pathauto**: URL alias pattern testing
- **drupal/scheduled_transitions**: Content moderation state transition testing
- **drupal/draggableviews**: Drag-and-drop view ordering testing

### Search & API Modules
- **drupal/search_api**: Search indexing and API testing

### Development Modules
- **drupal/testmode**: Test mode functionality for safe testing environment

## Custom Module: mysite_core

Located at `web/modules/custom/mysite_core/`, this module provides test-specific hooks:

### Features

**Cookie Testing** (`mysite_core_form_alter`):
```php
// Sets a test cookie when authenticated users interact with search form
setcookie('testcookiename', 'testcookievalue');
```

**Email Testing** (`mysite_core_mail`):
```php
// Provides a mail hook for testing email functionality
// Supports custom subjects, bodies, and headers
```

### Module Structure
```
mysite_core/
├── mysite_core.info.yml     # Module metadata
├── mysite_core.module        # Hook implementations
├── mysite_core.install       # Install/uninstall hooks
├── mysite_core.routing.yml   # Route definitions
└── src/                      # PSR-4 autoloaded classes
```

## Composer Configuration

### Special composer.json Entries

#### 1. Repositories
```json
"repositories": {
    "drupal": {
        "type": "composer",
        "url": "https://packages.drupal.org/8"
    }
}
```

#### 2. Autoload
```json
"autoload": {
    "classmap": [
        "scripts/composer/"
    ]
}
```
Loads the `ScriptHandler.php` class for post-install automation.

#### 3. Extra: installer-paths
```json
"extra": {
    "installer-paths": {
        "web/core": ["type:drupal-core"],
        "web/modules/contrib/{$name}": ["type:drupal-module"],
        "web/themes/contrib/{$name}": ["type:drupal-theme"],
        "web/profiles/contrib/{$name}": ["type:drupal-profile"]
    }
}
```
Ensures Drupal code is installed in the correct `web/` subdirectories.

#### 4. Extra: drupal-scaffold
```json
"extra": {
    "drupal-scaffold": {
        "locations": {
            "web-root": "./web"
        }
    }
}
```
Configures Drupal scaffold files to be placed in `web/` directory.

#### 5. Scripts: drupal-post-install
```json
"scripts": {
    "drupal-post-install": [
        "drush -r web config-set system.site uuid 5b4646a8-ed7b-453c-b5b6-78196271f41b -y --uri=http://nginx",
        "drush -r web ev \"\\Drupal::entityTypeManager()->getStorage('shortcut_set')->load('default')->delete();\" --uri=http://nginx || true",
        "drush -r web updb -y --uri=http://nginx",
        "drush -r web cim --source=../config/sync -y --uri=http://nginx",
        "drush -r web cr -y --uri=http://nginx",
        "drush -r web pm:enable mysite_core -y --uri=http://nginx"
    ]
}
```

**What it does:**
1. Sets the site UUID to match exported configuration
2. Deletes the default shortcut set (prevents import conflicts)
3. Runs database updates (`updb`)
4. Imports all configuration from `config/sync/` (`cim`)
5. Clears cache (`cr`)
6. Enables the `mysite_core` custom module

#### 6. Config: allow-plugins
```json
"config": {
    "allow-plugins": {
        "composer/installers": true,
        "cweagans/composer-patches": true,
        "drupal/core-composer-scaffold": true,
        "drupal/core-project-message": true,
        "oomphinc/composer-installers-extender": true
    }
}
```
Required for Composer 2.2+ to allow specific plugins.

## Configuration Management

### config/sync/ Directory

Contains exported Drupal configuration files:
- **Content types**: `node.type.*.yml` (article, page, landing_page, draggableviews_demo)
- **Field storage**: `field.storage.*.yml` (field definitions)
- **Field instances**: `field.field.*.yml` (field attachments to entities)
- **Form displays**: `core.entity_form_display.*.yml` (how forms are rendered)
- **View displays**: `core.entity_view_display.*.yml` (how content is displayed)
- **Views**: `views.view.*.yml` (custom Views configurations)
- **Webforms**: `webform.webform.*.yml` (form definitions)
- **ECK types**: `eck.eck_type.*.yml` (custom entity types)
- **Paragraphs**: `paragraphs.paragraphs_type.*.yml` (paragraph types)
- **Workflows**: `workflows.workflow.*.yml` (content moderation workflows)

### Key Configuration Features

**Content Types:**
- `article` - Standard article with fields
- `page` - Basic page content type
- `landing_page` - Landing page with paragraph fields
- `draggableviews_demo` - Demo type for drag/drop testing

**Custom Entity Types (ECK):**
- `test_entity_type.test_bundle` - For entity API testing

**Webforms:**
- Multiple webform configurations for form testing

**Workflows:**
- `editorial` - Content moderation workflow (draft → published)

## Scripts

### ScriptHandler.php

Located at `scripts/composer/ScriptHandler.php`, this class automates setup:

**createRequiredFiles() method:**
1. Creates required directories (`modules/`, `profiles/`, `themes/`)
2. Creates `settings.php` from `default.settings.php`
3. Sets `config_sync_directory` path to `../config/sync`
4. Creates `sites/default/files/` directory
5. Sets appropriate file permissions

## Usage

### Initial Setup

1. **Install dependencies:**
   ```bash
   cd tests/behat/fixtures_drupal/d10
   composer install
   ```

2. **Run post-install automation:**
   ```bash
   composer drupal-post-install
   ```
   This imports all configuration and enables the custom module.

### Updating Fixtures

When adding new test scenarios that require Drupal features:

1. **Make changes in the build environment:**
   ```bash
   ahoy drush cex -y
   ```
   This exports configuration to `build/config/sync/`

2. **Copy to fixtures:**
   ```bash
   ahoy update-fixtures
   ```
   This copies configuration to both `d10/` and `d11/` fixtures.

3. **Update both versions:**
   Always maintain both Drupal 10 and Drupal 11 fixtures in sync.

### Adding New Modules

If a new test requires additional Drupal modules:

1. **Add to both `d10/composer.json` and `d11/composer.json`:**
   ```json
   "require": {
       "drupal/example_module": "^1.0"
   }
   ```

2. **Enable in configuration:**
   The module will be enabled after `composer drupal-post-install` imports config from `config/sync/core.extension.yml`

3. **Export any configuration:**
   If the module provides configuration, export it to `config/sync/`

## Version-Specific Notes

### Drupal 10 (d10/)
- PHP >= 8.2
- Drupal core: `~10.5.0`
- CKEditor 5 (replaces CKEditor 4)

### Drupal 11 (d11/)
- PHP >= 8.3
- Drupal core: `~11.0.0`
- All modules must be Drupal 11 compatible

## Testing Flow

1. **Fixture build**: `ahoy build` installs Drupal in `build/` using fixtures
2. **Configuration import**: `composer drupal-post-install` imports all config
3. **Module enable**: `mysite_core` module is enabled with test hooks
4. **BDD tests run**: Behat scenarios use the fully configured Drupal site
5. **Update fixtures**: Changes exported back to fixtures for reproducibility

## Best Practices

1. **Keep fixtures minimal**: Only include modules/config actually used in tests
2. **Sync both versions**: Always update both d10 and d11 when making changes
3. **Document custom hooks**: Add comments to `mysite_core` module for test-specific functionality
4. **Export clean config**: Use `ahoy drush cex -y` to ensure all config is exported
5. **Test both versions**: Run BDD tests against both Drupal 10 and 11 fixtures

## Troubleshooting

### Configuration import fails
- Check that site UUID matches: `drush config-get system.site uuid`
- Delete shortcut set if it conflicts: `drush ev "\\Drupal::entityTypeManager()->getStorage('shortcut_set')->load('default')->delete();"`

### Module not found
- Run `composer install` in the fixture directory
- Check `composer.json` includes the required module

### Permission errors
- Ensure `sites/default/files/` has correct permissions (777)
- Check `settings.php` is writable (666) during install

### Tests fail after fixture update
- Run `ahoy build` to rebuild with new fixtures
- Clear cache: `ahoy drush cr`
- Verify config imports successfully: `ahoy drush cim -y`
