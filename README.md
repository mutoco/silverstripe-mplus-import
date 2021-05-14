# SilverStripe importer for Museum Plus

This is an importer to import data-records from [museumplus by zetcom](https://www.zetcom.com/en/museumplus-en/)

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

## Example configuration

Use the injector to set your credentials for the Mplus API.

```yml
SilverStripe\Core\Injector\Injector:
  Mutoco\Mplus\Api\Client:
    properties:
      BaseUrl: 'https://example.com/endpoint'
      Username: 'username'
      Password: '`MPLUS_PASSWORD`'
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
