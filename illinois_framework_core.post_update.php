<?php

/**
 * @file
 * Post update functions for Illinois Framework Core Module.
 */

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;

/**
 * Implements hook_removed_post_updates().
 */
function illinois_framework_core_removed_post_updates() {
  // The 6.x branch requires sites to be on the latest 5.x release before
  // upgrading, so all post-update hooks from the 5.x cycle have been removed.
  // See https://github.com/web-illinois/illinois_framework_theme/issues/1328.
  return [
    'illinois_framework_core_post_update_hide_cta_icon' => '6.0.0',
    'illinois_framework_core_post_update_cta_title_optional' => '6.0.0',
    'illinois_framework_core_post_update_remove_user_search_page' => '6.0.0',
    'illinois_framework_core_post_update_remove_help_search_page' => '6.0.0',
    'illinois_framework_core_post_update_remove_intro_home_fingerprint' => '6.0.0',
    'illinois_framework_core_post_update_remove_cta_fingerprint' => '6.0.0',
    'illinois_framework_core_post_update_replace_il_button_classes' => '6.0.0',
    'illinois_framework_core_post_update_replace_theme_button_solid_classes' => '6.0.0',
  ];
}

/**
 * Finds and replaces text strings across all formatted text fields.
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
    $sandbox['revisionable'] = [];
    $sandbox['total'] = 0;
    $sandbox['processed'] = 0;

    $database = \Drupal::database();
    $field_map = \Drupal::service('entity_field.manager')->getFieldMap();
    $text_field_types = ['text', 'text_long', 'text_with_summary'];

    foreach ($field_map as $entity_type => $fields) {
      $entity_type_def = \Drupal::entityTypeManager()->getDefinition($entity_type, FALSE);
      if (!$entity_type_def instanceof ContentEntityTypeInterface) {
        continue;
      }

      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      $is_revisionable = $entity_type_def->isRevisionable() && $storage instanceof RevisionableStorageInterface;
      $sandbox['revisionable'][$entity_type] = $is_revisionable;

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
          }
          catch (\Exception $e) {
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

    // We need to group by ID to reset cache effectively.
    $entity_ids_in_chunk = [];

    if (!empty($sandbox['revisionable'][$entity_type]) && $storage instanceof RevisionableStorageInterface) {
      $is_revisionable = TRUE;
      $entities = $storage->loadMultipleRevisions(array_keys($current_items));
    }
    else {
      $is_revisionable = FALSE;
      $entities = $storage->loadMultiple(array_keys($current_items));
    }

    foreach ($current_items as $identifier => $data) {
      $id = $data['id'];
      $fields_to_check = $data['fields'];
      $entity_ids_in_chunk[] = $id;
      $entity = $entities[$identifier] ?? NULL;

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

/**
 * Removes obsolete Bootstrap (b4_ and b5_) keys from the theme settings.
 *
 * The Illinois Framework theme no longer uses Bootstrap (its base theme is now
 * stable9), so the Bootstrap 4/5 scheme-selector settings are dead config. Left
 * in place they report "missing schema" now that their schema has been removed.
 */
function illinois_framework_core_post_update_remove_bootstrap_theme_settings() {
  $config = \Drupal::configFactory()->getEditable('illinois_framework_theme.settings');
  if ($config->isNew()) {
    return 'The illinois_framework_theme.settings config does not exist; nothing to clean up.';
  }

  $obsolete_keys = [
    'b4_top_container',
    'b4_body_schema',
    'b4_body_bg_schema',
    'b4_navbar_schema',
    'b4_navbar_bg_schema',
    'b4_footer_schema',
    'b4_footer_bg_schema',
    'b5_top_container',
    'b5_top_container_config',
    'b5_body_schema',
    'b5_body_bg_schema',
    'b5_navbar_schema',
    'b5_navbar_bg_schema',
    'b5_footer_schema',
    'b5_footer_bg_schema',
  ];

  $removed = [];
  foreach ($obsolete_keys as $key) {
    if ($config->get($key) !== NULL) {
      $config->clear($key);
      $removed[] = $key;
    }
  }

  if ($removed) {
    $config->save();
    return 'Removed obsolete Bootstrap theme settings: ' . implode(', ', $removed) . '.';
  }

  return 'No obsolete Bootstrap theme settings were found.';
}
