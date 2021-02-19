CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Usage
 * Maintainers


INTRODUCTION
------------

Entity Migrate Export is a Drupal (8|9) to Drupal (8|9) migration generator
tool. With this module, you are able to export entity instances of the specified
content entity types (including their referred entities) into migrations.

Entity Migrate Export generates a module with a set of migration plugin
definitions and data sources. After installing that module (its name defaults to
`eme_migrate`), you will be able to import the entities (e.g. nodes, files,
media, comments etc) with [Migrate Tools][1].


REQUIREMENTS
------------

This module does not depend on any contrib module. However, the migrations it
generates (and the generated module as well) require [Migrate Plus][2].


INSTALLATION
------------

You can install Entity Migrate Export as you would normally install a
contributed Drupal 9 module.


CONFIGURATION
-------------

The module's settings form can be found at
`/admin/config/development/entity-export/settings`.

Configuration options:
- Machine name of the generated module (defaults to `eme_migrate`).
- Human name of the generated module (defaults to `Content Entity Migration`).
- The group of the generated migration plugin definitions (defaults to `eme`).
- The ID prefix of the generated migration plugin definitions (defaults to
  `eme_migrate`).
- The subdirectory where the content entity data should be stored (defaults to
  `data`).
- The subdirectory where the file assets should be stored (defaults to
  `assets`).
- The list of entity type IDs which should be ignored (nothing ignored by
  default).


USAGE
-----

### Exporting content

#### From UI
- Go to the export form at `/admin/config/development/entity-export`, select
  which type of entities do you want to export to migrations, and submit the
  form.
- At the end of the batch process, you will get the generated migration module.
- Extract the downloaded archive to your Drupal codebase.

#### With Drush
- `drush eme:export --module eme_migrate --group test_content --types node,block_content --destination modules/custom`


### Importing content
- Install [Migrate Tools][1], [Migrate Plus][2] and the generated module.
- Execute the migrations with Drush:
  `drush migrate:import --group test_content --execute-dependencies`


### Update previous export

#### From UI
- Go to the collection form at `/admin/config/development/entity-export/collection`.
- Press the "Reexport" button of the collection you want to update with new content.

#### With Drush
`drush eme:export --update eme_migrate`

MAINTAINERS
-----------

* Zoltán Horváth (huzooka) - https://www.drupal.org/user/281301

[1]: https://drupal.org/node/2609548
[2]: https://drupal.org/node/2202391
