# SilverStripe importer for Museum Plus

This is an importer to import data-records from [museumplus by zetcom](https://www.zetcom.com/en/museumplus-en/).
It is implemented as a queued-job for the QueuedJobs Module.

## Requirements

* SilverStripe ^4.0
* [QueuedJobs Module](https://github.com/symbiote/silverstripe-queuedjobs) ^4

## Installation

Install using composer

```
composer require mutoco/silverstripe-mplus-import 1.x-dev
```

## License
See [License](license.md)

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
