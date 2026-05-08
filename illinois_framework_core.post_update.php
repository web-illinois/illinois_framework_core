<?php
use Drupal\field\Entity\FieldStorageConfig;
/**
 * @file
 * Post update functions for Illinois Framework Core Module.
 */

/**
 * Hide the field_cta_icon on the CTA paragraph form display.
 */
function illinois_framework_core_post_update_hide_cta_icon(&$sandbox) {
  // Load the specific form display config entity.
  $form_display = \Drupal::entityTypeManager()
    ->getStorage('entity_form_display')
    ->load('paragraph.cta.default');

  if ($form_display) {
    // The removeComponent method completely strips the field's widget settings
    // from the 'content' array and automatically registers it in 'hidden'.
    $form_display->removeComponent('field_cta_icon')
      ->save();

    return 'The field_cta_icon has been hidden on the CTA paragraph form display.';
  }

  return 'The CTA paragraph form display was not found.';
}

/**
 * Make the field_title on the CTA paragraph form display optional, not required.
 */
function illinois_framework_core_post_update_cta_title_optional(&$sandbox) {
  $field_config = FieldConfig::loadByName('paragraph', 'cta', 'field_cta_title');

  if ($field_config) {
    $field_config->setRequired(FALSE);
    $field_config->save();
  }
}

/**
 * Remove the user search page from default Drupal search.
 */
function illinois_framework_core_post_update_remove_user_search_page(&$sandbox) {
  $search_user = \Drupal::configFactory()->getEditable('search.page.user_search');

  if ($search_user) {
    $search_user->delete();
    return "Deleted user search page.";
  } else{
    return "No user search page found.";
  }
}

/**
 * Remove the help search page from default Drupal search.
 */
function illinois_framework_core_post_update_remove_help_search_page(&$sandbox) {
  $search_help = \Drupal::configFactory()->getEditable('search.page.help_search');

  if ($search_help) {
    $search_help->delete();
    return "Deleted help search page.";
  } else{
    return "No help search page found.";
  }
}
