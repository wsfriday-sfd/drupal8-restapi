# Drupal 8 REST API

Helper modules and setup overview for Drupal 8 sites using REST API features

## Modules

field_reference_delete - General Drupal 8 module used to remove references to deleted entities in entity reference fields. Based on issue: [When deleting an entity, references to the deleted entity remain in entity reference fields](https://www.drupal.org/project/drupal/issues/2723323)

rest_extras - Example module showing two different options for creating REST API features.
Views based option: Provides a custom field that contains a full node object with additional processing. Useful when creating custom API calls in a View.
Code based option: Example custom API calls for Main Menu and Triage.

Please see Wiki for more information