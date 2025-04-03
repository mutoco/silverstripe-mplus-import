# SilverStripe importer for Museum Plus

[![Code Coverage](https://codecov.io/gh/mutoco/silverstripe-mplus-import/branch/main/graph/badge.svg)](https://codecov.io/gh/mutoco/silverstripe-mplus-import)
[![Build Status](https://travis-ci.com/mutoco/silverstripe-mplus-import.svg?branch=main)](https://travis-ci.com/mutoco/silverstripe-mplus-import)
[![Latest Stable Version](https://poser.pugx.org/mutoco/silverstripe-mplus-import/v/stable)](https://packagist.org/packages/mutoco/silverstripe-mplus-import)
[![Monthly Downloads](https://poser.pugx.org/mutoco/silverstripe-mplus-import/d/monthly)](https://packagist.org/packages/mutoco/silverstripe-mplus-import)

This is an importer to import data-records from [museumplus by zetcom](https://www.zetcom.com/en/museumplus-en/).
It is implemented as a queued-job for the QueuedJobs Module.

## Requirements

* SilverStripe ^4.7
* [QueuedJobs Module](https://github.com/symbiote/silverstripe-queuedjobs) ^4
* PHP 7.4 or newer

## Installation

Install using composer

```
composer require mutoco/silverstripe-mplus-import 1.x-dev
```

## License
See [License](license.md)

## Project status

This is not a stable release (yet). The API might change and there are still several things [todo](todo.md)

## API configuration

Use the injector to set your credentials for the Mplus API.

```yml
SilverStripe\Core\Injector\Injector:
  Mutoco\Mplus\Api\Client:
    properties:
      BaseUrl: 'https://example.com/endpoint'
      Username: 'username'
      Password: '`MPLUS_PASSWORD`'
```

Here, an environment variable named `MPLUS_PASSWORD` is used for the actual password. Make sure you set this
environment variable in your system or in your `.env` file.

## Configuring the data-mapping

The `ImportEngine` class is responsible for importing Mplus Datarecords into SilverStripe DataObjects.
You have to configure a mapping from the remote "modules" to DataObjects by adding the following to your configuration
(ideally, this is stored in `app/_config/mplus.yml` or similar):

```yml
Mutoco\Mplus\Import\ImportEngine:
  modules:
    ModuleName:
      modelClass: DataObjectClass
      fields:
        Field: Path
      relations:
        RelationName:
          name: Path
          type: ModuleName
          fields:
            RelationField: Path
      attachment: RelationName
```

In this simplified example, `ModuleName` stands for a module name in Mplus, eg. `Person`, `Exhibition`, `Object`, etc.
Then you specify the mapping for this module. The allowed keys for each Module are:
- `modelClass`: Fully qualified classname of the SilverStripe DataObject
- `fields`: Mapping from SilverStripe field names (eg. typically what you have configured for `$db`) to paths in Mplus. These *have* to start at the "root" level, eg. nested paths need to be written with dot syntax. Example: `AdrContactGrp.ValueTxt`
- `relations`: Mapping from SilverStripe relations to collections in Mplus. Usually, collections in Mplus are either `repeatableGroup` or `moduleReference` nodes. The `RelationName` is the name of the relation in the SilverStripe ORM. Then you set the name (path) and type (name of the referenced module) and optional `fields` that should be stored with the relation (eg. for `many_many_extraFields` or similar).
- `attachment`: If set, an attachment will be stored in this relation. Eg. if you set: `attachment: Image`, the attachment will be stored as the `Image` relation on the DataObject.

Assuming you have a DataObject that represents a Person in SilverStripe, with the FQCN `MyProject\Person`,
then your Configuration might look like this:

```yml
Mutoco\Mplus\Import\ImportEngine:
  modules:
    Person:
      modelClass: MyProject\Person
      fields:
        Firstname: PerFirstNameTxt
        Lastname: PerLastNameTxt
        PlaceOfBirth: PerPlaceBirthTxt
        PlaceOfDeath: PerPlaceDeathTxt
        DateOfBirth: PerDateFromBeginDat
        DateOfDeath: PerDateToEndDat
```

Of course, the `modules` can contain multiple entries, which is necessary for more complex setups with relations.
Here's an example:

```yml
Mutoco\Mplus\Import\ImportEngine:
  modules:
    Person:
      modelClass: MyProjectPerson
      fields:
        Firstname: PerFirstNameTxt
        Lastname: PerLastNameTxt
        PlaceOfBirth: PerPlaceBirthTxt
        PlaceOfDeath: PerPlaceDeathTxt
        DateOfBirth: PerDateFromBeginDat
        DateOfDeath: PerDateToEndDat
    TextBlock:
      modelClass: MyProject\TextBlock
      fields:
        Text: TextClb
        Author: AuthorTxt
    Exhibition:
      modelClass: MyProject\Exhibition
      fields:
        Title: ExhTitleTxt
      relations:
        Texts:
          name: ExhTextGrp
          type: TextBlock
        Person:
          name: ExhPersonRef
          type: Person
```

In this example, you can import "Exhibitions", which will also import all related Persons and Text-Blocks (TextBlock isn't
a module in Mplus, it's an arbitrarily named Model, so that a mapping in the relation is possible at all).

## Configuring the import job

To tell the `ImportJob` which records to import, configure an initial search to get the records to import.
The following config would import the Exhibitions with ID `1` and `2`.

The `search` configuration is pretty much an YML to XML mapping of the [Mplus Search API](http://docs.zetcom.com/ws/#Perform_an_ad-hoc_search_for_modules_items).

```yml
Mutoco\Mplus\Job\ImportJob:
  imports:
    Exhibition:
      search:
        or:
          - type: equalsField
            fieldPath: __id
            operand: 1
          - type: equalsField
            fieldPath: __id
            operand: 2
```

## Import flow

The data-import is implemented as different import steps, which are placed inside
a priority queue and executed by the `ImportEngine`.

Usually, the first step to be added to the queue is a `LoadSearchStep`. In this step, a search on the
M+ API is performed to load results for the requested model. For each search-result, a `LoadModuleStep` will be created
in the queue.

The `LoadModuleStep` is responsible to resolve a complete import tree. That means, that the initial XML Data of the Model gets
parsed into a tree instance. The `LoadModuleStep` then follows all relations and completes the tree with data from relations, so
that after completion of one `LoadModuleStep`, the importer has a complete Data-Tree for the current model.

The `LoadModuleStep` will enqueue `ImportModuleStep` for the Module that needs to be imported. It can also trigger further `LoadModuleSteps` if more relations need to be loaded.

The `ImportModuleStep` will perform the actual import of the tree-data into the SilverStripe DataObjects. It will traverse the imported tree data and enqueue further `ImportModuleStep` instances
for relations.

Assets will be imported with an `ImportAttachmentStep`, which will also be triggered from a `ImportModuleStep`.

Relations will be linked with the `LinkRelationStep` *after* all related Modules of one Module have been imported.

Obsolete records will be removed with the `CleanupRecordsStep` and relations will be cleaned with the `CleanupRelationStep`.

### Extension hooks

Some aspects of the import-process can be controlled with methods on the imported records (either as method on the DataObject itself, or as method of an Extension).

#### Callbacks during `LoadModuleStep`

These callbacks will always get called on your affected DataObject instance. So if you mapped `Exhibition` to a `MyExhibition` DataObject, the callback/extension method will be called on a `MyExhibition` DataObject.

- `transformMplusResultTree`: After the whole import-tree has been resolved, it can be modified by a method on the model that is currently being imported. Parameters are `$tree: TreeNode` and `$engine: ImportEngine`.
- `shouldImportMplusModule`: This method will be called on the currently imported model. If you return `false`, the current model will skip import. Parameters are `$tree: TreeNode` (the fully resolved tree of the current module) and `$engine: ImportEngine`.
- `beforeMplusSearchRelated`: This method will be called before a search is issued to the M+ API to load related modules. Parameters are: `$search: SearchBuilder` (the SearchBuilder that will be used to issue the search), `$module: string` (the name of the related module that will be searched), `$cleanedIds: int[]` the numeric IDs of the modules that should be loaded.

#### Callbacks during `ImportModuleStep`

These callbacks will always get called on your affected DataObject instance. So if you mapped `Exhibition` to a `MyExhibition` DataObject, the callback/extension method will be called on a `MyExhibition` DataObject.

- `beforeMplusSkip`: If the engine determined that a DataObject should skip import (because it wasn't modified), you can return `false` from this method to *not* skip importing. E.g. force an import. Parameters to the callback are Parameters are `$step: ImportModuleStep` and `$engine: ImportEngine`.
- `beforeMplusImport`: Method that will be called *before* import starts. Parameters are `$step: ImportModuleStep` and `$engine: ImportEngine`.
- `transformMplusFieldValue`: Method that will be called to apply any transforms to imported fields values. Parameters are: `$fieldName: string` (the name of the field of the DataObject), `$fieldNode: TreeNode` (the imported Data from the XML node) and `$engine: ImportEngine`. If you return any value from this callback, it should be the new field-value!
- `afterMplusImport`: Method that will be called *after* the import has finished and `write()` has been called on the imported DataObject. Parameters are `$step: ImportModuleStep` and `$engine: ImportEngine`.
- `mplusShouldImportAttachment`: If there's an attachment configured for the current DataObject, you can use this callback to skip import of an attachment. Parameters are: `$attachment: string` (the name of the attachment), `$node: TreeNode` (the current object XML node) and `$engine: ImportEngine`.
- `shouldImportMplusRelation`: Relation nodes in the tree can be excluded from the import process with this callback by returning `false`. Parameters are: `$relationName: string` (the name of the relation), `$child: TreeNode` (tree node of the relation to import), `$engine: ImportEngine`
- `transformMplusRelationField`: Allows you to transform a relation import. You can return a custom value for the relation that should be saved. E.g. look up an ID yourself. Params are: `$field: string` (the name of the imported DataObject relation field), `$node: TreeNode` (the tree node for the relation), `$engine: ImportEngine`

#### Callbacks during `ImportAttachmentStep`

These callbacks will always get called on your affected DataObject instance. So if you mapped `Exhibition` to a `MyExhibition` DataObject, the callback/extension method will be called on a `MyExhibition` DataObject.

- `shouldImportMplusAttachment`: This callback will fire right after the headers from the remote File are present and allows you to cancel download/import of an attachment by returning `false`. By returning `true`, the import will be forced. If nothing is returned, the default behavior will trigger, which is to only import if the file doesn't exist yet. Parameters are: `$fileName: string` (name of the file), `$field: string` (name of the field on the DataObject), `$step: ImportAttachmentStep` (the current import step) and `$engine: ImportEngine`.

#### Callbacks during `LinkRelationStep` and `CleanupRelationStep`

These callbacks will always get called on your affected DataObject instance. E.g. the DataObject that has the affected relation.

- `beforeMplusRelationStep`: This callback will fire before the logic of the relation-step runs. Parameters are: `$type: string` (the type of relation, e.g. `has_one`, `many_many` etc.), `$relationName: string` (name of the relation), `$relationIds: array` (the IDs of this relation. This is always an array, also for `has_one`), `$step: AbstractRelationStep` (the relation step instance that is running), `$engine: ImportEngine`.
- `afterMplusRelationStep`: Called *after* relations have been linked. The params are identical to the ones from `beforeMplusRelationStep`.

#### Callbacks during `CleanupRecordsStep`

This callback will always get called on your affected DataObject instance.

- `beforeMplusDelete`: Called before a DataObject should get deleted (because it was no longer part of the import dataset). You can return `false` from this callback to prevent deletion.

#### Callbacks on `VocabularyItem`

The `VocabularyItem` is a model for the `VocabularyItem` Module in M+. It is widely used and therefore part of this codebase.
You can add an Extension to this model to customise it. There's one extension method that you can use to customise import:

- `onUpdateFromNode`: is called whenever this `VocabularyItem` should get updated by the import process. If you return a `falsy` value, the new `VocabularyItem` data will not get written. Params are: `$node: TreeNode` (the imported tree-node for the current `VocabularyItem`), `$engine: ImportEngine`.

## Maintainers
 * Roman Schmid <bummzack@gmail.com>

## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over
existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version,
 Operating System, any installed SilverStripe modules.

Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
