/* $Id$ */

-- SUMMARY --

Name is a module that provides a number of name fields and a FAPI name element.

There is a single new CCK name called Name. This contains a configurable selection
of the following name parts:

1) Title (a select field)
2) Given name
3) Middle name(s)
4) Family name
5) Generational suffix (a select field)
6) Credentials

At least given or family name must be selected. You can specify which of these
fields to display and the minimal required fields to consitute a valid name.

Multiple values are supported via the core fields engine.

-- REQUIREMENTS --

* CCK

You will require the CCK module to add fields to content.

-- INSTALLATION --

* Standard installation, see http://drupal.org/node/70151 for further information.

-- UPGRADING --

* N/A - This is a new module.

-- RELATED MODULES --

* Fullname field for CCK
  A similar module for Drupal 5 CCK, but with support for two concurrent name
  field sets for each entry. A legal and preferred set of:
  
  prefix, firstname, middlename, lastname, and suffix
   
  http://drupal.org/project/cck_fullname
  
* Namefield
  An "experiment" Drupal 5 development module.

  http://drupal.org/project/namefield

-- CONFIGURATION --

* There are no special configuration requirements. Just add these like any
  other Drupal fields.

-- REFERENCES --

Drupal 6

For details about CCK:
  http://drupal.org/handbook/modules/cck

Drupal 7

For details about Fields API:
  http://drupal.org/node/443536
For details about Drupal 7 FAPI:
  http://api.drupal.org/api/drupal/developer--topics--forms_api.html/7
  http://api.drupal.org/api/drupal/developer--topics--forms_api_reference.html/7
  
-- CONTACT --

Current maintainers:

* Alan D. - http://drupal.org/user/54136

If you want to help or be involved please contact me.

If you find any issues please lodge an issue after checking that the issue
is not a duplicate of an existing issue.