<?php
/*
Plugin Name: MU Plugins Subdirectory Loader
Plugin URI: http://code.ctlt.ubc.ca
Description: Enables the loading of plugins sitting in mu-plugins (as folders).  This plugin has to installed as a 'must-use' plugin.
Version: 0.1.0
Author: iamfriendly, CTLT
Author URI: http://ubc.ca/
License: GPL v2 or later
*/

global $CTLT_Load_MU_Plugins_In_SubDir;
$CTLT_Load_MU_Plugins_In_SubDir = new CTLT_Load_MU_Plugins_In_SubDir();

/**
 * @author Richard Tape <@richardtape>
 */
class CTLT_Load_MU_Plugins_In_SubDir {

  // The transient name
  static $transientName = 'mu_plugins_in_sub_dir';

  /**
   * Set up our actions and filters
   */
  public function __construct() {
    // Load the plugins
    add_action( 'muplugins_loaded', array( $this, 'muplugins_loaded__requirePlugins' ) );

    // Adjust the MU plugins list table to show which plugins are MU
    add_action( 'after_plugin_row_subdir-loader.php', array( $this, 'after_plugin_row__addRows' ) );
  }

  /**
   * Will clear cache when visiting the plugin page in /wp-admin/.
   * Will also clear cache if a previously detected mu-plugin was deleted.
   *
   * @return array $plugins - an array of plugins in sub directories in the WPMU plugins dir
   */
  public static function WPMUPluginFilesInSubDirs() {
    // Do we have a pre-existing cache of the plugins? This checks in %prefix%_sitemeta
    $plugins = get_site_transient( static::$transientName );

    // If we do have a cache, let's check the plugin still exists
    if( $plugins !== false ) {
      foreach( $plugins as $pluginFile ) {
        if( ! is_readable( WPMU_PLUGIN_DIR . '/' . $pluginFile ) ) {
          $plugins = false;
          break;
        }
      }
    }

    if( false !== $plugins ) {
      return $plugins;
    }

    // Check we have access to get_plugins()
    if( ! function_exists( 'get_plugins' ) ) {
      require ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Start fresh
    $plugins = array();

    foreach( get_plugins( '/' . MUPLUGINDIR ) as $pluginFile => $pluginData ) {
      // skip files directly at root (WP already handles these)
      if( dirname( $pluginFile ) != '.' ) {
        $plugins[] = $pluginFile;
      }
    }

    // OK, set the transient and...
    set_site_transient( static::$transientName, $plugins );

    // ...ship
    return $plugins;
  }

  /**
   * Delete the transient if we're on an individual site's plugins page
   * Require each of the MU plugins
   */
  public function muplugins_loaded__requirePlugins() {
    // delete cache when viewing the plugins page in the dashboard
    if( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/wp-admin/plugins.php' ) !== false ) {
      delete_site_transient( static::$transientName );
    }

    // Now load each plugin in a subdir
    foreach( static::WPMUPluginFilesInSubDirs() as $pluginFile ) {
      require WPMU_PLUGIN_DIR . '/' . $pluginFile;
    }
  }

  /**
   * Quick and dirty way to display which plugins are MU and slightly adjust their layout
   * to show which ones are subdir or not
   */
  public function after_plugin_row__addRows() {
    foreach( static::WPMUPluginFilesInSubDirs() as $pluginFile ) {
      // Super stripped down version of WP_Plugins_List_Table
      $data   = get_plugin_data( WPMU_PLUGIN_DIR . '/' . $pluginFile, false );
      $name   = empty( $data['Name'] ) ? $pluginFile : $data['Name'];
      $desc   = empty( $data['Description'] ) ? '&nbsp;' : $data['Description'];
      $id     = sanitize_title( $name );

      echo static::getPluginRowMarkup( $id, $name, $desc );
    }
  }

  /**
   * Helper function to output a table row in the MU plugins list
   *
   * @param string $id - plugin ID (slug of the $name)
   * @param string $name - Name of the plugin
   * @param string $desc - The plugin's description
   *
   * @return string the <tr> markup for this plugin
   */
  public static function getPluginRowMarkup( $id, $name, $desc ) {
    $output = <<<HTML
<tr id="' . $id . '" class="active">
  <th scope="row" class="check-column"></th>
  <td class="plugin-title"><strong style="padding-left: 10px;">+&nbsp;&nbsp;' . $name . '</strong></td>
  <td class="column-description desc">
    <div class="plugin-description"><p>' . $desc . '</p></div>
  </td>
</tr>
HTML;

    return $output;
  }
}