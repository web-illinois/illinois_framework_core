<?php
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;

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
 * Replace 'il-button' with 'ilw-button' in all formatted text fields across all revisions.
 */
function illinois_framework_core_post_update_replace_il_button_classes(&$sandbox) {
  // Step 1: Initialize the sandbox on the first pass.
  if (!isset($sandbox['total'])) {
    $sandbox['revisions_to_process'] = [];
    $sandbox['total'] = 0;
    $sandbox['processed'] = 0;

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
            // Query all revisions that contain the old class in this field.
            $query = \Drupal::entityQuery($entity_type)
              ->condition($field_name . '.value', '%il-button%', 'LIKE')
              ->accessCheck(FALSE);

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
    return 'No entities found containing "il-button" in any formatted text fields.';
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

              if (str_contains($text, 'il-button')) {
                $replacements = [
                  'il-button' => 'ilw-button',
                  'il-white-blue' => 'ilw-theme-blue',
                  'il-white-orange' => 'ilw-theme-orange',
                  'il-blue' => 'ilw-theme-blue-1',
                  'il-orange' => 'ilw-theme-orange-1',
                ];

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
    return 'Successfully updated ' . $sandbox['total'] . ' revisions by replacing "il-button" with "ilw-button".';
  }
}

function illinois_framework_core_post_update_add_pargraph_spacer_blockreference_modifications(&$sandbox) {

  // 1. Locate the config/install directory of your module.
  $module_path = \Drupal::service('extension.list.module')->getPath('illinois_framework_core');
  $config_path = $module_path . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
  $source_storage = new FileStorage($config_path);

  // 2. Define all the configuration files related to your new paragraph.
  // Replace "my_new_paragraph" with your actual paragraph bundle machine name.
  $config_files = [
    'paragraphs.paragraphs_type.spacer',
    'field.storage.paragraph.field_size', // Example field storage
    'field.field.paragraph.spacer.field_size', // Example field instance
    'core.entity_form_display.paragraph.spacer.default',
    'core.entity_view_display.paragraph.spacer.default',
  ];

  $config_manager = \Drupal::service('config.manager');
  $entity_type_manager = \Drupal::service('entity_type.manager');

  foreach ($config_files as $config_name) {
    // Check if the configuration already exists to prevent overwriting active changes.
    if (\Drupal::configFactory()->get($config_name)->isNew()) {
      $config_data = $source_storage->read($config_name);

      if ($config_data) {
        // Determine the entity type from the configuration name prefix.
        $entity_type_id = $config_manager->getEntityTypeIdByName($config_name);

        if ($entity_type_id) {
          // Create and save the new configuration entity.
          $storage = $entity_type_manager->getStorage($entity_type_id);
          $entity = $storage->createFromStorageOverride($config_data);
          $entity->save();
        } else {
          // If it's simple configuration (not an entity), save it via config factory.
          \Drupal::configFactory()->getEditable($config_name)
            ->setData($config_data)
            ->save();
        }
      }
    }
  }
}
