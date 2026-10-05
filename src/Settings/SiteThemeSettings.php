<?php
namespace AgileThemeTools\Settings;

use Omeka\Settings\AbstractTargetSettings;

/*
 * @class SiteThemeSettings
 * Extends AbstractTargetSettings, which provides Database CRUD access for settings values.
 * AbstractTargetSettings and its parent AbstractSettings run the getTableName() method to get the target settings
 *   table, and run getTargetIdColumnName to provide the table column that supplied the unique identifier that
 *   groups together settings.
 *
 *   In this case our settings are saved in the site_theme_setting table, and each setting key/pair is associated
 *   with the site_id for grouping.
 *
 * This class can be accessed via any controller through SiteThemeSettingsControllerPlugin, providing them with
 * ->set and ->get functions.
 */

class SiteThemeSettings extends AbstractTargetSettings
{
  public function getTableName()
  {
    return 'site_theme_setting';
  }

  public function getTargetIdColumnName()
  {
    return 'site_id';
  }
}
