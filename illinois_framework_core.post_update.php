<?php
use Drupal\field\Entity\FieldConfig;
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

/**
 * Remove field_intro_home_fingerprint from intro_home paragraph bundle.
 */
function illinois_framework_core_post_update_remove_intro_home_fingerprint(&$sandbox) {
  $field_name = 'field_intro_home_fingerprint';
  $entity_type = 'paragraph';
  $bundle = 'intro_home';

  $entity_type_manager = \Drupal::entityTypeManager();

  // Delete the field instance (bundle-specific).
  $field_config_id = "{$entity_type}.{$bundle}.{$field_name}";
  $field_config = $entity_type_manager
    ->getStorage('field_config')
    ->load($field_config_id);

  if ($field_config) {
    $field_config->delete();
  }

  // Delete the field storage.
  $field_storage_id = "{$entity_type}.{$field_name}";
  $field_storage = $entity_type_manager
    ->getStorage('field_storage_config')
    ->load($field_storage_id);

  if ($field_storage) {
    $field_storage->delete();
  }

  return 'Removed field_intro_home_fingerprint from intro_home paragraph bundle.';
}

/**
 * Remove field_cta_fingerprint from cta paragraph bundle.
 */
function illinois_framework_core_post_update_remove_cta_fingerprint(&$sandbox) {
  $field_name = 'field_cta_fingerprint';
  $entity_type = 'paragraph';
  $bundle = 'cta';

  $entity_type_manager = \Drupal::entityTypeManager();

  // Delete the field instance (bundle-specific).
  $field_config_id = "{$entity_type}.{$bundle}.{$field_name}";
  $field_config = $entity_type_manager
    ->getStorage('field_config')
    ->load($field_config_id);

  if ($field_config) {
    $field_config->delete();
  }

  // Delete the field storage.
  $field_storage_id = "{$entity_type}.{$field_name}";
  $field_storage = $entity_type_manager
    ->getStorage('field_storage_config')
    ->load($field_storage_id);

  if ($field_storage) {
    $field_storage->delete();
  }

  return 'Removed field_cta_fingerprint from cta paragraph bundle.';
}

/**
 * Replace legacy 'il-button' classes with 'ilw-button' classes everywhere.
 */
function illinois_framework_core_post_update_replace_il_button_classes(&$sandbox) {
  $replacements = [
    'il-button' => 'ilw-button',
    'il-white-blue' => 'ilw-theme-blue',
    'il-white-orange' => 'ilw-theme-orange',
    'il-blue' => 'ilw-theme-blue-1',
    'il-orange' => 'ilw-theme-orange-1',
  ];

  return _illinois_framework_core_replace_text_in_content($sandbox, $replacements);
}

/**
 * Replace numbered theme button classes with their semantic 'solid' variants.
 */
function illinois_framework_core_post_update_replace_theme_button_solid_classes(&$sandbox) {
  $replacements = [
    'ilw-theme-blue-1' => 'ilw-theme-blue-solid',
    'ilw-theme-orange-1' => 'ilw-theme-orange-solid',
  ];

  return _illinois_framework_core_replace_text_in_content($sandbox, $replacements);
}

/**
 * This is a helper function that will find and replace text strings across all
 * formatted text fields and revisions.
 *
 * Generic batch helper for post-update hooks. It scans every content entity
 * type's text/text_long/text_with_summary fields (including all revisions and
 * translations) for the given search strings and replaces each occurrence with
 * its mapped value. Replacements are matched with hyphen-aware word boundaries
 * so partial class names are not corrupted, and entities are saved in-place
 * without creating new revisions.
 *
 * @param array $sandbox
 *   The batch sandbox passed in by the calling post-update hook. Each hook has
 *   its own sandbox, so batch state stays isolated between hooks.
 * @param array $replacements
 *   An associative array mapping each string to search for to its replacement,
 *   e.g. ['old-class' => 'new-class'].
 *
 * @return string|null
 *   A status message when the batch finishes, or NULL while still processing.
 */
function _illinois_framework_core_replace_text_in_content(array &$sandbox, array $replacements) {
  $search_strings = array_keys($replacements);

  // Step 1: Initialize the sandbox on the first pass.
  if (!isset($sandbox['total'])) {
    $sandbox['revisions_to_process'] = [];
    $sandbox['total'] = 0;
    $sandbox['processed'] = 0;

    $database = \Drupal::database();
    $field_map = \Drupal::service('entity_field.manager')->getFieldMap();
    $text_field_types = ['text', 'text_long', 'text_with_summary'];

    foreach ($field_map as $entity_type => $fields) {
      $entity_type_def = \Drupal::entityTypeManager()->getDefinition($entity_type, FALSE);
      if (!$entity_type_def instanceof \Drupal\Core\Entity\ContentEntityTypeInterface) {
        continue;
      }

      $is_revisionable = $entity_type_def->isRevisionable();

      foreach ($fields as $field_name => $field_info) {
        if (in_array($field_info['type'], $text_field_types)) {
          try {
            // Query all revisions that contain ANY of the search strings in
            // this field, using an OR condition group.
            $query = \Drupal::entityQuery($entity_type)
              ->accessCheck(FALSE);

            $or_group = $query->orConditionGroup();
            foreach ($search_strings as $search_string) {
              $or_group->condition(
                $field_name . '.value',
                '%' . $database->escapeLike($search_string) . '%',
                'LIKE'
              );
            }
            $query->condition($or_group);

            if ($is_revisionable) {
              $query->allRevisions();
            }

            $results = $query->execute();

            if (!empty($results)) {
              if (!isset($sandbox['revisions_to_process'][$entity_type])) {
                $sandbox['revisions_to_process'][$entity_type] = [];
              }

              // If revisionable, keys are VIDs. If not, keys are IDs.
              // Normalize to an array structure where we track VIDs (or IDs if not revisionable).
              foreach ($results as $vid => $id) {
                $identifier = $is_revisionable ? $vid : $id;

                if (!isset($sandbox['revisions_to_process'][$entity_type][$identifier])) {
                  $sandbox['revisions_to_process'][$entity_type][$identifier] = [
                    'id' => $id,
                    'fields' => [],
                  ];
                }
                if (!in_array($field_name, $sandbox['revisions_to_process'][$entity_type][$identifier]['fields'])) {
                  $sandbox['revisions_to_process'][$entity_type][$identifier]['fields'][] = $field_name;
                }
              }
            }
          } catch (\Exception $e) {
            // Ignore if the entity type doesn't support the query properly.
          }
        }
      }
    }

    foreach ($sandbox['revisions_to_process'] as $entity_type => $items) {
      $sandbox['total'] += count($items);
    }
  }

  // Step 2: If no entities were found, we are already done.
  if ($sandbox['total'] == 0) {
    $sandbox['#finished'] = 1;
    return 'No entities found containing any of the search strings in formatted text fields.';
  }

  // Step 3: Process a chunk of entities (50 at a time is a safe limit).
  $limit = 50;
  $processed_this_run = 0;

  foreach ($sandbox['revisions_to_process'] as $entity_type => &$items) {
    if (empty($items)) {
      continue;
    }

    // Extract a chunk of items to process.
    $current_items = array_slice($items, 0, $limit - $processed_this_run, TRUE);
    // Remove the processed items from the main array.
    $items = array_diff_key($items, $current_items);

    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $is_revisionable = \Drupal::entityTypeManager()->getDefinition($entity_type)->isRevisionable();

    // We need to group by ID to reset cache effectively.
    $entity_ids_in_chunk = [];

    foreach ($current_items as $identifier => $data) {
      $id = $data['id'];
      $fields_to_check = $data['fields'];
      $entity_ids_in_chunk[] = $id;

      if ($is_revisionable) {
        $entity = $storage->loadRevision($identifier);
      } else {
        $entity = $storage->load($identifier);
      }

      if (!$entity) {
        $sandbox['processed']++;
        continue;
      }

      $changed = FALSE;

      // Process all translations for this specific revision/entity.
      foreach ($entity->getTranslationLanguages() as $langcode => $language) {
        $translation = $entity->getTranslation($langcode);

        foreach ($fields_to_check as $field_name) {
          if (!$translation->hasField($field_name)) {
            continue;
          }

          $field_data = $translation->get($field_name)->getValue();
          $field_changed = FALSE;

          foreach ($field_data as $delta => $item) {
            if (!empty($item['value'])) {
              $text = $item['value'];

              // Only attempt replacements when the text contains at least one
              // of the search strings.
              $contains_search_string = FALSE;
              foreach ($search_strings as $search_string) {
                if (str_contains($text, $search_string)) {
                  $contains_search_string = TRUE;
                  break;
                }
              }

              if ($contains_search_string) {
                $updated_text = $text;
                foreach ($replacements as $old_class => $new_class) {
                  $updated_text = preg_replace('/(?<![\w-])' . preg_quote($old_class, '/') . '(?![\w-])/', $new_class, $updated_text);
                }

                if ($updated_text !== $text) {
                  $field_data[$delta]['value'] = $updated_text;
                  $field_changed = TRUE;
                  $changed = TRUE;
                }
              }
            }
          }

          if ($field_changed) {
            $translation->set($field_name, $field_data);
          }
        }
      }

      if ($changed) {
        // IMPORTANT: We do NOT create a new revision.
        // We update the existing revision in-place so that any parent nodes
        // or layout builder blocks referencing this specific revision ID
        // will immediately display the updated text.
        if ($is_revisionable) {
          $entity->setNewRevision(FALSE);
        }
        // Save the entity/revision.
        $entity->save();
      }

      $sandbox['processed']++;
    }

    // Clear the entity cache for the IDs we just touched to prevent memory leaks.
    if (!empty($entity_ids_in_chunk)) {
      $storage->resetCache(array_unique($entity_ids_in_chunk));
    }

    $processed_this_run += count($current_items);

    if ($processed_this_run >= $limit) {
      break;
    }
  }

  // Step 4: Tell Drupal what percentage is complete.
  $sandbox['#finished'] = ($sandbox['processed'] / $sandbox['total']);

  // Step 5: Final message when the batch is complete.
  if ($sandbox['#finished'] >= 1) {
    return 'Successfully processed ' . $sandbox['total'] . ' revisions for text replacement.';
  }
}
