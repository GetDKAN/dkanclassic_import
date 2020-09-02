# DKAN Classic Import
Import data and content exported using
[dkan_export](https://github.com/GetDKAN/dkan_export) to a DKAN 2 install.

# Installation
## Requirements
* Full installation of DKAN 2.
* Dependencies:
    * drupal/migrate_plus
    * drupal/migrate_tools
    * drupal/migrate_source_csv

## Install Composer
Inside your DKAN 2 powered Drupal site, add `dkanclassic_import` using composer:

```
$ composer require "getdkan/dkanclassic_import"
```

# Usage
## Import Data
Imports leverage CSV files and Drupal migrations. The module currently support the following Data imports:

### Users
After getting a users CSV file export (instructions [here](https://github.com/GetDKAN/dkan_export#users)).
Save all users currently available on on a DKAN Classic site and uuids of datasets that they authored.

```
$ drush mim --update dkanclassic_users
```

Notes:
+ Make sure you harvested/imported any datasets prior to importing the users.
