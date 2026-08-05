<?php

/**
 * Synchronize Trash permissions with the Content Manager role.
 */
function illinois_framework_core_trash_admin_post_update_sync_trash_permissions(&$sandbox) {
  _illinois_framework_core_trash_admin_sync_role();
}
