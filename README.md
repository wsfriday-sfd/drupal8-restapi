# Drupal 8 REST API

Helper modules and setup overview for Drupal 8 sites using REST API features

## Modules

**field_reference_delete** - General module used to remove references to deleted entities in entity reference based fields. (Includes Reference Value Pair fields)

Node revisions can be used to keep a history of entity reference fields, and no longer having invalid enteries in reference fields made things much easier to deal with. 

Based on issue: [When deleting an entity, references to the deleted entity remain in entity reference fields](https://www.drupal.org/project/drupal/issues/2723323)

**rest_extras** - Example module showing two different options for creating custom REST API features.

Views based option: Provides a custom field (Node Export Array) that contains a full node object with additional processing. Great for simple API calls that don't require a specific structure for the data.

Code based option: Example custom API calls for Main Menu and Triage. Includes a temporary fix for cache issues with custom API calls.

Main Menu API Call generates a hierarchical object of the menu that combines an additional Taxonomy group for the Self-Help section on the site.

Triage API Call generates a hierarchical object of the Triage that also includes the Triage Status Taxonomy (Used for user input). The Triage object contains references to the content and any conditions that must be met before being displayed to the user.

Please see Wiki for more information

## Exports

**triage-export.csv** - Full Triage Tree export from CTLawHelp.org in CSV format.