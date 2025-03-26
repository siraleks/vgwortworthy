<?php

  /**
   * @package wp-worthy
   * @author Bernd Holzmueller <bernd@quarxconnect.de>
   * @author Jan P. Günther <jan.guenther@tiggerswelt.net>
   * @license GPLv3
   *
   * @wordpress-plugin
   * Plugin Name: Worthy - VG WORT Integration für Wordpress
   * Plugin URI: https://wp-worthy.de/
   * Description: Vereinfache die Arbeit mit VG WORT und verdiene einfacher Geld mit Deinen Texten als jemals zuvor.
   * Version: 1.7.4-1211172
   * Author: tiggersWelt.net
   * Author URI: https://tiggerswelt.net/
   * License: GPLv3
   * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
   * Text Domain: wp-worthy
   * Domain Path: /lang
   **/
  
  /**
   * Copyright (C) 2013-2022 Bernd Holzmueller <bernd@quarxconnect.de>
   * Copyright (C) 2022-2024 Innorize GmbH <info@innorize.gmbh>
   * 
   * This program is free software: you can redistribute it and/or modify
   * it under the terms of the GNU General Public License as published by
   * the Free Software Foundation, either version 3 of the License, or   
   * (at your option) any later version.
   * 
   * This program is distributed in the hope that it will be useful,
   * but WITHOUT ANY WARRANTY; without even the implied warranty of 
   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the  
   * GNU General Public License for more details.  
   *  
   * You should have received a copy of the GNU General Public License
   * along with this program.  If not, see <http://www.gnu.org/licenses/>.
   **/
  
  if (!defined ('WPINC'))
    die ('Please do not invoke this file directly');
  
  if (!interface_exists ('Throwable') && !class_exists ('Throwable'))
    class_alias ('Exception', 'Throwable');
  
  require_once (dirname (__FILE__) . '/qcWp.php');
  require_once (__DIR__ . '/class-wp-worthy-pixel.php');
  require_once (__DIR__ . '/class-wp-worthy-post.php');
  require_once (__DIR__ . '/class-wp-worthy-migration.php');
  require_once (__DIR__ . '/class-wp-worthy-premium.php');
  require_once (__DIR__ . '/class-wp-worthy-maintenance.php');
  require_once (dirname (__FILE__) . '/table/markers.php');
  require_once (dirname (__FILE__) . '/table/posts.php');
  
  class wp_worthy extends qcWp {
    /* Sections for admin-menu */
    const ADMIN_SECTION_OVERVIEW = 'overview';
    const ADMIN_SECTION_MARKERS = 'markers';
    const ADMIN_SECTION_POSTS = 'posts';
    const ADMIN_SECTION_CONVERT = 'convert';
    const ADMIN_SECTION_SETTINGS = 'settings';
    const ADMIN_SECTION_ADMIN = 'admin';
    const ADMIN_SECTION_PREMIUM = 'premium';
    
    /* Minimum length of posts to be relevant for VG WORT */
    /* public */ const MIN_LENGTH = 1800;
    /* public */ const EXTRA_LENGTH = 10000;
    const WARN_LIMIT = 1600; 
    
    /* Marker-Position on output */
    /* private */ const OUTPUT_BEFORE = 3;
    /* private */ const OUTPUT_START = 0;
    /* private */ const OUTPUT_MIDDLE = 1;
    /* private */ const OUTPUT_STOP = 2;
    /* private */ const OUTPUT_AFTER = 4;
    /* private */ const OUTPUT_DEFAULT = wp_worthy::OUTPUT_MIDDLE;
    
    /* Marker-Status */
    const MARKER_STATUS_UNCOUNTED = 0;
    const MARKER_STATUS_UNREACHED = 1;
    const MARKER_STATUS_PARTIAL = 2;
    const MARKER_STATUS_REACHED = 3;
    const MARKER_STATUS_REPORTED = 4;
    
    private static $defaultPixelStatis = [
      wp_worthy::MARKER_STATUS_UNREACHED,
      wp_worthy::MARKER_STATUS_PARTIAL,
      wp_worthy::MARKER_STATUS_REACHED,
      wp_worthy::MARKER_STATUS_REPORTED,
    ];
    
    /* Lazy-Loading settings */
    /* private */ const LAZY_LOADING_PREVENT = 0;
    /* private */ const LAZY_LOADING_ENFORCE = 1;
    /* private */ const LAZY_LOADING_AUTO = 2;
    /* private */ const LAZY_LOADING_DEFAULT = wp_worthy::LAZY_LOADING_PREVENT;
    
    /* Does VG WORT support anonymous pixels at the moment? */
    const ENABLE_ANONYMOUS_MARKERS = false;
    
    /* Meta-Key for post-length */
    /* public */ const META_LENGTH = 'worthy_counter';
    
    /* Status-Feedback for admin-menu */
    private $adminStatus = array ();
    
    /* Status of main-query */
    private $onMainQuery = false;
    
    /* Status of REST-Query */
    private $onRESTQuery = false;
    
    /* Set of markers on output */
    private $pixelsOut = [];
    
    /* Set of delayed pixels */
    private $pixelsDelayed = array ();
    
    /* Handle of our generic script */
    private $scriptHandle = null;
    
    /* Handle of our notice-script */
    private $noticeScriptHandle = null;
    
    /* Cached RPC-Interfaces for premium-users */
    private $premiumUsers = [];
    
    // {{{ singleton
    /**
     * Create and access a single instance of wp-worthy
     * 
     * @access public
     * @return wp_worthy
     **/
    public static function singleton () {
      static $self = null;
      
      if (!$self)
        $self = new wp_worthy ();
      
      return $self;
    }
    // }}}
    
    // {{{ __construct
    /**
     * Create a new worthy-plugin
     * 
     * @access friendly
     * @return void
     **/
    function __construct () {
      // Do some generic stuff first
      parent::__construct (__FILE__);
      
      // Setup database-schema
      wp_worthy_pixel::setupDatabase ();
      
      // Register our stylesheet
      if (is_admin ())
        $this->addStylesheet ('wp-worthy.css');
      else
        // We don't want an additional request to a stylesheet as this one is really small.
        // Unfortunately Wordpress does not allow us to enqueue/add this snippet to wp_styles()
        // without having an additional file added.
        add_action (
          'wp_head',
          function () {
            echo
              '<meta name="referrer" content="no-referrer-when-downgrade" />', "\n",
              '<style type="text/css"> #wp-worthy-pixel { line-height: 1px; height: 1px; margin: 0; padding: 0; overflow: hidden; } </style>', "\n";
          }
        );
      
      // Install our menu on admin
      $this->addAdminMenu (
        'Worthy - VG WORT Integration for Wordpress',
        'Worthy',
        'publish_posts',
        __CLASS__ . '-' . $this::ADMIN_SECTION_OVERVIEW,
        'assets/wp-worthy-small.svg',
        array ($this, 'adminMenuOverview'),
        array ($this, 'adminMenuPrepare'),
        array (
          array ('Overview', 'Overview', 'publish_posts', __CLASS__ . '-' . $this::ADMIN_SECTION_OVERVIEW, array ($this, 'adminMenuOverview')),
          array ('Markers', 'Markers', 'publish_posts', __CLASS__ . '-' . $this::ADMIN_SECTION_MARKERS, array ($this, 'adminMenuMarkers'), array ($this, 'adminMenuMarkersPrepare')),
          array ('Posts', 'Posts', 'publish_posts', __CLASS__ . '-' . $this::ADMIN_SECTION_POSTS, array ($this, 'adminMenuPosts'), array ($this, 'adminMenuPostsPrepare')),
          array ('Import / Export', 'Import / Export', 'publish_posts', __CLASS__ . '-' . $this::ADMIN_SECTION_CONVERT, array ($this, 'adminMenuConvert'), array ($this, 'adminMenuConvertPrepare')),
          array ('Settings', 'Settings', 'publish_posts', __CLASS__ . '-' . $this::ADMIN_SECTION_SETTINGS, array ($this, 'adminMenuSettings'), array ($this, 'adminMenuSettingsPrepare')),
          array ('Admin', 'Admin', 'manage_options', __CLASS__ . '-' . $this::ADMIN_SECTION_ADMIN, array ($this, 'adminMenuAdmin'), array ($this, 'adminMenuAdminPrepare')),
          array ('Premium', 'Premium', 'publish_posts', __CLASS__ . '-' . $this::ADMIN_SECTION_PREMIUM, array ($this, 'adminMenuPremium'), array ($this, 'adminMenuPremiumPrepare')),
        ),
        '25.20050505'
      );
      
      // Load counter-javascript for post-editor
      if (is_admin ()) {
        $this->scriptHandle = $this->addScript (
          'wp-worthy.js',
          [
            'characters' => 'Characters',
            'counter' => 'Characters (VG WORT)',
            'accept_tac' => 'You have to accept the terms of service and privacy statement before you can continue',
            'no_goods' => 'You don\'t have selected anything to buy, pressing this button does not make sense',
            'syncronizing' => 'Synchronizing "%s"...',
            'sync_error' => 'There was an error while syncronising the markers',
            'not_counted' => 'not counted',
            'not_qualified' => 'not qualified',
            'partial_qualified' => 'partial qualified',
            'qualified' => 'qualified',
            'reported' => 'reported',
            'shortcode_filter' => get_option ('wp-worthy-filter-shortcodes', false),
            # 'nonceOverview' => wp_create_nonce ($this::ADMIN_SECTION_OVERVIEW),
            # 'noncePremium' => wp_create_nonce ($this::ADMIN_SECTION_PREMIUM),
            'Yes' => 'Yes',
            'No' => 'No',
            'aiDisclaimerTitle' => 'Statement on the use of AI',
            'aiDisclaimer' => 'Ich bestätige hiermit, dass das gemeldete Werk meine persönliche geistige Schöpfung darstellt (vgl. § 2 Abs. 2 UrhG). Insbesondere habe ich dieses Werk nicht ausschließlich durch Verwendung von KI-Systemen erstellt.',
          ],
          'wpWorthyLang'
        );
        
        $this->noticeScriptHandle = $this->addScript ('wp-worthy-notices.js', array (), '', true);
        
        add_action (
          'enqueue_block_editor_assets',
          function () {
            wp_enqueue_script (
              'wp-worthy-gutenberg',
              plugins_url ('wp-worthy-gutenberg.js', __FILE__),
              [ $this->scriptHandle ]
            );
            
            // Push user-preferences
            wp_add_inline_script (
              'wp-worthy-gutenberg',
              'window.wp.worthy.autoAssign=' . (get_user_meta (get_current_user_id (), 'wp-worthy-auto-assign-markers', true) == 1 ? 'true' : 'false') . ';' .
              'window.wp.worthy.postTypes=' . json_encode ($this->getUserPostTypes ()) . ';'
            );
            
            // Add transaltions for gutenberg
            $translateWords = [
              'Characters',
              'Qualified',
              'Not qualified',
              'Marker',
              'Ignore',
              'Assign',
              'Assigned',
              'Don\'t assign',
              'Assign a marker',
              'Don\'t assign a marker',
              'Ignore this post',
              'Type of post',
              'Normal text',
              'Lyric',
              'This is your destinaion! Write a post that respects all rules by VG WORT to be reported and rewarded.',
              'If unsure you mag assign a marker later on, e.g. during a text-review.',
              'If you don\'t want to report this post to VG WORT, just ignore it.',
              'A normal text is just a usual text where all default rules of VG WORT apply. There is nothing to respect except the typed characters.',
              'For lyric there are special rules, e.g. thoses posts don\'t depend on length.',
            ];
            
            $i18n = [
              'domain' => 'wp-worthy',
              'lang'   => is_admin () ? get_user_locale () : get_locale (),
            ];
            
            foreach ($translateWords as $translateWord)
              $i18n [$translateWord] = [ __ ($translateWord, $this->textDomain) ];
            
            wp_add_inline_script (
              'wp-i18n',
              'wp.i18n.setLocaleData (' . json_encode ($i18n) . ', "wp-worthy");'
            );
          }
        );
      }
      
      // Add ourself to dashboard
      add_filter ('dashboard_glance_items', array ($this, 'dashboardContent'));
      
      // Hook in to posts/pages tables
      add_filter ('manage_posts_columns', array ($this, 'adminPostColumnHeaders'));
      add_filter ('manage_pages_columns', array ($this, 'adminPostColumnHeaders'));
      add_action ('manage_posts_custom_column', array ($this, 'adminPostColumns'), 10, 2);
      add_action ('manage_pages_custom_column', array ($this, 'adminPostColumns'), 10, 2);
      
      // Append custom option to publish-box
      add_action ('post_submitbox_misc_actions', array ($this, 'adminPostPublishBox'));
      
      // Hook into user-profile editor
      add_action ('user_edit_form_tag', array ($this, 'adminUserProfileForm'));
      add_action ('edit_user_profile', array ($this, 'adminUserProfile'));
      add_action ('personal_options_update', array ($this, 'adminUserProfileUpdate'));
      add_action ('edit_user_profile_update', array ($this, 'adminUserProfileUpdate'));
      
      // Hook into save-/deleteprocess
      add_action ('admin_notices', array ($this, 'adminAddPostBanner'));
      add_action ('edit_page_form', array ($this, 'adminAddPostBanner'));
      add_action ('edit_form_advanced', array ($this, 'adminAddPostBanner'));
      add_action ('save_post', array ($this, 'adminSavePost'), 10, 3);
      
      add_action ('delete_user', array ($this, 'adminDeleteUser'), 10, 2);
      
      add_action ('admin_notices', array ($this, 'adminNoticeUnknownPixels'));
      
      // Add VG WORT pixel to output
      add_action ('loop_start',                    array ($this, 'onLoopStart'));
      add_action ('loop_end',                      array ($this, 'onLoopEnd'));
      add_filter ('the_content',                   array ($this, 'onContent'));
      add_filter ('the_content_export',            array ($this, 'onContent'));
      add_filter ('the_content_feed',              array ($this, 'onContent'));
      add_action ('wp_footer',                     array ($this, 'onFooter'));
      add_action ('export_wp',                     array ($this, 'onExport'));
      add_filter ('rest_request_before_callbacks', array ($this, 'onREST'), 10, 2);
      
      add_action ('admin_post_wp-worthy-settings-personal', array ($this, 'saveSettingsPersonal'));
      add_action ('admin_post_wp-worthy-settings-sharing', array ($this, 'saveSettingsSharing'));
      add_action ('admin_post_wp-worthy-settings-publisher', array ($this, 'saveSettingsPublisher'));
      add_action ('admin_post_wp-worthy-post-types', array ($this, 'saveUserPostSettings'));
      add_action ('admin_post_wp-worthy-admin-common-settings', array ($this, 'saveAdminCommonSettings'));
      add_action ('admin_post_wp-worthy-admin-output-settings', array ($this, 'saveAdminOutputSettings'));
      add_action ('admin_post_wp-worthy-admin-content-settings', array ($this, 'saveAdminContentSettings'));
      add_action ('admin_post_wp-worthy-set-orphaned', array ($this, 'setOrphanedAdopter'));
      add_action ('admin_post_wp-worthy-admin-share', array ($this, 'setSharingAdmin'));
      add_action ('admin_post_wp-worthy-import-csv', array ($this, 'importMarkers'));
      
      if ($this::ENABLE_ANONYMOUS_MARKERS)
        add_action ('admin_post_wp-worthy-claim-and-import-csv', array ($this, 'importClaimMarkers'));
      
      add_action ('admin_post_wp-worthy-report-csv', [ $this, 'reportMarkers' ]); // CVE 2023-24417: Not relevant as it only generates output
      add_action ('admin_post_wp-worthy-export-csv', [ $this, 'exportUnusedMarkers' ]);
      add_action ('admin_post_wp-worthy-migrate-preview', [ $this, 'migratePostsPreview' ]); // CVE 2023-24417: Not relevant as it's only a redirect
      add_action ('admin_post_wp-worthy-bulk-migrate', array ($this, 'migratePostsBulk'));
      add_action ('admin_post_wp-worthy-migrate', array ($this, 'migratePosts'));
      add_action ('admin_post_wp-worthy-marker-inquiry', array ($this, 'searchPrivateMarkers'));
      add_action ('admin_post_wp-worthy-reindex', array ($this, 'reindexPosts'));
      add_action ('admin_post_wp-worthy-bulk-assign', array ($this, 'assignPosts'));
      add_action ('admin_post_wp-worthy-bulk-ignore', array ($this, 'ignorePosts'));
      add_action ('admin_post_wp-worthy-bulk-burn-post-pixel', array ($this, 'burnPostPixel'));
      add_action ('admin_post_wp-worthy-feedback', array ($this, 'doFeedback'));
      add_action ('admin_post_wp-worthy-premium-signup', array ($this, 'premiumSignup'));
      add_action ('admin_post_wp-worthy-premium-sync-status', array ($this, 'premiumSyncStatus'));
      add_action ('admin_post_wp-worthy-premium-sync-pixels', array ($this, 'premiumSyncPixels'));
      add_action ('admin_post_wp-worthy-premium-import', array ($this, 'premiumImportMarkers'));
      add_action ('admin_post_wp-worthy-premium-import-private', array ($this, 'premiumImportPrivate'));
      add_action ('admin_post_wp-worthy-premium-create-webareas', array ($this, 'premiumCreateWebareas'));
      add_action ('admin_post_wp-worthy-premium-report-posts-preview', [ $this, 'premiumReportPostsPreview' ]); // CVE 2023-24417: Not relevant as it's only a redirect
      add_action ('admin_post_wp-worthy-premium-report-posts', array ($this, 'premiumReportPosts'));
      add_action ('admin_post_wp-worthy-premium-select-server', array ($this, 'premiumDebugSetServer'));
      add_action ('admin_post_wp-worthy-premium-drop-session', array ($this, 'premiumDebugDropSession'));
      add_action ('admin_post_wp-worthy-premium-drop-registration', [ $this, 'premiumDropRegistration' ]);
      add_action ('admin_post_wp-worthy-premium-purchase', array ($this, 'premiumPurchase'));
      add_action ('admin_post_wp-worthy-dismiss-unknown-reportable-pixels', array ($this, 'dismissUnknownPixels'));
      
      // FIXME: This feature may be removed when enough time has passed like in 2021/Q3
      add_action ('admin_post_wp-worthy-assign-site', array ($this, 'multisiteAssignSite'));
            
      add_action ('admin_post_-1', [ $this, 'redirectNoAction' ]); // CVE 2023-24417: Not relevant as it's only a redirect
      add_action ('wp-worthy-cron-daily', [ $this, 'checkRandomPosts' ]);
      add_action ('wp-worthy-cron-daily', [ 'wp_worthy_premium', 'cronDaily' ]);
      add_action ('wp-worthy-cron-daily', [ 'wp_worthy_migration', 'regenerateMigrationStats' ]);
      
      // Check for an action on posts-list
      if (
        isset ($_GET ['action']) &&
        ($_GET ['action'] == 'wp-worthy-apply') &&
        (intval ($_GET ['post_id']) > 0)
      ) {
        $postID = (int)$_GET ['post_id'];
        
        add_action (
          'plugins_loaded',
          function () use ($postID) {
            wp_worthy_pixel::assignToPost (
              wp_worthy_post::fromID ($postID)
            );
          }
        );
        
        unset ($_GET ['action'], $_GET ['post_id']);
      }
      
      // Perform some migration-checks
      if (($version = get_option ('wp-worthy-version', 0)) == 0) {
        $version =  get_option ('worthy_version', 0);
        
        if ($version > 0) {
          // Rename the old option
          update_option ('wp-worthy-version', $version);
          delete_option ('worthy_version');
          
          // Make sure our cron is set up
          if (!wp_next_scheduled ('wp-worthy-cron-daily'))
            wp_schedule_event (time (), 'daily', 'wp-worthy-cron-daily');
        }
      }
      
      if ($version < 1) {
        $userID = $this->getUserID ();
        
        // Convert options to user-metas
        foreach (array ('worthy_markers_imported_csv', 'worthy_premium_markers_imported', 'worthy_premium_username', 'worthy_premium_password', 'worthy_premium_server', 'worthy_premium_status', 'worthy_premium_status_updated', 'worthy_premium_markers_updated', 'worthy_premium_marker_updates', 'worthy_premium_markers_updated') as $k)
          if ($v = get_option ($k, false))
            update_user_meta ($userID, $k, $v);
        
        // Remove obsoleted options
        foreach (array ('worthy_premium_username', 'worthy_premium_password', 'worthy_premium_server', 'worthy_premium_status', 'worthy_premium_status_updated', 'worthy_premium_markers_updated', 'worthy_premium_session') as $k)
          delete_option ($k);
        
        // Update to Version 1
        update_option ('wp-worthy-version', 1);
      }
      
      if ($version < 2) {
        // Release markers that are assigned to revisions
        foreach ($this->getSiteIDs () as $siteId) {
          if (is_multisite ())
            switch_to_blog ($siteId);
          
          $GLOBALS ['wpdb']->query (
            'UPDATE `' . $this->getTablename ('worthy_markers', 0) . '` ' .
            'SET `postid`=NULL ' .
            'WHERE ' .
              '`siteid`="' . (int)$siteId . '" AND ' .
              '`postid` IN (SELECT ID FROM `' . $this->getTablename ('posts', $siteId) . '` WHERE post_type="revision")'
          );
          
          if (is_multisite ())
            restore_current_blog ();
        }
        
        // Update to Version 2
        update_option ('wp-worthy-version', 2);
      }
      
      // Worthy 1.5.5
      if ($version < 3) {
        // Fix invalid burned markers
        $GLOBALS ['wpdb']->query (
          'UPDATE `' . $this->getTablename ('worthy_markers', 0) . '` ' .
          'SET ' .
            '`siteid`="0", ' .
            '`postid`=NULL ' .
          'WHERE `postid`=0'
        );
        
        // Make sure our cron is set up
        if (!wp_next_scheduled ('wp-worthy-cron-hourly'))
          wp_schedule_event (time (), 'hourly', 'wp-worthy-cron-hourly');
        # TODO: Add Premium-Status-Check to hourly cron
        
        // Update to Version 3
        update_option ('wp-worthy-version', 3);
      }
      
      // Worthy 1.6.1
      if ($version < 4) {
        if (!is_multisite ())
          $GLOBALS ['wpdb']->query (
            'UPDATE `' . $this->getTablename ('worthy_markers', 0) . '` ' .
            'SET ' .
              '`siteid`="' . get_current_blog_id () . '" ' .
            'WHERE ' .
              '`postid`>0 AND ' .
              '`siteid`="0"'
          );
        
        // Update to Version 4
        update_option ('wp-worthy-version', 4);
      }
      
      // Worthy 1.6.3.2
      if ($version < 5) {
        // Lazy-Loading is now a tri-state
        if (!!get_option ('wp-worthy-prevent-lazy-loading', true))
          update_option ('wp-worthy-lazy-loading', self::LAZY_LOADING_PREVENT);
        else
          update_option ('wp-worthy-lazy-loading', self::LAZY_LOADING_AUTO);
        
        delete_option ('wp-worthy-prevent-lazy-loading');
        
        // Update to Version 5
        update_option ('wp-worthy-version', 5);
      }
      
      # Last migration-step: 2021-09-13
      
      // Create post-class
      wp_worthy_post::init ();
    }
    // }}}
    
    // {{{ onActivate
    /**
     * Common installation stuff
     * 
     * @access public
     * @return void
     **/
    public static function onActivate () {
      // Make sure our cron is set up
      if (!wp_next_scheduled ('wp-worthy-cron-daily'))
        wp_schedule_event (time (), 'daily', 'wp-worthy-cron-daily');
      
      // Register uninstall-handler
      register_uninstall_hook (__FILE__, array ('wp_worthy', 'onUninstall'));
    }
    // }}}
    
    // {{{ onUninstall
    /**
     * Common deinstallation stuff
     * 
     * @access public
     * @return void
     **/
    public static function onUninstall () {
      // Remove our cron-event
      wp_clear_scheduled_hook ('wp-worthy-cron-daily');
      
      // Remove options
      $removeOptions = array (
        'wp-worthy-version',
        'wp-worthy-enable-account-sharing',
        'wp-worthy-default-account',
        'wp-worthy-enable-burn',
        'wp-worthy-enable-webarea',
        'wp-worthy-overlong-titles',
        'wp-worthy-embed-on-feed',
        'wp-worthy-embed-on-rest',
        'wp-worthy-embed-on-export',
        'wp-worthy-marker-position',
        'wp-worthy-prevent-lazy-loading',
        'wp-worthy-lazy-loading',
        'wp-worthy-pixel-classes',
        'wp-worthy-locale-filter',
        'wp-worthy-filter-shortcodes',
        'worthy_markers_imported_csv',
        'worthy_premium_markers_claimed',
        'wp-worthy-check-missing',
        'worthy_premium_markers_imported',
        'worthy_premium_marker_updates',
      );
      
      foreach ($removeOptions as $removeOption)
        delete_option ($removeOption);
      
      // Remove user-metas
      $removeUserMetas = [
        'worthy_premium_username',
        'worthy_premium_password',
        'worthy_premium_server',
        'worthy_premium_status',
        'worthy_premium_session',
        'worthy_premium_markers_updated',
        'worthy_premium_markers_imported',
        'worthy_premium_marker_updates',
        'worthy_premium_markers_claimed',
        'worthy_premium_status_updated',
        'worthy_markers_imported_csv',
        'wp-worthy-auto-assign-markers',
        'wp-worthy-disable-output',
        'wp-worthy-default-server',
        'wp-worthy-authorid',
        'wp-worthy-allow-account-sharing',
        'wp-worthy-post-types',
        'wp-worthy-unknown-reportable-pixels',
        'wp-worthy-overlong-titles',
        'wp-worthy-forename',
        'wp-worthy-lastname',
        'wp-worthy-cardid',
        'wp-worthy-autocreate-webranges',
      ];
      
      $GLOBALS ['wpdb']->query (
        'DELETE FROM `' . $GLOBALS ['wpdb']->usermeta . '` ' .
        'WHERE meta_key IN ("' . implode ('","', $removeUserMetas) . '")'
      );
      
      // Remove post-metas
      $removePostMetas = array (
        # 'worthy_ignore',
        # 'worthy_lyric',
        'wp-worthy-duplicate',
        self::META_LENGTH,
      );
      
      if (!is_multisite ())
        $GLOBALS ['wpdb']->query (
          'DELETE FROM `' . $GLOBALS ['wpdb']->postmeta . '` ' .
          'WHERE meta_key IN ("' . implode ('","', $removePostMetas) . '")'
        );
      else
        foreach (get_sites () as $site) {
          switch_to_blog ($site->ID);
          
          $GLOBALS ['wpdb']->query (
            'DELETE FROM `' . $GLOBALS ['wpdb']->postmeta . '` ' .
            'WHERE meta_key IN ("' . implode ('","', $removePostMetas) . '")'
          );
          
          restore_current_blog ();
        }
    }
    // }}}
    
    // {{{ onLoopStart
    /**
     * Callback: WP-Query-Loop started
     * Check if the current WP-Query-Loop is the main-query
     * 
     * @param WP_Query $theQuery
     * 
     * @access public
     * @return void
     **/
    public function onLoopStart (WP_Query $theQuery) /* : void */ {
      // Check if this is the main-query
      if (!$theQuery->is_main_query ())
        return;
      
      // Remember that we are on the main-query
      $this->onMainQuery = true;
      
      // Check wheter to output the pixel now
      $pixelPosition = get_option ('wp-worthy-marker-position', self::OUTPUT_DEFAULT);
      
      if (
        ($pixelPosition == self::OUTPUT_BEFORE) &&
        ($theQuery->is_single || $theQuery->is_page)
      )
        $this->pixelCheck ($theQuery->post);
    }
    // }}}
    
    // {{{ onLoopEnd
    /**
     * Callback: WP-Query-Loop finished
     * Check if the current WP-Query-Loop was the main-query
     * and make sure a marker is on the output
     * 
     * @param WP_Query $theQuery
     * 
     * @access public
     * @return void
     **/
    public function onLoopEnd (WP_Query $theQuery) /* : void */ {
      if (!$theQuery->is_main_query ())
        return;
      
      $this->onMainQuery = false;
      
      if (
        $theQuery->is_single ||
        $theQuery->is_page
      )
        $this->pixelCheck ($theQuery->post);
    }
    // }}}
    
    // {{{ onContent
    /**
     * Callback: Process Content-Output
     * Append a marker to content if neccessary
     * 
     * @param string $Content
     * 
     * @access public
     * @return string
     **/
    public function onContent (/* string */ $theContent) /* : string */ {
      // Retrive name of current filter
      $currentFilter = current_filter ();
      
      // Check if there is an export running
      if (
        ($currentFilter == 'the_content_export') &&
        ($GLOBALS ['wp_the_query']->post)
      )
        return $this->pixelAdd ($theContent, $GLOBALS ['wp_the_query']->post, 'export');
      
      // Skip this function if we are not on the main-query
      /* [accelerated-mobile-pages] Does not use main-query, we let it pass if it's an AMP-Endpoint */
      if (
        !$this->onMainQuery &&
        !$this->onRESTQuery &&
        (!function_exists ('is_amp_endpoint') || !is_amp_endpoint ())
      )
        return $theContent;
      
      // Only output markers on single pages
      if (
        (($currentFilter != 'the_content_feed') || !get_option ('wp-worthy-embed-on-feed', false)) &&
        !($GLOBALS ['wp_the_query']->is_single || $GLOBALS ['wp_the_query']->is_page) &&
        (!$this->onRESTQuery || !get_option ('wp-worthy-embed-on-rest', false))
      )
        return $theContent;
      
      // Try to append marker to content
      return $this->pixelAdd ($theContent, ($this->onRESTQuery ? $GLOBALS ['post'] : $GLOBALS ['wp_the_query']->post), null);
    }
    // }}}
    
    // {{{ onFooter
    /**
     * Callback: Footer of page is being generated. Check if we have some delayed pixels to output
     * 
     * @access public
     * @return void
     **/
    public function onFooter () /* : void */ {
      // Output delayed pixels
      foreach ($this->pixelsDelayed as $pixelDelayed)
        echo $this->pixelAdd (null, $pixelDelayed, null);
      
      // Destroy delayed pixels
      $this->pixelsDelayed = null;
    }
    // }}}
    
    // {{{ onExport
    /**
     * Callback: A Wordpress-Export is being made
     * 
     * @access public
     * @return void
     **/
    public function onExport () /* : void */ {
      // Catch posts on export
      if (get_option ('wp-worthy-embed-on-export', false))
        add_action ('the_post', [ $this, 'onExportPost' ]);
    }
    // }}}
    
    // {{{ onExportPost
    /**
     * Callback: Catch a post during export-phase
     * 
     * @param WP_Post $thePost
     * 
     * @access public
     * @return void
     **/
    public function onExportPost (WP_Post $thePost) /* : void */ {
      $GLOBALS ['wp_the_query']->post = $thePost;
    }
    // }}}
    
    // {{{ onREST
    /**
     * Callback: Check if we are on a REST-Query and should prepare to embed a marker
     * 
     * @param void $theResponse UNUSED
     * @param array $restHandler
     * 
     * @access public
     * @return void
     **/
    public function onREST (/* UNUSED */ $theResponse, /* array */ $restHandler) /* : void */ {
      // Make sure we are facing post-controller
      $this->onRESTQuery =
        isset ($restHandler ['callback']) &&
        is_array ($restHandler ['callback']) &&
        (count ($restHandler ['callback']) == 2) &&
        ($restHandler ['callback'][0] instanceof WP_REST_Posts_Controller) &&
        ($restHandler ['callback'][1] == 'get_item');
    }
    // }}}
    
    // {{{ pixelCheck
    /**
     * Make sure there is a marker on the output
     * 
     * @param WP_Post $thePost
     * 
     * @access private
     * @return void
     **/
    private function pixelCheck (WP_Post $thePost) /* : void */ {
      // Make sure the pixel was on output
      if (isset ($this->pixelsOut [$thePost->ID]))
        return;
      
      // Check if there is a pixel assigned
      if (!is_object ($thePixel = wp_worthy_post::fromObject ($thePost)->getPixel ()))
        return;
      
      // Output pixel
      if (headers_sent ())
        echo $this->pixelAdd (null, $thePost, null);
      elseif (is_array ($this->pixelsDelayed))
        $this->pixelsDelayed [] = $thePost;
      else
        trigger_error ('Failed to add pixel to output', E_USER_WARNING);
    }
    // }}}
    
    // {{{ pixelAdd
    /**
     * Append VG WORT pixel to output if neccessary
     * 
     * @param string $theContent (optional)
     * @param WP_Post $thePost
     * @param enum $outputMode (optional)
     * 
     * @access private
     * @return string
     **/
    private function pixelAdd (/* ?string */ $theContent, WP_Post $thePost, /* string */ $outputMode = null) /* : string */ {
      // Check if we are a real content-filter
      if ($theContent === null) {
        $onContent = false;
        $theContent = '';
      } else
        $onContent = true;
      
      // Check where to output the pixel
      $pixelPosition = get_option ('wp-worthy-marker-position', self::OUTPUT_DEFAULT);
      
      if ($this->onRESTQuery) {
        if ($pixelPosition == self::OUTPUT_BEFORE)
          $pixelPosition = self::OUTPUT_START;
        elseif ($pixelPosition == self::OUTPUT_AFTER)
          $pixelPosition = self::OUTPUT_END;
      }
      
      if (
        $onContent &&
        (($pixelPosition == self::OUTPUT_BEFORE) || ($pixelPosition == self::OUTPUT_AFTER))
      )
        return $theContent;
      
      // Mark the pixel as processed
      $this->pixelsOut [$thePost->ID] = true;
      
      // Check if there should be a pixel on the output
      if (get_post_meta ($thePost->ID, 'worthy_ignore', true) == 1)
        return $theContent;
      
      // Check if there is a pixel assigned
      if (!is_object ($thePixel = wp_worthy_post::fromObject ($thePost)->getPixel ()))
        return $theContent;
      
      // Check if the user disabled pixel-output
      if (get_user_meta ($this->getUserID ($thePixel->userId ? $thePixel->userId : $thePost->post_author), 'wp-worthy-disable-output', true) == 1)
        return $theContent;
      
      // Check locale
      $filterLocales = get_option ('wp-worthy-locale-filter', '');
      
      if (strlen ($filterLocales) > 0) {
        $currentLocale = $this->normalizeLocale (get_locale ());
        $localeMatched = true;
        
        foreach (explode (' ', $filterLocales) as $filterLocale) {
          $localeMatched = true;
          
          foreach ($this->normalizeLocale ($filterLocale) as $localePart=>$localeFilter)
            if (
              ($localeFilter !== null) &&
              (strcasecmp ($localeFilter, $currentLocale [$localePart]) != 0)
            ) {
              $localeMatched = false;
              
              break;
            }
          
          if ($localeMatched)
            break;
        }
        
        if (!$localeMatched)
          return $theContent;
      }
      
      // Generate HTML-Code for pixel
      if (($pixelCode = $this->pixelCode ($thePixel, $thePost, $outputMode)) === false)
        return $theContent;
      
      // Check if there is a pixel inside
      $inlinePixels = [];
      
      if (($cleanedContent = $this->removeInlineMarkers ($theContent, true, $inlinePixels)) !== null) {
        // Remove our pixels from removed inlined pixels
        $pixelURL = $this->pixelURL ($thePixel, null, $thePost);
        
        if (isset ($inlinePixels [$pixelURL]))
          unset ($inlinePixels [$pixelURL]);
        
        // Check if there are other pixels left and mark as duplicate
        if (count ($inlinePixels) > 0)
          add_post_meta ($thePost->ID, 'wp-worthy-duplicate', 1, true);
        
        // Change content to cleaned up version
        $theContent = $cleanedContent;
      } elseif ($theContent !== null)
        delete_post_meta ($thePost->ID, 'wp-worthy-duplicate');
      
      // Find the right place for the pixel
      if (
        ($pixelPosition == self::OUTPUT_START) ||
        ($pixelPosition == self::OUTPUT_BEFORE) ||
        ($pixelPosition == self::OUTPUT_AFTER)
      )
        $p = 0;
      elseif ($pixelPosition == self::OUTPUT_STOP)
        $p = strlen ($theContent);
      elseif (
        ($pixelPosition == self::OUTPUT_MIDDLE) &&
        (($p = strpos ($theContent, '<span id="more-')) !== false)
      ) {
        $p = strpos ($theContent, '</span>', $p) + 7;
        
        // Check if the more-marker is embeded into a paragraph, if yes: skip this paragraph as it will mess up the template
        if (
          (($p2 = strpos ($theContent, '</p>', $p)) !== false) &&
          (($p3 = strpos ($theContent, '<p>', $p)) !== false) &&
          ($p2 < $p3)
        )
          $p = $p2 + 4;
      } elseif (($p = strrpos ($theContent, '</p>')) !== false)
        $p += 4;
      else
        $p = strlen ($theContent);
      
      // Insert marker into output
      return substr ($theContent, 0, $p) . $pixelCode . substr ($theContent, $p);
    }
    // }}}
    
    // {{{ pixelURL
    /**
     * Retrive the URL for a marker
     * 
     * @param object $thePixel
     * @param string $outputMode (optional)
     * @param WP_Post $thePost (optional)
     * 
     * @access public
     * @return string
     **/
    public function pixelURL (wp_worthy_pixel $thePixel, /* string */ $outputMode = null, WP_Post $thePost = null) /* : ?string */ {
      // Determine which protocol-scheme to use
      if (
        (isset ($_SERVER ['HTTPS']) && ($_SERVER ['HTTPS'] == 'on')) ||
        ($outputMode == 'amp')
      )
        $urlScheme = 'https';
      else
        $urlScheme = 'http';
      
      // Determine which server to use
      if ($thePixel->server)
        $urlHost = $thePixel->server;
      elseif ($thePixel->url && ($pixelHost = parse_url ($thePixel->url, PHP_URL_HOST)))
        $urlHost = $pixelHost;
      elseif ($userHost = get_user_meta ($this->getUserID ($thePixel->userId ? $thePixel->userId : $thePost->post_author), 'wp-worthy-default-server', true))
        $urlHost = $userHost;
      else
        $urlHost = 'vg0' . (($thePixel->id % 9) + 1) . '.met.vgwort.de';
      
      // Check wheter to fix the server
      /**
       * Prosodia seems to include parts from the path in the
       * server-field that wasn't taken into account when importing
       * from there
       **/
      if (($p = strpos ($urlHost, '/')) !== false)
        $urlHost = substr ($urlHost, 0, $p);
      
      // Find public marker
      if ($thePixel->public)
        $publicCode = $thePixel->public;
      elseif (!$thePixel->url || !($publicCode = parse_url ($thePixel->url,  PHP_URL_PATH)))
        return null;
      
      // Generate full URL
      return $urlScheme . '://' . $urlHost . '/na/' . basename ($publicCode);
    }
    // }}}
    
    // {{{ pixelCode
    /**
     * Create HTML-Code for a given marker
     * 
     * @param wp_worthy_pixel $thePixel
     * @param WP_Post $thePost
     * @param enum $outputMode (optional)
     * 
     * @access private
     * @return string
     **/
    private function pixelCode (wp_worthy_pixel $thePixel, WP_Post $thePost, /* string */ $outputMode = null) /* : ?string */ {
      // Auto-detect mode
      if ($outputMode === null) {
        // Set mode to normal by default
        $outputMode = 'normal';
        
        // Check for AMP
        if (
          (function_exists ('is_amp_endpoint') && is_amp_endpoint ()) ||
          (method_exists ('AMPHTML', 'instance') && method_exists ('AMPHTML', 'is_amp') && ($singleton = AMPHTML::instance ()) && $singleton->is_amp ())
        )
          $outputMode = 'amp';
      }
      
      // Generate URL
      if (($url = $this->pixelURL ($thePixel, $outputMode, $thePost)) === null)
        return null;
      
      // Return HTML/AMP-Code depending on mode
      if ($outputMode == 'amp')
        return '<amp-pixel src="' . esc_attr ($url) . '"></amp-pixel>';
      
      static $lazyLoadValues = [
        self::LAZY_LOADING_PREVENT => 'eager',
        self::LAZY_LOADING_ENFORCE => 'lazy',
        self::LAZY_LOADING_AUTO => 'auto',
      ];
      
      $lazyLoading = (int)get_option ('wp-worthy-lazy-loading', self::LAZY_LOADING_DEFAULT) % 2;
      $imageClasses = array ();
      
      if ($outputMode !== 'export') {
        $imageClasses [] = 'wp-worthy-pixel-img';
        
        if ($lazyLoading === self::LAZY_LOADING_PREVENT)
          $imageClasses [] = 'skip-lazy';
      } else
        $imageClasses [] = 'wp-worthy-export';
      
      $imageClasses = array_merge ($imageClasses, explode (' ', get_option ('wp-worthy-pixel-classes', '')));
      
      $imageElement =
        '<img ' .
          'class="' . esc_attr (implode (' ', $imageClasses)) . '" ' .
          'src="' . esc_attr ($url) . '" ' .
          'loading="' . (isset ($lazyLoadValues [$lazyLoading]) ? $lazyLoadValues [$lazyLoading] : 'auto') . '" ' .
          ($lazyLoading == self::LAZY_LOADING_PREVENT ? 'data-no-lazy="1" data-skip-lazy="1" ' : '') .
          'height="1" ' .
          'width="1" ' .
          'alt="" ' .
        '/>';
      
      if ($outputMode == 'export')
        return $imageElement;
      
      return
        '<div id="wp-worthy-pixel">' .
          $imageElement .
          (get_option ('wp-worthy-premium-counter', false) ? '<img src="https://api.wp-worthy.de/c/' . esc_attr ($thePixel->public) . '" data-no-lazy="1" height="1" width="1" />' : '') .
        '</div>';
    }
    // }}}
    
    // {{{ normalizeLocale
    /**
     * Try to normalize a locale and return parts as an array
     * 
     * @param string $localeCode
     * 
     * @access private
     * @return array
     **/
    private function normalizeLocale (string $localeCode) : array {
      $normalizedLocale = [
        'language' => null,
        'territory' => null,
        'codeset' => null,
        'modifier' => null,
      ];
      
      if (preg_match ('/^([a-zA-Z0-9\-]+)(_([a-zA-Z0-9\-]+))?(\.([a-zA-Z0-9\-]+))?(@([a-zA-Z0-9\-]+))?$/', $localeCode, $localeMatches) !== false)
        foreach ([ 1 => 'language', 3 =>'territory', 5 => 'codeset', 7 => 'modifier' ] as $matchedIndex=>$localePart)
          if (
            isset ($localeMatches [$matchedIndex]) &&
            (strlen ($localeMatches [$matchedIndex]) > 0)
          )
            $normalizedLocale [$localePart] = $localeMatches [$matchedIndex];
      
      return $normalizedLocale;
    }
    // }}}
    
    // {{{ getRelevantUnassignedCount
    /**
     * Retrive the number of (indexed) posts that are relevant for worthy but do not have a marker assigned
     * 
     * @param int $siteId (optional)
     * 
     * @access private
     * @return int
     **/
    private function getRelevantUnassignedCount ($siteId = null) {
      return array_sum (
        $this->querySites (
          'SELECT COUNT(DISTINCT p.`ID`) ' .
          'FROM ' .
            '`%tablePostMeta` pml, ' .
            '`%tablePosts` p ' .
              'LEFT JOIN `%tablePostMeta` pmi ON (p.`ID`=pmi.`post_id` AND pmi.`meta_key`="worthy_ignore") ' .
          'WHERE ' .
            'pml.`meta_key`="' . $this::META_LENGTH . '" AND ' .
            'CONVERT(pml.`meta_value`, UNSIGNED INTEGER)>=' . $this::MIN_LENGTH . ' AND ' .
            'pml.`post_id`=p.`ID` AND ' .
            'p.`post_type` IN ("' . implode ('","', $this->getUserPostTypes ()) . '") AND ' .
            'p.`post_status`="publish" AND ' .
            'p.`ID` NOT IN (SELECT `postid` FROM `' . $this->getTablename ('worthy_markers', 0) . '` WHERE `siteid`="%siteId") AND ' .
            '((pmi.`meta_value` IS NULL) OR NOT (pmi.`meta_value`="1"))',
          $siteId
        )
      );
    }
    // }}}
    
    // {{{ getAvailableMarkersCount
    /**
     * Retrive number of available markers
     * 
     * @param int $userID (optional)
     * 
     * @access private
     * @return int
     **/
    private function getAvailableMarkersCount ($userID = null) {
      return wp_worthy_pixel::availablePixels ($this->getUserIDs ($userID));
    }
    // }}}
    
    // {{{ getReportablePixelsCount
    /**
     * Retrive the number of pixels that may be reported using worthy
     * 
     * @param int $userID (optional)
     * @param int $siteId (optional)
     * 
     * @access private
     * @return array
     **/
    private function getReportablePixelsCount ($userID = null, $siteId = null) {
      // Check local cache
      static $resultCache = array ();
      
      $userIDs = implode ('","', $this->getUserIDs ($userID));
      
      if (isset ($resultCache [$userIDs . $siteId]))
        return $resultCache [$userIDs . $siteId];
      
      // Query the database
      $siteResults = $this->querySites (
        'SELECT ' .
          '"%siteId" AS `siteId`, ' .
          'COUNT(*) AS `reportable` ' .
        'FROM ' .
          '`' . wp_worthy_pixel::getTableName () . '` pixel, ' .
          '`%tablePosts` p ' .
        'WHERE ' .
          'pixel.`reportable`="1" AND ' .
          'pixel.`siteid`="%siteId" AND ' .
          'pixel.`postid`=p.`ID` AND ' .
          'p.`post_type` IN ("' . implode ('","', $this->getUserPostTypes ()) . '") AND ' .
          'pixel.`userid` IN ("0", "' . $userIDs . '")',
        $siteId
      );
      
      $queryResult = array (
        'all' => 0,
        'this' => 0,
        'other' => 0,
      );
      
      foreach ($siteResults as $siteResult) {
        if (count ($siteResult) == 0)
          continue;
        
        $queryResult ['all'] += $siteResult [0]->reportable;
        
        if (
          is_network_admin () ||
          (get_current_blog_id () == $siteResult [0]->siteId)
        )
          $queryResult ['this'] += $siteResult [0]->reportable;
        else
          $queryResult ['other'] += $siteResult [0]->reportable;
      }
      
      // Push to local cache
      $resultCache [$userIDs . $siteId] = $queryResult;
      
      return $queryResult;
    }
    // }}}
    
    // {{{ getSiteIDs
    /**
     * Retrive all site-IDs for a given network
     * 
     * @access public
     * @return array
     **/
    public function getSiteIDs () {
      if (!is_multisite ())
        return array (get_current_blog_id ());
      
      $siteIds = [];
      
      foreach (get_sites () as $site)
        $siteIds [] = (int)$site->id;
      
      return $siteIds;
    }
    // }}}
    
    // {{{ querySites
    /**
     * Execute an SQL-Query for a site or all sites
     * 
     * @param string $queryTemplate
     * @param int $siteId (optional)
     * 
     * @access public
     * @return array
     **/
    public function querySites ($queryTemplate, $siteId = null) {
      $resultSet = array ();
      
      // Determine which sites to query
      if ($siteId !== null)
        $siteIds = [ (int)$siteId ];
      else
        $siteIds = $this->getSiteIDs ();
      
      // Check all sites
      foreach ($siteIds as $siteId) {
        if (is_multisite ())
          switch_to_blog ($siteId);
        
        $siteResult = $GLOBALS ['wpdb']->get_results (
          str_replace (
            [
              '%tablePosts',
              '%tablePostMeta',
              '%siteId',
            ],
            [
              $this->getTablename ('posts'),
              $this->getTablename ('postmeta'),
              $siteId,
            ],
            $queryTemplate
          )
        );
        
        if (is_multisite ())
          restore_current_blog ();
        
        // Compress the result if a single column was returned
        if (
          is_array ($siteResult) &&
          (count ($siteResult) == 1) &&
          (count ((array)$siteResult [0]) == 1)
        ) {
          $siteResult [0] = (array)$siteResult [0];
          $siteResult = array_shift ($siteResult [0]);
        }
        
        $resultSet [$siteId] = $siteResult;
      }
      
      return $resultSet;
    }
    // }}}
    
    // {{{ querySiteTables
    /**
     * Retrive a set of table-names derived from all networks/sites
     * 
     * @param string $tableName
     * @param bool $checkExistance (optional)
     * @param array $siteIds (optional)
     * 
     * @access public
     * @return array
     **/
    public function querySiteTables (string $tableName, bool $checkExistance = false, array $siteIds = null) : array {
      // Check for tables
      $tableNames = [];
      
      foreach ($this->getSiteIDs () as $siteId) {
        // Check wheter to include this site
        if (
          $siteIds &&
          !in_array ($siteId, $siteIds)
        )
          continue;
        
        // Switch network/site
        if (is_multisite ())
          switch_to_blog ($siteId);
        
        // Construct table-name
        if (isset ($GLOBALS ['wpdb']->$tableName))
          $checkTableName = $GLOBALS ['wpdb']->$tableName;
        else
          $checkTableName = $GLOBALS ['wpdb']->prefix . $tableName;
        
        if (is_multisite ())
          restore_current_blog ();
        
        // Check for existance if requested
        if ($checkExistance) {
          // Silence WPDB
          $showErrors = $GLOBALS ['wpdb']->show_errors;
          $suppressErrors = $GLOBALS ['wpdb']->suppress_errors;

          $GLOBALS ['wpdb']->show_errors = false;
          $GLOBALS ['wpdb']->suppress_errors = true;
          
          // Try to query the table
          $isExisting = ($GLOBALS ['wpdb']->get_var ('SELECT COUNT(*) FROM `' . $checkTableName . '`') !== null);
          
          // Unsilence WPDB
          $GLOBALS ['wpdb']->show_errors = $showErrors;
          $GLOBALS ['wpdb']->suppress_errors = $suppressErrors;
          
          if (!$isExisting)
           continue;
        }
        
        // Push to result
        $tableNames [] = [
          'name'   => $checkTableName,
          'siteid' => $siteId,
        ];
      }
      
      return $tableNames;
    }
    // }}}
    
    // {{{ getTablename
    /**
     * Retrive the real (prefixed) name of a table
     * 
     * @param string $tableName
     * @param int $siteId (optional)
     * 
     * @access public
     * @return string
     **/
    public function getTablename (string $tableName, int $siteId = null) : string {
      // Handle edge-cases
      if (
        ($siteId === null) ||
        ($siteId === 0) ||
        !is_multisite ()
      ) {
        if (isset ($GLOBALS ['wpdb']->$tableName))
          return $GLOBALS ['wpdb']->$tableName;
        else
          return ($siteId === 0 ? $GLOBALS ['wpdb']->base_prefix : $GLOBALS ['wpdb']->prefix) . $tableName;
      }
      
      // Switch site/network and generate the table-name
      switch_to_blog ($siteId);
      
      $tableName = $GLOBALS ['wpdb']->prefix . $tableName;
      
      restore_current_blog ();
      
      return $tableName;
    }
    // }}}
    
    // {{{ getAdminMenuBadge
    /**
     * Output Badge on admin-menu if there are posts to be reported
     * 
     * @access protected
     * @return int
     **/
    protected function getAdminMenuBadge () {
      $reportablePixels = $this->getReportablePixelsCount ();
      
      if ($reportablePixels ['all'] > 0)
        return $reportablePixels ['all'];
    }
    // }}}
    
    // {{{ packPostID
    /**
     * Pack a post-id into a string
     * 
     * @param array $postID
     * 
     * @access private
     * @return string
     **/
    private function packPostID ($postID) {
      return $postID ['siteid'] . '/' . $postID ['postid'];
    }
    // }}}
    
    // {{{ packPostIDs
    /**
     * Pack a set of post-ids into an array
     * 
     * @param array $postIDs
     * 
     * @access private
     * @return array
     **/
    private function packPostIDs ($postIDs) {
      foreach ($postIDs as $index=>$postID)
        $postIDs [$index] = $this->packPostID ($postID);
      
      return $postIDs;
    }
    // }}}
    
    // {{{ unpackPostID
    /**
     * Extract site- and post-ID from a string
     * 
     * @param string $postID
     * 
     * @access public
     * @return array
     +*/
    public function unpackPostID ($postID) {
      if (($p = strpos ($postID, '/')) === false)
        return null;
      
      return array (
        'siteid' => (int)substr ($postID, 0, $p),
        'postid' => (int)substr ($postID, $p + 1),
      );
    }
    // }}}
    
    // {{{ unpackPostIDs
    /**
     * Extract site- and post-IDs from an array of strings
     * 
     * @param array $postIDs
     * 
     * @access public
     * @return array
     **/
    public function unpackPostIDs ($postIDs) {
      if (!is_array ($postIDs))
        return array ();
      
      foreach (array_unique ($postIDs) as $index=>$postID) {
        if (($p = strpos ($postID, '/')) !== false)
          $postIDs [$index] = array (
            'siteid' => (int)substr ($postID, 0, $p),
            'postid' => (int)substr ($postID, $p + 1),
          );
        else
          unset ($postIDs [$index]);
      }
      
      return $postIDs;
    }
    // }}}
    
    // {{{ linkSection
    /**
     * Generate a link to admin-section of this plugin
     * 
     * @param enum $Section
     * @param array $Parameters (optional)
     * @param bool $asPost (optional)
     * @param bool $forceNetwork (otional)
     * 
     * @access public
     * @return string
     **/
    public function linkSection ($Section, $Parameters = null, $asPost = false, $forceNetwork = false) {
      // Generate the Base-URL for the section
      static $isNetworkAdmin = null;
      
      if (
        ($isNetworkAdmin === null) &&
        ($isNetworkAdmin = is_multisite ())
      ) {
        $networkBase = admin_url ('network/');
        $isNetworkAdmin = (is_network_admin () || isset ($_GET ['wp-worthy-network-admin']) || (substr (wp_get_referer (), 0, strlen ($networkBase)) == $networkBase));
      }
      
      $URL = admin_url (
        ($asPost ?
          'admin-post.php?' . ($isNetworkAdmin || $forceNetwork ? 'wp-worthy-network-admin&' : '') . 'wp-worthy-nonce=' . urlencode (wp_create_nonce ($Section)) . '&' :
          ($isNetworkAdmin || $forceNetwork ? 'network/' : '') . 'admin.php?'
        ) .
        'page=' . urlencode (__CLASS__ . '-' . $Section)
      );
      
      // Append parameters
      if (is_array ($Parameters)) {
        unset ($Parameters ['page']);
        
        $URL = add_query_arg ($Parameters, $URL);
      }
      
      // Return the URL
      return $URL;
    }
    // }}}
    
    // {{{ inlineAction
    /**
     * Embed a form as simple link to trigger some changing-actions
     * 
     * @param string $Section
     * @param string $Action
     * @param string $Caption
     * @param array $Parameter (optional)
     * 
     * @access private
     * @return void
     **/
    private function inlineAction ($Section, $Action, $Caption, $Parameter = array ()) {
      if (is_array ($Parameter)) {
        $buf = '';
        
        foreach ($Parameter as $Key=>$Value)
          $buf .= '<input type="hidden" name="' . esc_attr ($Key) . '" value="' . esc_attr ($Value) . '" />';
        
        $Parameter = $buf;
      } else
        $Parameter = '';
      
      return
        '<form class="worthy_inline" method="post" action="' . $this->linkSection ($Section, null, true) . '">' .
          $Parameter .
          '<button type="submit" name="action" value="' . esc_attr ($Action) . '">' . $Caption . '</button>' .
        '</form>';
    }
    // }}}
    
    // {{{ inlineActions
    /**
     * Embed a form as simple link to trigger some changing-actions
     * 
     * @param string $Section
     * @param array $Actions
     * @param array $Parameter (optional)
     * 
     * @access private
     * @return void
     **/
    private function inlineActions ($Section, $Actions, $Parameter = array ()) {
      $buf =
        '<form class="worthy_inline" method="post" action="' . $this->linkSection ($Section, null, true) . '">';
            
      if (is_array ($Parameter))
        foreach ($Parameter as $Key=>$Value)
          $buf .= '<input type="hidden" name="' . esc_attr ($Key) . '" value="' . esc_attr ($Value) . '" />';
      
      foreach ($Actions as $Action=>$Caption)
        $buf .= '<button type="submit" name="action" value="' . esc_attr ($Action) . '">' . $Caption . '</button><br />';
      
      return
          $buf .
        '</form>';
    }
    // }}}
    
    // {{{ getUserID
    /**
     * Retrive the primary ID of the current user we work for
     * 
     * @param int $userID (optional)
     * @param int $stopAt (optional)
     * 
     * @access public
     * @return int
     **/
    public function getUserID ($userID = null, $stopAt = null) {
      // Check if we should always use the current user (legacy)
      if ($userID === true)
        return intval (get_current_user_id ());
      
      // Check wheter to start with the current user
      if ($userID === null)
        $userID = intval (get_current_user_id ());
      
      $eUserID = $userID;
      
      // Check if this account shares from another one (if enabled)
      if (
        (get_option ('wp-worthy-enable-account-sharing', '1') == 1) ||
        ($stopAt !== null)
      ) {
        $seenUsers = [ $eUserID ];
        
        while (($oID = intval (get_user_meta ($eUserID, 'wp-worthy-authorid', true))) > 0) {
          // Check if the user allows sharing
          if (get_user_meta ($oID, 'wp-worthy-allow-account-sharing', true) == '0')
            break;
          
          // Check if this user was already seen
          if (isset ($seenUsers [$oID])) {
            trigger_error ('Loop detected in account-sharing');
            
            break;
          }
          
          // Make sure the referenced user is valid
          if (get_userdata ($oID) === false) {
            // Remove stale reference
            delete_user_meta ($eUserID, 'wp-worthy-authorid');
            
            break;
          }
          
          // Set the new ID
          $eUserID = $oID;
          $seenUsers [$eUserID] = true;
          
          if ($eUserID === $stopAt)
            break;
        }
      }
      
      // Check if this user should fall back to a default user
      if (
        ($eUserID == $userID) &&
        (($defaultAccount = get_option ('wp-worthy-default-account', 0)) > 0)
      ) {
        static $hasOwnPixels = array ();
        
        if (!isset ($hasOwnPixels [$eUserID]))
          $hasOwnPixels [$eUserID] = ($GLOBALS ['wpdb']->get_var (
            'SELECT COUNT(*) ' .
            'FROM `' . $this->getTablename ('worthy_markers', 0) . '` ' .
            'WHERE userid="' . (int)$eUserID . '"'
          ) > 0);
        
        if (!$hasOwnPixels [$eUserID])
          return $defaultAccount;
      }
      
      // Return the result
      return $eUserID;
    }
    // }}}
    
    // {{{ getUserIDs
    /**
     * Retrive user-IDs that are assigned to the current or a given user
     * 
     * @param int $userID (optional)
     * @param int $stopAt (optional)
     * 
     * @access public
     * @return array
     **/
    public function getUserIDs ($userID = null, $stopAt = null) {
      # TODO
      return array ($this->getUserID ($userID, $stopAt));
    }
    // }}}
    
    // {{{ getUserIDforPost
    /**
     * Retrive a user-id based on a given post
     * 
     * @param mixed $Post
     * 
     * @access public
     * @return int
     **/
    public function getUserIDforPost ($Post) {
      if (!is_object ($Post))
        $Post = get_post ($Post);
      
      return $this->getUserID (($Post && isset ($Post->post_author) ? $Post->post_author : null));
    }
    // }}}
    
    // {{{ getSharingUsers
    /**
     * Retrive a list of all wordpress-users including worthy-sharing-information
     * 
     * @access public
     * @return array
     **/
    public function getSharingUsers () {
      // Check for a cached list
      static $userList = null;
      
      if ($userList !== null)
        return $userList;
      
      // Find roles for query
      $userRoles = [];
      
      foreach (wp_roles ()->roles as $roleName=>$userRole)
        if (
          ($userRole = get_role ($roleName)) &&
          $userRole->has_cap ('edit_posts')
        )
          $userRoles [] = $roleName;
      
      // Query users
      $userQuery = new WP_User_Query ([
        'fields' => 'all_with_meta',
        'role__in' => $userRoles,
        'orderby' => 'display_name',
      ]);
      
      $userMetas = $GLOBALS ['wpdb']->get_results (
        'SELECT ' .
          'u.`ID`, ' .
          'ma.`meta_value` AS `vgwort_username`, ' .
          'mp.`meta_value` AS `allows_sharing`, ' .
          'ms.`meta_value` AS `shares_from` ' .
        'FROM `' . $this->getTablename ('users') . '` u ' .
          'LEFT JOIN `' . $this->getTablename ('usermeta') . '` ms ON (u.`ID`=ms.`user_id` AND ms.`meta_key`="wp-worthy-authorid") ' .
          'LEFT JOIN `' . $this->getTablename ('usermeta') . '` mp ON (u.`ID`=mp.`user_id` AND mp.`meta_key`="wp-worthy-allow-account-sharing") ' .
          'LEFT JOIN `' . $this->getTablename ('usermeta') . '` ma ON (u.`ID`=ma.`user_id` AND ma.`meta_key`="worthy_premium_username") ' .
        'WHERE u.`ID` IN ("' . implode ('", "', array_map ('intval', array_keys ($userQuery->results))) . '")',
        OBJECT_K
      );
      
      $userList = $userQuery->results;
      
      foreach ($userMetas as $userMeta)
        if (isset ($userList [$userMeta->ID])) {
          $userList [$userMeta->ID]->vgwort_username = $userMeta->vgwort_username;
          $userList [$userMeta->ID]->allows_sharing = $userMeta->allows_sharing;
          $userList [$userMeta->ID]->shares_from = $userMeta->shares_from;
        }
      
      return $userList;
    }
    // }}}
    
    // {{{ getUserPostTypes
    /**
     * Retrive a set of post-types to consider for a given (or the current) user
     * 
     * @param int $User (optional)
     * 
     * @access public
     * @return array
     **/
    public function getUserPostTypes ($User = null) {
      // Try to retrive the current setting
      $Result = get_user_meta (($User === null ? get_current_user_id () : $User), 'wp-worthy-post-types', true);
      
      // Make sure we have an initial/minimal setting
      if (
        !is_array ($Result) ||
        (count ($Result) == 0)
      )
        $Result = array ('post', 'page');
      
      return $Result;
    }
    // }}}
    
    // {{{ dashboardContent
    /**
     * Output some values on the dashbord "at a glance"-Section
     * 
     * @access public
     * @return void
     **/
    public function dashboardContent () {
      // Check some premium stuff
      if ($this->hasPremium ()) {
        // Check if there are messages pending
        if (is_array ($Status = $this->premiumUpdateStatus ())) {
          if (
            isset ($Status ['MessagePending']) &&
            $Status ['MessagePending']
          )
            echo
              '<li class="wp-worthy-dashboard-message">',
                '<a href="https://tom.vgwort.de" target="_blank">',
                  __ ('Messages at VG WORT', $this->textDomain),
                '</a>',
              '</li>';
          
          if (
            isset ($Status ['Ready']) &&
            !$Status ['Ready']
          )
            echo
              '<li class="wp-worthy-dashboard-message-warning">',
                '<a href="https://tom.vgwort.de" target="_blank">',
                  __ ('Premium is not working', $this->textDomain),
                '</a>',
              '</li>';
        }
        
        // Check if there is something to report
        $reportablePixels = $this->getReportablePixelsCount ();
        
        if ($reportablePixels ['all'] > 0) {
          if (
            is_multisite () &&
            !is_network_admin ()
          ) {
            if ($reportablePixels ['this'] > 0)
              echo
                '<li class="wp-worthy-dashboard-reportable">',
                  '<a href="', $this->linkSection ($this::ADMIN_SECTION_POSTS, array ('wp-worthy-filter-marker' => 'sr')), '">',
                    sprintf (
                      _n (
                        '<strong>%d pixel</strong> may be reported on this site',
                        '<strong>%d pixels</strong> may be reported on this site',
                        $reportablePixels ['this'],
                        $this->textDomain
                      ),
                      $reportablePixels ['this']
                    ),
                  '</a>',
                '</li>';
            
            if ($reportablePixels ['other'] > 0)
              echo
                '<li class="wp-worthy-dashboard-reportable">',
                  '<a href="', $this->linkSection ($this::ADMIN_SECTION_POSTS, array ('wp-worthy-filter-marker' => 'sr'), false, true), '">',
                    sprintf (
                      _n (
                        '<strong>%d pixel</strong> may be reported on other sites',
                        '<strong>%d pixels</strong> may be reported on other sites',
                        $reportablePixels ['other'],
                        $this->textDomain
                      ),
                      $reportablePixels ['other']
                    ),
                  '</a>',
                '</li>';
          } else
            echo
              '<li class="wp-worthy-dashboard-reportable">',
                '<a href="', $this->linkSection ($this::ADMIN_SECTION_POSTS, array ('wp-worthy-filter-marker' => 'sr')), '">',
                  sprintf (
                    _n (
                      '<strong>%d pixel</strong> may be reported',
                      '<strong>%d pixels</strong> may be reported',
                      $reportablePixels ['all'],
                      $this->textDomain
                    ),
                    $reportablePixels ['all']
                  ),
                '</a>',
              '</li>';
        }
      }
      
      // Check if there are relevant posts without a marker assigned
      if (($c = $this->getRelevantUnassignedCount (get_current_blog_id ())) > 0)
        echo
          '<li class="wp-worthy-dashboard-unassigned">',
            '<a href="', $this->linkSection ($this::ADMIN_SECTION_POSTS, array ('wp-worthy-filter-marker' => 0, 'wp-worthy-filter-length' => 1, 'wp-worthy-filter-site' => get_current_blog_id ())), '">',
              sprintf (_n ('%d relevant for VG WORT', '%d relevant for VG WORT', $c, $this->textDomain), $c),
            '</a>',
          '</li>';
      
      // Count available markers for this user
      $sum = 0;
      
      foreach ($GLOBALS ['wpdb']->get_results ('SELECT userid, count(*) As count FROM `' . $this->getTablename ('worthy_markers', 0) . '` WHERE postid IS NULL AND userid IN ("0", "' . implode ('","', $this->getUserIDs ()) . '") AND (status IS NULL OR status<1) AND NOT (disabled>0) GROUP BY userid') as $UserMarkers)
        if ($UserMarkers->userid == 0)
          echo
            '<li class="wp-worthy-dashboard-unused">',
              '<a href="', $this->linkSection ($this::ADMIN_SECTION_MARKERS, array ('orderby' => 'postid', 'order' => 'asc')), '">',
                sprintf (_n ('%d unused general marker', '%d unused general markers', $UserMarkers->count, $this->textDomain), $UserMarkers->count),
              '</a>',
            '</li>';
        else
          $sum += $UserMarkers->count;
      
      echo
        '<li class="wp-worthy-dashboard-unused">',
          '<a href="', $this->linkSection ($this::ADMIN_SECTION_MARKERS, array ('orderby' => 'postid', 'order' => 'asc')), '">',
            sprintf (_n ('%d unused marker', '%d unused markers', $sum, $this->textDomain), $sum),
          '</a>',
        '</li>';
    }
    // }}}
    
    // {{{ adminDeleteUser
    /**
     * Hook: A user is being removed from wordpress
     * 
     * @param int $userID The ID of the user being removed
     * @param int $newUserID (optional) The ID of the user that should be assigned to existing content
     * 
     * @accesus public
     * @return void
     **/
    public function adminDeleteUser ($userID, $newUserID = null) {
      // If content should be removed, remove unused pixels and
      // disable pixels that were assigned to a post
      if ($newUserID === null) {
        // Remove unused pixels
        $GLOBALS ['wpdb']->delete (
          $this->getTablename ('worthy_markers', 0),
          [
            'userid' => $userID,
            'postid' => null,
          ],
          [ '%d', null ]
        );
        
        // Disable used pixels
        $GLOBALS ['wpdb']->update (
          $this->getTablename ('worthy_markers', 0),
          [
            'userid' => -$userID,
            'disabled' => 1,
            'siteid' => 0,
            'postid' => null,
          ],
          [ 'userid' => $userID ],
          [ '%d', '%d', '%d', null ],
          [ '%d' ]
        );
        
        // Remove shared-mappings for other users to the removed user
        $GLOBALS ['wpdb']->delete (
          $GLOBALS ['wpdb']->usermeta,
          [
            'meta_key' => 'wp-worthy-authorid',
            'meta_value' => $userID,
          ],
          [ '%s', '%d' ]
        );
      
      // Just assign pixels to another user
      } else {
        // Assign pixels to new owner
        $GLOBALS ['wpdb']->update (
          $this->getTablename ('worthy_markers', 0),
          [ 'userid' => $newUserID ],
          [ 'userid' => $userID ],
          [ '%d' ],
          [ '%d' ]
        );
        
        // Update shared-mappings to new user
        $GLOBALS ['wpdb']->update (
          $GLOBALS ['wpdb']->usermeta,
          [ 'meta_value' => $newUserID ],
          [
            'meta_key' => 'wp-worthy-authorid',
            'meta_value' => $userID,
          ],
          [ '%d' ],
          [ '%s', '%d' ]
      );
      }
    }
    // }}}
    
    // {{{ adminPostColumnHeaders
    /**
     * Append custom column-headers to post/pages-table
     * 
     * @param array $defaults
     * 
     * @access public
     * @return array
     **/
    public function adminPostColumnHeaders ($defaults) {
      $defaults ['worthy'] = 'Worthy';
       
      return $defaults;
    }
    // }}}
    
    // {{{ getPostLink
    /**
     * Retrive a frontend-link for a given post
     * 
     * @param mixed $postID
     * @param int $siteId (optional)
     * 
     * @access public
     * @return string
     **/
    public function getPostLink ($postID, int $siteId = null) : string {
      if (
        ($siteId !== null) &&
        is_multisite ()
      )
        switch_to_blog ($siteId);
      
      $postLink = get_permalink ($postID);
      
      if (
        ($siteId !== null) &&
        is_multisite ()
      )
        restore_current_blog ();
      
      return $postLink;
    }
    // }}}
    
    // {{{ getPostAdminLink
    /**
     * Retrive a backend-link for a given post
     * 
     * @param mixed $postID
     * @param int $siteId (optional)
     * 
     * @access public
     * @return string|null
     **/
    public function getPostAdminLink ($postID, int $siteId = null) {
      // Make sure our callee is admin
      if (!is_admin ())
        return null;
      
      if ($siteId === null)
        $siteId = get_current_blog_id ();
      
      if (!is_object ($thePost = wp_worthy_post::fromID ($postID, $siteId)))
        return null;
      
      // Collect all information
      $postTitle = $thePost->getTitle ();
      
      // Generate the link
      $url = get_admin_url ($siteId) . 'post.php?post=' . $postID . '&action=edit';
      
      // Return the HTML
      return '<a href="' . esc_attr ($url) . '">' . esc_html ($postTitle) . ' (' . $postID . ')' . '</a>';
    }
    // }}}
    
    // {{{ postHasMarker
    /**
     * Check if a given post has a marker assigned
     * 
     * @param mixed $post
     * @param bool $extended (optional) Check for foreign markers as well
     * 
     * @access public
     * @return bool
     **/
    public function postHasMarker ($postID, $extended = false) {
      // Convert Post to Post-ID
      if (is_object ($postID))
        $postID = $postID->ID;
      
      // Check if we have a marker assigned
      $loc = is_object ($this->getPixelByPostID ($postID));
      
      if (
        $loc ||
        !$extended
      )
        return $loc;
      
      $postID = array (
        'siteid' => get_current_blog_id (),
        'postid' => $postID,
      );
      
      $siteIds = [
        get_current_blog_id (),
      ];
      
      // Check for foreign markers
      return (
        (count (wp_worthy_migration::migrateInline (false, true, [ $postID ], $siteIds)) > 0) ||
        (count (wp_worthy_migration::migrateByMeta ([ 'vgwpixel' ], false, true, [ $postID ], $siteIds)) > 0) ||
        (count (wp_worthy_migration::migrateByMeta ([ get_option ('wp_vgwortmetaname', 'wp_vgwortmarke') ], false, true, [ $postID ], $siteIds)) > 0) ||
        (count (wp_worthy_migration::migrateProsodia (false, true, [ $postID ], $siteIds)) > 0) ||
        (count (wp_worthy_migration::migrateTlVGWort (false, true, [ $postID ], $siteIds)) > 0)
      );
    }
    // }}}
    
    // {{{ adminPostColumns
    /**
     * Generate output on post/pages-table
     * 
     * @param string $postColumn
     * @param int $postId
     * 
     * @access public
     * @return void
     **/
    public function adminPostColumns (/* string */ $postColumn, /* int */ $postId) /* : void */ {
      // Check if our column is requested 
      if (
        !isset ($GLOBALS ['post']) ||
        !is_object ($GLOBALS ['post']) ||
        ($postColumn != 'worthy')
      )
        return;
      
      $postTable = new wp_worthy_table_posts ($this);
      
      echo $postTable->column_status ($GLOBALS ['post'], false);
      
      // Get an instance of the post
      $thePost = wp_worthy_post::fromObject ($GLOBALS ['post']);
      $thePixel = $thePost->getPixel ();
      
      // Check if this post-type is handled by worthy
      if (
        !$thePixel &&
        !in_array ($GLOBALS ['post']->post_type, $this->getUserPostTypes ())
      )
        return;
      
      // Output length of post
      $Class = 'wp-worthy-neutral';
      
      if ($thePost->isRelevant ()) {
        $Class = 'wp-worthy-relevant';
        
        if ($thePixel)
          $Class .= ' wp-worthy-marker';
        else
          $Class .= ' worthy-nomarker';
      }
      
      echo '<span class="', $Class, '">', sprintf (__ ('%d chars', $this->textDomain), $thePost->getLength ()), '</span>';
      
      // Output assign-link
      if (
        !$thePost->isRelevant () ||
        $thePixel
      )
        return;
      
      $url = $_SERVER ['REQUEST_URI'];
      
      if (strpos ($url, '?') === false)
        $url .= '?action=wp-worthy-apply&post_id=';
      else
        $url .= '&action=wp-worthy-apply&post_id=';
      
      echo '<br /><a href="', esc_attr ($url . intval ($GLOBALS ['post']->ID)), '">', __ ('Assign marker', $this->textDomain), '</a>';
    }
    // }}}
    
    // {{{ getPixelByPostID
    /**
     * Retrive (a cached) pixel by post-id
     * 
     * @param int $postID
     * @param int $siteId (optional)
     * @param bool $skipCache (optional)
     * 
     * @access public
     * @return object
     **/
    public function getPixelByPostID ($postID, int $siteId = null, bool $skipCache = false) {
      return wp_worthy_pixel::getPixelForPost ($postID, $siteId, $skipCache);
    }
    // }}}
    
    // {{{ adminAddPostBanner
    /**
     * Add some notices to wordpress' post editor
     * 
     * @param WP_Post $post (optional)
     * 
     * @access public
     * @return void
     **/
    public function adminAddPostBanner ($post = null) {
      // Just output notice-section if no post is used for this call
      if (!$post) {
        echo '<div id="worthy-notices">';
        
        #if (count (get_option ('wp-worthy-check-missing', array ())) > 0)
        #  echo '<div class="notice notice-error fade"><p><strong>Worthy: ', __ ('The automatic check was unable to find markers on your page, please double-check if there is an issue while interacting with your theme or any other plugin!', $this->textDomain), '</strong></p></div>';
        
        echo '</div>';
        
        return;
      }
      
      if (!is_object ($thePost = wp_worthy_post::fromObject ($post)))
        return;

      // Check if the post has a marker assigned
      if ($thePost->hasPixel ())
        return;
      
      // Check if the post is ignored
      if ($thePost->isIgnored ())
        return;
      
      // Retrive some information first
      $isLyric = $thePost->isLyric ();
      $noMarkers = ($this->getAvailableMarkersCount ($this->getUserIDforPost ($post)) == 0);
      $postLength = $thePost->getLength ();
      
      // Check wheter to output a notice
      if (
        isset ($_REQUEST ['wp-worthy-assign-status']) &&
        ((int)$_REQUEST ['wp-worthy-assign-status'] == 1)
      )
        $this->adminNotice (__ ('Worthy was not able to assign a marker to this post because there are no free markers left on the database.', $this->textDomain), 'error');
      elseif (
        isset ($_REQUEST ['wp-worthy-assign-status']) &&
        ((int)$_REQUEST ['wp-worthy-assign-status'] == 2)
      )
        $this->adminNotice (__ ('Worthy was not able to assign a marker to this post because of an unknown internal error. Please contact developers.', $this->textDomain), 'error');
      elseif (
        ($isLyric || ($postLength >= $this::WARN_LIMIT)) &&
        $noMarkers
      )
        $this->adminNotice (__ ('Worthy will not be able to assign a marker to this post because there are no free markers left on the database.', $this->textDomain), 'error');
      elseif ($isLyric)
        $this->adminNotice (__ ('This article is flagged as lyric work but you did not assign a marker. The lyric-flag only makes sense if you want to assign a marker to a short text.', $this->textDomain), 'error');
      elseif ($postLength >= $this::MIN_LENGTH)
        $this->adminNotice (sprintf (__ ('Your article is more than %d characters long but you did not assign a marker. It is advisable to assign a marker now or to ignore it for use with worthy.', $this->textDomain), $this::MIN_LENGTH), 'error');
      elseif (
        ($postLength < $this::MIN_LENGTH) &&
        ($postLength >= $this::WARN_LIMIT)
      )
        $this->adminNotice (sprintf (__ ('Your article is close to %d characters long and though may qualify to be reported to VG WORT if you write some more words.', $this->textDomain), $this::MIN_LENGTH), 'update-nag');
    }
    // }}}
    
    // {{{ adminNoticeUnknownPixels
    /**
     * Check and notice the user if there were unknown pixels received that have qualified
     * 
     * @access public
     * @return void
     **/
    public function adminNoticeUnknownPixels () {
      // Don't run if this isn't premium
      if (!$this->hasPremium ())
        return;
      
      // Check if there are unknown pixels to report
      if (($unknownPixels = get_user_meta ($this->getUserID (), 'wp-worthy-unknown-reportable-pixels', true)) < 1)
        return;
      
      // Check if the message was dismissed
      if (get_user_meta ($this->getUserID (), 'wp-worthy-unknown-reportable-pixels-dismissed', true) >= date ('Y'))
        return;
      
      // Push to output
      $this->adminNotice (
        sprintf (__ ('There were <strong>%d pixels</strong> on the last update that have qualified at VG WORT but are unknown to Worthy. Please check if you have used these pixels somewhere else!', $this->textDomain), $unknownPixels),
        'error',
        'wp-worthy-dismiss-unknown-reportable-pixels'
      );
    }
    // }}}
    
    // {{{ dismissUnknownPixels
    /**
     * Remove the unknown pixels message for the current year
     * 
     * @access public
     * @return void
     **/
    public function dismissUnknownPixels () {
      # This is not considered to be dangerous
      # $this->verifyNonce ([ $this::ADMIN_SECTION_OVERVIEW ]);
      
      update_user_meta ($this->getUserID (), 'wp-worthy-unknown-reportable-pixels-dismissed', date ('Y'));
    }
    // }}}
    
    // {{{ displayError
    /**
     * Output an error or exception
     * 
     * @param \Throwable $error
     * @param bool $returnInline (optional)
     * 
     * @access private
     * @return void|string
     **/
    private function displayError (\Throwable $error, $returnInline = false) {
      // Extract stack of errors
      $messageStack = [ $error ];
      
      while ($error = $error->getPrevious ())
        array_unshift ($messageStack, $error);
      
      // Output the exception
      if (!$returnInline)
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('There was an error while processing your request', $this->textDomain), '</h2>',
            '<div class="inside">';
      
      if (count ($messageStack) > 1)
        $outputBuffer =
              '<ol>';
      else
        $outputBuffer = '';
      
      foreach ($messageStack as $error) {
        if (count ($messageStack) > 1)
          $outputBuffer .=
                '<li class="wp-worthy-error">';
        else
          $outputBuffer .=
                '<p class="wp-worthy-error">';
        
        if ($error instanceof \SoapFault) {
          $errorMessage = $error->faultstring;
          $errorCode = $error->faultcode;
        } else {
          $errorMessage = $error->getMessage ();
          $errorCode = $error->getCode ();
        }
        
        $outputBuffer .=
                  esc_html (__ ($errorMessage, $this->textDomain) . ' (' . ($errorCode ? $errorCode . ', ' : '') . $error->getFile () . ':' . $error->getLine () . ')');
        
        if (count ($messageStack) > 1)
          $outputBuffer .=
                '</li>';
        else
          $outputBuffer .=
                '</p>';
      }
      
      if (count ($messageStack) > 1)
        $outputBuffer .=
              '</ol>';
      
      if ($returnInline)
        return $outputBuffer;
      
      echo
              $outputBuffer,
            '</div>',
          '</div>';
    }
    // }}}
    
    // {{{ adminNotice
    /**
     * Enqueue an admin-notice
     * 
     * @param 
     * @access private
     * @return void
     **/
    private function adminNotice ($Message, $Class, $dismissAction = null) {
      if ($this->noticeScriptHandle)
        wp_add_inline_script ($this->noticeScriptHandle, 'window.wp.worthy.postNotice.apply (null, ' . wp_json_encode (array ($Message, $Class, $dismissAction)) . ');');
    }
    // }}}
    
    // {{{ adminSavePost
    /**
     * Assign a marker to a post if requested
     * 
     * @param int $postID
     * @param object $thePost
     * @param bool $postUpdated
     * @param bool $forcePixel (optional)
     * 
     * @access public
     * @return bool
     **/
    public function adminSavePost ($postID, $thePost, $postUpdated, $forcePixel = false) {
      // Just ignore revisions
      if (wp_is_post_revision ($postID))
        return false;
      
      // Ignore auto-save
      if (wp_is_post_autosave ($postID))
        return false;
      
      // Make sure we have the post
      if (!is_object ($thePost))
        return false;
      
      // Create Worthy-Interface to post
      $worthyPost = new wp_worthy_post ($thePost);
      
      // Store the length of the post
      $worthyPost->updateLength ();
      
      if (!$forcePixel) {
        // Toggle ignore-flag
        if (isset ($_POST ['wp-worthy-ignore'])) {
          $worthyPost->isIgnored (true);
          
          unset ($_POST ['wp-worthy-embed']);
        } elseif (isset ($_POST ['wp-worthy-classic-editor']))
          $worthyPost->isIgnored (false);
        
        // Toggle lyric-flag
        if (
          isset ($_POST ['wp-worthy-lyric']) &&
          ($_POST ['wp-worthy-lyric'] != 0)
        )
          $worthyPost->isLyric (true);
          
        elseif (isset ($_POST ['wp-worthy-classic-editor']))
          $worthyPost->isLyric (false);
      }
      
      // Check wheter to assign a marker
      if (
        $worthyPost->hasPixel () ||
        (!$forcePixel && (!isset ($_POST ['wp-worthy-embed']) || ($_POST ['wp-worthy-embed'] != 1)))
      )
        return $worthyPost->hasPixel ();
      
      // Determine user-ids for the process
      if (isset ($_REQUEST ['wp-worthy-marker-owner'])) {
        $postUserIDs = $this->getUserIDs ($this->getUserIDforPost ($postID));
        
        if (in_array ((int)$_REQUEST ['wp-worthy-marker-owner'], $postUserIDs))
          $userIDs = [ (int)$_REQUEST ['wp-worthy-marker-owner'] ];
        else
          $userIDs = [];
      } else
        $userIDs = null;
      
      // Make sure we have a user-id to try with
      if (
        ($userIDs !== null) &&
        (count ($userIDs) == 0)
      ) {
        add_filter (
          'redirect_post_location',
          function ($location, $postID) {
            return add_query_arg ('wp-worthy-assign-status', 3, $location);
          },
          10,
          2
        );
        
        return false;
      }
      
      // Assign a random pixel to this post
      if (!wp_worthy_pixel::assignToPost (wp_worthy_post::fromID ($postID, null), $userIDs)) {
        add_filter (
          'redirect_post_location',
          function ($location, $postID) {
            return add_query_arg ('wp-worthy-assign-status', ($this->getAvailableMarkersCount ($this->getUserIDforPost ($postID)) == 0 ? 1 : 2), $location);
          },
          10,
          2
        );
        
        return false;
      }
      
      return true;
    }
    // }}}
    
    // {{{ adminPostPublishBox
    /**
     * Place our options on publish-box
     * 
     * @access public
     * @return void
     **/
    public function adminPostPublishBox ($post) {
      // Check current settings
      if ($enabled = ($thePost = wp_worthy_post::fromObject ($post))) {
        $c_checked = $thePost->hasPixel ();
        
        if (!$c_checked)
          $d_checked = $this->postHasMarker ($post, true);
        else
          $d_checked = false;
        
        $l_checked = $thePost->isLyric ();
        $i_checked = $thePost->isIgnored ();
      } else
        $c_checked = $d_checked = $l_checked = $i_checked = false;
      
      // Don't display publish-box on unsupported post-types
      if (
        !$c_checked &&
        !in_array ($post->post_type, $this->getUserPostTypes ())
      )
        return;
      
      // Make sure there are markers available
      if (!$c_checked)
        $enabled = ($this->getAvailableMarkersCount ($this->getUserIDforPost ($post)) > 0);
      
      // Append our options to the publish-box
      echo
        '<div class="misc-pub-section misc-worthy worthy-publish">',
          '<input type="hidden" name="wp-worthy-classic-editor" value="1" />',
          '<span class="label">Worthy:</span>',
          '<span class="value">',
          ($enabled && !$d_checked ? '' :
            ($d_checked ?
              ($c_checked ?
                '' :
                '<span class="wp-worthy-warning" title="' . __ ('This post has a pixel assigned on other VG WORT related plugin but not on Worthy. Use the migration-tools to migrate it to Worthy!', $this->textDomain) . '">' . __ ('Needs to be migrated', $this->textDomain) . '</span>'
              ) :
              '<span class="wp-worthy-warning">' . __ ('No markers available', $this->textDomain) . '</span>'
            )
          ),
            '<input type="checkbox" data-worthy-autoassign="' . (get_user_meta (get_current_user_id (), 'wp-worthy-auto-assign-markers', true) == 1 ? '1' : '0') . '" name="wp-worthy-embed" id="wp-worthy-embed" value="1"', ($c_checked ? ' checked="1"' : ''), ($c_checked || !$enabled ? ' readonly disabled' : ''), ' /> ',
            '<label for="wp-worthy-embed" id="wp-worthy-embed-label">', __ ('Assign VG WORT pixel', $this->textDomain), '</label><br />',
            '<input onclick="window.wp.worthy.counter (false);" type="checkbox" name="wp-worthy-lyric" id="wp-worthy-lyric" value="1"', ($l_checked ? ' checked="1"' : ''), ' /> ',
            '<label for="wp-worthy-lyric">', __ ('Lyric Work', $this->textDomain), '</label><br />',
            '<input onclick="worthy.counter (false);" type="checkbox" name="wp-worthy-ignore" id="wp-worthy-ignore" value="1"', ($i_checked ? ' checked="1"' : ''), '/> ',
            '<label for="wp-worthy-ignore" id="wp-worthy-ignore-label">', __ ('Ignore this post', $this->textDomain), '</label>',
          '</span>',
          '<div class="clear"></div>',
        '</div>';
    }
    // }}}
    
    // {{{ adminMenuHeader
    /**
     * Output HTML-code for admin-menu
     * 
     * @param enum $Current (optional)
     * 
     * @access private
     * @return void
     **/
    private function adminMenuHeader ($Current = null) {
      // Output the header of the administration-menu
      echo
        '<div id="wp-worthy" class="wrap">',
          '<h1>', __ ('Worthy - VG WORT Integration for Wordpress', $this->textDomain), '</h1>',
          '<h2 class="nav-tab-wrapper">';
      
      if (
        !($Menu = $this->getAdminMenu ()) ||
        !isset ($Menu [7]) ||
        !is_array ($Menu [7])
      ) {
        $Sections = array (
          $this::ADMIN_SECTION_OVERVIEW => 'Overview',
          $this::ADMIN_SECTION_MARKERS => 'Markers',
          $this::ADMIN_SECTION_POSTS => 'Posts',
          $this::ADMIN_SECTION_CONVERT => 'Import / Export',
          $this::ADMIN_SECTION_SETTINGS => 'Settings',
          # $this::ADMIN_SECTION_ADMIN => 'Admin',
          $this::ADMIN_SECTION_PREMIUM => array ('Premium', 1, 'worthy-premium'),
        );
        
        foreach ($Sections as $Key=>$Title) {
          if (is_array ($Title)) {
            $Class = (isset ($Title [2]) ? $Title [2] : null);
            $Align = (isset ($Title [1]) ? $Title [1] : 0);
            $Title = $Title [0];
          } else {
            $Align = 0;
            $Class = null;
          }
          
          echo
            '<a href="', $this->linkSection ($Key), '" class="nav-tab', ($Key == $Current ? ' nav-tab-active' : ''), ($Align == 1 ? ' nav-tab-right' : ''), ($Class !== null ? ' ' . $Class : '') . '">',
              __ ($Title, $this->textDomain),
            '</a>';
        }
      } else
        foreach ($Menu [7] as $ID=>$Page) {
          if (!current_user_can ($Page [2]))
            continue;
          
          $Key = $Page [3];
          
          if (substr ($Key, 0, strlen (__CLASS__) + 1) == __CLASS__ . '-')
            $Key = substr ($Key, strlen (__CLASS__) + 1);
          
          if ($ID == count ($Menu [7]) - 1) {
            $Align = 1;
            $Class = 'worthy-premium';
          } else
            $Align = $Class = null;
          
          if (
            ($Class == 'worthy-premium') &&
            $this->hasPremium ()
          )
            $Class .= ' worthy-premium-active';
          
          echo
            '<a href="', $this->linkSection ($Key), '" class="nav-tab', ($Key == $Current ? ' nav-tab-active' : ''), ($Align == 1 ? ' nav-tab-right' : ''), ($Class !== null ? ' ' . $Class : '') . '">',
              __ ($Page [1], $this->textDomain),
            '</a>';
        }
      
      echo
            '<div class="clear"></div>',
          '</h2>';
      
      echo
          '<div id="poststuff">';
      
      // Output status-messages first
      $this->adminMenuStatus ();
    }
    // }}}
    
    // {{{ adminMenuFooter
    /**
     * 
     **/
    private function adminMenuFooter () {
      // Finish the output
      echo
          '</div>',
        '</div>';
    }
    // }}}
    
    // {{{ adminMenuOverview
    /**
     * Generate an overview about our status
     * 
     * @access public
     * @return void
     **/
    public function adminMenuOverview () {
      // Draw admin-Header
      $this->adminMenuHeader ($this::ADMIN_SECTION_OVERVIEW);
      
      // Collect some status-information
      $notIndexed = wp_worthy_maintenance::getUnindexedCount ();
      $unassigedRelevant = $this->getRelevantUnassignedCount (is_network_admin () ? null : get_current_blog_id ());
      
      $duplicatePixelCount = array_sum (
        $this->querySites (
          'SELECT COUNT(*) ' .
          'FROM `%tablePostMeta` ' .
          'WHERE `meta_key`="worthy_duplicate"',
          (is_network_admin () ? null : get_current_blog_id ())
        )
      );
      
      $invalidAssigned = array_sum (
        $this->querySites (
          'SELECT COUNT(*) ' .
          'FROM `' . $this->getTablename ('worthy_markers', 0) . '` ' .
          'WHERE ' .
            '`siteid`="%siteId" AND ' .
            'NOT (`postid` IS NULL) AND ' .
            'NOT `postid` IN (' .
              'SELECT `ID` ' .
              'FROM `%tablePosts` ' .
              'WHERE ' .
                '`post_type` IN ("' . implode ('","', $this->getUserPostTypes ()) . '") AND ' .
                '`post_status`="publish"' .
            ')'
        )
      );
      
      // Start the output
      echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-status">', __ ('Overview', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<h3>', __ ('Markers', $this->textDomain), '</h3>',
            '<ul id="worthy-marker-status">';
      
      // Output marker-summaries
      $userID = $this->getUserID ();
      $hasPremium = $this->hasPremium ();
      $Users = array ();
      $unused = $used = $invalid = $reportable = 0;
      $userPixelStats = $GLOBALS ['wpdb']->get_results (
        'SELECT ' .
          '`userid`, ' .
          'IF(LENGTH(`private`)>0, 1, 0) AS `has_private`, ' .
          'IF((NOT (`postid` IS NULL)) OR (`status`>0), 1, 0) AS `has_post`, ' .
          '(`disabled`>0) AS `is_disabled`, ' .
          'COUNT(*) AS `count` ' .
        'FROM `' . $this->getTablename ('worthy_markers', 0) . '` ' .
        'GROUP BY `userid`, `has_private`, `has_post`, `is_disabled` ' .
        'ORDER BY `userid` ASC, `has_post` ASC'
      );
      
      foreach ($userPixelStats as $userPixelInfo) {
        if (!isset ($Users [$userPixelInfo->userid]))
          $Users [$userPixelInfo->userid] = array (
            'unused' => 0,
            'used' => 0,
            'invalid' => 0,
            'reportable' => array (
              'all' => 0,
              'this' => 0,
              'other' => 0,
            ),
          );
        
        $Users [$userPixelInfo->userid][($userPixelInfo->has_private ? ($userPixelInfo->has_post == 0 ? 'unused' : 'used') : 'invalid')] += $userPixelInfo->count;
      }
      
      if ($hasPremium) {
        // Find reportable pixels
        $resultSet = $this->querySites (
          'SELECT "%siteId" AS siteId, userid, COUNT(DISTINCT p.ID) AS reportable ' .
          'FROM ' .
            '`' . $this->getTablename ('worthy_markers', 0) . '` wm, ' .
            '`%tablePosts` p ' .
            'LEFT JOIN `%tablePostMeta` pm ON (p.ID=pm.post_id AND pm.meta_key="' . $this::META_LENGTH . '") ' .
            'LEFT JOIN `%tablePostMeta` pmi ON (p.ID=pmi.post_id AND pmi.meta_key="worthy_ignore") ' .
            'LEFT JOIN `%tablePostMeta` pml ON (p.ID=pml.post_id AND pml.meta_key="worthy_lyric") ' .
          'WHERE ' .
            'NOT wm.`postid` IS NULL AND ' .
            'wm.`siteid`="%siteId" AND ' .
            'wm.`postid`=p.`ID` AND ' .
            'p.post_type IN ("' . implode ('","', $this->getUserPostTypes ()) . '") AND ' .
            '(pmi.meta_value IS NULL OR pmi.meta_value="0") AND ' .
            '((wm.`status`="' . wp_worthy::MARKER_STATUS_REACHED . '" AND (CONVERT(pm.`meta_value`, UNSIGNED INTEGER)>="' . $this::MIN_LENGTH . '" OR pml.meta_value="1")) OR ' .
             '(wm.`status`="' . wp_worthy::MARKER_STATUS_PARTIAL . '" AND (CONVERT(pm.`meta_value`, UNSIGNED INTEGER)>="' . $this::EXTRA_LENGTH . '"))) ' .
          'GROUP BY userid'
        );
        
        foreach ($resultSet as $siteResult)
          foreach ($siteResult as $pStat)
            if (isset ($Users [$pStat->userid])) {
              $Users [$pStat->userid]['reportable']['all'] += $pStat->reportable;
              
              if (
                is_network_admin () ||
                ($pStat->siteId == get_current_blog_id ())
              )
                $Users [$pStat->userid]['reportable']['this'] += $pStat->reportable;
              else
                $Users [$pStat->userid]['reportable']['other'] += $pStat->reportable;
            }
      }
      
      foreach ($Users as $userid=>$Info) {
        // Increase counters
        $unused += $Info ['unused'];
        $used += $Info ['used'];
        $invalid += $Info ['invalid'];
        
        echo '<li><strong>';
        
        if ($userid == $userID)
          echo __ ('Your markers', $this->textDomain), ':';
        elseif ($userid == 0)
          echo __ ('Not personalized markers', $this->textDomain), ':';
        elseif ($u = get_userdata ($userid))
          echo sprintf (__ ('Markers for %s', $this->textDomain), esc_html ($u->display_name . ' (' . $u->user_login . ')')) . ':';
        else
          echo __ ('Markers for an unknown user', $this->textDomain), ':';
        
        echo '</strong> <small>(<a href="' . $this->linkSection ($this::ADMIN_SECTION_MARKERS, array ('worthy-filter-author' => intval ($userid))), '">', __ ('Show only these', $this->textDomain), '</a>)</small><ul>';
        
        if (
          $hasPremium &&
          ($Info ['reportable']['all'] > 0)
        ) {
          if (
            is_multisite () &&
            !is_network_admin ()
          ) {
            if ($Info ['reportable']['this'] > 0)
              echo
              '<li>',
                '<span class="wp-worthy-important">',
                  sprintf (
                    _n (
                      '<strong>%d pixel</strong> may be reported on this site',
                      '<strong>%d pixels</strong> may be reported on this site',
                      $Info ['reportable']['this'],
                      $this->textDomain
                    ),
                    $Info ['reportable']['this']
                  ),
                '</span> ',
                '<small>(<a href="', $this->linkSection ($this::ADMIN_SECTION_POSTS, array ('wp-worthy-filter-marker' => 'sr')), '">', __ ('Find them', $this->textDomain), '</a>)</small>',
              '</li>';
            
            if ($Info ['reportable']['other'] > 0) {
              echo
              '<li>',
                '<span class="wp-worthy-important">',
                  sprintf (
                    _n (
                      '<strong>%d pixel</strong> may be reported on other sites',
                      '<strong>%d pixels</strong> may be reported on other sites',
                      $Info ['reportable']['other'],
                      $this->textDomain
                    ),
                    $Info ['reportable']['other']
                  ),
                '</span> ',
                '<small>(<a href="', $this->linkSection ($this::ADMIN_SECTION_POSTS, array ('wp-worthy-filter-marker' => 'sr'), false, true), '">', __ ('Find them', $this->textDomain), '</a>)</small>',
              '</li>';
            }
          } else
            echo
              '<li>',
                '<span class="wp-worthy-important">',
                  sprintf (
                    _n (
                      '<strong>%d pixel</strong> may be reported',
                      '<strong>%d pixels</strong> may be reported',
                      $Info ['reportable']['all'],
                      $this->textDomain
                    ),
                    $Info ['reportable']['all']
                  ),
                '</span> ',
                '<small>(<a href="', $this->linkSection ($this::ADMIN_SECTION_POSTS, array ('wp-worthy-filter-marker' => 'sr')), '">', __ ('Find them', $this->textDomain), '</a>)</small>',	
              '</li>';
        }
        
        echo
              '<li>',
                sprintf (_n ('<strong>%d unused marker</strong> on database', '<strong>%d unused markers</strong> on database', $Info ['unused'], $this->textDomain), $Info ['unused']), ' ',
            ($userID != $userid ? '' :
                '<small>(<a href="' . $this->linkSection ($this::ADMIN_SECTION_CONVERT) . '">' . __ ('Import new markers', $this->textDomain) . '</a>)</small>'),
              '</li><li>',
                sprintf (_n ('<strong>%d used marker</strong> on database', '<strong>%d used markers</strong> on database', $Info ['used'], $this->textDomain), $Info ['used']),
              '</li>',
          ($Info ['invalid'] == 0 ? '' :
              '<li>' .
                sprintf (_n ('<strong>%d marker</strong> has no private marker assigned', '<strong>%d markers</strong> have no private marker assigned', $Info ['invalid'], $this->textDomain), $Info ['invalid']) .
            ($userID != $userid ? '' :
              ($hasPremium ? 
                '<small>(' . $this->inlineAction ($this::ADMIN_SECTION_PREMIUM, 'wp-worthy-premium-import-private', __ ('Load private markers with Worthy Premium', $this->textDomain)) . ')</small>' :
                '<small>(<a href="' . $this->linkSection ($this::ADMIN_SECTION_CONVERT) . '">' . __ ('Import CSV containing private markers', $this->textDomain) . '</a>)</small>'
              )
            ) .
              '</li>'
          ),
          (count ($Users) > 1 ? '<li>' . sprintf (__ ('<strong>%d markers</strong> total on database', $this->textDomain), $Info ['unused'] + $Info ['used'] + $Info ['invalid']) . '</li>' : ''),
          '</ul></li>';
      }
      
      // Check if there are some markers wasted on non-existant posts
      if ($invalidAssigned > 0)
        echo  '<li>', sprintf (_n ('<strong>%d marker</strong> of them is assigned to non-existant posts', '<strong>%d markers</strong> of them are assigned to non-existant posts', $invalidAssigned, $this->textDomain), $invalidAssigned), '</li>';
      
      echo
              '<li>', sprintf (__ ('<strong>%d markers</strong> total on database', $this->textDomain), $unused + $used + $invalid), '</li>';
      
      // Check if there are markers for the current user
      if (!isset ($Users [$userID]))
        echo '<li><a href="' . $this->linkSection ($this::ADMIN_SECTION_CONVERT) . '">', __ ('Import new markers', $this->textDomain), '</a></li>';
      
      echo
            '</ul>';
      
      if (
        $unassigedRelevant ||
        $notIndexed ||
        $duplicatePixelCount
      )
        echo
            '<h3>', __ ('Posts', $this->textDomain), '</h3>',
            '<ul>';
      
      if ($unassigedRelevant)
        echo  '<li>',
                '<strong>',
                  sprintf (
                    _n (
                      '%d post',
                      '%d posts',
                      $unassigedRelevant,
                      $this->textDomain
                    ),
                    $unassigedRelevant
                  ),
                '</strong> ',
                (is_network_admin () ? '' : __ ('from this site', $this->textDomain) . ' '),
                _n (
                  'on the index that has qualified but do not have a pixel assigned',
                  'on the index that have qualified but do not have a pixel assigned',
                  $unassigedRelevant,
                  $this->textDomain
                ), ' ',
                '<small>(<a href="' . $this->linkSection ($this::ADMIN_SECTION_POSTS, array ('wp-worthy-filter-marker' => 0, 'wp-worthy-filter-length' => 1)) . '">' . __ ('Find them', $this->textDomain) . '</a>)</small>',
              '</li>';
      
      if ($notIndexed)
        echo  '<li>',
                '<strong>', sprintf (_n ('%d post', '%d posts', $notIndexed, $this->textDomain), $notIndexed), '</strong> ', __ ('do not have a length-index for Worthy stored', $this->textDomain), ' ',
                '<small>(' . $this->inlineAction ($this::ADMIN_SECTION_SETTINGS, 'wp-worthy-reindex', __ ('Generate length-index', $this->textDomain)) . ')</small>',
              '</li>';
      
      if ($duplicatePixelCount)
        echo  '<li>',
                '<strong>',
                  sprintf (
                    _n (
                      '%d post',
                      '%d posts',
                      $duplicatePixelCount,
                      $this->textDomain
                    ),
                    $duplicatePixelCount
                  ),
                '</strong> ',
                (is_network_admin () ? '' : __ ('from this site', $this->textDomain) . ' '),
                __ ('were found on frontend with at least two pixels assigned!', $this->textDomain),
              '</li>';
      
      if (
        $unassigedRelevant ||
        $notIndexed ||
        $duplicatePixelCount
      )
        echo
            '</ul>';
      
      if ($hasPremium) {
        $Status = $this->premiumUpdateStatus ();
        $tf = get_option ('time_format');
        $df = get_option ('date_format');
        
        echo
            '<h3>', __ ('Premium', $this->textDomain), '</h3>',
            '<ul>',
              '<li><span class="wp-worthy-label">', __ ('Number of reports remaining', $this->textDomain), ':</span> ', sprintf (__ ('%d reports', $this->textDomain), $Status ['ReportLimit']), '</li>',
              # '<li><span class="wp-worthy-label">', __ ('Begin of subscribtion', $this->textDomain), ':</span> ', sprintf (__ ('%s at %s', $this->textDomain), date_i18n ($df, $Status ['ValidFrom']), date_i18n ($tf, $Status ['ValidFrom'])), '</li>',
              # '<li><span class="wp-worthy-label">', __ ('End of subscribtion', $this->textDomain), ':</span> ', sprintf (__ ('%s at %s', $this->textDomain), date_i18n ($df, $Status ['ValidUntil']), date_i18n ($tf, $Status ['ValidUntil'])), '</li>',
              '<li>',
                '<span class="wp-worthy-label">', __ ('Last check of subscribtion-status', $this->textDomain), ':</span> ',
              ((($ts = intval (get_user_meta ($userID, 'worthy_premium_status_updated', true))) > 0) ?
                sprintf (__ ('%s at %s', $this->textDomain), date_i18n ($df, $ts), date_i18n ($tf, $ts)) : __ ('Not yet', $this->textDomain)), ' ',
                '<small>(', $this->inlineAction ($this::ADMIN_SECTION_PREMIUM, 'wp-worthy-premium-sync-status', __ ('Synchronize now', $this->textDomain)), ')</small>',
              '</li>',
              '<li>', 
                '<span class="wp-worthy-label">', __ ('Last syncronisation of marker-status', $this->textDomain), ':</span> ',
              ((($ts = $this->premiumGetLastMarkerUpdate ($userID)) > 0) ?
                sprintf (__ ('%s at %s', $this->textDomain), date_i18n ($df, $ts), date_i18n ($tf, $ts)) : __ ('Not yet', $this->textDomain)), ' ',
                '<small>(', $this->inlineAction ($this::ADMIN_SECTION_PREMIUM, 'wp-worthy-premium-sync-pixels', __ ('Synchronize now', $this->textDomain)), ')</small>',
              '</li>',
            '</ul>';
      }
      
      echo
          '</div>',
        '</div>';
      
      // Check wheter to suggest migration
      if (is_network_admin ())
        $siteIds = null;
      else
        $siteIds = [ get_current_blog_id () ];
      
      $migrationStats = wp_worthy_migration::getMigrationStats ($siteIds, true);
      $pixelsFound = array_sum (array_map (function ($x) { return count ($x); }, $migrationStats));
      
      if ($pixelsFound > 0) {
        // Output summary to to-migrate-posts
        echo
          '<div class="stuffbox">',
            '<h2 id="wp-worthy-box-migration">', __ ('Migration', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<ul>';
        
        foreach ($migrationStats as $pluginHint=>$pluginPostIDs)
          if (count ($pluginPostIDs) > 0)
            echo  '<li><strong>', sprintf (_n ('%d post', '%d posts', count ($pluginPostIDs), $this->textDomain), count ($pluginPostIDs)), '</strong> ', __ ('are using ' . $pluginHint, $this->textDomain), '</li>';
        
        echo
              '</ul>';
        
        // Sanity-check all IDs
        $queryConditions = [];
        
        foreach ($migrationStats as $pluginPostIDs)
          foreach ($pluginPostIDs as $pluginPostID)
            $queryConditions [$this->packPostID ($pluginPostID)] = '(`siteid`="' . (int)$pluginPostID ['siteid'] . '" AND `postid`="' . (int)$pluginPostID ['postid'] . '")';
        
        if (count ($queryConditions) != $pixelsFound)
          echo '<p><strong>', __ ('Attention', $this->textDomain), ':</strong> ', __ ('Some of this posts seem to have assigned markers using more than one plugin!', $this->textDomain), '</p>';
        
        if (($managedPixels = $GLOBALS ['wpdb']->get_var ('SELECT COUNT(*) FROM `' . $this->getTablename ('worthy_markers', 0) . '` WHERE ' . implode (' OR ', $queryConditions))) > 0)
          echo
              '<p>',
                '<strong>', __ ('Attention', $this->textDomain), ':</strong> ',
                sprintf (
                  _n (
                    '%d of this posts is already managed by Worthy!',
                    '%d of this posts are already managed by Worthy!',
                    $managedPixels,
                    $this->textDomain
                  ),
                  $managedPixels
                ),
              '</p>';
        
        echo
              '<p><a href="', $this->linkSection ($this::ADMIN_SECTION_CONVERT), '">', __ ('Go to Import / Export to migrate those posts to Worthy', $this->textDomain), '</a></p>',
            '</div>',
          '</div>';
      }
      
      $this->adminMenuFooter ();
    }
    // }}}
    
    // {{{ adminMenuMarkers
    /**
     * Display a summary of all VG WORT pixels
     * 
     * @access public
     * @return void
     **/
    public function adminMenuMarkers () {
      // Draw admin-header
      $this->adminMenuHeader ($this::ADMIN_SECTION_MARKERS);
      
      // Make sure our premium-status is registered / known
      $hasPremium = $this->hasPremium ();
      
      // Check if there are markers without private code assigned
      $noPrivate = $GLOBALS ['wpdb']->get_var (
        'SELECT count(*) ' .
        'FROM `' . $this->getTablename ('worthy_markers', 0) . '` ' .
        'WHERE private IS NULL AND (userid="0" OR userid="' . (int)$this->getUserID () . '")'
      );
      
      if ($noPrivate > 0) {
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Markers without private code found', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>',
                sprintf (_n ('<strong>%d marker</strong> has no private marker assigned', '<strong>%d markers</strong> have no private marker assigned', $noPrivate, $this->textDomain), $noPrivate), '. ',
                __ ('It looks like you have migrated some pixels from other VG WORT managing plugins, that did not store the private code on your database.', $this->textDomain), '<br />',
                __ ('Worthy should know all parts of its markers to allow efficient report-creation - by hand or via Worthy Premium.', $this->textDomain), ' ',
                __ ('You can complete pixels by uploading the original CSV-file from VG WORT or by using the automatic search as included with Worthy Premium.', $this->textDomain),
              '</p>';
        
        if ($hasPremium)
          echo
              '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, null, true), '">',
                '<button class="button action button-primary" name="action" value="wp-worthy-premium-import-private">', __ ('Load private markers with Worthy Premium', $this->textDomain), '</button>',
              '</form>';
        else
          echo
              '<p>',
                '<a href="', $this->linkSection ($this::ADMIN_SECTION_CONVERT), '">', __ ('Import CSV containing private markers', $this->textDomain), '</a>',
              '</p>';
        
        echo
            '</div>',
          '</div>';
      }
      
      // Create a table-widget
      $Table = new wp_worthy_table_markers ($this);
      $Table->prepare_items ();
      
      // Display the table-widget
      echo
        '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_MARKERS, null, true), '" class="wp-worthy-might-message">',
          (count ($Table->items) == 0 ? '<input type="hidden" name="action" value="-1" />' : ''),
          (isset ($_REQUEST ['displayMarkers']) ? '<input type="hidden" name="displayMarkers" value="' . esc_attr ($_REQUEST ['displayMarkers']) . '" />' : ''),
          (isset ($_REQUEST ['orderby']) ? '<input type="hidden" name="orderby" value="' . esc_attr ($_REQUEST ['orderby']) . '" />' : ''),
          (isset ($_REQUEST ['order']) ? '<input type="hidden" name="order" value="' . esc_attr ($_REQUEST ['order']) . '" />' : ''),
          $Table->search_box (__ ('Search Marker', $this->textDomain), 'wp-worthy-search'),
          $Table->display (),
        '</form>';
      
      // Output Marker-Inquiry
      echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-marker-inquiry">', __ ('Private Marker Inquiry', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form method="post" enctype="multipart/form-data" action="', $this->linkSection ($this::ADMIN_SECTION_MARKERS, null, true), '">',
              '<p>',
                __ ('The private-marker-inquiry will display a list of markers managed by Worthy (including post-assignment) from a CSV-List.', $this->textDomain), '<br />',
                __ ('You may upload a CSV-file like the one you can download from the marker-inquiry at T.O.M..', $this->textDomain),
              '</p><p>',
                __ ('CSV-File containing private markers', $this->textDomain), ':<br />',
                '<input type="file" name="wp-worthy-marker-file" accept="text/csv" />',
              '</p>',
              '<p><button type="submit" name="action" value="wp-worthy-marker-inquiry" class="button action">', __ ('Search private markers', $this->textDomain), '</button></p>',
            '</form>',
          '</div>',
        '</div>';
      
      // Finish admin-page
      $this->adminMenuFooter ();
    }
    // }}}
    
    // {{{ adminMenuMarkersPrepare
    /**
     * Prepare to display markers-table
     * 
     * @access public
     * @return void
     **/
    public function adminMenuMarkersPrepare () {
      // Setup the table
      wp_worthy_table_markers::setupOptions ();
      
      if ($current_screen = get_current_screen ())
        add_filter ('manage_' . $current_screen->id . '_columns', array ('wp_worthy_table_markers', 'setupColumns'));
      
      // Do some more commen stuff
      $this->adminMenuPrepare ();
    }
    // }}}
    
    // {{{ adminMenuPosts
    /**
     * Display all posts and their markers
     * This is just like wordpress' own post-table but with focus on markers
     * 
     * @access public
     * @return void
     **/
    public function adminMenuPosts () {
      // Draw admin-header
      $this->adminMenuHeader ($this::ADMIN_SECTION_POSTS);
      
      // Make sure our premium-status is registered / known
      $hasPremium = $this->hasPremium ();
      
      // Prepare the table
      $Table = new wp_worthy_table_posts ($this);
      $Table->prepare_items ();
      
      // Check if we are running low on markers
      $perPage = $Table->get_items_per_page ('wp_worthy_posts_per_page');
      $freeMarkers = $this->getAvailableMarkersCount ();
      
      if ($freeMarkers == 0)
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('No more markers on the database available', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>',
                '<strong>', __ ('There are no more markers available!', $this->textDomain), '</strong><br />',
                __ ('There are no markers left on the Worthy Database.', $this->textDomain), '<br />',
                __ ('It is not possible to assign a new marker to a post or page until you import a new set of markers ' . ($hasPremium ? 'via Worthy Premium or ' : '') . 'from a csv file.', $this->textDomain),
              '</p>',
              '<p><a href="', $this->linkSection ($this::ADMIN_SECTION_CONVERT), '">', __ ('Import new markers', $this->textDomain), '</a></p>',
            '</div>',
          '</div>';
      
      elseif ($freeMarkers < $perPage)
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Low amount of unused markers left', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>',
                '<strong>', __ ('Worthy is running low on markers!', $this->textDomain), '</strong><br />',
                sprintf (__ ('If you are going to assign more than %d markers to posts without a marker assigned, some of them will fail until you import new markers ' . ($hasPremium ? 'via Worthy Premium or ' : '') . 'from a csv file into the Worthy database.', $this->textDomain), $freeMarkers),
              '</p>',
              '<p><a href="', $this->linkSection ($this::ADMIN_SECTION_CONVERT), '">', __ ('Import new markers', $this->textDomain), '</a></p>',
            '</div>',
          '</div>';
      
      // Display the table
      echo
        '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_POSTS, null, true), '" class="wp-worthy-might-message">',
          '<input type="hidden" name="x" value="y" />',
          (count ($Table->items) == 0 ? '<input type="hidden" name="action" value="-1" />' : ''),
          (isset ($_REQUEST ['displayMarkersForMigration']) ? '<input type="hidden" name="displayMarkers" value="' . esc_attr ($_REQUEST ['displayMarkersForMigration']) . '" />' : ''),
          (isset ($_REQUEST ['migrate_inline']) ? '<input type="hidden" name="migrate_inline" value="' . esc_attr ($_REQUEST ['migrate_inline']) . '" />' : ''),
          (isset ($_REQUEST ['migrate_vgw']) ? '<input type="hidden" name="migrate_vgw" value="' . esc_attr ($_REQUEST ['migrate_vgw']) . '" />' : ''),
          (isset ($_REQUEST ['migrate_vgwort']) ? '<input type="hidden" name="migrate_vgwort" value="' . esc_attr ($_REQUEST ['migrate_vgwort']) . '" />' : ''),
          (isset ($_REQUEST ['migrate_wppvgw']) ? '<input type="hidden" name="migrate_wppvgw" value="' . esc_attr ($_REQUEST ['migrate_wppvgw']) . '" />' : ''),
          (isset ($_REQUEST ['migrate_tlvgw']) ? '<input type="hidden" name="migrate_tlvgw" value="' . esc_attr ($_REQUEST ['migrate_tlvgw']) . '" />' : ''),
          (isset ($_REQUEST ['migrate-repair-duplicates']) ? '<input type="hidden" name="migrate-repair-duplicates" value="' . esc_attr ($_REQUEST ['migrate-repair-duplicates']) . '" />' : ''),
          (isset ($_REQUEST ['orderby']) ? '<input type="hidden" name="orderby" value="' . esc_attr ($_REQUEST ['orderby']) . '" />' : ''),
          (isset ($_REQUEST ['order']) ? '<input type="hidden" name="order" value="' . esc_attr ($_REQUEST ['order']) . '" />' : ''),
          $Table->search_box (__ ('Search Marker', $this->textDomain), 'wp-worthy-search'),
          $Table->display (),
        '</form>';
      
      $this->adminMenuFooter ();
    }
    // }}}
    
    // {{{ adminMenuPostsPrepare
    /**
     * Prepare to display the posts-table
     * 
     * @access public
     * @return void
     **/
    public function adminMenuPostsPrepare () {
      // Setup the posts-table
      wp_worthy_table_posts::setupOptions ();
      
      if ($current_screen = get_current_screen ())
        add_filter ('manage_' . $current_screen->id . '_columns', array ('wp_worthy_table_posts', 'setupColumns'));
      
      // Do more common stuff
      $this->adminMenuPrepare ();
    }
    // }}}
    
    // {{{ adminMenuConvert
    /**
     * Output HTML-code for convert-section on admin-menu
     * 
     * @access public
     * @return void
     **/
    public function adminMenuConvert () {
      // Draw admin-header
      $this->adminMenuHeader ($this::ADMIN_SECTION_CONVERT);
      
      // Check if we are subscribed to premium
      $hasPremium = $this->hasPremium ();
      
      // Output the dialog
      echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-import">', __ ('VG WORT pixels', $this->textDomain), '</h2>',
          '<div class="inside">';
      
      if ($hasPremium)
        echo
            '<div class="wp-worthy-menu-half">',
              '<form method="post" enctype="multipart/form-data" action="', $this->linkSection ($this::ADMIN_SECTION_CONVERT, null, true), '">',
                '<p>', __ ('By using Worthy Premium you may directly order pixels without the need to download them manually from VG WORT.', $this->textDomain), '</p>',
                '<p>', __ ('Number of pixels to order (at most 100)', $this->textDomain), '</p>',
                '<p><input type="number" name="count" id="count" min="1" max="100" step="1" value="10" /></p>',
                '<p><button type="submit" class="button action button-primary" name="action" value="wp-worthy-premium-import">', __ ('Order via Worthy Premium', $this->textDomain), '</button></p>',
              '</form>',
            '</div>',
            '<div class="wp-worthy-menu-half">';
      
      echo
              '<form method="post" enctype="multipart/form-data" action="', $this->linkSection ($this::ADMIN_SECTION_CONVERT, null, true), '">',
                '<p>', __ ('If you have requested a CSV-list of pixels via VG WORT you may upload this file and import contained pixels here.', $this->textDomain), '</p>',
                ($hasPremium ? '<p>&nbsp;</p>' : ''),
                '<p><input type="file" name="wp-worthy-marker-file" /></p>',
                '<p><button type="submit" class="button action', ($hasPremium ? '' : ' button-primary'), '" name="action" value="wp-worthy-import-csv">', __ ('Import CSV', $this->textDomain), '</button></p>',
              '</form>',
            ($hasPremium ? '</div><div class="clear"></div>' : ''),
          '</div>',
        '</div>';
      
      if (
        $hasPremium &&
        $this::ENABLE_ANONYMOUS_MARKERS
      )
        echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-personalize">', __ ('Personalize and import VG WORT pixels', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form method="post" enctype="multipart/form-data" action="', $this->linkSection ($this::ADMIN_SECTION_CONVERT, null, true), '">',
              '<p>',
                __ ('Some very active authors may run out of pixels before the end of the year. If this also happend to you, you may personalize anonymous pixels here by uploading the CSV-File you received from VG WORT by ordering anonymous pixels on their website.', $this->textDomain),
              '</p><p>',
                '<label for="wp-worthy-claim-csv">', __ ('CSV-File containing anonymous markers', $this->textDomain), '</label><br />',
                '<input type="file" name="wp-worthy-claim-csv" id="wp-worthy-claim-csv" />',
              '</p><p>',
                '<input type="checkbox" name="wp-worthy-claim-import" id="wp-worthy-claim-import" value="1" checked="1" /> ',
                '<label for="wp-worthy-claim-import">', __ ('Import markers after they have been claimed', $this->textDomain), '</label>',
              '</p><p>',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-claim-and-import-csv">', __ ('Personalize and import markers', $this->textDomain), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>';
      
      echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-report">', __ ('Create Report about VG WORT pixels', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<p>',
              __ ('Worthy can generate a CSV-file for you that contains known markers and may be imported into any spreadsheet program, e.g. LibreOffice Calc or Microsoft Excel.', $this->textDomain), '<br />',
              sprintf (__ ('You can choose which markers to be included in the report and extend them with further information. Using <a href="%s">Worthy Premium</a> you may also filter by state of the markers.', $this->textDomain), $this->linkSection ($this::ADMIN_SECTION_PREMIUM)),
            '</p>',
            '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_CONVERT, null, true), '">',
              '<p>',
                '<input type="checkbox" name="wp-worthy-report-unused" id="wp-worthy-report-unused" value="1" /> ',
                '<label for="wp-worthy-report-unused">', __ ('Report markers that are not assigned to any post or page', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="wp-worthy-report-used" id="wp-worthy-report-used" value="1" checked="1" /> ',
                '<label for="wp-worthy-report-used">', __ ('Report markers that are actually in use by a post or a page', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="wp-worthy-report-title" id="wp-worthy-report-title" value="1" /> ',
                '<label for="wp-worthy-report-title">', __ ('Report title of post if assigned', $this->textDomain), '</label>';
      
      if (count ($Users = $GLOBALS ['wpdb']->get_results ('SELECT m.userid, u.display_name FROM `' . $this->getTablename ('worthy_markers', 0)  . '` m, `' . $this->getTablename ('users')  . '` u WHERE m.userid=u.ID GROUP BY userid')) > 1) {
        echo
              '</p><p data-worthy-if="wp-worthy-report-used">',
                '<input type="radio" name="wp-worthy-report-filter-users" id="wp-worthy-report-users-all" value="0" onchange="document.getElementById(\'wp-worthy-report-users\').style.display=\'none\'" checked="1" /> ',
                '<label for="wp-worthy-report-users-all">', __ ('Report markers from all authors', $this->textDomain), '</label><br />',
                '<input type="radio" name="wp-worthy-report-filter-users" id="wp-worthy-report-users-filter" value="1" onchange="document.getElementById(\'wp-worthy-report-users\').style.display=\'block\'" /> ',
                '<label for="wp-worthy-report-users-filter">', __ ('Report markers from specific authors', $this->textDomain), '</label><br />',
                '<blockquote style="display:none" id="wp-worthy-report-users">';
      
        $uid = $this->getUserID ();
      
        foreach ($Users as $User)
          echo    '<input type="checkbox" id="wp-worthy-report-user-', (int)$User->userid, '" name="wp-worthy-report-user[]" value="', (int)$User->userid, '"', ($uid == $User->userid ? ' checked="1"' : ''), ' /> ',
                  '<label for="wp-worthy-report-user-', (int)$User->userid, '">', esc_html ($User->display_name), '</label><br />';
        
        echo 
                '</blockquote>';
      }
      
      if (is_network_admin ()) {
        echo
              '</p><p data-worthy-if="wp-worthy-report-used">',
                '<strong>', __ ('Sites to report', $this->textDomain), '</strong><br />';
        
        foreach (get_sites () as $site)
          if (is_user_member_of_blog (null, $site->id))
            echo
                '<input type="checkbox" name="wp-worthy-report-sites[]" id="wp-worthy-report-site-', esc_attr ($site->id), '" value="', esc_attr ($site->id), '" checked="1" /> ',
                '<label for="wp-worthy-report-site-', esc_attr ($site->id), '">', esc_html ($site->blogname), '</label><br />';
      }
      
      if ($hasPremium)
        echo
              '</p><p data-worthy-if="wp-worthy-report-used">',
                '<strong>', __ ('Pixel-Status to report', $this->textDomain), '</strong><br />',
                '<input type="checkbox" name="wp-worthy-report-premium-uncounted" id="wp-worthy-report-premium-uncounted" value="1" checked="1" /> ',
                '<label for="wp-worthy-report-premium-uncounted">', __ ('Report markers that were not counted yet', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="wp-worthy-report-premium-notqualified" id="wp-worthy-report-premium-notqualified" value="1" checked="1" /> ',
                '<label for="wp-worthy-report-premium-notqualified">', __ ('Report markers that have not qualified yet', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="wp-worthy-report-premium-partialqualified" id="wp-worthy-report-premium-partialqualified" value="1" checked="1" /> ',
                '<label for="wp-worthy-report-premium-partialqualified">', __ ('Report markers that have qualified partial', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="wp-worthy-report-premium-qualified" id="wp-worthy-report-premium-qualified" value="1" checked="1" /> ',
                '<label for="wp-worthy-report-premium-qualified">', __ ('Report markers that have qualified', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="wp-worthy-report-premium-reported" id="wp-worthy-report-premium-reported" value="1" checked="1" /> ',
                '<label for="wp-worthy-report-premium-reported">', __ ('Report markers that have already been reported', $this->textDomain), '</label>';
      
      echo
              '</p><p>',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-report-csv">', __ ('Generate Report as CSV', $this->textDomain), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>',
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-export">', __ ('Export unused VG WORT pixels', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_CONVERT, null, true), '">',
              '<p>',
                __ ('Worthy can generate a CSV-File containing unused VG WORT pixels for you. All pixels on the export will be removed from Worthy\'s database.', $this->textDomain), ' ',
                __ ('The exported CSV-file is usefull if you already have ordered the maximum amount of markers for an entire year and need additional markers at another place.', $this->textDomain), ' ',
                __ ('You may choose between two export-formats - one suitable for "normal" VG WORT Authors and another one for publishers. We recommend you to use the one for authors as less informations are lost on export.', $this->textDomain),
              '</p><p>',
                '<label for="wp-worthy-export-count">', __ ('Number of unused markers', $this->textDomain), ' (', sprintf (__ ('%d available', $this->textDomain), $this->getAvailableMarkersCount (true)), ')</label><br />',
                '<input type="number" name="wp-worthy-export-count" id="wp-worthy-export-count" value="100" /><br />',
                '<label for="wp-worthy-export-format">', __ ('Export-Format to use', $this->textDomain), '</label><br />',
                '<select name="wp-worthy-export-format" id="wp-worthy-export-format">',
                  '<option value="author">', __ ('Authors', $this->textDomain), '</option>',
                  '<option value="publisher">', __ ('Publishers', $this->textDomain), '</option>',
                '</select>',
              '</p><p>',
                __ ('Be aware that using this export-function will remove information from your Worthy-Database. Use it with caution!', $this->textDomain),
              '</p><p>',
                '<button type="submit" class="button button-primary delete" name="action" value="wp-worthy-export-csv">', __ ('Export markers and remove from database', $this->textDomain), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>',
        '<hr />',
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-migrate">', __ ('Migrate existing VG WORT pixels', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<p>',
              __ ('If you have used markers before Worthy you may want to migrate them to worthy.', $this->textDomain), ' ',
              __ ('Worthy is able to import markers from other plugins and also markers that are manually embeded into posts.', $this->textDomain), '<br />',
              __ ('If markers where embeded manually or managed via some basic plugins it is neccessary that you import the corresponding CSV-files as well, because worthy need to get in touch with the original private markers.', $this->textDomain),
            '</p><p>',
              '<span class="worthy-exclamation">!</span>',
              '<strong>', __ ('Please make sure that you have a recent backup of your wordpress-installation!', $this->textDomain), '</strong><br />',
                __ ('We have made some effors to make sure that there are no issues with the migrate-tool, but nobody can say that it is safe in every case.', $this->textDomain), '<br />',
                __ ('It is recommended to make a backup of your wordpress at least once a week even without using Worthy. We just want to remind you to make sure that you are able to restore lost data in case of any error.', $this->textDomain),
              '<div class="clear"></div>',
            '</p>',
            '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_CONVERT, null, true), '">';
      
      if (is_network_admin ()) {
        echo
              '<p>',
                '<strong>', __ ('Sites', $this->textDomain), ':</strong><br />';
        
        foreach (get_sites () as $site)
          if (is_user_member_of_blog (null, $site->id))
            echo
                '<input type="checkbox" name="wp-worthy-migrate-sites[]" id="wp-worthy-migrate-site-', esc_attr ($site->id), '" value="', esc_attr ($site->id), '" checked="1" /> ',
                '<label for="wp-worthy-migrate-site-', esc_attr ($site->id), '">', esc_html ($site->blogname), '</label><br />';
        
        echo  '</p>';
      }
      
      echo
              '<p>',
                '<strong>', __ ('Selection:', $this->textDomain), '</strong><br />',
                '<input type="checkbox" name="migrate_inline" id="wp-worthy-migrate_inline" value="1" /> ',
                '<label for="wp-worthy-migrate_inline">', __ ('Markers that are embeded into posts or pages', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="migrate_vgw" id="wp-worthy-migrate_vgw" value="1" /> ',
                '<label for="wp-worthy-migrate_vgw">', __ ('Markers from plugin VGW (VG-Wort Krimskram)', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="migrate_vgwort" id="wp-worthy-migrate_vgwort" value="1" /> ',
                '<label for="wp-worthy-migrate_vgwort">', __ ('Markers from plugin WP VG-Wort', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="migrate_wppvgw" id="wp-worthy-migrate_wppvgw" value="1" /> ',
                '<label for="wp-worthy-migrate_wppvgw">', __ ('Markers from plugin Prosodia VGW', $this->textDomain), '</label><br />',
                '<input type="checkbox" name="migrate_tlvgw" id="wp-worthy-migrate_tlvgw" value="1" /> ',
                '<label for="wp-worthy-migrate_tlvgw">', __ ('Markers from plugin Torben Leuschner VG-Wort', $this->textDomain), '</label><br />',
              '</p><p>',
                '<strong>', __ ('Repair-Options:', $this->textDomain), '</strong><br />',
                '<input type="checkbox" name="migrate-repair-duplicates" id="wp-worthy-migrate-repair-duplicates" value="1" /> ',
                '<label for="wp-worthy-migrate-repair-duplicates">', __ ('Assign new markers to posts that have a marker assigned that is already used', $this->textDomain), '</label>',
              '</p><p>',
                '<button type="submit" class="button action" name="action" value="wp-worthy-migrate-preview">', __ ('Preview', $this->textDomain), '</button> ',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-migrate">', __ ('Migrate posts and pages', $this->textDomain), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>';
      
      $this->adminMenuFooter ();
    }
    // }}}
    
    // {{{ adminMenuConvertPrepare
    /**
     * Prepare to show convert-menu
     * 
     * @access public
     * @return void
     **/
    public function adminMenuConvertPrepare () {
      // Do some common stuff
      $this->adminMenuPrepare ();
      
      // Check wheter to display some status-messages
      if (!isset ($_REQUEST ['displayStatus']))
        return;
      
      switch ($_REQUEST ['displayStatus']) {
        case 'importDone':
          if ((int)$_REQUEST ['fileCount'] > 0)
            $this->adminStatus [] =
              '<div class="wp-worthy-success">' .
                '<ul class="ul-square">' .
                  '<li>' . sprintf (__ ('Read %d files containing %d markers', $this->textDomain), intval ($_REQUEST ['fileCount']), intval ($_REQUEST ['filePixelCount'])) . '</li>' .
                  '<li>' . sprintf (__ ('%d markers were already known, %d of them received an update', $this->textDomain), intval ($_REQUEST ['pixelsExisting']), intval ($_REQUEST ['pixelsUpdated'])) . '</li>' .
                  '<li>' . sprintf (__ ('%d markers were newly added to database, %d updates in total', $this->textDomain), intval ($_REQUEST ['pixelsCreated']) - intval ($_REQUEST ['pixelsUpdated']), intval ($_REQUEST ['pixelsCreated'])) . '</li>' .
                '</ul>' .
              '</div>';
          else
            $this->adminStatus [] = '<div class="wp-worthy-error">' . __ ('No files were uploaded or there was an error importing all records', $this->textDomain) . '</div>';
        
          break;
        case 'importClaimDone':
          if ((int)$_REQUEST ['fileCount'] > 0) {
            $claimedMarkers = (isset ($_REQUEST ['markerClaimed']) && (strlen ($_REQUEST ['markerClaimed']) > 0) ? explode (',', esc_html ($_REQUEST ['markerClaimed'])) : array ());
            $failedMarkers = (isset ($_REQUEST ['markerFailed']) && (strlen ($_REQUEST ['markerFailed']) > 0) ? explode (',', esc_html ($_REQUEST ['markerFailed'])) : array ());
            
            $this->adminStatus [] =
              '<div class="wp-worthy-success">' .
                '<ul class="ul-square">' .
                  '<li>' . sprintf (__ ('Read %d files containing %d markers', $this->textDomain), intval ($_REQUEST ['fileCount']), intval ($_REQUEST ['filePixelCount'])) . '</li>' .
                (count ($claimedMarkers) == 0 ? '' :
                  '<li>' . __ ('The following markers were personalized:', $this->textDomain) . '<ul class="ul-square"><li>' . implode ('</li><li>', $claimedMarkers) . '</li></ul></li>' .
                  '<li>' . sprintf (__ ('%d markers were added to database', $this->textDomain), intval ($_REQUEST ['pixelsCreated'])) . '</li>') .
                (count ($failedMarkers) == 0 ? '' :
                  '<li>' . __ ('The following markers could not be personalized:', $this->textDomain) . '<ul class="ul-square"><li>' . implode ('</li><li>', $failedMarkers) . '</li></ul></li>') .
                 '</ul>' .
               '</div>';
          } else
            $this->adminStatus [] = '<div class="wp-worthy-error">' . __ ('No files were uploaded or there was an error importing all records', $this->textDomain) . '</div>';
          
          break;
        case 'premiumImportDone':
          $this->adminStatus [] = '<div class="wp-worthy-success">' . sprintf (__ ('<strong>%d new markers</strong> were imported via Worthy Premium', $this->textDomain), (isset ($_REQUEST ['markerCount']) ? intval ($_REQUEST ['markerCount']) : 0)) . '</div>';
          
          break;
        case 'migrateDone':
          $postsMigrated = (isset ($_REQUEST ['migrateCount']) ? intval ($_REQUEST ['migrateCount']) : 0);
          $postsTotal = (isset ($_REQUEST ['totalCount']) ? intval ($_REQUEST ['totalCount']) : 0);
          $dups = (isset ($_REQUEST ['duplicates']) && is_array ($_REQUEST ['duplicates']) ? $this->unpackPostIDs ($_REQUEST ['duplicates']) : array ());
          $repair_dups = (isset ($_REQUEST ['repair_dups']) ? (int)$_REQUEST ['repair_dups'] % 2 : 0);
          $migrate_inline = (isset ($_REQUEST ['migrate_inline']) ? (int)$_REQUEST ['migrate_inline'] % 2 : 0);
          $migrate_vgw = (isset ($_REQUEST ['migrate_vgw']) ? (int)$_REQUEST ['migrate_vgw'] % 2 : 0);
          $migrate_vgwort = (isset ($_REQUEST ['migrate_vgwort']) ? (int)$_REQUEST ['migrate_vgwort'] % 2 : 0);
          $migrate_wppvgw = (isset ($_REQUEST ['migrate_wppvgw']) ? (int)$_REQUEST ['migrate_wppvgw'] % 2 : 0);
          $migrate_tlvgw = (isset ($_REQUEST ['migrate_tlvgw']) ? (int)$_REQUEST ['migrate_tlvgw'] % 2 : 0);
          
          // Give initial feedback
          $this->adminStatus [] = 
            '<div class="wp-worthy-success">' .
              sprintf (__ ('<strong>%s of %s posts and pages</strong> were successfully migrated', $this->textDomain), $postsMigrated, $postsTotal) .
            '</div>';
          
          // Check for errors
          if (
            isset ($_REQUEST ['errors']) &&
            is_array ($_REQUEST ['errors']) &&
            (count ($_REQUEST ['errors']) > 0)
          ) {
            $errorOutput =
              '<div class="wp-worthy-error">' .
                __ ('Errors that were raised during migration', $this->textDomain) . ':' .
                '<ul class="ul-square">';
            
            foreach ($_REQUEST ['errors'] as $postID=>$errorMessage)
              if ($postID = $this->unpackPostID ($postID))
                echo '<li>', $this->getPostAdminLink ($postID ['postid'], $postID ['siteid']), '<br />', __ (esc_html ($errorMessage), $this->textDomain), '</li>';
            
            $errorOutput .=
                '</ul>' .
              '</div>';
            
            $this->adminStatus [] = $errorOutput;
          }
          
          // Check for duplicates 
          if (count ($dups) > 0) {
            $markers = $this->getAvailableMarkersCount ();
            
            $msg =
              '<div class="wp-worthy-error">' .
                __ ('There were some duplicate VG WORT pixels on the following posts and pages detected during migration', $this->textDomain) .
                '<ul>';
            
            foreach ($dups as $postID)
              $msg .= '<li>' . $this->getPostAdminLink ($postID ['postid'], $postID ['siteid']) . '</li>';
            
            $msg .= '</ul>';
            
            if ($repair_dups)
              $msg .= '<p></p>';
            elseif ($markers > 0)
              $msg .=
                '<p>' .
                  '<strong>' .
                    $this->inlineAction (
                      $this::ADMIN_SECTION_CONVERT,
                      'wp-worthy-migrate',
                      __ ('Restart migration and assign new markers to this posts', $this->textDomain),
                      [
                        'migrate_inline' => ($migrate_inline ? 1 : 0),
                        'migrate_vgw' => ($migrate_vgw ? 1 : 0),
                        'migrate_vgwort' => ($migrate_vgwort ? 1 : 0),
                        'migrate_wppvgw' => ($migrate_wppvgw ? 1 : 0),
                        'migrate_tlvgw' => ($migrate_tlvgw ? 1 : 0),
                        'migrate-repair-duplicates' => 1,
                      ]
                    ) .
                  '</strong>' .
                '</p>';
            else
              $msg .=
                '<p>' .
                  __ ('There are no markers left on the Worthy Database.', $this->textDomain) . ' ' .
                  __ ('It is not possible to assign a new marker to a post or page until you import a new set of markers', $this->textDomain) .
                '</p>';
            
            $msg .= '</div>';
            
            $this->adminStatus [] = $msg;
          }
          
          break;
      }
    }
    // }}}
    
    // {{{ adminMenuSettings
    /**
     * Display settings-section on admin-menu
     * 
     * @access public
     * @return void
     **/
    public function adminMenuSettings () {
      // Draw admin-header
      $this->adminMenuHeader ($this::ADMIN_SECTION_SETTINGS);
      
      // Retrive user-ids
      $eUID = get_current_user_id ();
      $eUser = wp_get_current_user ();
      
      $OverlongTitles = get_user_meta ($eUID, 'wp-worthy-overlong-titles', true);
      
      if (
        ($OverlongTitles === false) ||
        (is_string ($OverlongTitles) && (strlen ($OverlongTitles) == 0))
      )
        $OverlongTitles = -1;
      else
        $OverlongTitles = (int)$OverlongTitles;
      
      // Personal settings
      echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-settings">', __ ('Personal settings', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_SETTINGS, null, true), '">',
              '<p>',
                '<input type="checkbox" id="wp-worthy-auto-assign-markers" name="wp-worthy-auto-assign-markers" value="1"', (get_user_meta ($eUID, 'wp-worthy-auto-assign-markers', true) == 1 ? ' checked="1"' : ''), ' /> ',
                '<label for="wp-worthy-auto-assign-markers">', __ ('Automatically assign a marker to qualified posts', $this->textDomain), '</label>',
              '</p><p>',
                __ ('Worthy should automatically assign a fresh marker to newly created posts as long as they are long enough.', $this->textDomain), ' ',
                __ ('This is helpful if you are too focused to see the flashy notices Worthy gives you when writing new posts.', $this->textDomain),
              '</p><hr class="wp-worthy-no-sharing" /><p class="wp-worthy-no-sharing">',
                '<input type="checkbox" id="wp-worthy-disable-output" name="wp-worthy-disable-output" value="1"', (get_user_meta ($eUID, 'wp-worthy-disable-output', true) == 1 ? ' checked="1"' : ''), ' /> ',
                '<label for="wp-worthy-disable-output">', __ ('Don\'t output markers on wordpress-frontend', $this->textDomain), '</label>',
              '</p><p class="wp-worthy-no-sharing">',
                __ ('There might be situations when you want to disable the output of markers managed by Worthy entirely. When you check this option Worthy will stop inserting markers into posts that are viewed on the wordpress frontend.', $this->textDomain),
              '</p><hr class="wp-worthy-no-sharing" /><p class="wp-worthy-no-sharing">',
                '<label for="wp-worthy-default-server">', __ ('Default VG WORT Server', $this->textDomain), '</label>',
                '<input type="text" id="wp-worthy-default-server" name="wp-worthy-default-server" value="', esc_attr (get_user_meta ($eUID, 'wp-worthy-default-server', true)), '" />',
              '</p><p class="wp-worthy-no-sharing">',
                __ ('When using a publisher-account at VG WORT CSV-files don\'t come with server-information set, in this case Worthy has to know which server you are using.', $this->textDomain),
              '</p>',
              '<hr />';
      
      if (
        $this->hasPremium () &&
        ((int)get_user_meta ($eUID, 'wp-worthy-authorid', true) == 0)
      )
        echo
              '<p>',
                '<input type="checkbox" name="wp-worthy-autocreate-webranges" id="wp-worthy-autocreate-webranges" ', ((int)get_user_meta ($eUID, 'wp-worthy-autocreate-webranges', true) == 1 ? 'checked ' : ''), '/>',
                '<label for="wp-worthy-autocreate-webranges">',
                  __ ('Automatically create webranges', $this->textDomain),
                '</label>',
              '</p><p>',
                __ ('Worthy Premium can automatically create webranges at VG WORT for you.', $this->textDomain), ' ',
                __ ('While this is not neccessary until a message can be sent for this post, it helps a lot if you have to find the post connected to a pixel at VG WORT.', $this->textDomain),
              '</p>',
              '<hr />';
      
      echo
              '<p>',
                '<label for="wp-worthy-overlong-titles">', __ ('Overlong titles', $this->textDomain), '</label>',
                '<select id="wp-worthy-overlong-titles" name="wp-worthy-overlong-titles">',
                  '<option value="-1"', ($OverlongTitles < 0 ? ' selected' : ''), '>', __ ('Use default setting', $this->textDomain), '</option>',
                  '<option value="0"', ($OverlongTitles == 0 ? ' selected' : ''), '>', __ ('Give warning, don\'t report', $this->textDomain), '</option>',
                  '<option value="1"', ($OverlongTitles == 1 ? ' selected' : ''), '>', __ ('Truncate at the end', $this->textDomain), '</option>',
                  '<option value="2"', ($OverlongTitles == 2 ? ' selected' : ''), '>', __ ('Truncate full words at the end', $this->textDomain), '</option>',
                  '<option value="3"', ($OverlongTitles == 3 ? ' selected' : ''), '>', __ ('Truncate words in the middle', $this->textDomain), '</option>',
                '</select>',
              '</p><p>',
                sprintf (__ ('Titles longer than %d characters may not be reported to VG WORT.', $this->textDomain), wp_worthy_post::TITLE_MAX_LENGTH), ' ',
                __ ('Worthy enables you to truncate some characters or words from overlong titles to fit into these limits, which may save some time but rewriting titles by hand will always be the better choice.', $this->textDomain), ' ',
                __ ('The above selected rule will be applied to reports via Worthy Premium and to CSV-Export as well.', $this->textDomain),
              '</p><p>',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-settings-personal">', __ ('Save'), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>';
      
      // Marker-Sharing-Options
      if (get_option ('wp-worthy-enable-account-sharing', '1') == 1) {
        echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-markers">', __ ('Markers', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_SETTINGS, null, true), '">';
      
        // Collect available users for account-sharing (users that do not share with others)
        $sharingUsers = $this->getSharingUsers ();
        
        foreach ($sharingUsers as $userIndex=>$sharingUser)
          if (
            ($sharingUser->shares_from > 0) ||
            ($sharingUser->ID == $eUID) ||
            (($sharingUser->allows_sharing === '0') && ($sharingUser->ID != $this->getUserID ()))
          )
            unset ($sharingUsers [$userIndex]);
        
        // Output options
        $allowAccountSharing = get_user_meta ($eUID, 'wp-worthy-allow-account-sharing', true);
        
        if (strlen ($allowAccountSharing) == 0)
          $allowAccountSharing = 1;
        else
          $allowAccountSharing = intval ($allowAccountSharing);
        
        echo
              '<p class="wp-worthy-no-sharing">',
                __ ('Account-Sharing enables other wordpress-users on this blog to use markers assigned to your account.', $this->textDomain), ' ',
                __ ('If you do not want to enable other users to use your markers, please uncheck this option.', $this->textDomain), ' ',
                __ ('Changes will take effect immediately, but may be undone whenever you toggle this option again.', $this->textDomain),
              '</p><p class="wp-worthy-no-sharing">',
                '<input type="radio" name="wp-worthy-allow-account-sharing" id="wp-worthy-allow-account-sharing-none" value="0"', ($allowAccountSharing == 0 ? ' checked="1"' : ''), ' />',
                '<label for="wp-worthy-allow-account-sharing-none">', __ ('Nobody is allowed to use my markers', $this->textDomain), '</label><br />',
                '<input type="radio" name="wp-worthy-allow-account-sharing" id="wp-worthy-allow-account-sharing-all" value="1"', ($allowAccountSharing == 1 ? ' checked="1"' : ''), ' />',
                '<label for="wp-worthy-allow-account-sharing-all">', __ ('Everyone may use my markers', $this->textDomain), '</label><br />',
                #'<input type="radio" name="wp-worthy-allow-account-sharing" id="wp-worthy-allow-account-sharing-some" value="2"', ($allowAccountSharing == 2 ? ' checked="1"' : ''), ' />',
                #'<label for="wp-worthy-allow-account-sharing-some">', __ ('I want to choose who may use my markers', $this->textDomain), '</label>',
              '</p>';
      
        if (count ($sharingUsers) > 0) {
          echo
              '<hr class="wp-worthy-no-sharing" /><p>',
                '<label for="wp-worthy-account-sharing">', __ ('Account-Sharing', $this->textDomain), ':</label> ',
                '<select id="wp-worthy-account-sharing" name="wp-worthy-account-sharing">',
                  '<option value="0">', __ ('Don\'t use other account', $this->textDomain), '</option>';
          
          foreach ($sharingUsers as $sharingUser)
            echo  '<option value="', (int)$sharingUser->ID, '"', (get_user_meta ($eUID, 'wp-worthy-authorid', true) == $sharingUser->ID ? ' selected="1"' : ''), '>',
                    ($sharingUser->allows_sharing == '0' ? __ ('SHARING IS DISABLED BY USER', $this->textDomain) . ': ' : ''),
                    esc_html ($sharingUser->display_name . ' (' . $sharingUser->user_login . ($sharingUser->vgwort_username != null ? ', VG WORT: ' . $sharingUser->vgwort_username : '') . ')'),
                  '</option>';
          
          echo
                '</select>',
              '</p><p>',
                __ ('With account-sharing you can link your wordpress-account to another one.', $this->textDomain), ' ',
                __ ('In this case worthy will behave just like the other account performs your actions, e.g. markers will be imported for this account and will be assigned to posts from this account.', $this->textDomain), ' ',
                __ ('The same also applies to Worthy Premium of course.', $this->textDomain),
              '</p>';
        }
        
        echo  '<p>',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-settings-sharing">', __ ('Save'), '</button>',
              '</p>',
            '</form>',
          '</div>',   
        '</div>';
      }
      
      if ($this->hasPremium ())
        echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-publisher">', __ ('VG WORT Publisher Settings', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_SETTINGS, null, true), '">',
              '<p>',
                '<label for="wp-worthy-forename">', __ ('Forename', $this->textDomain), '</label>',
                '<input type="text" name="wp-worthy-forename" id="wp-worthy-forename" value="', esc_attr (get_user_meta ($eUID, 'wp-worthy-forename', true)), '" placeholder="', esc_attr ($eUser->first_name), '" />',
              '</p><p>',
                '<label for="wp-worthy-lastname">', __ ('Lastname', $this->textDomain), '</label>',
                '<input type="text" name="wp-worthy-lastname" id="wp-worthy-lastname" value="', esc_attr (get_user_meta ($eUID, 'wp-worthy-lastname', true)), '" placeholder="', esc_attr ($eUser->last_name), '" />',
              '</p><p>',
                '<strong>', __ ('Worthy Premium', $this->textDomain), ':</strong>', ' ',
                __ ('If you use Worthy Premium in combination with a publisher-account, it is neccessary to specify at least the full name of each author.', $this->textDomain), ' ',
                __ ('Once you submit a report this information is transmitted togehter with the original post and the optional Card-ID to VG WORT.', $this->textDomain),
              '</p><hr /><p>',
                '<label for="wp-worthy-cardid">', __ ('Card-ID', $this->textDomain), '</label>',
                '<input type="text" name="wp-worthy-cardid" id="wp-worthy-cardid" value="', esc_attr (get_user_meta ($eUID, 'wp-worthy-cardid', true)), '" />',
              '</p><p>',
                '<strong>', __ ('Worthy Premium', $this->textDomain), ':</strong>', ' ',
                __ ('Assigning just the name of the author does not enable VG WORT to create a direct relation between the author and his/her post.', $this->textDomain), ' ',
                __ ('It is always recommended to provide a Card-ID of the author as well to assure that the post is linked with the author withour any issues at VG WORT.', $this->textDomain),
              '</p><p>',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-settings-publisher">', __ ('Save'), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>';
      
      // Toolbox for post-types
      $enabledPostTypes = $this->getUserPostTypes ();
      
      echo
        '<div class="stuffbox wp-worthy-no-sharing">',
          '<h2 id="wp-worthy-box-posttypes">', __ ('Post Types to consider', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_SETTINGS, null, true), '">',
              '<p>',
                __ ('Which post-types should be handled by Worthy?', $this->textDomain), '<br />',
                __ ('By default Worthy will only consider posts and pages. Depending on installed plugins you might want to assign markers to other post-types.', $this->textDomain), ' ',
                __ ('You may select the desired post-types from the list below that worthy should assign markers to and display them on the post-overview.', $this->textDomain),
              '</p><p>';
      
      foreach (array_merge (array (get_post_type_object ('post'), get_post_type_object ('page')), get_post_types (array ('public' => true, 'show_ui' => true, '_builtin' => false), 'objects')) as $postType)
        echo
          '<input type="checkbox"', (in_array ($postType->name, $enabledPostTypes) ? ' checked="1"' : ''), ' name="wp-worthy-post-types[]" value="' . esc_attr ($postType->name) . '" id="wp-worthy-post-type-' . esc_attr ($postType->name) . '">',
          '<label for="wp-worthy-post-type-' . esc_attr ($postType->name) . '">',
            esc_html ($postType->labels->name),
          '</label><br />';
      
      echo    '</p><p>',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-post-types">', __ ('Save'), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>';
      
      $this->adminMenuFooter ();
    }
    // }}}
    
    // {{{ adminMenuSettingsPrepare
    /**
     * Prepare to display settings-menu
     * 
     * @access public
     * @return void
     **/
    public function adminMenuSettingsPrepare () {
      // Do some common stuff
      $this->adminMenuPrepare ();
      
      if (!isset ($_REQUEST ['displayStatus']))
        return;
      
      if ($_REQUEST ['displayStatus'] == 'settingsSaved')
        $this->adminStatus [] =
          '<div class="wp-worthy-success">' .
            __ ('Settings have been saved', $this->textDomain) .
          '</div>';
    }
    // }}}
    
    // {{{ adminMenuAdmin
    /**
     * Display admin-menu
     * 
     * @access public
     * @return void
     **/
    public function adminMenuAdmin () {
      // Draw admin-header
      $this->adminMenuHeader ($this::ADMIN_SECTION_ADMIN);
      
      // Check settings
      $OverlongTitles = (int)get_option ('wp-worthy-overlong-titles', 0);
      $pixelPosition = (int)get_option ('wp-worthy-marker-position', self::OUTPUT_DEFAULT);
      $lazyLoading = (int)get_option ('wp-worthy-lazy-loading', self::LAZY_LOADING_DEFAULT);
      
      // Pre-Load users
      $sharingUsers = $this->getSharingUsers ();
      $userList = array ();
      $IDs = array ();
    
      foreach ($sharingUsers as $sharingUser) {
        $userList [$sharingUser->ID] = $sharingUser->display_name . ' (' . $sharingUser->user_login . ')';
    
        if ($isSharing = ($sharingUser->shares_from > 0)) {
          $isSharing = false;
   
          foreach ($sharingUsers as $sharesFrom)
            if ($isSharing = ($sharesFrom->ID == $sharingUser->shares_from))
              break;
    
          if ($isSharing)
            $userList [$sharingUser->ID] = substr ($userList [$sharingUser->ID], 0, -1) . ', teilt von ' . $sharesFrom->user_login . ')';
        }
        
        if (
          !$isSharing &&
          ($sharingUser->vgwort_username != null)
        )
          $userList [$sharingUser->ID] = substr ($userList [$sharingUser->ID], 0, -1) . ', VG WORT: ' . $sharingUser->vgwort_username . ')';
        
        if ($sharingUser->allows_sharing == '0')
          $userList [$sharingUser->ID] .=  '(' . __ ('has disabled sharing', $this->textDomain) . ')';
  
        $IDs [] = $sharingUser->ID;
      }
      
      // Output global settings
      echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-common">', __ ('Common settings', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_ADMIN, null, true), '">',
              '<p>',
                '<input type="checkbox" name="wp-worthy-enable-account-sharing" id="wp-worthy-enable-account-sharing" value="1"', (get_option ('wp-worthy-enable-account-sharing', '1') == 1 ? ' checked="1"' : ''), ' /> ',
                '<label for="wp-worthy-enable-account-sharing">', __ ('Enable account-sharing', $this->textDomain), '</label>',
              '</p><p>',
                __ ('With account-sharing you may share markers and settings among multiple wordpress-users.', $this->textDomain), ' ',
                __ ('This is usefull whenever you use multiple users - e.g. an admin- and an editor-account - on wordpress and do not want to switch users always.', $this->textDomain), ' ',
                __ ('Account-sharing may be configured on the settings-page of the user that whishes to use settings from another user.', $this->textDomain),
              '</p>',
              '<hr />',
              '<div id="wp-worthy-default-account-box">',
                '<p>',
                  '<label for="wp-worthy-default-account">', __ ('Default account', $this->textDomain), '</label>',
                  '<select id="wp-worthy-default-account" name="wp-worthy-default-account">',
                    '<option value="0">', __ ('Do not use a default account', $this->textDomain), '</option>';
      
      foreach ($userList as $ID=>$User)
        echo      '<option value="', (int)$ID, '"', (get_option ('wp-worthy-default-account', 0) == $ID ? ' selected="1"' : ''), '>', esc_html ($User), '</option>';
      
      echo
                  '</select>',
                '</p><p>',
                  __ ('You can assign a default account for users who do not have any own pixels available and are not assigned directly to another account.', $this->textDomain), ' ',
                  __ ('Please be aware that it is not possible to import own pixels on a per-user-basis for users without own pixels after this option has been enabled.', $this->textDomain),
                '</p>',
                '<hr />',
              '</div>',
              '<p>',
                '<input type="checkbox" name="wp-worthy-enable-burn" id="wp-worthy-enable-burn" value="1"', (get_option ('wp-worthy-enable-burn', false) ? ' checked="1"' : ''), ' /> ',
                '<label for="wp-worthy-enable-burn">', __ ('Always allow to burn pixels', $this->textDomain), '</label>',
              '</p><p>',
                __ ('For safety reasons Worthy only allows to burn pixels that are in a conflicted state.', $this->textDomain), ' ',
                __ ('If you really know what you are doing you can disable this safety-check here.', $this->textDomain),
              '</p>',
              '<hr />';
      
      if ($this->hasPremium ())
        echo
              '<p>',
                '<input type="checkbox" name="wp-worthy-enable-webarea" id="wp-worthy-enable-webarea" value="1"', (get_option ('wp-worthy-enable-webarea', false) ? ' checked="1"' : ''), ' /> ',
                '<label for="wp-worthy-enable-webarea">', __ ('Allow to create Webareas via Worthy Premium', $this->textDomain) , '</label>',
              '</p><p>',
                __ ('Worthy Premium may help you to prepare your reports by offering an option to create webareas in the meantime.', $this->textDomain), ' ',
                __ ('This feature is not required but works regardless reports can be made or not.', $this->textDomain), ' ',
                __ ('It\'s disabled by default now because it had confused users.', $this->textDomain),
              '</p>',
              '<hr />';
      
      echo
              '<p>',
                '<label for="wp-worthy-overlong-titles">', __ ('Overlong titles', $this->textDomain), '</label>',
                '<select id="wp-worthy-overlong-titles" name="wp-worthy-overlong-titles">',
                  '<option value="0"', ($OverlongTitles == 0 ? ' selected' : ''), '>', __ ('Give warning, don\'t report', $this->textDomain), '</option>',
                  '<option value="1"', ($OverlongTitles == 1 ? ' selected' : ''), '>', __ ('Truncate at the end', $this->textDomain), '</option>',
                  '<option value="2"', ($OverlongTitles == 2 ? ' selected' : ''), '>', __ ('Truncate full words at the end', $this->textDomain), '</option>',
                  '<option value="3"', ($OverlongTitles == 3 ? ' selected' : ''), '>', __ ('Truncate words in the middle', $this->textDomain), '</option>',
                '</select>',
              '</p><p>',
                sprintf (__ ('Titles longer than %d characters may not be reported to VG WORT.', $this->textDomain), wp_worthy_post::TITLE_MAX_LENGTH), ' ',
                __ ('Worthy enables you to truncate some characters or words from overlong titles to fit into these limits, which may save some time but rewriting titles by hand will always be the better choice.', $this->textDomain), ' ',
                __ ('The above selected rule will be applied to reports via Worthy Premium and to CSV-Export as well and may be overriden on a per-user basis.', $this->textDomain),
              '</p><p>',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-admin-common-settings">', __ ('Save'), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>';
      
      // Output-Settings
      echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-contents">', __ ('Output settings', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_ADMIN, null, true), '">',
              '<p>',
                '<input type="checkbox" name="wp-worthy-embed-on-feed" id="wp-worthy-embed-on-feed" value="1"', (get_option ('wp-worthy-embed-on-feed', false) ? ' checked="1"' : ''),  ' /> ',
                '<label for="wp-worthy-embed-on-feed">', __ ('Embed markers on feed', $this->textDomain), '</label>',
              '</p><p>',
                __ ('By default Worthy only embeds markers on posts and pages when viewed on a dedicated page.', $this->textDomain), ' ',
                __ ('If your target audience prefers to read your posts via RSS2 it is possible to make Worthy embed markers in your RSS2-Feed as well.', $this->textDomain),
              '</p>',
              '<hr />',
              '<p>',
                '<input type="checkbox" name="wp-worthy-embed-on-rest" id="wp-worthy-embed-on-rest" value="1"', (get_option ('wp-worthy-embed-on-rest', false) ? ' checked="1"' : ''),  ' /> ',
                '<label for="wp-worthy-embed-on-rest">', __ ('Embed markers on REST-API', $this->textDomain), '</label>',
              '</p><p>',
                __ ('Worthy enhances the REST-API by default with all neccessary informations assigned to a post.', $this->textDomain), ' ',
                __ ('If you want it to also embed the marker into rendered content, check this option.', $this->textDomain),
              '</p>',
              '<hr />',
              '<p>',
                '<input type="checkbox" name="wp-worthy-embed-on-export" id="wp-worthy-embed-on-export" value="1"', (get_option ('wp-worthy-embed-on-export', false) ? ' checked="1"' : ''),  ' /> ',
                '<label for="wp-worthy-embed-on-export">', __ ('Embed markers on export', $this->textDomain), '</label>',
              '</p><p>',
                __ ('Wordpress does not embed informations regarding markers assigned to posts on its exports.', $this->textDomain), ' ',
                __ ('If you don\'t want to loose assigned markers, you may re-embed them into the exported HTML.', $this->textDomain), ' ',
                __ ('After importing your content again you may migrate them back to Worthy and import markers separably.', $this->textDomain),
              '</p>',
              '<hr />',
              '<p>',
                '<label for="wp-worthy-marker-position">', __ ('Position of marker on output', $this->textDomain), '</label>',
                '<select id="wp-worthy-marker-position" name="wp-worthy-marker-position">',
                  '<option value="', self::OUTPUT_BEFORE, '"', ($pixelPosition == self::OUTPUT_BEFORE ? ' selected' : ''), '>', __ ('Before the post', $this->textDomain), '</option>',
                  '<option value="', self::OUTPUT_START,  '"', ($pixelPosition == self::OUTPUT_START  ? ' selected' : ''), '>', __ ('At beginning of content', $this->textDomain), '</option>',
                  '<option value="', self::OUTPUT_MIDDLE, '"', ($pixelPosition == self::OUTPUT_MIDDLE ? ' selected' : ''), '>', __ ('After teaser-text', $this->textDomain), '</option>',
                  '<option value="', self::OUTPUT_STOP,   '"', ($pixelPosition == self::OUTPUT_STOP   ? ' selected' : ''), '>', __ ('At the end of content', $this->textDomain), '</option>',
                  '<option value="', self::OUTPUT_AFTER,  '"', ($pixelPosition == self::OUTPUT_AFTER  ? ' selected' : ''), '>', __ ('After the post', $this->textDomain), '</option>',
                '</select>',
              '</p><p>',
                __ ('Worthy has several strategies to place a marker on the output of your posts and pages.', $this->textDomain), ' ',
                __ ('While everyone of these is suitable you sometimes want to choose where to output the marker.', $this->textDomain),
              '</p>',
              '<hr />',
              '<p>',
                '<label for="wp-worthy-lazy-loading">', __ ('Lazy Loading', $this->textDomain), '</label>',
                '<select id="wp-worthy-lazy-loading" name="wp-worthy-lazy-loading">',
                  '<option value="', esc_attr (self::LAZY_LOADING_PREVENT), '"', ($lazyLoading == self::LAZY_LOADING_PREVENT ? ' selected' : ''), '>',
                    __ ('Try to prevent lazy loading', $this->textDomain),
                  '</option>',
                  '<option value="', esc_attr (self::LAZY_LOADING_ENFORCE), '"', ($lazyLoading == self::LAZY_LOADING_ENFORCE ? ' selected' : ''), '>',
                    __ ('Try to enforce lazy loading', $this->textDomain),
                  '</option>',
                  '<option value="', esc_attr (self::LAZY_LOADING_AUTO), '"', ($lazyLoading == self::LAZY_LOADING_AUTO ? ' selected' : ''), '>',
                    __ ('Don\'t try to change default behaviour', $this->textDomain),
                  '</option>',
                '</select>',
              '</p><p>',
                __ ('Some websites try to load images only on demand to achieve better render-times.', $this->textDomain), ' ',
                __ ('While this is desirable it is also important that every possible reader is counted even if one does not scroll down far enough.', $this->textDomain), ' ',
                __ ('Worthy features some techniques to communicate that a pixel should be loaded always and not only on demand.', $this->textDomain),
              '</p>',
              '<hr />',
              '<p>',
                '<label for="wp-worthy-pixel-classes">', __ ('Custom CSS-Classes', $this->textDomain), '</label>',
                '<input type="text" name="wp-worthy-pixel-classes" id="wp-worthy-pixel-classes" value="', esc_attr (get_option ('wp-worthy-pixel-classes', '')), '" />',
              '</p><p>',
                __ ('The custom CSS-Classes will be embeded into the class-attribute of the image-element containing the pixel.', $this->textDomain), ' ',
                __ ('Under normal circumstances you do not need to specify anything here.', $this->textDomain),
              '</p>',
              '<hr />',
              '<p>',
                '<label for="wp-worthy-locale-filter">', __ ('Filter for specific locale', $this->textDomain), '</label>',
                '<input type="text" name="wp-worthy-locale-filter" id="wp-worthy-locale-filter" value="', esc_attr (get_option ('wp-worthy-locale-filter', '')), '" />',
              '</p><p>',
                __ ('On multilingual websites it may be neccessarry to filter for a specific locale (like "de_DE") before a pixel is embeded into the output.', $this->textDomain), ' ',
                __ ('Leave this field empty if in doubt, otherwise find out which locale is used for pages in german language and enter it here.', $this->textDomain),
              '</p><p>',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-admin-output-settings">', __ ('Save'), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>';
      
      // Content-Settings
      echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-contents">', __ ('Content settings', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_ADMIN, null, true), '">',
              '<p>',
                __ ('Allow these shortcodes on Worthy-Output:', $this->textDomain),
              '</p>',
              '<ul>';
      
      if (
        ($Filter = get_option ('wp-worthy-filter-shortcodes', false)) &&
        (strlen ($Filter) > 0)
      )
        $Filter = explode (',', $Filter);
      else
        $Filter = array ();
      
      foreach ($GLOBALS ['shortcode_tags'] as $Key=>$Callback)
        echo
                '<li style="width:33%;display:inline-block;box-sizing:border-box;overflow:hidden;">',
                  '<input type="checkbox" id="wp-worthy-shortcode-filter-', esc_attr ($Key), '" name="wp-worthy-shortcode-filter[]" value="', esc_attr ($Key), '"', (in_array ($Key, $Filter) ? '' : ' checked="1"'), ' />',
                  '<label for="wp-worthy-shortcode-filter-', esc_attr ($Key), '">', esc_html ($Key), '</label>',
                '</li>';
      
      echo
              '</ul>',
              '<p>',
                __ ('All shortcodes that are not selected won\'t be taken into account when calculating the size of a post or when creating a report via Worthy Premium.', $this->textDomain),
              '</p><p>',
                '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-admin-content-settings">', __ ('Save'), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>';
      
      // Toolbox for reindexing posts
      $Count = array_sum ($this->querySites ('SELECT COUNT(DISTINCT post_id) FROM `%tablePostMeta` WHERE meta_key="' . $this::META_LENGTH . '"'));
      $Unindexed = wp_worthy_maintenance::getUnindexedCount ();

      echo
        '<div class="stuffbox">',
          '<h2 id="wp-worthy-box-index">', __ ('Maintenance', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_ADMIN, null, true), '">',
              '<ul>',
                '<li><strong>', sprintf (_n ('%d post', '%d posts', $Count, $this->textDomain), $Count) . '</strong> ', __ ('on index', $this->textDomain), '</li>',
                '<li><strong>', sprintf (_n ('%d post', '%d posts', $Unindexed, $this->textDomain), $Unindexed), '</strong> ', __ ('do not have a length-index for Worthy stored', $this->textDomain), '</li>',
              '</ul>',
              '<p><input type="checkbox" value="1" name="wp-worthy-reindex-all" id="wp-worthy-reindex-all" /> <label for="wp-worthy-reindex-all">', __ ('Reindex everything, even posts that are already indexed', $this->textDomain), '</label></p>',
              '<p><button type="submit" name="action" value="wp-worthy-reindex" class="button action button-primary">', __ ('Generate length-index', $this->textDomain), '</button></p>',
            '</form>';
      
      $invalidAuthorCount = wp_worthy_maintenance::getInvalidAuthorCount ();
      
      if ($invalidAuthorCount > 0) {
        echo
            '<hr />',
            '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_ADMIN, null, true), '">',
              '<p>',
                '<strong>', sprintf (_n ('%d pixel', '%d pixels', $invalidAuthorCount, $this->textDomain), $invalidAuthorCount), '</strong> ',
                _n ('is assigned to a non-existing author', 'are assigned to non-existing authors', $invalidAuthorCount, $this->textDomain),
              '<p>',
                __ ('Orphaned pixels may exist on your database if you were an early adopter of Worthy or if you removed an author meanwhile without assigning its markers to another author.', $this->textDomain), '<br />',
                __ ('If you want to regain access to these pixels you may assign them to another author here.', $this->textDomain),
              '</p><p>',
                '<label for="wp-worthy-orphan-adopter">', __ ('Wordpress-User', $this->textDomain), '</label>',
                '<select id="wp-worthy-orphan-adopter" name="wp-worthy-orphan-adopter">';

        foreach ($userList as $ID=>$User)
          echo    '<option value="', (int)$ID, '">', esc_html ($User), '</option>';

        echo
                '</select>',
              '</p><p>',
                '<button type="submit" name="action" value="wp-worthy-set-orphaned" class="button action button-primary">', __ ('Assign to new author', $this->textDomain), '</button>',
              '</p>',
            '</form>';
      }
      
      echo
          '</div>',
        '</div>';
      
      // Output marker-migration
      if (count ($sharingUsers) > 1) {
        echo
          '<div class="stuffbox"id="wp-worthy-box-sharing-container">',
            '<h2 id="wp-worthy-box-sharing">', __ ('Sharing accounts and migrating markers', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<form class="worthy-form" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_ADMIN, null, true), '">',
                '<p>',
                  __ ('Markers are bound to a specific account, but may be shared with other accounts.', $this->textDomain), ' ',
                  __ ('Worthy provides the option to share markers as a self-service, users may configure sharing without administrator-privileges required.', $this->textDomain), ' ',
                  __ ('If you want to configure sharing for other users, you may do it here.', $this->textDomain), ' ',
                  __ ('Additionally you can change the ownership of existing markers, but always be careful using this function and make sure that you have the permission of the owner before doing so.', $this->textDomain),
                '</p><p>',
                  '<label for="wp-worthy-admin-share-source">', __ ('Wordpress-User', $this->textDomain), '</label>',
                  '<select name="wp-worthy-admin-share-source" id="wp-worthy-admin-share-source">';
        
        foreach ($userList as $ID=>$User)
          echo      '<option value="', (int)$ID, '"', ($IDs [0] == $ID ? ' selected="1"' : ''), '>', esc_html ($User), '</option>';
        
        if (count ($userList) > 2)
          echo      '<option value="-1">', __ ('Everyone (all users)', $this->textDomain), '</option>';
        
        echo
                  '</select>',
                '</p><p>',
                  '<label for="wp-worthy-admin-share-mode">', __ ('Action'), '</label>',
                  '<select name="wp-worthy-admin-share-mode" id="wp-worthy-admin-share-mode">',
                    '<option value="share">', __ ('should use markers of the user (sharing)', $this->textDomain), '</option>',
                    '<option value="migrate">', __ ('should move his markers to the user (migrating)', $this->textDomain), '</option>',
                    '<option value="both">', __ ('should move his markers to and use the markers of the user (migrate and share)', $this->textDomain), '</option>',
                  '</select>',
                '</p><p>',
                  '<label for="wp-worthy-admin-share-destination">', __ ('Wordpress-User', $this->textDomain), '</label>',   
                  '<select name="wp-worthy-admin-share-destination" id="wp-worthy-admin-share-destination">';
        
        foreach ($userList as $ID=>$User)
          echo      '<option value="', (int)$ID, '"', ($IDs [1] == $ID ? ' selected="1"' : ''), '>', esc_html ($User), '</option>';
        
        echo
                  '</select>',
                '</p><p>',
                  '<button type="submit" class="button action button-primary" name="action" value="wp-worthy-admin-share">', __ ('Apply'), '</button>',
                '</p>',
              '</form>',
            '</div>',
          '</div>';
      }
    }
    // }}}
    
    // {{{ adminMenuAdminPrepare
    /**
     * Prepare to display admin-menu
     * 
     * @access public
     * @return void
     **/
    public function adminMenuAdminPrepare () {
      // Do some common stuff
      $this->adminMenuPrepare ();
      
      // Stop here if there is no status to display
      if (!isset ($_REQUEST ['displayStatus']))
        return;
      
      // Output status
      if ($_REQUEST ['displayStatus'] == 'reindexDone')
        $this->adminStatus [] =
          '<div class="wp-worthy-success">' .
            '<strong>' . sprintf (_n ('%d post', '%d posts', intval ($_REQUEST ['postCount']), $this->textDomain), intval ($_REQUEST ['postCount'])) . '</strong> ' .
            __ ('have been indexed', $this->textDomain) .
          '</div>';
      elseif ($_REQUEST ['displayStatus'] == 'settingsSaved')
        $this->adminStatus [] =
          '<div class="wp-worthy-success">' .
            __ ('Settings have been saved', $this->textDomain) .
          '</div>';
      
      elseif ($_REQUEST ['displayStatus'] == 'shareAndMigrateDone')
        $this->adminStatus [] =
          '<div class="wp-worthy-success">' .
            __ ('Operation was completed successfully!', $this->textDomain) . ' ' .
            (($_REQUEST ['mode'] == 'migrate') || ($_REQUEST ['mode'] == 'both') ? ' ' . sprintf (__ ('%d markers were migrated.', $this->textDomain), (int)$_REQUEST ['count']) : '') .
          '</div>';
      
      elseif ($_REQUEST ['displayStatus'] == 'invalidParameter')
        $this->adminStatus [] =
          '<div class="wp-worthy-error">' .
            __ ('Strange! Share and Migrate was called with an invalid parameter.', $this->textDomain) .
          '</div>';
      
      elseif ($_REQUEST ['displayStatus'] == 'duplicateUser')
        $this->adminStatus [] =
          '<div class="wp-worthy-error">' .
            __ ('You can not run Share and Migrate on the same user.', $this->textDomain) .
          '</div>';
      
      elseif ($_REQUEST ['displayStatus'] == 'loopDetected')
        $this->adminStatus [] =
          '<div class="wp-worthy-error">' .
            __ ('Oops! Sharing in this way would cause an endless loop.', $this->textDomain) .
          '</div>';
      
      elseif ($_REQUEST ['displayStatus'] == 'setOrphanedAdopterDone')
        $this->adminStatus [] =
          '<div class="wp-worthy-success">' .
            __ ('Operation was completed successfully!', $this->textDomain) . ' ' .
            sprintf (__ ('%d markers were migrated.', $this->textDomain), (int)$_REQUEST ['count']) .
          '</div>';
    }
    // }}}
    
    // {{{ adminMenuPremium
    /**
     * Display premium section
     * 
     * @access public
     * @return void
     **/
    public function adminMenuPremium () {
      // Draw admin-header
      $this->adminMenuHeader ($this::ADMIN_SECTION_PREMIUM);
      
      // Make sure we have SOAP available
      if (
        !extension_loaded ('soap') ||
        !extension_loaded ('openssl')
      ) {
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Worthy Premium', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>', __ ('You need to have the SOAP- and OpenSSL-Extension for PHP available to use Worthy Premium.', $this->textDomain), '</p>',
            '</div>',
          '</div>';
        
        return $this->adminMenuFooter ();
      }
      
      if (isset ($_REQUEST ['feedback'])) {
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Worthy Premium Feedback', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<form class="worthy-form" id="worthy-feedback" method="post" action="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, null, true), '">',
                '<p>',
                  '<label for="worthy-feedback-mail">', __ ('E-Mail (optional)'), '</label>',
                  '<input type="text" id="worthy-feedback-mail" name="worthy-feedback-mail" />',
                '</p><p>',
                  '<label for="worthy-feedback-caption">', __ ('Summary'), '</label>',
                  '<input type="text" id="worthy-feedback-caption" name="worthy-feedback-caption" />',
                '</p><p>',
                  '<label for="worthy-feedback-rating">', __ ('Rating'), '</label>',
                  '<select name="worthy-feedback-rating" id="worthy-feedback-rating">',
                    '<option value="0">', __ ('0 stars - you guys really messed it up', $this->textDomain), '</option>',
                    '<option value="1">', __ ('1 star - good idea, but ...', $this->textDomain), '</option>',
                    '<option value="2">', __ ('2 stars - works with some issues', $this->textDomain), '</option>',
                    '<option value="3" selected="1">', __ ('3 stars - works for me, but could be better', $this->textDomain), '</option>',
                    '<option value="4">', __ ('4 stars - great work that could be improved a bit', $this->textDomain), '</option>',
                    '<option value="5">', __ ('5 stars - it\'s simply amazing!', $this->textDomain), '</option>',
                  '</select>',
                '</p><p>',
                  '<label for="worthy-feedback-text">', __ ('Feedback'), '</label>',
                  '<textarea name="worthy-feedback-text" id="worthy-feedback-text"></textarea>',
                '</p><p>',
                  '<button type="submit" name="action" value="wp-worthy-feedback" class="button button-large button-primary">', __ ('Submit'), '</button>',
                '</p>',
              '</form>',
            '</div>',
          '</div>';
        
        return $this->adminMenuFooter ();
      }
      
      // Try to retrive our account-status from worthy-premium
      $Status = $this->premiumUpdateStatus ();
      
      if (
        isset ($_REQUEST ['shopping']) &&
        ($Status ['Status'] != 'unregistered')
      )
        return $this->adminMenuPremiumShop ($Status);
      
      // Display notice if this account is not active
      if (
        ($Status ['Status'] != 'testing') &&
        ($Status ['Status'] != 'registered')
      )
        return $this->adminMenuPremiumUnregistered ($Status);
      
      // Check wheter to output status
      if (
        isset ($_REQUEST ['displayStatus']) &&
        (($webAreas = ($_REQUEST ['displayStatus'] == 'webareasDone')) || ($_REQUEST ['displayStatus'] == 'reportDone'))
      ) {
        echo
          '<div class="stuffbox">',
            '<h2>', __ ($webAreas ? 'Webareas were created' : 'Report to VG WORT was done', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<ul class="ul-square">';
        
        // Output list of posts that were successfully reported
        if ($haveSuccess = (isset ($_REQUEST ['sR']) && is_array ($_REQUEST ['sR']) && (count ($_REQUEST ['sR']) > 0))) {
          echo
            '<li>',
               __ ($webAreas ? 'Posts that were webareas created for' : 'Posts that were successfully reported', $this->textDomain),
              '<ul class="ul-square">';
          
          foreach ($this->unpackPostIDs ($_REQUEST ['sR']) as $postID)
            echo '<li>', $this->getPostAdminLink ($postID ['postid'], $postID ['siteid']), '</li>';
          
          echo
              '</ul>',
            '</li>';
          
          // Update markers-status for reported markers
          if (!$webAreas) {
            $this->premiumUpdatePixelStatus ([ self::MARKER_STATUS_REPORTED ]);
            $this->premiumUpdateStatus (true);
          }
        }
        
        // Output list of posts that could not be reported
        if (
          isset ($_REQUEST ['fR']) &&
          is_array ($_REQUEST ['fR']) &&
          (count ($_REQUEST ['fR']) > 0)
        ) {
          if (
            !$webAreas &&
            ($Status = $Status = $this->premiumUpdateStatus (!$haveSuccess)) &&
            (!isset ($Status ['ReportLimit']) || ($Status ['ReportLimit'] < 1))
          )
            echo '<li><strong>', sprintf (__ ('You have no budget for reports left. Consider to buy a new bundle on <a href="%s">our shop</a> and try again.', $this->textDomain), $this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('shopping' => 'isfun'))), '</strong></li>';
          
          echo
            '<li>',
              __ ($webAreas ? 'Post that could not be a webarea created for' : 'Posts that could not be reported', $this->textDomain),
              '<ul class="ul-square">';
          
          foreach ($_REQUEST ['fR'] as $postID=>$errorMessage)
            if ($postID = $this->unpackPostID ($postID))
              echo '<li>', $this->getPostAdminLink ($postID ['postid'], $postID ['siteid']), '<br />', esc_html ($errorMessage), '</li>';
          
          echo
              '</ul>',
            '</li>';
        } elseif (
          !isset ($_REQUEST ['iI']) ||
          !is_array ($_REQUEST ['iI']) ||
          (count ($_REQUEST ['iI']) == 0)
        )
          echo '<li>', __ ('No errors happended during the process', $this->textDomain), '</li>';
        
        // Output list of invalid post-ids
        if (
          isset ($_REQUEST ['iI']) &&
          is_array ($_REQUEST ['iI']) &&
          (count ($_REQUEST ['iI']) > 0)
        ) {
          echo
            '<li>',
              __ ('Invalid Post-IDs', $this->textDomain),
              '<ul class="ul-square">';
          
          foreach ($this->unpackPostIDs ($_REQUEST ['iI']) as $postID)
            echo '<li>', $this->packPostID ($postID), '</li>';
          
          echo
              '</ul>',
            '</li>';
        }
        
        echo
              '</ul>',
            '</div>',
          '</div>';
      } elseif (
        isset ($_REQUEST ['displayStatus']) &&
        ($_REQUEST ['displayStatus'] == 'privateImportDone')
      )
        echo  
          '<div class="stuffbox">',
            '<h2>', __ ('Private part of markers imported', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>',
                sprintf (__ ('<strong>%d of %d</strong> private parts where imported via Worthy Premium.', $this->textDomain), intval ($_REQUEST ['done']), intval ($_REQUEST ['total'])),
              '</p>',
              ($_REQUEST ['done'] != $_REQUEST ['total'] ?
              '<p>' .
                  __ ('All other where not found on this VG WORT-Account!', $this->textDomain) . '<br />' .
                  ($this::ENABLE_ANONYMOUS_MARKERS ? __ ('It is possible that the unknown private parts are anonymous markers, before Worthy Premium is able to find them, they have to be personalized - Worthy Premium may do this for you, too.', $this->textDomain) . ' ' : '') .
                  __ ('To find out more about the markers that were not found, please start a manual marker inquiry on T.O.M.', $this->textDomain) .
              '</p>' : ''),
            '</div>',
          '</div>';
      
      // Check wheter to preview the report for a number of posts
      if (
        isset ($_REQUEST ['action']) &&
        ($_REQUEST ['action'] == 'wp-worthy-premium-report-posts-preview')
      ) {
        echo
          '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, null, true), '" class="wp-worthy-might-message">',
            '<input type="hidden" name="action" value="wp-worthy-premium-report-posts" />',
            '<div class="stuffbox">',
              '<button type="submit" style="float: right; margin: 6px;">', __ ('Report to VG WORT', $this->textDomain), '</button>',
              '<h2>', __ ('Report preview', $this->textDomain), '</h2>',
              '<div style="clear: both;"></div>',
            '</div>';
        
        // Retrive SOAP-Client
        try {
          $soapClient = $this->getSOAPClient ();
          $soapSession = $this->premiumGetSession ();
        } catch (\Throwable $error) {
          $this->displayError ($error);
          echo '</form>';
          
          return $this->adminMenuFooter ();
        }
         
        // Create a helper-table for output
        $Table = new wp_worthy_table_posts ($this);
        static $sMap = array (
          -1 => 'not synced',
           0 => 'not counted',
           1 => 'not qualified',
           2 => 'partial qualified',
           3 => 'qualified',
           4 => 'reported',
        );
        
        if (
          !isset ($_REQUEST ['post']) ||
          !is_array ($_REQUEST ['post'])
        )
          $_REQUEST ['post'] = array ();
        
        foreach ($this->unpackPostIDs ($_REQUEST ['post']) as $postID) {
          // Retrive this post
          if (!is_object ($thePost = wp_worthy_post::fromID ($postID ['postid'], $postID ['siteid']))) {
            echo
              '<div class="wp-worthy-error">',
                '<p>', sprintf (__ ('Could not retrive the requested post %d', $this->textDomain), $postID ['postid']), '</p>',
              '</div>';
            
            continue;
          }
          
          // Check ownership of the post
          if (!$thePost->isOwnPost ())
            continue;
          
          // Retrive marker for this post
          $markerStatus = $GLOBALS ['wpdb']->get_row (
            'SELECT * ' .
            'FROM `' . $this->getTablename ('worthy_markers', 0) . '` ' .
            'WHERE ' .
              '`siteid`="' . $postID ['siteid'] . '" AND ' .
              '`postid`=' . $postID ['postid']
          );
          
          if (!$markerStatus)
            continue;
          
          try {
            $Author = get_userdata ($thePost->authorId);
            $postTitle = $thePost->getTitle (true);
            
            $authorFirstname =get_user_meta ($thePost->authorId, 'wp-worthy-forename', true);
            $authorLastname = get_user_meta ($thePost->authorId, 'wp-worthy-lastname', true);
            $authorCardNumber = get_user_meta ($thePost->authorId, 'wp-worthy-cardid', true);
            
            // Check wheter to inherit names from user-preferences
            if (strlen ($authorFirstname) == 0)
              $authorFirstname = $Author->first_name;
            
            if (strlen ($authorLastname) == 0)
              $authorLastname = $Author->last_name;
            
            echo
              '<div class="stuffbox" style="padding-left: 20px; padding-bottom: 20px;">',
                '<p class="worthy-report-preview">',
                  '<input type="checkbox" checked="1" id="post_', $postID ['siteid'], '-', $postID ['postid'], '" name="post[]" value="', $this->packPostID ($postID), '" /> ',
                  '<label for="post_', $postID ['siteid'], '-', $postID ['postid'], '">',
                    (is_multisite () ? '<span>' . __ ('Site-ID', $this->textDomain) . ': <strong>' . $postID ['siteid'] . '</strong>,</span> ' : ''),
                    '<span>', __ ('Post-ID', $this->textDomain), ': <strong>', $postID ['postid'], '</strong>,</span> ',
                    '<span>', __ ('Title', $this->textDomain), ': <strong>', esc_html ($postTitle), '</strong>,</span> ',
                    '<span>', __ ('Author', $this->textDomain), ': <strong>', esc_html ($authorFirstname . ' ' . $authorLastname . (strlen ($authorCardNumber) > 0 ? ' (' . __ ('Card-Number', $this->textDomain) . ' ' . $authorCardNumber . ')' : '')),'</strong>,</span> ',
                    '<span>', __ ('Date', $this->textDomain), ': <strong>', $Table->column_date ($thePost), '</strong>,</span> ',   
                    '<span>', __ ('Length', $this->textDomain), ': <strong>', $Table->column_characters ($thePost), '</strong>,</span> ',
                    '<span>', __ ('URL', $this->textDomain), ': <a target="_blank" href="', esc_attr ($postURL = $thePost->getURL ()), '">', esc_html ($postURL), '</a>,</span> ',
                    '<span>', __ ('Private Marker', $this->textDomain), ': <strong>', esc_html ($markerStatus->private), '</strong>,</span> ',
                    '<span>', __ ('Status', $this->textDomain), ': <strong>', __ ($sMap [$markerStatus->status === null ? -1 : $markerStatus->status], $this->textDomain), '</strong></span>',
                  '</label>',
                '</p>',
              (strlen ($postTitle) <= wp_worthy_post::TITLE_MAX_LENGTH ? '' :
                '<p><span class="wp-worthy-warning">' . __ ('Title is too long', $this->textDomain) . '</span></p>') .
                '<pre class="wp-worthy-preview">',
                  '<span class="wp-worthy-inline-title" id="wp-worthy-title-', $postID ['siteid'], '-', $postID ['postid'], '">',
                    esc_html ($postTitle), "\n", str_repeat ('-', strlen ($postTitle)),
                  '</span>', "\n",
                  '<span class="wp-worthy-inline-content" id="wp-worthy-content-', $postID ['siteid'], '-', $postID ['postid'], '">',
                    esc_html ($soapClient->reportPreview ($soapSession, '', $thePost->getContent (), false)),
                  '</span>',
                '</pre>',
              '</div>';  
          } catch (\Throwable $error) {
            $this->displayError ($error);
          }
        }  
           
        echo '</form>';
        
        return $this->adminMenuFooter ();
      }
      
      /**
       * Display subscribtion-status
       **/
      $tf = get_option ('time_format');
      $df = get_option ('date_format');
      $userID = $this->getUserID ();
      
      echo
        '<div class="stuffbox">',
          '<h2>', __ ('Worthy Premium Subscription', $this->textDomain), '</h2>',
          '<div class="inside">';
      
      if ($Status ['Status'] == 'registered')
        echo '<p>', __ ('You are fully subscribed to Worthy Premium.', $this->textDomain), '</p>';
      else
        echo
          '<p>', __ ('You are using the Worthy Premium Test-Drive.', $this->textDomain), '</p>' .
          ($Status ['Status'] == 'testing-pending' ? '<p>' . __ ('Please be patient! We received your subscription-request but have not received or processed your payment yet.', $this->textDomain) . '</p>' : '');
      
      echo
            '<ul class="ul-square">',
              '<li><span class="wp-worthy-label">', __ ('Number of reports remaining', $this->textDomain), ':</span> ', sprintf (__ ('%d reports', $this->textDomain), $Status ['ReportLimit']), '</li>',
              '<li><span class="wp-worthy-label">', __ ('Begin of subscribtion', $this->textDomain), ':</span> ', sprintf (__ ('%s at %s', $this->textDomain), date_i18n ($df, $Status ['ValidFrom']), date_i18n ($tf, $Status ['ValidFrom'])), '</li>',
              '<li><span class="wp-worthy-label">', __ ('End of subscribtion', $this->textDomain), ':</span> ', sprintf (__ ('%s at %s', $this->textDomain), date_i18n ($df, $Status ['ValidUntil']), date_i18n ($tf, $Status ['ValidUntil'])), '</li>',
            '</ul>',
            '<p><a href="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('shopping' => 'isfun')), '">',
            ($Status ['Status'] == 'registered' ?
              __ ('If you need more reports or want to advance you subscribtion, please visit our Shop.', $this->textDomain) :
              __ ('If you want to subscribe to Worthy Premium, please visit our Shop.', $this->textDomain)
            ),
            '</a></p>',
          '</div>',
        '</div>',
        '<div class="stuffbox">',
          '<h2>', __ ('Worthy Premium Status', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<ul class="ul-square">',
              '<li>',
                '<span class="wp-worthy-label">', __ ('Number of markers imported', $this->textDomain), ':</span> ', intval (get_user_meta ($userID, 'worthy_premium_markers_imported', true)), ' (', sprintf (__ ('%d total', $this->textDomain), get_option ('worthy_premium_markers_imported', 0)), ') ',
                '<small>(<a href="', $this->linkSection ($this::ADMIN_SECTION_CONVERT), '">', __ ('Import new markers', $this->textDomain), '</a>)</small>',
              '</li>',
              '<li>',
                '<span class="wp-worthy-label">', __ ('Number of markers synced', $this->textDomain), ':</span> ', intval (get_user_meta ($userID, 'worthy_premium_marker_updates', true)), ' (', sprintf (__ ('%d total', $this->textDomain), get_option ('worthy_premium_marker_updates', 0)), ') ',
                # '<small>(', $this->inlineAction ($this::ADMIN_SECTION_PREMIUM, 'wp-worthy-premium-sync-pixels', __ ('Synchronize now', $this->textDomain)), ')</small>',
              '</li>',
            '</ul>',
            '<ul class="ul-square">',
              '<li>',
                '<span class="wp-worthy-label">', __ ('Last check of subscribtion-status', $this->textDomain), ':</span> ',
              ((($ts = intval (get_user_meta ($userID, 'worthy_premium_status_updated', true))) > 0) ?
                sprintf (__ ('%s at %s', $this->textDomain), date_i18n ($df, $ts), date_i18n ($tf, $ts)) : __ ('Not yet', $this->textDomain)), ' ',
                '<small>(', $this->inlineAction ($this::ADMIN_SECTION_PREMIUM, 'wp-worthy-premium-sync-status', __ ('Synchronize now', $this->textDomain)), ')</small>',
              '</li>',
              '<li>',
                '<span class="wp-worthy-label">', __ ('Last syncronisation of marker-status', $this->textDomain), ':</span> ',
              ((($ts = $this->premiumGetLastMarkerUpdate ($userID)) > 0) ?
                sprintf (__ ('%s at %s', $this->textDomain), date_i18n ($df, $ts), date_i18n ($tf, $ts)) : __ ('Not yet', $this->textDomain)), ' ',
                '<small>(', $this->inlineAction ($this::ADMIN_SECTION_PREMIUM, 'wp-worthy-premium-sync-pixels', __ ('Synchronize now', $this->textDomain)), ')</small>',
              '</li>',
            '</ul>',
          '</div>',
        '</div>';
      
      $this->adminMenuPremiumDropCredentials ();
      $this->adminMenuPremiumServer ();
      $this->adminMenuFooter ();
    }
    // }}}
    
    // {{{ adminMenuPremiumUnregistered
    /**
     * Display Premium-Menu for unregistered or expired users
     * 
     * @param enum $Status
     * 
     * @access private
     * @return void
     **/
    private function adminMenuPremiumUnregistered ($Status) {
      /**
       * Display a notice if testing-period is expired
       **/
      if ($Status ['Status'] == 'testing-expired') {
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Worthy Premium Subscription', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>',
                __ ('Sadly your test-drive is over now. :-(', $this->textDomain), '<br />',
                __ ('We hope you enjoyed the test and we could convince you with our service!', $this->textDomain),
              '</p>',
              '<ul class="ul-square">',
                '<li><a href="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('shopping' => 'isfun')), '">', __ ('Subscribe to Worthy Premium', $this->textDomain), '</a></li>',
                '<li><a href="http://wordpress.org/support/view/plugin-reviews/wp-worthy" target="_blank">', __ ('Write a review about Worthy', $this->textDomain), '</a></li>',
                '<li><a href="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('feedback' => 1)), '">', __ ('Tell us your opinion about Worthy - in private', $this->textDomain), '</a></li>',
                '<li>', $this->inlineAction ($this::ADMIN_SECTION_PREMIUM, 'wp-worthy-premium-sync-status', __ ('Check your subscription-status again', $this->textDomain)), '</li>',
              '</ul>',
            '</div>',
          '</div>';
          
          $this->adminMenuPremiumDropCredentials ();
      /**
       * Display notice if a premium-subscribtion has expired
       **/
      } elseif ($Status ['Status'] == 'expired') {
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Worthy Premium Subscription', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>', __ ('Your Worthy Premium Subscription expired.', $this->textDomain), '</p>',
              '<p>', __ ('If you want continue to use Worthy Premium, we ask you to renew your subscribtion. We would be very glad to have you for another year as our customer!', $this->textDomain), '</p>',
              '<ul class="ul-square">',
                '<li><a href="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('shopping' => 'isfun')), '">', __ ('Subscribe to Worthy Premium', $this->textDomain), '</a></li>',
                '<li><a href="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('feedback' => 1)), '">', __ ('Tell us your opinion about Worthy - in private', $this->textDomain), '</a></li>',
                '<li>', $this->inlineAction ($this::ADMIN_SECTION_PREMIUM, 'wp-worthy-premium-sync-status', __ ('Check your subscription-status again', $this->textDomain)), '</li>',
              '</ul>',
            '</div>',
          '</div>';
        
        $this->adminMenuPremiumDropCredentials ();
      /**
       * Display notice if the account is being upgraded (not from testing)
       **/
      } elseif ($Status ['Status'] == 'pending') {
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Worthy Premium Subscription', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>',
                __ ('Please be patient! We received your subscription-request but have not received or processed your payment yet.', $this->textDomain),
              '</p>',
            '</div>',
          '</div>';
        
        $this->adminMenuPremiumServer ();
        
        return $this->adminMenuFooter ();
      
      /**
       * Display sign-up formular
       **/
      } else
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Worthy Premium Sign Up', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<div class="worthy-signup">',
                '<form method="post" id="wp-worthy-signup" class="worthy-form" action="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, null, true), '">',
                  '<fieldset>',
                    '<p>',
                      '<label for="wp-worthy-username">', __ ('Username for VG WORT T.O.M.', $this->textDomain), '</label>',
                      '<input id="wp-worthy-username" type="text" name="wp-worthy-username" />',
                    '</p><p>',
                      '<label for="wp-worthy-password">', __ ('Password', $this->textDomain), '</label>',
                      '<input id="wp-worthy-password" type="password" name="wp-worthy-password" />',
                    '</p><p>',
                      '<input type="checkbox" name="wp-worthy-accept-tac" id="wp-worthy-accept-tac" value="1" /> ',
                      '<label for="wp-worthy-accept-tac">',
                        sprintf (__ ('I have read and accepted the <a href="%s" id="wp-worthy-terms" target="_blank">terms of service</a> and <a href="%s" id="wp-worthy-privacy" target="_blank">the privacy statement</a>', $this->textDomain), 'https://api.wp-worthy.de/soap/terms.html', 'https://api.wp-worthy.de/soap/privacy.html'),
                      '</label>',
                    '</p><p>',
                      '<button type="submit" class="button action" name="action" value="wp-worthy-premium-signup">', __ ('Sign up for Worthy Premium Testdrive', $this->textDomain), '</button>',
                    '</p>',
                  '</fieldset>',
                '</form>',
              '</div><div>',
                '<p>', __ ('To sign up for Worthy Premium, you\'ll need a valid T.O.M.-Login.', $this->textDomain), '</p>',
                '<p>', __ ('Your Login-Information is required to get automated access to your T.O.M. account. Without this Worthy Premium is not able to work.', $this->textDomain), '</p>',
                '<p>',
                  __ ('To get in touch with Worthy Premium and its amazing functions, you\'ll receive a risk-free trail-account in first place. We are very sure that you\'ll be excited.', $this->textDomain), ' ',
                  __ ('You may buy a full-featured Worthy Premium-Subscribtion whenever you want.', $this->textDomain),
              '</div>',
              '<div class="clear"></div>',
            '</div>',
          '</div>';
      
      /**
       * Give an overview what "Worthy Premium" is
       **/
      echo
        '<div class="stuffbox">',
          '<h2>', __ ('Worthy Premium', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<p>', __ ('Why should I use Worthy Premium?', $this->textDomain), '</p>',
            '<ul class="ul-square">',
              '<li>',
                __ ('Worthy Premium gives you an <strong>automated import of markers</strong>.', $this->textDomain), '<br />',
                __ ('You will no longer have to leave Wordpress and login at T.O.M. for this task.', $this->textDomain),
              '</li>',
              '<li>',
                __ ('Worthy Premium <strong>keeps track on the status of markers</strong>.', $this->textDomain), '<br />',
                __ ('You will be able to directly see if a post has already qualified, is on a good way or not. Everything happens directly inside your wordpress admin-panel!', $this->textDomain),
              '</li>',
              '<li>',
                '<strong>', __ ('Most important', $this->textDomain), ':</strong> ', __ ('Worthy Premium enables you to <strong>generate reports for all qualified posts</strong>!', $this->textDomain), '<br />',
                __ ('Save hours of time by submitting reports to VG WORT via Worthy Premium instead of copy and pasting posts on your own! This is the most comfortable feature most professional authors and bloggers have waited for!', $this->textDomain),
              '</li>',
            '</ul>',
          '</div>',
        '</div>';
      
      /**
       * Introduce the "Worthy Premium Testdrive"
       * 
       * Huge remark:
       * Below are stated some numbers belonging to our free test-drive.
       * They are inserted dynamically into output just to keep translation-overhead
       * small. If you change them on your own, it does not affect anything.
       **/
      if ($Status ['Status'] == 'unregistered')
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Worthy Premium Testdrive', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>',
                __ ('I guess we do now have your attention, right? But before you have to paid even a cent for Worthy Premium you may validate every of our promises by yourself.', $this->textDomain), '<br />',
                __ ('There are no hidden costs, no traps and no automatic renewals. There is no way that is more fair or uncomplicated!', $this->textDomain),
              '</p><p>',
                __ ('This is the reason why we offer a limited test-drive.', $this->textDomain),
              '</p>',
              '<ul class="ul-square">',
                '<li><strong>', sprintf (__ ('Get free access to our service for %d days', $this->textDomain), 7), '</strong></li>',
                '<li><strong>', sprintf (__ ('Submit reports to VG WORT for up to %d posts during that time', $this->textDomain), 3), '</strong></li>',
                '<li>', __ ('Import as much new markers as you like to (in batches of 100 markers per import) using your limited trial-access', $this->textDomain), '</li>',
                '<li>', __ ('Get free status-updates for all your markers while the trial-period is running', $this->textDomain), '</li>',
              '</ul>',
              '<p>', __ ('To setup a trial-account you only need to have an existing VG WORT T.O.M. account. We only need your login-credentials. (See Worthy Premium Security Notes for details)', $this->textDomain), '</p>',
            '</div>',
          '</div>';
      
      if ($Status ['Status'] == 'unregistered')
        echo
          '<div class="stuffbox">',
            '<h2>', __ ('Worthy Premium Security Notes', $this->textDomain), '</h2>',
            '<div class="inside">',
              '<p>',
                __ ('Worthy Premium is a webservice located between your Wordpress-Blog and VG WORT T.O.M..', $this->textDomain), ' ',
                __ ('As Worthy Premium works on your behalf at T.O.M., you need to supply your login-information to Worthy.', $this->textDomain),
              '</p><p>',
                __ ('Your login-information will be handled with highest security. Worthy Premium will not store your password, it will be submitted by your wordpress-installation whenever a login for T.O.M. is required.', $this->textDomain),
              '</p><p>',
                __ ('If you choose not to use our service, we ask kindly to change your login-credentials and remove them here.', $this->textDomain),
              '</p>',
            '</div>',
          '</div>';
      
      $this->adminMenuPremiumServer ();
      $this->adminMenuFooter ();
    }
    // }}}
    
    private function adminMenuPremiumDropCredentials () {
      echo
        '<div class="stuffbox">',
          '<h2>', __ ('VG WORT-Credentials', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, null, true), '">',
              '<p>',
                '<input type="checkbox" id="wp-worthy-remove-premium-credentials" name="wp-worthy-remove-premium-credentials" value="1" /> ',
                '<label for="wp-worthy-remove-premium-credentials">', __ ('Remove stored VG WORT-Credentials from this Wordpress-Installation', $this->textDomain), '</label>',
              '</p><p>',
                __ ('If you don\'t want to use Worthy Premium on this Wordress-Installation anymore, you can remove your stored VG WORT-Credentials from this site using this option.', $this->textDomain),
              '</p></p>',
                __ ('Please note that this does not result in a cancellation of your Worthy Premium subscribtion.', $this->textDomain), ' ',
                __ ('Your subscribtion will expire as normal and may still be used on other sites.', $this->textDomain), ' ',
                __ ('If you want to cancel your Worthy Premium subscribtion, please write an e-mail to <a href="mailto:support@wp-worthy.de">support@wp-worthy.de</a>.', $this->textDomain), ' ',
              '</p><p>',
                '<button class="button action button-link-delete" type="submit" name="action" value="wp-worthy-premium-drop-registration">', __ ('Drop local user-credentials', $this->textDomain), '</button>',
              '</p>',
            '</form>',
          '</div>',
        '</div>';
    }
    
    // {{{ adminMenuPremiumShop
    /**
     * Output Worthy Premium Shop
     * 
     * @access private
     * @return void
     **/
    private function adminMenuPremiumShop ($Status) {
      // Access the SOAP-Client here
      try {
        $soapClient = $this->getSOAPClient ();
        $soapSession = $this->premiumGetSession (true);
      } catch (\Throwable $error) {
        $this->displayError ($error);
        
        return $this->adminMenuFooter ();
      }
      
      // Check if there is a shopping-result
      if (
        isset ($_GET ['rc']) &&
        in_array ($_GET ['rc'], array ('done', 'processing', 'canceled'))
      ) {
        if ($_GET ['rc'] == 'done') {
          $msg = array ('All done', 'Your order was successfull and is already paid. We hope that you enjoy using Worthy Premium! Thank you!');
          
          $this->premiumUpdateStatus (true);
        } elseif ($_GET ['rc'] == 'processing')
          $msg = array ('We are processing your order', 'Once your order is paid your account will be updated. This usually takes less than a minute but can depend on how you processed the payment.');
        elseif ($_GET ['rc'] == 'canceled')
          $msg = array ('Payment was canceled', 'How sad! Your payment was canceled. Don\'t you feel confident with using Worthy Premium?');
        
        echo
          '<div class="stuffbox">',
            '<h2>', __ ($msg [0], $this->textDomain), '</h2>',
            '<div class="inside">',
              __ ($msg [1], $this->textDomain),
            '</div>',
          '</div>';
      }
      
      // Output items available on the shop
      try {
        $Goods = $soapClient->serviceGetPurchableGoods ($soapSession);
        
        echo
          '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('shopping' => 'isfun'), true), '" id="wp-worthy-shop">',
            '<input type="hidden" name="action" value="wp-worthy-premium-purchase" />',
            '<div class="stuffbox" id="wp-worthy-shop-goods">',
              '<h2>', __ ('Worthy Premium Shop', $this->textDomain), '</h2>',
              '<div class="inside">';
        
        $r = 0;
        $c = 0;
        
        foreach ($Goods as $Good) {
          echo
                '<div class="wp-worthy-menu-half">',
                  '<h3>', esc_html__ ($Good->Name, $this->textDomain), '</h3>',
                ($Good->Description ? '<p>' . esc_html__ ($Good->Description, $this->textDomain) . '</p>' : ''),
                  '<p>';
          
          if (
            !isset ($Good->Required) ||
            !$Good->Required
          )
            echo
                    '<input type="radio" name="wp-worthy-good-', esc_attr ($Good->ID), '" value="none" id="wp-worthy-good-', esc_attr ($Good->ID), '-none" checked="1" /> ',
                    '<label for="wp-worthy-good-', esc_attr ($Good->ID), '-none">',
                      __ ('Leave unchanged', $this->textDomain),
                    '</label>',
                  '</p><p>';
          else
            $r++;
          
          foreach ($Good->Options as $Option)
            echo
                    '<input type="radio" name="wp-worthy-good-', esc_attr ($Good->ID), '" value="', esc_attr ($Option->ID), '" id="wp-worthy-good-', esc_attr ($Good->ID), '-', esc_attr ($Option->ID), '"', ($Good->Required && $Option->Default ? ' checked="1"' : ''), ' data-value="', esc_attr ($Option->PriceTotal), '" data-tax="', esc_attr ($Option->PriceTax), '" /> ',
                    '<label for="wp-worthy-good-', esc_attr ($Good->ID), '-', esc_attr ($Option->ID), '">',
                      '<span class="wp-worthy-label">',
                        esc_html__ ($Option->Name, $this->textDomain),
                        (isset ($Option->Description) ? '<br /><span class="wp-worthy-shop-option-description">' . esc_html__ ($Option->Description, $this->textDomain) . '</span>' : ''),
                      '</span>',
                      '<span class="wp-worthy-value wp-worthy-price">', number_format ($Option->PriceTotal, 2, ',', '.'), ' &euro;*</span>',
                    '</label><br />';
          
          echo
                  '</p>',
                '</div>';
          
          if ($c++ % 2 == 1)
            echo '<div class="clear"></div>';
        }
        
        echo
              ($r == count ($Goods) ? '<div class="wp-worthy-menu-half"><p>' . __ ('You have not purchased any subscribtion yet. Upon the first subscribtion it is required that you but a subscribtion and a bundle as well in combination.', $this->textDomain) . '</p></div>' : ''),
                '<div class="clear"></div>',
              '</div>',
            '</div>',
            '<div class="stuffbox">',
              '<h2>', __ ('Payment Options', $this->textDomain), '</h2>',
              '<div class="inside">',
                '<div class="wp-worthy-menu-half">',
                  '<p>',
                    '<label for="wp-worthy-payment-paypal">',
                      '<img src="', esc_attr (plugins_url ('assets/paypal.png', __FILE__)), '" width="150" height="38" align="absmiddle" />',
                    '</label>',
                  '</p><p>',
                    '<ul class="ul-square">',
                      '<li>', __ ('Works with credit-cards', $this->textDomain), '</li>',
                      '<li>', __ ('Checkout finishes fast', $this->textDomain), '</li>',
                    '</ul>',
                  '</p>',
                '</div>',
              '</div>',
              '<div class="clear"></div>',
            '</div>',
            '<div class="stuffbox">',
              '<div class="inside">',
                '<p style="float: right; text-align: right; max-width: 200px;">',
                  '<button type="submit" class="button button-large button-primary">', __ ('Proceed to checkout', $this->textDomain), '</button><br />',
                '</p>',
                '<p>',
                  '<strong>', __ ('Total', $this->textDomain), ': <span id="wp-worthy-shop-price">0,00</span> &euro;</strong><br />',
                  '<small>', __ ('Tax included', $this->textDomain), ': <span id="wp-worthy-shop-tax">0,00</span> &euro;</small>',
                '</p><p>',
                  '<input type="checkbox" value="1" name="wp-worthy-accept-tac" id="wp-worthy-accept-tac" /> ',
                  '<label for="wp-worthy-accept-tac">',
                    sprintf (__ ('I have read and accepted the <a href="%s" id="wp-worthy-terms" target="_blank">terms of service</a> and <a href="%s" id="wp-worthy-privacy" target="_blank">the privacy statement</a>', $this->textDomain), 'https://api.wp-worthy.de/soap/terms.html', 'https://api.wp-worthy.de/soap/privacy.html'),
                  '</label>',
                '</p>',
                '<p>', __ ('* All price are with tax included', $this->textDomain), '</p>',
                '<div class="clear"></div>',
              '</div>',
            '</div>',
          '</form>';
      } catch (\Throwable $error) {
        $this->displayError ($error);
      }
      
      $this->adminMenuPremiumServer ();
      $this->adminMenuFooter ();
    }
    // }}}
    
    // {{{ adminMenuPremiumServer
    /**
     * Output Menu to select server to use with worthy-premium
     * 
     * @access private
     * @return void
     **/
    private function adminMenuPremiumServer () {
      // Check the server-setting
      $Server = get_user_meta ($this->getUserID (), 'worthy_premium_server', true);
      
      // Check if this is wanted
      if (
        (!defined ('WP_DEBUG') || !WP_DEBUG) &&
        (!defined ('WORTHY_DEBUG') || !WORTHY_DEBUG) &&
        ($Server != 'devel') &&
        !isset ($_REQUEST ['wp-worthy-show-debug'])
      )
        return;
      
      echo
        '<div class="stuffbox">',
          '<h2>', __ ('Worthy Premium Debugging', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<form method="post" action="', $this->linkSection ($this::ADMIN_SECTION_PREMIUM, null, true), '">',
              '<p>',
                '<input type="radio" name="wp-worthy-server" id="worthy-server-production" value="production"', (!$Server || ($Server != 'devel') ? ' checked="1"' : ''),' /> ',
                '<label for="worthy-server-production">', __ ('Use Worthy Premium Production Server', $this->textDomain), ' (HTTPS)</label><br />',
                '<input type="radio" name="wp-worthy-server" id="worthy-server-devel" value="devel"', (!$Server || ($Server != 'devel') ? '' : ' checked="1"'), ' /> ',
                '<label for="worthy-server-devel">', __ ('Use Worthy Premium Development Server', $this->textDomain), ' (HTTP)</label><br />',
              '</p><p>',
                '<button class="button action" name="action" value="wp-worthy-premium-select-server">', __ ('Change Worthy Premium Server', $this->textDomain), '</button>',
              '</p><p>',
                __ ('If something in S2S-Communication does not work, you might want to drop the current session', $this->textDomain),
              '</p><p>',
                '<button class="button action" name="action" value="wp-worthy-premium-drop-session">', __ ('Drop current session', $this->textDomain), '</button>',
              '</p><p>',
                __ ('You may want to drop the local user-credentials to make Worthy belive its not subscribed to Worthy Premium', $this->textDomain),
              '</p>',
            '</form>',
          '</div>',
        '</div>';
    }
    // }}}
    
    // {{{ adminMenuPremiumPrepare
    /**
     * Prepare to display premium-menu
     * 
     * @access public
     * @return void
     **/
    public function adminMenuPremiumPrepare () {
      // Do some common stuff first
      $this->adminMenuPrepare ();
      
      // Check if there is some status to display
      if (!isset ($_REQUEST ['displayStatus']))
        return;
      
      // Output status-message
      switch ($_REQUEST ['displayStatus']) {
        case 'signupDone':
          $this->adminStatus [] =
            ((int)$_REQUEST ['status'] == 0 ?
              '<div class="wp-worthy-error">' . __ ('Could sign up. Please check your login-credentials!', $this->textDomain) . '</div>' :
              ((int)$_REQUEST ['status'] == 1 ?
                '<div class="wp-worthy-success">' .  __ ('Signup with Worthy Premium was successfull!', $this->textDomain) . '</div>' :
                '<div class="wp-worthy-error">' . __ ('Could not store username and/or password on your wordpress-configuration. Strange!', $this->textDomain) . '</div>'
              )
            );
          
          break;
        case 'syncStatusDone':
          $this->adminStatus [] = '<div class="wp-worthy-success">' . __ ('Worthy Premium Status was successfully updated', $this->textDomain) . '</div>';
          
          break;
        case 'syncMarkerDone':
          if (($Count = (isset ($_REQUEST ['markerCount']) ? intval ($_REQUEST ['markerCount']) : -1)) >= 0)
            $this->adminStatus [] =
              '<div class="wp-worthy-success">' .
                '<p>' .
                  __ ('Synchronization was successfull.', $this->textDomain) . '<br />' .
                  sprintf (__ ('<strong>%d markers</strong> received an update (all others are unchanged)', $this->textDomain), $Count) .
                '</p>' .
                ($Count > 0 ? '<p><a href="' . $this->linkSection ($this::ADMIN_SECTION_MARKERS, array ('status_since' => (time () - 5))) . '">' . __ ('Show me that updates, please!', $this->textDomain) . '</a></p>' : '') .
              '</div>';
          else
            $this->adminStatus [] =
              '<div class="wp-worthy-error">' .
                __ ('There was an error while syncronising the markers', $this->textDomain) .
              '</div>';
          
          break;
        case 'feedbackDone':
          $this->adminStatus [] =
            '<div class="wp-worthy-success">' .
              '<p><strong>' . __ ('Thank you for your feedback!', $this->textDomain) . '</strong></p>' .
              '<p>' . __ ('We promise to read it carefully and respond within short time if a response is needed.', $this->textDomain) . '</p>' .
            '</div>';
          
          break;
        case 'noGoods':
          $this->adminStatus [] = '<div class="wp-worthy-error">' . __ ('You did not select anything to purchase.', $this->textDomain) . '</div>';
          
          break;
        case 'paymentError':
          $this->adminStatus [] =
            '<div class="wp-worthy-error">' .
              '<p>' . __ ('There was an error while initiating the payment', $this->textDomain) . ':</p>' .
              '<p>' . esc_html (__ ($_REQUEST ['Error'], $this->textDomain)) . '</p>' .
            '</div>';
          
          break;
      }
    }
    // }}}
    
    // {{{ adminMenuStatus
    /**
     * Output status-messages for admin-menu
     * 
     * @access private
     * @return void
     **/
    private function adminMenuStatus () {
      // Check Premium-Status
      if ($Status = $this->premiumUpdateStatus ()) {
        if (
          isset ($Status ['MessagePending']) &&
          $Status ['MessagePending'] &&
          (!isset ($Status ['Ready']) || $Status ['Ready'])
        )
          array_unshift ($this->adminStatus, __ ('There are messages pending at VG WORT. Please visit T.O.M. and see if there is something to confirm.', $this->textDomain));
        
        if (
          isset ($Status ['Ready']) &&
          isset ($Status ['Status']) &&
          !$Status ['Ready'] &&
          ($Status ['Status'] != 'unregistered')
        ) {
          static $statusHintMap = array (
            'expired' => 'Your subscribtion has expired and should be renewed. Please visit our shop under the "Premium"-tab.',
            'testing-expired' => 'Your testing-period has expired. You can purchase a subscribtion or remove the test-account under the "Premium"-tab.',
            'registered' => 'This may be because of changes on VG WORT terms and conditions. Please visit T.O.M. to see if there has something changed.',
            'testing' => 'This may be because of changes on VG WORT terms and conditions. Please visit T.O.M. to see if there has something changed.'
          );
          
          if (isset ($statusHintMap [$Status ['Status']]))
            array_unshift (
              $this->adminStatus,
              '<strong>' . __ ('Worthy-Premium is not available at the moment.', $this->textDomain) . '</strong> ' .
              __ ($statusHintMap [$Status ['Status']], $this->textDomain)
            );
        }
      }
      
      // Check further messages
      if (count ($this->adminStatus) == 0)
        return;
      
      echo
        '<div class="stuffbox">',
          '<h2>', __ ('Status', $this->textDomain), '</h2>',
          '<div class="inside">',
            '<ul>';
      
      foreach ($this->adminStatus as $Status)
        echo '<li class="wp-worthy-status">', $Status, '</li>';
      
      echo
            '</ul>',
          '</div>',
        '</div>';
    }
    // }}}
    
    // {{{ adminMenuPrepare
    /**
     * Prepare the output of the admin-menu
     * 
     * @access public
     * @return void
     **/
    public function adminMenuPrepare () {
      // Check wheter to redirect
      if (!empty ($_REQUEST ['_wp_http_referer'])) {
        wp_redirect (remove_query_arg (array ('_wp_http_referer', '_wpnonce'), wp_unslash ($_SERVER ['REQUEST_URI'])));
        
        exit ();
      }
      
      // Check wheter to display some status-messages
      if (!isset ($_REQUEST ['displayStatus']))
        return;
      
      switch ($_REQUEST ['displayStatus']) {
        case 'genericException':
          if (
            isset ($_REQUEST ['exceptionMessage']) &&
            isset ($_REQUEST ['exceptionCode'])
          )
            $this->adminStatus [] = $this->displayError (new \Exception ($_REQUEST ['exceptionMessage'], (int)$_REQUEST ['exceptionCode']), true);
          
          break;
        case 'soapException':
          if (
            isset ($_REQUEST ['faultCode']) &&
            isset ($_REQUEST ['faultString'])
          )
            $this->adminStatus [] = $this->displayError (new \SoapFault ($_REQUEST ['faultCode'], $_REQUEST ['faultString']), true);
          
          break;
      }
    }
    // }}}
    
    // {{{ adminUserProfileForm
    /**
     * Make sure form on user-profiles uses form-data as enctype
     * 
     * @access public
     * @return void
     **/
    public function adminUserProfileForm () {
      echo 'enctype="multipart/form-data"';
    }
    // }}}
    
    // {{{ adminUserProfile
    /**
     * Output some user-preferences on profile-page
     * 
     * @param WP_User $User (optional)
     * 
     * @access public
     * @return void
     **/
    public function adminUserProfile ($User = null) {
      // Make sure we have a user-profile
      if (!$User)
        $User = get_current_user ();
      
      // Make sure the current user is allowed to do this
      if (
        !current_user_can ('edit_user', $User->ID) ||
        !current_user_can ('publish_posts')
      )
        return;
      
      if (
        (count ($User->roles) == 1) &&
        in_array ('subscriber', $User->roles)
      )
        return;
      
      // Output the form
      echo
        '<h2>', __ ('Worthy', $this->textDomain), '</h2>',
        '<table class="form-table">',
          '<tbody>';
      
      // Output sharing-preference
      if (count ($sharingUsers = $this->getSharingUsers ()) > 1) {
        echo
          '<tr>',
            '<th><label for="wp-worthy-account-sharing">', __ ('Account-Sharing', $this->textDomain), '</label></th>',
            '<td>',
              '<select id="wp-worthy-account-sharing" name="wp-worthy-account-sharing">',
                '<option value="0">', __ ('Don\'t use other account', $this->textDomain), '</option>';
        
        foreach ($sharingUsers as $sharingUser) {
          if (
            ($sharingUser->shares_from > 0) ||
            ($sharingUser->ID == $User->ID) ||
            (($sharingUser->allows_sharing === '0') && ($sharingUser->ID != $this->getUserID ()))
          )
            continue;
          
          echo '<option value="', esc_attr ($sharingUser->ID), '"', ($sharingUsers [$User->ID]->shares_from == $sharingUser->ID ? ' selected="1"' : ''), '>',
                  esc_html ($sharingUser->display_name . ' (' . $sharingUser->user_login . ($sharingUser->vgwort_username != null ? ', VG WORT: ' . $sharingUser->vgwort_username : '') . ')'),
                '</option>';
        }
      
        echo
              '</select>',
              '<p class="description">',
                __ ('With account-sharing you can link your wordpress-account to another one.', $this->textDomain), ' ',
                __ ('In this case worthy will behave just like the other account performs your actions, e.g. markers will be imported for this account and will be assigned to posts from this account.', $this->textDomain), ' ',
                __ ('The same also applies to Worthy Premium of course.', $this->textDomain),
              '</p>',
            '</td>',
          '</tr>';
      }
      
      if ($this->hasPremium ())
        echo
            '<tr>',
              '<th><label for="wp-worthy-forename">', __ ('Forename', $this->textDomain), '</label></th>',
              '<td><input type="text" name="wp-worthy-forename" id="wp-worthy-forename" value="', esc_attr (get_user_meta ($User->ID, 'wp-worthy-forename', true)), '" placeholder="', esc_attr ($User->first_name), '" /></td>',
            '</tr><tr>',
              '<th><label for="wp-worthy-lastname">', __ ('Lastname', $this->textDomain), '</label></th>',
              '<td>',
                '<input type="text" name="wp-worthy-lastname" id="wp-worthy-lastname" value="', esc_attr (get_user_meta ($User->ID, 'wp-worthy-lastname', true)), '" placeholder="', esc_attr ($User->last_name), '" />',
                '<p class="description">',
                  __ ('If you use Worthy Premium in combination with a publisher-account, it is neccessary to specify at least the full name of each author.', $this->textDomain), ' ',
                  __ ('Once you submit a report this information is transmitted togehter with the original post and the optional Card-ID to VG WORT.', $this->textDomain),
                '</p>',
              '</td>',
            '</tr><tr>',
              '<th><label for="wp-worthy-cardid">', __ ('Card-ID', $this->textDomain), '</label></th>',
              '<td>',
                '<input type="text" name="wp-worthy-cardid" id="wp-worthy-cardid" value="', esc_attr (get_user_meta ($User->ID, 'wp-worthy-cardid', true)), '" />',
                '<p class="description">',
                  __ ('Assigning just the name of the author does not enable VG WORT to create a direct relation between the author and his/her post.', $this->textDomain), ' ',
                  __ ('It is always recommended to provide a Card-ID of the author as well to assure that the post is linked with the author withour any issues at VG WORT.', $this->textDomain),
                '</p>',
              '</td>',
            '</tr>';
      
      echo  '<tr>',
              '<th><label for="wp-worthy-marker-file">', __ ('Import VG WORT pixels', $this->textDomain), '</label></th>',
              '<td>',
                '<input type="file" id="wp-worthy-marker-file" name="wp-worthy-marker-file" />',
                '<p class="description">', __ ('If you have requested a CSV-list of pixels via VG WORT you may upload this file and import contained pixels here.', $this->textDomain), '</p>',
              '</td>',
            '</tr>',
          '</tbody>',
        '</table>';
    }
    // }}}
    
    // {{{ adminUserProfileUpdate
    /**
     * Store changes on the user-profile
     * 
     * @param int $UserID
     * 
     * @access public
     * @return void
     **/
    public function adminUserProfileUpdate ($userID) {
      // Make sure the current user is allowed to do this
      if (!current_user_can ('edit_user', $userID))
        return;
      
      // Update user-meta's
      if (isset ($_REQUEST ['wp-worthy-account-sharing'])) {
        if ((int)$_REQUEST ['wp-worthy-account-sharing'] == 0)
          delete_user_meta ($userID, 'wp-worthy-authorid');
        elseif ($GLOBALS ['wpdb']->get_row (
          'SELECT u.`ID`, m.`meta_value` ' .
          'FROM `' . $this->getTablename ('users') . '` u ' .
          'LEFT JOIN `' . $this->getTablename ('usermeta') . '` m ON (u.`ID`=m.`user_id` AND m.`meta_key`="wp-worthy-authorid") ' .
          'WHERE (m.`meta_value` IS NULL OR (m.`meta_value` < 1)) AND u.`ID`=' . (int)$_REQUEST ['wp-worthy-account-sharing']
        ))
          update_user_meta ($userID, 'wp-worthy-authorid', (int)$_REQUEST ['wp-worthy-account-sharing']);
      }
      
      if ($this->hasPremium ()) {
        update_user_meta ($userID, 'wp-worthy-forename', (isset ($_REQUEST ['wp-worthy-forename']) ? sanitize_text_field ($_REQUEST ['wp-worthy-forename']) : ''));
        update_user_meta ($userID, 'wp-worthy-lastname', (isset ($_REQUEST ['wp-worthy-lastname']) ? sanitize_text_field ($_REQUEST ['wp-worthy-lastname']) : ''));
        update_user_meta ($userID, 'wp-worthy-cardid', (isset ($_REQUEST ['wp-worthy-cardid']) ? sanitize_text_field ($_REQUEST ['wp-worthy-cardid']) : ''));
      }
      
      // Check if a csv-list is being imported
      if (
        isset ($_FILES ['wp-worthy-marker-file']) &&
        ($_FILES ['wp-worthy-marker-file']['error'] == UPLOAD_ERR_OK)
      ) {
        try {
          $this->importPixelsFromFile ($_FILES ['wp-worthy-marker-file']['tmp_name'], $userID);
        } catch (\Throwable $error) {
          // No-Op
        }
        
        @unlink ($_FILES ['wp-worthy-marker-file']['tmp_name']);
        unset ($_FILES ['wp-worthy-marker-file']);
      }
    }
    // }}}
    
    // {{{ redirectNoAction
    /**
     * Just redirect to normal page, if post-action was executed without an action selected
     * 
     * @access public
     * @return void
     **/
    public function redirectNoAction () {
      // Check if this is a worthy-call
      if (
        !isset ($_REQUEST ['page']) ||
        (substr ($_REQUEST ['page'], 0, strlen (__CLASS__)) != __CLASS__)
      )
        return;
      
      if (
        isset ($_REQUEST ['action']) &&
        isset ($_REQUEST ['action2']) &&
        ((int)$_REQUEST ['action'] == -1) &&
        ((int)$_REQUEST ['action2'] != -1)
      )
        return do_action ('admin_post_' . $_REQUEST ['action2']);
      
      // Remove some parameters
      unset ($_REQUEST ['action']);
      unset ($_REQUEST ['action2']);
      
      // Redirect
      wp_redirect (
        admin_url (
          (is_network_admin () || isset ($_GET ['wp-worthy-network-admin']) ? 'network/' : '') . 'admin.php?' . http_build_query ($_REQUEST)
        )
      );
      
      exit ();
    }
    // }}}
    
    // {{{ saveSettingsPersonal
    /**
     * Store personal user-preferences
     * 
     * @access public
     * @return void
     **/
    public function saveSettingsPersonal () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_SETTINGS ]);
      
      // Retrive the ID of the current user
      $eUID = get_current_user_id ();
      
      // Store user-settings
      update_user_meta ($eUID, 'wp-worthy-auto-assign-markers', (isset ($_REQUEST ['wp-worthy-auto-assign-markers']) && ((int)$_REQUEST ['wp-worthy-auto-assign-markers'] == 1)) ? 1 : 0);
      update_user_meta ($eUID, 'wp-worthy-disable-output', (isset ($_REQUEST ['wp-worthy-disable-output']) && ((int)$_REQUEST ['wp-worthy-disable-output'] == 1)) ? 1 : 0);
      update_user_meta ($eUID, 'wp-worthy-overlong-titles', (isset ($_REQUEST ['wp-worthy-overlong-titles']) ? (int)$_REQUEST ['wp-worthy-overlong-titles'] : -1));
      
      if ($this->hasPremium ()) {
        if (isset ($_REQUEST ['wp-worthy-autocreate-webranges']))
          update_user_meta ($eUID, 'wp-worthy-autocreate-webranges', 1);
        else
          delete_user_meta ($eUID, 'wp-worthy-autocreate-webranges');
      }
      
      // Safely process default server
      $defaultServer = (isset ($_REQUEST ['wp-worthy-default-server']) ? sanitize_text_field ($_REQUEST ['wp-worthy-default-server']) : '');
      
      if (strlen ($defaultServer) > 0) {
        // Strip anything like scheme from server
        if (($p = strpos ($defaultServer, '://')) !== false)
          $defaultServer = substr ($defaultServer, $p + 3);
        elseif (substr ($defaultServer, 0, 2) == '//')
          $defaultServer = substr ($defaultServer, 2);
        
        // Strip anything like a path from server
        if (($p = strpos ($defaultServer, '/')) !== false)
          $defaultServer = substr ($defaultServer, 0, $p);
      }
      
      if ($defaultServer !== null)
        update_user_meta ($eUID, 'wp-worthy-default-server', $defaultServer);
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_SETTINGS, array ('displayStatus' => 'settingsSaved')));
    }
    // }}}
    
    // {{{ saveSettingsSharing
    /**
     * Store sharing-preferences of current user
     * 
     * @access public
     * @return void
     **/
    public function saveSettingsSharing () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_SETTINGS ]);
      
      // Retrive the ID of the current user
      $eUID = get_current_user_id ();
    
      // Store user-settings
      update_user_meta ($eUID, 'wp-worthy-allow-account-sharing', (isset ($_REQUEST ['wp-worthy-allow-account-sharing']) && ((int)$_REQUEST ['wp-worthy-allow-account-sharing'] == 1)) ? 1 : 0);
      
      // Check wheter to set user-sharing
      if (isset ($_REQUEST ['wp-worthy-account-sharing'])) {
        // Update the current user
        if ((int)$_REQUEST ['wp-worthy-account-sharing'] == 0)
          delete_user_meta ($eUID, 'wp-worthy-authorid');
        elseif ($GLOBALS ['wpdb']->get_row (
          'SELECT u.`ID`, m.`meta_value` ' .
          'FROM `' . $this->getTablename ('users') . '` u ' .
          'LEFT JOIN `' . $this->getTablename ('usermeta') . '` m ON (u.`ID`=m.`user_id` AND m.`meta_key`="wp-worthy-authorid") ' .
          'WHERE (m.`meta_value` IS NULL OR (m.`meta_value` < 1)) AND u.`ID`=' . (int)$_REQUEST ['wp-worthy-account-sharing']
        ))
          update_user_meta ($eUID, 'wp-worthy-authorid', intval ($_REQUEST ['wp-worthy-account-sharing']));
      }
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_SETTINGS, array ('displayStatus' => 'settingsSaved')));
    }
    // }}}
    
    // {{{ saveSettingsPublisher
    /**
     * Store personal publisher-settings of the current user
     * 
     * @access public
     * @return void
     **/
    public function saveSettingsPublisher () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_SETTINGS ]);
      
      // Retrive the ID of the current user
      $eUID = get_current_user_id ();
    
      // Store user-settings
      if ($this->hasPremium ()) {
        update_user_meta ($eUID, 'wp-worthy-forename', (isset ($_REQUEST ['wp-worthy-forename']) ? sanitize_text_field ($_REQUEST ['wp-worthy-forename']) : ''));
        update_user_meta ($eUID, 'wp-worthy-lastname', (isset ($_REQUEST ['wp-worthy-lastname']) ? sanitize_text_field ($_REQUEST ['wp-worthy-lastname']) : ''));
        update_user_meta ($eUID, 'wp-worthy-cardid', (isset ($_REQUEST ['wp-worthy-cardid']) ? sanitize_text_field ($_REQUEST ['wp-worthy-cardid']) : ''));
      }
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_SETTINGS, array ('displayStatus' => 'settingsSaved')));
    }
    // }}}
    
    // {{{ saveUserPostSettings
    /**
     * Update post-type-settings for the current user
     * 
     * @access public
     * @return void
     **/
    public function saveUserPostSettings () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_SETTINGS ]);
      
      // Retrive the ID of the current user
      $eUID = get_current_user_id ();
      
      // Filter the request
      if (
        isset ($_POST ['wp-worthy-post-types']) &&
        is_array ($_POST ['wp-worthy-post-types'])
      ) {
        $validPostTypes = array_merge (
          array (
            get_post_type_object ('post'),
            get_post_type_object ('page'),
          ),
          get_post_types (
            array (
              'public' => true,
              'show_ui' => true,
              '_builtin' => false,
            ),
            'objects'
          )
        );
        
        $postTypes = array ();
        
        foreach ($validPostTypes as $postType)
          if (in_array ($postType->name, $_POST ['wp-worthy-post-types']))
            $postTypes [] = $postType->name;
        
        update_user_meta ($eUID, 'wp-worthy-post-types', $postTypes);
      }
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_SETTINGS, array ('displayStatus' => 'settingsSaved')));
    }
    // }}}
    
    // {{{ saveAdminCommonSettings
    /**
     * Store common settings made on admin-menu
     * 
     * @access public
     * @return void
     **/
    public function saveAdminCommonSettings () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_ADMIN ]);
      
      // Update account-sharing-settings
      update_option ('wp-worthy-enable-account-sharing', (isset ($_REQUEST ['wp-worthy-enable-account-sharing']) ? intval ($_REQUEST ['wp-worthy-enable-account-sharing']) % 2 : 0));
      update_option ('wp-worthy-default-account', intval ($_REQUEST ['wp-worthy-default-account']));
      update_option ('wp-worthy-enable-burn', (isset ($_REQUEST ['wp-worthy-enable-burn']) ? (int)$_REQUEST ['wp-worthy-enable-burn'] % 2 : 0));
      update_option ('wp-worthy-enable-webarea', (isset ($_REQUEST ['wp-worthy-enable-webarea']) ? intval ($_REQUEST ['wp-worthy-enable-webarea']) % 2 : 0));
      update_option ('wp-worthy-overlong-titles', intval ($_REQUEST ['wp-worthy-overlong-titles']));
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_ADMIN, array ('displayStatus' => 'settingsSaved')));
    }
    // }}}
    
    // {{{ saveAdminCommonSettings
    /**
     * Store common settings made on admin-menu
     * 
     * @access public
     * @return void
     **/
    public function saveAdminOutputSettings () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_ADMIN ]);
      
      // Sanatize CSS-classes for pixel-tag
      if (isset ($_REQUEST ['wp-worthy-pixel-classes']))
        $pixelClasses = array_map ('sanitize_html_class', explode (' ', $_REQUEST ['wp-worthy-pixel-classes']));
      else
        $pixelClasses = [];
      
      // Update account-sharing-settings
      update_option ('wp-worthy-embed-on-feed', (isset ($_REQUEST ['wp-worthy-embed-on-feed']) ? intval ($_REQUEST ['wp-worthy-embed-on-feed']) %2 : 0));
      update_option ('wp-worthy-embed-on-rest', (isset ($_REQUEST ['wp-worthy-embed-on-rest']) ? intval ($_REQUEST ['wp-worthy-embed-on-rest']) %2 : 0));
      update_option ('wp-worthy-embed-on-export', (isset ($_REQUEST ['wp-worthy-embed-on-export']) ? intval ($_REQUEST ['wp-worthy-embed-on-export']) % 2 : 0));
      update_option ('wp-worthy-marker-position', intval ($_REQUEST ['wp-worthy-marker-position']), true);
      update_option ('wp-worthy-lazy-loading', (isset ($_REQUEST ['wp-worthy-lazy-loading']) ? (int)$_REQUEST ['wp-worthy-lazy-loading'] % 3 : self::LAZY_LOADING_DEFAULT));
      update_option ('wp-worthy-pixel-classes', implode (' ', $pixelClasses));
      update_option ('wp-worthy-locale-filter', sanitize_text_field ($_REQUEST ['wp-worthy-locale-filter']));
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_ADMIN, array ('displayStatus' => 'settingsSaved')));
    }
    // }}}
    
    // {{{ saveAdminContentSettings
    /**
     * Store content-settings made on admin-menu
     * 
     * @access public
     * @return void
     **/
    public function saveAdminContentSettings () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_ADMIN ]);
      
      // Make sure there is is a shortcode-array on the request
      if (
        !isset ($_REQUEST ['wp-worthy-shortcode-filter']) ||
        !is_array ($_REQUEST ['wp-worthy-shortcode-filter'])
      )
        $_REQUEST ['wp-worthy-shortcode-filter'] = array ();
      
      // Filter the shortcode-array and store preference
      $Filter = array ();
      
      foreach ($GLOBALS ['shortcode_tags'] as $Key=>$Callback)
        if (!in_array ($Key, $_REQUEST ['wp-worthy-shortcode-filter']))
          $Filter [] = $Key;
      
      update_option ('wp-worthy-filter-shortcodes', implode (',', $Filter));
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_ADMIN, array ('displayStatus' => 'settingsSaved')));
    }
    // }}}
    
    // {{{ setSharingAdmin
    /**
     * Apply sharing-settings from admin-menu
     * 
     * @access public
     * @return void
     **/
    public function setSharingAdmin () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_ADMIN ]);
      
      // Make sure all parameters are present
      if (
        !isset ($_REQUEST ['wp-worthy-admin-share-source']) ||
        !isset ($_REQUEST ['wp-worthy-admin-share-destination']) ||
        !isset ($_REQUEST ['wp-worthy-admin-share-mode']) ||
        !in_array ($_REQUEST ['wp-worthy-admin-share-mode'], array ('share', 'migrate', 'both'))
      )
        return wp_redirect ($this->linkSection ($this::ADMIN_SECTION_ADMIN, array ('displayStatus' => 'invalidParameter')));
      
      // Get the users to work with
      $sharingUsers = $this->getSharingUsers ();
      $fromUsers = (int)$_REQUEST ['wp-worthy-admin-share-source'];
      $toUser = (int)$_REQUEST ['wp-worthy-admin-share-destination'];
      
      if ($fromUsers == -1)
        $fromUsers = array_keys ($sharingUsers);
      else
        $fromUsers = array ($fromUsers);
      
      if (
        !isset ($sharingUsers [$toUser]) ||
        !is_object ($toUser = $sharingUsers [$toUser])
      )
        return wp_redirect ($this->linkSection ($this::ADMIN_SECTION_ADMIN, array ('displayStatus' => 'invalidParameter')));
      
      $Count = 0;
      
      foreach ($fromUsers as $fromUser) {
        // Sanity-check users
        if ($fromUser == $toUser->ID) {
          if (count ($fromUsers) == 1)
            return wp_redirect ($this->linkSection ($this::ADMIN_SECTION_ADMIN, array ('displayStatus' => 'duplicateUser')));
          
          continue;
        }
        
        // Try to get instance of that user
        if (
          !isset ($sharingUsers [$fromUser]) ||
          !is_object ($fromUser = $sharingUsers [$fromUser])
        ) {
          if (count ($fromUsers) == 1)
            return wp_redirect ($this->linkSection ($this::ADMIN_SECTION_ADMIN, array ('displayStatus' => 'invalidParameter')));
          
          continue;
        }
        
        // Check wheter to set sharing
        if (
          ($_REQUEST ['wp-worthy-admin-share-mode'] == 'share') ||
          ($_REQUEST ['wp-worthy-admin-share-mode'] == 'both')
        ) {
          // Check for cyclic sharing
          if ($this->getUserID ($toUser->ID, $fromUser->ID) == $fromUser->ID) {
            if (count ($fromUsers) == 1)
              return wp_redirect ($this->linkSection ($this::ADMIN_SECTION_ADMIN, array ('displayStatus' => 'loopDetected')));
            
            continue;
          }
          
          // Set the sharing
          update_user_meta ($fromUser->ID, 'wp-worthy-authorid', $toUser->ID);
        }
        
        // Check wheter to migrate markers
        if (
          ($_REQUEST ['wp-worthy-admin-share-mode'] == 'migrate') ||
          ($_REQUEST ['wp-worthy-admin-share-mode'] == 'both')
        )
          $Count += $GLOBALS ['wpdb']->update (
            $this->getTablename ('worthy_markers', 0),
            [ 'userid' => $toUser->ID ],
            [ 'userid' => $fromUser->ID ],
            [ '%d' ],
            [ '%d' ]
          );
      }
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_ADMIN, array ('displayStatus' => 'shareAndMigrateDone', 'mode' => $_REQUEST ['wp-worthy-admin-share-mode'], 'count' => $Count)));
    }
    // }}}
    
    // {{{ setOrphanedAdopter
    /**
     * Store a new user-id for orphaned markers
     * 
     * @access public
     * @return void
     **/
    public function setOrphanedAdopter () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_ADMIN ]);
      
      try {
        $adoptedCount = wp_worthy_maintenance::adoptOrphanedPixels ((int)$_REQUEST ['wp-worthy-orphan-adopter']);
        $adopterID = (int)$_REQUEST ['wp-worthy-orphan-adopter'];
      } catch (Throwable $error) {
        # TODO: Handle errors here more properly
        $adoptedCount = 0;
        $adopterID = 0;
      }
      
      // Redirect back
      wp_redirect (
        $this->linkSection (
          $this::ADMIN_SECTION_ADMIN,
          [
            'displayStatus' => 'setOrphanedAdopterDone',
            'adopter' => $adopterID,
            'count' => $adoptedCount,
          ]
        )
      );
    }
    // }}}
    
    // {{{ importMarkers
    /**
     * Import a list of markers from an uploaded CSV-File
     * 
     * @access public
     * @return void
     **/
    public function importMarkers () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_CONVERT ]);
      
      // Check all uploaded files
      $userID = $this->getUserID ();
      $resultParameters = [
        'displayStatus' => 'importDone',
        'fileCount' => 0,
      ];
      
      foreach ($_FILES as $fieldName=>$fileInfo) {
        if ($fileInfo ['error'] != UPLOAD_ERR_OK)
          continue;
        
        try {
          $importResult = $this->importPixelsFromFile ($fileInfo ['tmp_name']);
          
          foreach ($importResult as $resultKey=>$resultValue)
            if (isset ($resultParameters [$resultKey]))
              $resultParameters [$resultKey] += $resultValue;
            else
              $resultParameters [$resultKey] = $resultValue;
          
          $resultParameters ['fileCount']++;
        } catch (\Throwable $error) {
          // No-Op
        }
        
        @unlink ($fileInfo ['tmp_name']);
        unset ($_FILES [$fieldName]);
      }
      
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_CONVERT, $resultParameters));
      
      exit ();
    }
    // }}}
    
    // {{{ importPixelsFromFile
    /**
     * Import a list of markers from a CSV-File
     * 
     * @param string $fileName
     * @param int $userID (optional)
     * 
     * @access public
     * @return array
     **/
    private function importPixelsFromFile ($fileName, $userID = null) : array {
      // Make sure we have a vaild user-ID
      $userID = $this->getUserID ($userID);
      
      // Try to read records from this file
      if (!is_resource ($f = @fopen ($fileName, 'r')))
        throw new \Exception ('Failed to open input-file');
      
      $parsedPixels = $this->parsePixelsFromFile ($f);
      
      fclose ($f);
      
      // Skip if there are no markers on this file
      $importResult = [
        'filePixelCount' => count ($parsedPixels),
        'pixelsExisting' => 0,
        'pixelsUpdated' => 0,
        'pixelsCreated' => 0,
      ];
      
      if ($importResult ['filePixelCount'] < 1)
        return $importResult;
      
      // Check existing markers
      $existingPixelsQuery = 'SELECT `public`, `private` FROM `' . $this->getTablename ('worthy_markers', 0) . '` WHERE `public` IN (';
      
      foreach ($parsedPixels as $parsedPixel)
        $existingPixelsQuery .= $GLOBALS ['wpdb']->prepare ('%s,', $parsedPixels ['pixelPublic']);
      
      $existingPixels = $GLOBALS ['wpdb']->get_results (substr ($existingPixelsQuery, 0, -1) . ')', ARRAY_N);
      
      foreach ($existingPixels as $existingPixel) {
        $pixelFound = false;
        
        foreach ($parsedPixels as $parsedPixels)
          if ($pixelFound = ($existingPixel [1] == $parsedPixels ['pixelPrivate']))
            break;
        
        if ($pixelFound)
          $importResult ['pixelsUpdated']++;
      }
      
      $importResult ['pixelsExisting'] = count ($existingPixels);
      
      // Import the markers into database
      $createPixelsQuery = 'INSERT IGNORE INTO `' . $this->getTablename ('worthy_markers', 0) . '` (`userid`, `public`, `private`, `server`, `url`) VALUES ';
      
      foreach ($parsedPixels as $parsedPixels)
        $createPixelsQuery .= $GLOBALS ['wpdb']->prepare (
          '(%d, %s, %s, %s, %s), ',
          $userID,
          $parsedPixels ['pixelPublic'],
          $parsedPixels ['pixelPrivate'],
          parse_url ($parsedPixels ['url'], PHP_URL_HOST),
          $parsedPixels ['url']
        );
      
      $GLOBALS ['wpdb']->query (substr ($createPixelsQuery, 0, -2) . ' ON DUPLICATE KEY UPDATE `Private`=VALUES(`Private`)');
      $importResult ['pixelsCreated'] = $GLOBALS ['wpdb']->rows_affected;
      
      // Update statistics
      update_option ('worthy_markers_imported_csv', get_option ('worthy_markers_imported_csv') + $importResult ['pixelsCreated']);
      update_user_meta ($userID, 'worthy_markers_imported_csv', (int)get_user_meta ($userID, 'worthy_markers_imported_csv', true) + $importResult ['pixelsCreated']);
      
      return $importResult;
    }
    // }}}
    
    // {{{ importClaimMarkers
    /**
     * Claim and import a set of anonymous markers
     * 
     * @access public
     * @return void
     **/
    public function importClaimMarkers () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_CONVERT ]);
      
      // Check if we are subscribed to premium
      if (
        !$this->hasPremium () ||
        !$this::ENABLE_ANONYMOUS_MARKERS
      )
        exit (wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM)));
      
      // Try to access the SOAP-Client
      try {
        $soapClient = $this->getSOAPClient ();
        $soapSession = $this->premiumGetSession ();
      } catch (\SoapFault $soapError) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'soapException',
              'faultCode' => $soapError->faultcode,
              'faultString' => $soapError->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      // Process all required parameters
      $Import = (isset ($_REQUEST ['wp-worthy-claim-import']) && ((int)$_REQUEST ['wp-worthy-claim-import'] == 1));
      
      // Collect all markers from upload
      $files = 0;
      $created = 0;
      $allMarkers = array ();
      
      foreach ($_FILES as $Key=>$Info) {
        // Try to read records from this file
        $markers = null;
        
        if (is_resource ($f = @fopen ($Info ['tmp_name'], 'r'))) {
          if ($markers = $this->parsePixelsFromFile ($f))
            $files++;
          
          fclose ($f);
        }
        
        if (!$markers)
          continue;
        
        // Just forward to complete set
        foreach ($markers as $marker)
          $allMarkers [$marker ['pixelPrivate']] = $marker;
        
        // Remove all informations about this upload
        @unlink ($Info ['tmp_name']);
        unset ($_FILES [$Key]);
      }
      
      // Try to claim all markers
      try {
        $Result = $soapClient->markersClaim ($soapSession, array_keys ($allMarkers));
      } catch (\SoapFault $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'soapException',
              'faultCode' => $error->faultcode,
              'faultString' => $error->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      // Process the claim-result
      $claimedMarkers = array ();
      $failedMarkers = array ();
      
      foreach ($Result as $Item)
        if ($Item->Status == 'ok')
          $claimedMarkers [$Item->Private] = $allMarkers [$Item->Private];
        else
          $failedMarkers [$Item->Private] = $allMarkers [$Item->Private];
      
      // Update statistics
      if (count ($claimedMarkers) > 0) {
        update_option ('worthy_premium_markers_claimed', get_option ('worthy_premium_markers_claimed') + count ($claimedMarkers));
        update_user_meta ($userID, 'worthy_premium_markers_claimed', get_user_meta ($userID, 'worthy_premium_markers_claimed', true) + count ($claimedMarkers));
      }
      
      // Import claimed markers
      if (
        $Import &&
        (count ($claimedMarkers) > 0)
      ) {
        $create_query = 'INSERT IGNORE INTO `' . $this->getTablename ('worthy_markers', 0) . '` (userid, public, private, server, url) VALUES ';
        $userID = $this->getUserID ();
        
        foreach ($claimedMarkers as $marker)
          $create_query .= $GLOBALS ['wpdb']->prepare ('(%d, %s, %s, %s, %s), ', $userID, $marker ['pixelPublic'], $marker ['pixelPrivate'], parse_url ($marker ['url'], PHP_URL_HOST), $marker ['url']);
        
        $GLOBALS ['wpdb']->query (substr ($create_query, 0, -2) . ' ON DUPLICATE KEY UPDATE Private=VALUES(Private)');
        $created += $GLOBALS ['wpdb']->rows_affected;
        
        // Update statistics
        if ($created > 0) {
          update_option ('worthy_markers_imported_csv', get_option ('worthy_markers_imported_csv') + $created);
          update_user_meta ($userID, 'worthy_markers_imported_csv', get_user_meta ($userID, 'worthy_markers_imported_csv', true) + $created);
        }
      }
      
      // Check if there was anything imported
      $Parameters = array (
        'displayStatus' => 'importClaimDone',
      );
      
      if ($files > 0) {
        $Parameters ['fileCount'] = $files;
        $Parameters ['filePixelCount'] = count ($allMarkers);
        $Parameters ['markerClaimed'] = implode (',', array_keys ($claimedMarkers));
        $Parameters ['markerFailed'] = implode (',', array_keys ($failedMarkers));
        $Parameters ['pixelsCreated'] = $created;
      }
      
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_CONVERT, $Parameters));
      
      exit ();
    }
    // }}}
    
    // {{{ reportMarkers
    /**
     + Generate a list of markers
     * 
     * @access public
     * @return void
     **/
    public function reportMarkers () {
      // Determine with types to export
      $pixelUnassigned = (isset ($_REQUEST ['wp-worthy-report-unused']) && ((int)$_REQUEST ['wp-worthy-report-unused'] == 1));
      $pixelAssigned = (isset ($_REQUEST ['wp-worthy-report-used']) && ((int)$_REQUEST ['wp-worthy-report-used'] == 1));
      $includeTitle = (isset ($_REQUEST ['wp-worthy-report-title']) && ((int)$_REQUEST ['wp-worthy-report-title'] == 1));
      
      // Process user-filter
      if (
        isset ($_REQUEST ['wp-worthy-report-filter-users']) &&
        ((int)$_REQUEST ['wp-worthy-report-filter-users'] == 1)
      ) {
        if (is_array ($_REQUEST ['wp-worthy-report-user']))
          $userIDs = array_map ('intval', $_REQUEST ['wp-worthy-report-user']);
        else
          $userIDs = array ();
        
        # $Where .= ' AND `userid` IN ("' . implode ('", "', $userIDs) . '")';
      } else
        $userIDs = null;
      
      // Process site-filter
      if (
        isset ($_REQUEST ['wp-worthy-report-sites']) &&
        is_array ($_REQUEST ['wp-worthy-report-sites'])
      )
        $siteIds = array_unique (array_map ('intval', $_REQUEST ['wp-worthy-report-sites']));
      elseif (!is_multisite ())
        $siteIds = [ 0, 1 ];
      else
        $siteIds = array (get_current_blog_id ());
      
      // Process premium-filter
      // We need premium here because of the marker-synchronization - without this does not make sense
      if ($hasPremium = $this->hasPremium ()) {
        $pixelStatus = array ();
        
        if (isset ($_REQUEST ['wp-worthy-report-premium-notqualified']))
          $pixelStatus [] = 1;
        
        if (isset ($_REQUEST ['wp-worthy-report-premium-partialqualified']))
          $pixelStatus [] = 2;
        
        if (isset ($_REQUEST ['wp-worthy-report-premium-qualified']))
          $pixelStatus [] = 3;
        
        if (isset ($_REQUEST ['wp-worthy-report-premium-reported']))
          $pixelStatus [] = 4;
        
        if (isset ($_REQUEST ['wp-worthy-report-premium-uncounted']))
          $pixelStatus [] = 0;
      } else
        $pixelStatus = null;
      
      // Load all pixels for export
      if (
        $includeTitle ||
        ($userIDs !== null)
      ) {
        $tableCondition = '`' . $this->getTablename ('worthy_markers', 0)  . '` wm ';
        $postTitleField = 'CASE wm.`siteid` ';
        $postAuthorField = 'CASE wm.`siteid` ';
        
        foreach ($siteIds as $siteIndex=>$siteId) {
          if (!is_user_member_of_blog (null, $siteId)) {
            unset ($siteIds [$siteIndex]);
            
            continue;
          }
          
          $tableCondition .= 'LEFT JOIN `' . $this->getTablename ('posts', $siteId)  . '` p' . $siteId . ' ON (wm.`siteid`="' . $siteId . '" AND wm.`postid`=p' . $siteId . '.`ID`) ';
          $postTitleField .= 'WHEN ' . $siteId . ' THEN p' . $siteId . '.`post_title` ';
          $postAuthorField .= 'WHEN ' . $siteId . ' THEN p' . $siteId . '.`post_author` ';
        }
        
        if (count ($siteIds) > 0)
          $reportPixels = $GLOBALS ['wpdb']->get_results (
            'SELECT ' .
              'wm.`public`, ' .
              'wm.`private`' .
              ($hasPremium ? ', wm.`status`' : '') .
              ($pixelAssigned ? ', wm.`siteid`, wm.`postid`' . ($includeTitle ? ', ' . $postTitleField . 'ELSE NULL END AS `post_title`, ' . $postAuthorField .  'ELSE NULL END AS `post_author`' : '') : '') . ' ' .
            'FROM ' . $tableCondition .
            'WHERE ' .
              '(' .
                ($pixelUnassigned ? '`postid` IS NULL' : '0') . ' OR ' .
                ($pixelAssigned ? '(NOT (`postid` IS NULL)' . ($siteIds !== null ? ' AND siteid IN (' . implode (', ', $siteIds) . ')' : '') . ')' : '0') .
              ')' .
              ($pixelStatus !== null ? ' AND (`status` IN (' . implode (', ', $pixelStatus) . ')' . (in_array (0, $pixelStatus) ? ' OR `status` IS NULL' : '') . ')' : '') .
              ($userIDs !== null ? ' HAVING `post_author` IN (' . implode (', ', $userIDs) . ')' : '')
          );
        else
          $reportPixels = array ();
      } elseif (
        ($siteIds === null) ||
        (count ($siteIds) > 0)
      )
        $reportPixels = $GLOBALS ['wpdb']->get_results (
          'SELECT `public`, `private`' . ($hasPremium ? ', `status`' : '') . ($pixelAssigned ? ', `siteid`, `postid`' : '') . ' ' .
          'FROM `' . $this->getTablename ('worthy_markers', 0) . '` ' .
          'WHERE ' .
            '(' .
              ($pixelUnassigned ? '`postid` IS NULL' : '0') . ' OR ' .
              ($pixelAssigned ? 'NOT (`postid` IS NULL)' . ($siteIds !== null ? ' AND siteid IN (' . implode (', ', $siteIds) . ')' : '') : '0') .
            ')' .
            ($pixelStatus !== null ? ' AND (`status` IN (' . implode (', ', $pixelStatus) . ')' . (in_array (0, $pixelStatus) ? ' OR `status` IS NULL' : '') . ')' : '')
        );
      else
        $reportPixels = array ();
      
      // Generate the output
      header ('Content-Type: text/csv; charset=utf-8');
      header ('Content-Disposition: attachment; filename="wp-worthy-report-' . date ('Ymd-His') . '.csv"');
      
      static $Map = array (
        -1 => 'not synced',
         0 => 'not counted',
         1 => 'not qualified',
         2 => 'partial qualified',
         3 => 'qualified',
         4 => 'reported',
      );
      
      echo
        __ ('Public Marker', $this->textDomain), ';',
        __ ('Private Marker', $this->textDomain),
        ($hasPremium ? ';' . __ ('Status', $this->textDomain) : ''),
        ($pixelAssigned && is_multisite () ? ';' . __ ('Website', $this->textDomain) : ''),
        ($pixelAssigned ? ';' . __ ('Post ID', $this->textDomain) : ''),
        ($pixelAssigned && $includeTitle ? ';' . __ ('Post title', $this->textDomain) : ''), "\r\n";
      
      foreach ($reportPixels as $reportPixel) {
        if ($hasPremium)
          $reportPixel->status = __ ($Map [$reportPixel->status === null ? -1 : $reportPixel->status], $this->textDomain);
        
        if ($pixelAssigned) {
          $originalSiteId = $reportPixel->siteid;
          
          if (!is_multisite ())
            unset ($reportPixel->siteid);
          elseif (!$reportPixel->postid) {
            $reportPixel->siteid = '';
            $reportPixel->postid = '';
          } else
            foreach (get_sites (array ('site__in' => array ($reportPixel->siteid))) as $site)
              if (is_user_member_of_blog (null, $site->id))
                $reportPixel->siteid = $site->blogname;
          
          if ($includeTitle) {
            $reportPixel->ID = $reportPixel->postid;
            $reportPixel->post_title = wp_worthy_post::fromObject ($reportPixel, $originalSiteId)->getTitle (true);
            
            unset ($reportPixel->ID, $reportPixel->post_author);
          }
        }
        
        echo implode (';', get_object_vars ($reportPixel)), "\r\n";
      }
      
      exit ();
    }
    // }}}
    
    // {{{ exportUnusedMarkers
    /**
     * Export and remove unused markers from our database
     * 
     * @access public
     * @return void
     **/
    public function exportUnusedMarkers () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_CONVERT ]);
      
      // Retrive all parameters
      $Count = (isset ($_REQUEST ['wp-worthy-export-count']) ? intval ($_REQUEST ['wp-worthy-export-count']) : 0);
      $Format = (isset ($_REQUEST ['wp-worthy-export-format']) ? $_REQUEST ['wp-worthy-export-format'] : 'author'); // This value is sanatized later
      $UserID = $this->getUserID (true);
      
      // Check if any marker should be returned
      if ($Count == 0) {
        header ('HTTP/1.1 204 Nothing to export');
        header ('Status: 204 Nothing to export');
        exit ();
      }
      
      // Make sure the format is valid
      if (
        ($Format != 'author') &&
        ($Format != 'publisher')
      ) {
        header ('HTTP/1.1 406 Invalid format selected');
        header ('Status: 406 Invalid format selected');
        exit ();
      }
      
      // Start output
      header ('Content-Type: text/csv; charset=utf-8');
      header ('Content-Disposition: attachment; filename="wp-worthy-export-' . date ('Ymd-His') . '.csv"');
      
      if ($Format == 'publisher')
        echo '"Öffentlicher Identifikationscode";Privater Identifikationscode', "\r\n";
      else
        echo ';VG WORT', "\r\n", ';Zählmarken', "\r\n", ';', "\r\n", ';Die unten angegebenen Zählmarken wurden am ', date ('d.m.Y'), ' um ', date ('H:i'), ' aus WP-Worthy exportiert.', "\r\n", ';', "\r\n";
      
      // Retrive all markers
      $markers = $GLOBALS ['wpdb']->get_results (
        'SELECT id, public, private, url ' .
        'FROM `' . $this->getTablename ('worthy_markers', 0) . '` ' .
        'WHERE ' .
          '`postid` IS NULL AND ' .
          '`userid`=' . intval ($UserID) . ' ' .
        'LIMIT ' . $Count
      );
      
      // Output markers first
      $ids = array ();
      $c = 0;
      
      foreach ($markers as $marker) {
        $ids [] = intval ($marker->id);
        
        if ($Format == 'publisher')
          echo $marker->public, ';', $marker->private, "\r\n";
        else
          echo
            ';Zählmarke für HTML Texte;Zählmarke für PDF Dokumente', "\r\n",
            ++$c, ';<img src="', $marker->url, '" width="1" height="1" alt="">;<a href="', $marker->url, '?l=PDF-ADRESSE">LINK-NAME</a>', "\r\n",
            ';Privater Identifikationscode:;', $marker->private, "\r\n\r\n";
      }
      
      // Remove markers from database
      $GLOBALS ['wpdb']->query ('DELETE FROM `' . $this->getTablename ('worthy_markers', 0) . '` WHERE id IN ("' . implode ('","', $ids) . '")');
      
      exit ();
    }
    // }}}
    
    // {{{ migratePostsPreview
    /**
     * Generate a preview of all posts to be migrated
     * 
     * @access public
     * @return void
     **/
    public function migratePostsPreview () {
      // Determine what to migrate
      $inline = (isset ($_REQUEST ['migrate_inline']) && ((int)$_REQUEST ['migrate_inline'] == 1));
      $vgw = (isset ($_REQUEST ['migrate_vgw']) && ((int)$_REQUEST ['migrate_vgw'] == 1));
      $vgwort = (isset ($_REQUEST ['migrate_vgwort']) && ((int)$_REQUEST ['migrate_vgwort'] == 1));
      $wppvgw = (isset ($_REQUEST ['migrate_wppvgw']) && ((int)$_REQUEST ['migrate_wppvgw'] == 1));
      $tlvgw = (isset ($_REQUEST ['migrate_tlvgw']) && ((int)$_REQUEST ['migrate_tlvgw'] == 1));
      $repair_dups = (isset ($_REQUEST ['migrate-repair-duplicates']) && ((int)$_REQUEST ['migrate-repair-duplicates'] == 1));
      
      if (
        isset ($_REQUEST ['wp-worthy-migrate-sites']) &&
        is_array ($_REQUEST ['wp-worthy-migrate-sites'])
      )
        $siteIds = array_unique (array_map ('intval', $_REQUEST ['wp-worthy-migrate-sites']));
      else
        $siteIds = [ get_current_blog_id () ];
      
      // Collect post-ids
      if ($inline)
        $postIDs = wp_worthy_migration::migrateInline (false, true, null, $siteIds);
      else
        $postIDs = array ();
      
      $keys = array ();
      
      if ($vgw)
        $keys ['vgwpixel'] = 'vgwpixel';
      
      if ($vgwort && ($key =  get_option ('wp_vgwortmetaname', 'wp_vgwortmarke')))  
        $keys [$key] = $key;
       
      if (count ($keys) > 0)
        $postIDs = array_merge ($postIDs, wp_worthy_migration::migrateByMeta ($keys, false, true, null, $siteIds));
      
      if ($wppvgw)
        $postIDs = array_merge ($postIDs, wp_worthy_migration::migrateProsodia (false, true, null, $siteIds));
      
      if ($tlvgw)
        $postIDs = array_merge ($postIDs, wp_worthy_migration::migrateTlVGWort (false, true, null, $siteIds));
      
      // Just redirect to posts-view
      wp_redirect (
        $this->linkSection (
          $this::ADMIN_SECTION_POSTS,
          [
            'migrate_inline' => ($inline ? 1 : 0),
            'migrate_vgw' => ($vgw ? 1 : 0),
            'migrate_vgwort' => ($vgwort ? 1 : 0),
            'migrate_wppvgw' => ($wppvgw ? 1 : 0),
            'migrate_tlvgw' => ($tlvgw ? 1 : 0),
            'migrate-repair-duplicates' => ($repair_dups ? 1 : 0),
            'displayPostsForMigration' => (count ($postIDs) > 0 ? $this->packPostIDs ($postIDs) : ''),
          ]
        )
      );
      
      exit ();
    }
    // }}}
    
    // {{{ migratePosts
    /**
     * Migrate existing VG WORT pixels to worthy
     * 
     * @params array $postIDs (optional)
     * @param bool $skipNonce (optional)
     * 
     * @access public
     * @return void
     **/
    public function migratePosts ($postIDs = null, $skipNonce = false) {
      if (!$skipNonce)
        $this->verifyNonce ([ $this::ADMIN_SECTION_CONVERT ]);
      
      // Determine what to migrate
      $inline = (isset ($_REQUEST ['migrate_inline']) && ((int)$_REQUEST ['migrate_inline'] == 1));
      $vgw = (isset ($_REQUEST ['migrate_vgw']) && ((int)$_REQUEST ['migrate_vgw'] == 1));
      $vgwort = (isset ($_REQUEST ['migrate_vgwort']) && ((int)$_REQUEST ['migrate_vgwort'] == 1));
      $wppvgw = (isset ($_REQUEST ['migrate_wppvgw']) && ((int)$_REQUEST ['migrate_wppvgw'] == 1));
      $tlvgw = (isset ($_REQUEST ['migrate_tlvgw']) && ((int)$_REQUEST ['migrate_tlvgw'] == 1));
      $posts = array (0, 0);
      $dups = array ();
      $migrationErrors = [];
      
      if (!is_array ($postIDs)) {
        $postIDs = null;
        
        if (
          isset ($_REQUEST ['wp-worthy-migrate-sites']) &&
          is_array ($_REQUEST ['wp-worthy-migrate-sites'])
        )
          $siteIds = array_unique (array_map ('intval', $_REQUEST ['wp-worthy-migrate-sites']));
        else
          $siteIds = [ get_current_blog_id () ];
      } else
        $siteIds = null;
      
      // Migrate inline markers
      if ($inline) {
        $rc = wp_worthy_migration::migrateInline (false, false, $postIDs, $siteIds);
        
        $posts [0] += $rc ['total'];
        $posts [1] += $rc ['migrated'];
        $dups = $rc ['duplicates'];
        
        $migrationErrors = array_merge ($migrationErrors, $rc ['errors']);
      }
      
      // Migrate extensions
      $keys = array ();
      
      if ($vgw)
        $keys ['vgwpixel'] = 'vgwpixel';
      
      if (
        $vgwort &&
        ($key =  get_option ('wp_vgwortmetaname', 'wp_vgwortmarke'))
      )
        $keys [$key] = $key;
      
      if (count ($keys) > 0) {
        $rc = wp_worthy_migration::migrateByMeta ($keys, false, false, $postIDs, $siteIds);
        
        $posts [0] += $rc ['total'];
        $posts [1] += $rc ['migrated'];
        $dups = array_merge ($dups, $rc ['duplicates']);
        
        $migrationErrors = array_merge ($migrationErrors, $rc ['errors']);
      }
      
      // Migrate Prosodia VGW
      if ($wppvgw) {
        $rc = wp_worthy_migration::migrateProsodia (false, false, $postIDs, $siteIds);
          
        $posts [0] += $rc ['total'];
        $posts [1] += $rc ['migrated'];
        $dups = $rc ['duplicates'];
        
        $migrationErrors = array_merge ($migrationErrors, $rc ['errors']);
      }
      
      // Migrate Torben Leuschners VG-Wort
      if ($tlvgw) {
        $rc = wp_worthy_migration::migrateTlVGWort (false, false, $postIDs, $siteIds);
        
        $posts [0] += $rc ['total'];
        $posts [1] += $rc ['migrated'];
        $dups = $rc ['duplicates'];
        
        $migrationErrors = array_merge ($migrationErrors, $rc ['errors']);
      }
      
      // Check wheter to re-run with repair of duplicates
      $repair_dups = (isset ($_REQUEST ['migrate-repair-duplicates']) && ((int)$_REQUEST ['migrate-repair-duplicates'] == 1));
      
      if (
        (count ($dups) > 0) &&
        $repair_dups
      ) {
        if ($inline) {
          $rc = wp_worthy_migration::migrateInline (true, false, $postIDs, $siteIds);
          
          $posts [1] += $rc ['migrated'];
          $dups = $rc ['duplicates'];
          
          $migrationErrors = array_merge ($migrationErrors, $rc ['errors']);
        }
        
        if (count ($keys) > 0) {
          $rc = wp_worthy_migration::migrateByMeta ($keys, true, false, $postIDs, $siteIds);
          
          $posts [1] += $rc ['migrated'];
          $dups = array_merge ($dups, $rc ['duplicates']);
          
          $migrationErrors = array_merge ($migrationErrors, $rc ['errors']);
        }
        
        if ($wppvgw) {
          $rc = wp_worthy_migration::migrateProsodia (true, false, $postIDs, $siteIds);
          
          $posts [1] += $rc ['migrated'];
          $dups = array_merge ($dups, $rc ['duplicates']);
          
          $migrationErrors = array_merge ($migrationErrors, $rc ['errors']);
        }
        
        if ($tlvgw) {
          $rc = wp_worthy_migration::migrateTlVGWort (true, false, $postIDs, $siteIds);
          
          $posts [1] += $rc ['migrated'];
          $dups = array_merge ($dups, $rc ['duplicates']);
          
          $migrationErrors = array_merge ($migrationErrors, $rc ['errors']);
        }
      }
      
      // Redirect to summary
      wp_redirect (
        $this->linkSection (
          $this::ADMIN_SECTION_CONVERT,
          [
            'displayStatus' => 'migrateDone',
            'migrateCount' => $posts [1],
            'totalCount' => $posts [0],
            'duplicates' => $this->packPostIDs ($dups),
            'repair_dups' => ($repair_dups ? 1 : 0),
            'migrate_inline' => ($inline ? 1 : 0),
            'migrate_vgw' => ($vgw ? 1 : 0),
            'migrate_vgwort' => ($vgwort ? 1 : 0),
            'migrate_wppvgw' => ($wppvgw ? 1 : 0),
            'migrate_tlvgw' => ($tlvgw ? 1 : 0),
            'errors' => $migrationErrors,
          ]
        )
      );

      exit ();
    }
    // }}}
    
    // {{{ migratePostsBulk
    /**
     * Migrate posts by using a bulk-action
     * 
     * @access public
     * @return void
     **/
    public function migratePostsBulk () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_POSTS ]);
      
      if (
        !isset ($_REQUEST ['post']) ||
        !is_array ($_REQUEST ['post'])
      )
        return;
      
      $this->migratePosts ($this->unpackPostIDs ($_REQUEST ['post']), true);
    }
    // }}}
    
    // {{{ removeInlineMarkers
    /**
     * Remove and extract VG WORT pixels from a given content
     * 
     * @param string $Content
     * @param bool $Extract (optional)
     * @param array &$Markers (optional)
     * 
     * @access public
     * @return string NULL if nothing was changed
     **/
    public function removeInlineMarkers ($Content, $Extract = false, &$Markers = null) {
      $p = 0;
      $c = false;
      $m = false;
      $Markers = array ();
      
      while (($p = strpos ($Content, 'src=', $p)) !== false) {
        $p += 4;
        
        // Extract URL from Tag
        if (
          ($Content [$p] == '"') ||
          ($Content [$p] == "'")
        )
          $URL = substr ($Content, $p + 1, strpos ($Content, $Content [$p], $p + 2) - $p - 1);
        else
          continue;
        
        if (($pURL = parse_url ($URL)) === false)
          continue;
        
        // Check if this is a VG WORT URL
        if (
          !isset ($pURL ['host']) ||
          !isset ($pURL ['path'])
        )
          continue;
        
        if (
          (
            (substr ($pURL ['host'], 0, 2) != 'vg') &&
            (substr ($pURL ['host'], 0, 6) != 'ssl-vg')
          ) ||
          (substr ($pURL ['host'], -14, 14) != '.met.vgwort.de') ||
          (substr ($pURL ['path'], 0, 4) != '/na/')
        )
          continue;
        
        if (!$c)
          $c = true;
        
        // Extract public marker from URL
        if (
          $Extract &&
          ($pixelPublic = wp_worthy_pixel::getPixelPublicFromURL ($URL))
        )
          $Markers [$URL] = $pixelPublic;
        
        // Find the whole tag
        $ps = null;
        
        for ($i = $p - 4; $i >= 0; $i--)
          if ($Content [$i] == '<') {
            $ps = $i;
            break;
          }
        
        if (
          ($ps === null) ||
          (($pe = strpos ($Content, '>', $ps)) === false)
        )
          continue;
        
        $m = true;
        
        // Remove the marker from content
        $Content = substr ($Content, 0, $ps) . substr ($Content, $pe + 1);
        $p = $ps;
      }
      
      if ($m)
        return $Content;
    }
    // }}}
    
    // {{{ searchPrivateMarkers
    /**
     * Search for private markers
     * 
     * @access public
     * @return void
     **/
    public function searchPrivateMarkers () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_MARKERS ]);
      
      // Check all uploaded files
      $files = 0;
      $records = 0;
      $created = 0;
      $markers = array ();

      foreach ($_FILES as $Key=>$Info) {
        // Try to read records from this file
        if (is_resource ($f = @fopen ($Info ['tmp_name'], 'r'))) {
          $columnIndex = null;
          
          while ($rec = fgetcsv ($f, 0, ';')) {
            if (
              ($columnIndex === null) &&
              (($columnIndex = array_search ('Privater Identifikationscode', $rec)) === false)
            ) {
              $columnIndex = 0;
              
              continue;
            }
            
            if (!isset ($markers [$rec [$columnIndex]]))
              $markers [$rec [$columnIndex]] = $GLOBALS ['wpdb']->prepare ('%s', $rec [$columnIndex]);
          }
          
          $files++;
          $records += count ($markers);
        }
        
        // Remove all informations about this upload 
        @fclose ($f);
        @unlink ($Info ['tmp_name']);
        unset ($_FILES [$Key]);
      }
      
      // Search markers on database
      $ids = $GLOBALS ['wpdb']->get_col ('SELECT id FROM `' . $this->getTablename ('worthy_markers', 0) . '` WHERE private IN (' . implode (',', $markers) . ')');
      
      // Check if there was anything imported
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_MARKERS, array ('displayMarkers' =>implode (',', $ids))));

      exit ();
    }
    // }}}
    
    // {{{ reindexPost
    /**
     * Update index of a single post
     * 
     * @param mixed $postID
     * @param int $siteId (optional)
     * 
     * @access public
     * @return int
     **/
    public function reindexPost ($postID, int $siteId = null) : int {
      // Make sure we have a post-object
      if (is_object ($postID))
        $thePost = WP_Worthy_Post::fromObject ($postID);
      else {
        $thePost = WP_Worthy_Post::fromID ($postID, $siteId);
        
        if (!is_object ($thePost))
          throw new Exception ('Failed to get post');
      }
      
      // Update the length
      $thePost->updateLength ();
      
      // Return the length as result
      return $thePost->getLength ();
    }
    // }}}
    
    // {{{ reindexPosts
    /**
     * Reindex character-counter
     * 
     * @access public
     * @return void
     **/
    public function reindexPosts () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_POSTS, $this::ADMIN_SECTION_SETTINGS, $this::ADMIN_SECTION_ADMIN ]);
      
      // Disable time-limit
      set_time_limit (0);
      
      // Determine which sites to reindex
      $siteIds = [];
      $sitesExpanded = false;
      $forceReindex = (isset ($_REQUEST ['wp-worthy-reindex-all']) && ((int)$_REQUEST ['wp-worthy-reindex-all'] == 1));
      
      if ($sitesExpanded = (isset ($_REQUEST ['post']) && is_array ($_REQUEST ['post']))) {
        $forceReindex = true;
        
        foreach ($this->unpackPostIDs ($_REQUEST ['post']) as $postID) {
          if (isset ($siteIds [$postID ['siteid']]))
            $siteIds [$postID ['siteid']][] = $postID ['postid'];
          else
            $siteIds [$postID ['siteid']] = [ $postID ['postid'] ];
        }
      } elseif (
        isset ($_REQUEST ['wp-worthy-reindex-site-all']) &&
        ((int)$_REQUEST ['wp-worthy-reindex-site-all'] == 1)
      )
        $siteIds = $this->getSiteIDs ();
      elseif (isset ($_REQUEST ['wp-worthy-reindex-site']))
        $siteIds = (is_array ($_REQUEST ['wp-worthy-reindex-site']) ? array_map ('intval', $_REQUEST ['wp-worthy-reindex-site']) : [ (int)$_REQUEST ['wp-worthy-reindex-site'] ]);
      elseif (
        is_network_admin () ||
        isset ($_GET ['wp-worthy-network-admin'])
      )
        $siteIds = $this->getSiteIDs ();
      else
        $siteIds = [ get_current_blog_id () ];
      
      if (!$sitesExpanded) {
        $newSiteIDs = [];
        
        foreach ($siteIds as $siteId)
          $newSiteIDs [$siteId] = [];
        
        $siteIds = $newSiteIDs;
        unset ($newSiteIDs);
      }
      
      // Reindex requested sites
      $postsReindexedTotal = 0;
      
      foreach ($siteIds as $siteId=>$postIDs) {
        // If reindexing is forced remove all metas before the process
        if ($forceReindex)
          $GLOBALS ['wpdb']->query (
            $GLOBALS ['wpdb']->prepare (
              'DELETE FROM `' . $this->getTablename ('postmeta', $siteId) . '` ' .
              'WHERE ' .
                (count ($postIDs) > 0 ? '`post_id` IN ("' . implode ('","', $postIDs) . '") AND ' : '') .
                '`meta_key`=%s',
              $this::META_LENGTH
            )
          );
        
        // Prepare the query
        $reindexQuery =
          'SELECT ' .
            'p.`ID`, ' .
            'p.`post_content` ' .
          'FROM ' .
            '`' . $this->getTablename ('posts', $siteId) . '` p ' .
              'LEFT JOIN `' . $this->getTablename ('postmeta', $siteId) . '` pm ON (p.`ID`=pm.`post_id` AND pm.`meta_key`="' . $this::META_LENGTH . '") ' .
          'WHERE ' .
            'pm.meta_value IS NULL AND ';
        
        if (count ($postIDs) > 0)
          $reindexQuery .= 'p.`ID` IN ("' . implode ('","', $postIDs) . '") ';
        else
          $reindexQuery .= 'p.`post_type` IN ("' . implode ('","', $this->getUserPostTypes ()) . '") ';
          
        $reindexQuery .= 'ORDER BY p.`ID`';
        
        // Update the index
        $postOffset = 0;
        $postsPerRound = 100;
        
        while (count ($reindexPosts = $GLOBALS ['wpdb']->get_results ($reindexQuery . ' LIMIT ' . $postOffset . ',' . $postsPerRound)) > 0) {
          $postsReindexed = 0;
          
          foreach ($reindexPosts as $reindexPost)
            if (($this->reindexPost ($reindexPost, $siteId)) !== false)
             $postsReindexed++;
          
          $postsReindexedTotal += $postsReindexed;
          $postOffset += $postsPerRound - $postsReindexed;
          
          if (count ($reindexPosts) < $postsPerRound)
            break;
        }
      }
      
      // Redirect to summary
      wp_redirect (
        $this->linkSection (
          $this::ADMIN_SECTION_ADMIN,
          array (
            'displayStatus' => 'reindexDone',
            'postCount' => $postsReindexedTotal,
          )
        )
      );
      
      exit ();
    }
    // }}}
    
    // {{{ assignPosts
    /**
     * Assign markers to a set of posts
     * 
     * @access public
     * @return void
     **/
    public function assignPosts () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_POSTS ]);
      
      // Check wheter to ignore this action
      if (
        isset ($_REQUEST ['filter_action']) &&
        ((int)$_REQUEST ['filter_action'] == 1)
      )
        return $this->redirectNoAction ();
      
      // Fetch Post-IDs to assign
      $sIDs = array ();
      $fIDs = array ();
      
      foreach ($this->unpackPostIDs ($_REQUEST ['post']) as $postID) {
        if (is_multisite ())
          switch_to_blog ($postID ['siteid']);
        
        if (!is_object ($thePost = wp_worthy_post::fromID ($postID ['postid'])))
          $fIDs [] = $this->packPostID ($postID);
        elseif (
          !$thePost->isOwnPost () ||
          !$thePost->assignPixel ()
        )
          $fIDs [] = $this->packPostID ($postID);
        else
          $sIDs [] = $this->packPostID ($postID);
        
        if (is_multisite ())
          restore_current_blog ();
      }
      
      // Push the client back
      $sendback = wp_get_referer ();
      
      if (!$sendback)
        $sendback = $this->linkSection ($this::ADMIN_SECTION_POSTS);
      
      wp_redirect (
        add_query_arg (
          array (
            'assigned' => implode (',', $sIDs),
            'not_assigned' => implode (',', $fIDs),
          ),
          $sendback
        )
      );
      
      exit ();
    }
    // }}}
    
    // {{{ ignorePosts
    /**
     * Ignore a set of posts for worthy
     * 
     * @access public
     * @return void
     **/
    public function ignorePosts () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_MARKERS, $this::ADMIN_SECTION_POSTS ]);
      
      // Check wheter to ignore this action
      if (
        isset ($_REQUEST ['filter_action']) &&
        ((int)$_REQUEST ['filter_action'] == 1)
      )
        return $this->redirectNoAction ();
      
      // Mark all those posts as ignored
      if (
        isset ($_REQUEST ['post']) &&
        is_array ($_REQUEST ['post'])
      )
        foreach ($this->unpackPostIDs ($_REQUEST ['post']) as $postID) {
          // Try to retrieve the post
          if (!is_object ($thePost = wp_worthy_post::fromID ($postID ['postid'], $postID ['siteid'])))
            continue;
          
          // Ignore the post if it's owned by the user
          if ($thePost->isOwnPost ())
            $thePost->isIgnored (true);
        }
      
      // Push the client back
      $sendback = wp_get_referer();
      
      if (!$sendback)
        $sendback = $this->linkSection ($this::ADMIN_SECTION_POSTS);
      
      wp_redirect ($sendback);
      
      exit ();
    }
    // }}}
    
    // {{{ burnPostPixel
    /**
     * Remove and burn an assigned pixel from posts
     * 
     * @access public
     * @return void
     **/
    public function burnPostPixel () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_POSTS ]);
      
      // Check wheter to ignore this action
      if (
        isset ($_REQUEST ['filter_action']) &&
        ((int)$_REQUEST ['filter_action'] == 1)
      )
        return $this->redirectNoAction ();
      
      // Mark all those posts as ignored
      foreach ($this->unpackPostIDs ($_REQUEST ['post']) as $postID) {
        // Retrieve the post and it's pixel
        if (!($thePost = wp_worthy_post::fromID ($postID ['postid'], $postID ['siteid'])))
          continue;
        
        if (!($thePixel = $thePost->getPixel ()))
          continue;
        
        // Sanity-Check if we may burn the pixel
        if (!get_option ('wp-worthy-enable-burn', false)) {
          $postUserIDs = $this->getUserIDs ($thePost->authorId);
          
          if (in_array ($pixelItem->userId, $postUserIDs))
            continue;
        }
        
        // De-assign and disable the pixel
        $thePixel->burn ();
      }
      
      // Push the client back
      $sendback = wp_get_referer();
      
      if (!$sendback)
        $sendback = $this->linkSection ($this::ADMIN_SECTION_POSTS);
      
      wp_redirect ($sendback);
      
      exit ();
    }
    // }}}
    
    // {{{ multisiteAssignSite
    /**
     * POST-Action: Assign site-ids to orphaned posts
     * 
     * @access public
     * @return void
     **/
    public function multisiteAssignSite () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_MARKERS ]);
      
      // Check wheter to ignore this action
      if (
        isset ($_REQUEST ['filter_action']) &&
        ((int)$_REQUEST ['filter_action'] == 1)
      )
        return $this->redirectNoAction ();
      
      // Sanatize input-paramterers
      if (
        !isset ($_REQUEST ['wp-worthy-siteid']) ||
        !is_array ($_REQUEST ['wp-worthy-siteid'])
      )
        return $this->redirectNoAction ();
      
      $_REQUEST ['wp-worthy-siteid'] = array_map ('intval', $_REQUEST ['wp-worthy-siteid']);
      
      // Mark all those posts as ignored
      foreach ($this->unpackPostIDs ($_REQUEST ['post']) as $postID) {
        // Ignore post-id with site assigned
        if ($postID ['siteid'])
          continue;
        
        // Check which site to assign
        if (!isset ($_REQUEST ['wp-worthy-siteid']['0/' . $postID ['postid']]))
          continue;
        
        $postID ['siteid'] = $_REQUEST ['wp-worthy-siteid']['0/' . $postID ['postid']];
        
        // Make sure the postid is valid on that site
        if (!is_object (wp_worthy_post::fromID ($postID ['postid'], $postID ['siteid'])))
          continue;
        
        // Assign the site-id
        $GLOBALS ['wpdb']->update (
          $this->getTablename ('worthy_markers', 0),
          array (
            'siteid' => $postID ['siteid'],
          ),
          array (
            'siteid' => 0,
            'postid' => $postID ['postid'],
          ),
          array ('%d'),
          array ('%d', '%d')
        );
        
        // Reindex
        $this->reindexPost ($postID ['postid'], $postID ['siteid']);
      }
      
      // Push the client back
      $sendback = wp_get_referer();
      
      if (!$sendback)
        $sendback = $this->linkSection ($this::ADMIN_SECTION_POSTS);
      
      wp_redirect ($sendback);
      
      exit ();
    }
    // }}}
    
    // {{{ checkRandomPosts
    /**
     * Pick up random posts and make sure that there is a marker embeded
     * 
     * @access public
     * @return void
     **/
    public function checkRandomPosts () {
      // Get table-metrics
      $Metric = $GLOBALS ['wpdb']->get_row (
        'SELECT MIN(`id`) AS min, MAX(`id`) AS max ' .
        'FROM `' . $this->getTablename ('worthy_markers', 0) . '` ' .
        'WHERE NOT `postid` IS NULL'
      );
      
      // Get random post-ids (yes, it's complex but fast)
      $postIDs = $GLOBALS ['wpdb']->get_results (
        'SELECT wm.`siteid`, wm.`postid` ' .
        'FROM `' . $this->getTablename ('worthy_markers', 0) . '` wm ' .
        'JOIN (' .
          'SELECT `id` ' .
          'FROM (' .
            'SELECT `id` ' .
            'FROM (SELECT ' . (int)$Metric->min . ' + (' . (int)($Metric->max - $Metric->min) . ' + 1 - 20) * RAND() AS `start` FROM DUAL) i ' .
            'JOIN `' . $this->getTablename ('worthy_markers', 0) . '` y ' .
            'WHERE y.`id`>i.`start` AND NOT (y.`postid` IS NULL) AND (y.`disabled`="0" OR y.`disabled` IS NULL) ' .
            'ORDER BY y.`id` ' .
            'LIMIT 20' .
          ') z ' .
          'ORDER BY RAND() ' .
          'LIMIT 2' .
        ') r ON wm.`id`=r.`id`',
        ARRAY_A
      );
      
      // Check each post
      $missingPixels = get_option ('wp-worthy-check-missing', array ());
      $issueDetected = false;
      $ctx = stream_context_create (array (
        'http' => array (
          'protocol_version' => 1.1,
          'user_agent' => 'WP-Worthy Cron/1.4.19',
          'timeout' => 5
        ),
      ));
      
      # TODO: Multisite
      foreach ($postIDs as $postID) {
        # TODO: Make sure the pixel wasn't ignored
        
        // Get the URL of the post
        if (!($url = $this->getPostLink ($postID ['postid'], $postID ['siteid'])))
          continue;
        
        // Try to retrive its rendered html
        if (($html = file_get_contents ($url, false, $ctx)) === false)
          continue;
        
        // Check if there are markers embeded
        $pPostID = $this->packPostID ($postID);
        
        if (
          (($Cleanup = $this->removeInlineMarkers ($html, true, $inlineMarkers)) === null) ||
          (count ($inlineMarkers) == 0)
        ) {
          if (!in_array ($pPostID, $missingPixels))
            $missingPixels [] = $pPostID;
          
          $issueDetected = true;
        } else
          foreach (array_keys ($missingPixels, $pPostID) as $missingIndex)
            unset ($missingPixels [$missingIndex]);
      }
      
      // Recheck invalids if no issue was found
      if (!$issueDetected)
        foreach ($missingPixels as $pPostID) {
          // Unpack the ID
          $postID = $this->unpackPostID ($pPostID);
          
          // Make sure there is a pixel for the post and the pixel is not marked as disabled
          # TODO: Check if the post was ignored in between
          if (
            !is_object ($pixel = $this->getPixelByPostID ($postID ['postid'], $postID ['siteid'])) ||
            ($pixel->disabled > 0)
          ) {
            foreach (array_keys ($missingPixels, $pPostID) as $missingIndex)
              unset ($missingPixels [$missingIndex]);
            
            continue;
          }
          
          // Get the URL of the post
          if (!($url = $this->getPostLink ($postID ['postid'], $postID ['siteid'])))
            continue;
          
          // Try to retrive its rendered html
          if (($html = file_get_contents ($url, false, $ctx)) === false)
            continue;
          
          // Check if there are markers embeded
          if (
            (($Cleanup = $this->removeInlineMarkers ($html, true, $inlineMarkers)) !== null) &&
            (count ($inlineMarkers) > 0)
          )
            foreach (array_keys ($missingPixels, $PostID) as $missingIndex)
              unset ($missingPixels [$missingIndex]);
        }
      
      // Store back the results
      update_option ('wp-worthy-check-missing', $missingPixels, false);
    }
    // }}}
    
    // {{{ doFeedback
    /**
     * Send feedback back to ourself
     * 
     * @access public
     * @return void
     **/
    public function doFeedback () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      // Try to access the SOAP-Client
      try {
        $soapClient = $this->getSOAPClient ();
        $soapSession = $this->premiumGetSession ();
      } catch (\SoapFault $soapError) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'soapException',
              'faultCode' => $soapError->faultcode,
              'faultString' => $soapError->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      try {
        $soapClient->serviceFeedback (
          $soapSession,
          $_REQUEST ['worthy-feedback-mail'],
          $_REQUEST ['worthy-feedback-caption'],
          $_REQUEST ['worthy-feedback-rating'],
          $_REQUEST ['worthy-feedback-text']
        );
      } catch (\Throwable $error) {
        exit (
          wp_redirect (
            $this->linkSection (
              self::ADMIN_SECTION_PREMIUM,
              [ 'displayStatus' => 'feedbackFailed' ]
            )
          )
        );
      }
      
      exit (
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [ 'displayStatus' => 'feedbackDone' ]
          )
        )
      );
    }
    // }}}
    
    // {{{ parsePixelsFromFile
    /**
     * Parse VG WORT pixels from a file/stream-resource
     * 
     * @param resource $fp
     * 
     * @access private
     * @return array
     **/
    private function parsePixelsFromFile ($fp) : array {
      $parsedPixels = [];
      
      // Read all CSV-Records from file-pointer
      while ($csvRecord = fgetcsv ($fp, 0, ';')) {
        // Check if first column contains text
        if (strlen ($csvRecord [0]) == 0)
          continue;
        
        // Retrive the number
        $pixelIndex = intval ($csvRecord [0]);
        
        // Process pixels for publishers
        if ($csvRecord [0] != (string)$pixelIndex) {
          if (
            (count ($csvRecord) >= 2) &&
            preg_match (wp_worthy_pixel::PIXEL_REGEX, $csvRecord [0]) &&
            preg_match (wp_worthy_pixel::PIXEL_REGEX, $csvRecord [1])
          )
            $parsedPixels [] = [
              'url' => null,
              'pixelPublic' => $csvRecord [0],
              'pixelPrivate' => $csvRecord [1],
            ];
          
          continue;
        }
        
        // URL with public pixel
        if (!($URL = wp_worthy_pixel::getURLFromHTML ($csvRecord [1])))
          continue;
        
        // Grep public part from URL
        if (!($pixelPublic = wp_worthy_pixel::getPixelPublicFromURL ($URL)))
          continue;
        
        // Extract private part of pixel
        if (!($csvRecord = fgetcsv ($fp, 0, ';')))
          throw new \Exception ('Failed to read CSV-line');
        
        $pixelPrivate = $csvRecord [2];
        
        if (!preg_match (wp_worthy_pixel::PIXEL_REGEX, $pixelPrivate))
          continue;
        
        // Store the result
        $parsedPixels [$pixelIndex] = [
          'url' => $URL,
          'pixelPublic' => $pixelPublic,
          'pixelPrivate' => $pixelPrivate,
        ];
      }
      
      return $parsedPixels;
    }
    // }}}
    
    // {{{ hasPremium
    /**
     * Check if a user has a valid Worthy Premium Subscribtion
     * 
     * @access public
     * @return bool
     **/
    public function hasPremium ($userID = null) {
      if (!is_object ($premiumInterface = $this->getPremium ($userID)))
        return false;
      
      return $premiumInterface->isPremium ();
    }
    // }}}
    
    // {{{ getPremium
    /**
     * Retrive interface to worthy premium for a given user
     * 
     * @param int $userID (optional)
     * 
     * @access public
     * @return wp_worthy_premium
     **/
    public function getPremium ($userID = null) : wp_worthy_premium {
      // Retrive default user-id if neccessary
      if ($userID === null)
        $userID = $this->getUserID ();
      
      // Check for a cached instance
      if (!isset ($this->premiumUsers [$userID]))
        $this->premiumUsers [$userID] = new wp_worthy_premium ($this, $userID);
      
      // Return cached instance
      return $this->premiumUsers [$userID];
    }
    // }}}
    
    // {{{ premiumGetSession
    /**
     * Retrive the authorization-parameter for SOAP-Calls
     * 
     * @param bool $allowUC (optional) Allow session to contain user-credentials only
     * 
     * @access private
     * @return mixed
     **/
    private function premiumGetSession ($allowUC = false) {
      if (!is_object ($premiumInstance = $this->getPremium ()))
        return false;
      
      return $premiumInstance->getSession ($allowUC);
    }
    // }}}

    // {{{ premiumGetLastMarkerUpdate
    /**
     * Retrive timestamp of last marker-synchronization
     * 
     * @param int $forUserID (optional)
     * @param bool $returnWorst (optional)
     * @param array $checkStatus (optional)
     * 
     * @access private
     * @return int
     **/
    private function premiumGetLastMarkerUpdate ($forUserID = null, $returnWorst = false, $checkStatus = null) {
      $meta = get_user_meta ($this->getUserID ($forUserID), 'worthy_premium_markers_updated', true);
      
      if (!is_array ($meta))
        return (int)$meta;
      
      if (!is_array ($checkStatus))
        $checkStatus = self::$defaultPixelStatis;
      
      $result = ($returnWorst ? time () : 0);
      
      foreach ($meta as $status=>$ts)
        if (
          (
            ($returnWorst && ($ts < $result)) ||
            (!$returnWorst && ($ts > $result))
          ) &&
          (
            ($checkStatus === null) ||
            in_array ($status, $checkStatus)
          )
        )
          $result = $ts;
      
      return $result;
    }
    // }}}
    
    // {{{ premiumUpdatePixelStatus
    /**
     * Retrive status of markers
     * 
     * @param array $syncStatis (optional) Synchronize these marker-statuses only
     * @param int $userID (optional) Do update for this user-id
     * 
     * @access private
     * @return int
     **/
    private function premiumUpdatePixelStatus ($syncStatis = null, $userID = null) {
      // Retrive interface to worthy premium
      if (!is_object ($premiumInterface = $this->getPremium ($userID)))
        return null;
      
      // Refresh the status
      try {
        return $premiumInterface->refreshPixelStatus ($syncStatis);
      } catch (\Throwable $error) {
        header ('HTTP/1.1 502 S2S Problem');
        header ('Status: 502 S2S Problem');
        header ('Content-Type: text/plain');
        
        echo $error;
        
        exit (1);
      }
    }
    // }}}
    
    // {{{ getSOAPClient
    /**
     * Retrive SOAP-Client for Worthy-Premium
     * 
     * @param bool $requireCredentials (optional) Only return a soap-client if login-credentials are available (default)
     * 
     * @access private
     * @return SOAPClient
     **/
    private function getSOAPClient ($requireCredentials = true, $userID = null) {
      if (!is_object ($premiumInterface = $this->getPremium ($userID)))
        return null;
      
      return $premiumInterface->getSOAPClient ($requireCredentials);
    }
    // }}}
    
    // {{{ premiumUpdateStatus
    /**
     * Retrive (if neccessary) our worthy-premium status and return it
     * 
     * @param bool $forceRefresh (optional) Force an update from service
     * @param int $userID (optional) Check status for user-id
     * 
     * @access private
     * @return array
     **/
    private function premiumUpdateStatus ($forceRefresh = false, $userID = null) {
      // Retrive interface to worthy premium
      if (!is_object ($premiumInterface = $this->getPremium ($userID)))
        return [ 'Status' => 'unregistered' ];
      
      // Refresh the status
      try {
        return $premiumInterface->refreshStatus ($forceRefresh);
      } catch (\Throwable $error) {
        // No-Op
      }
    }
    // }}}
    
    // {{{ premiumDebugDropSession
    /**
     * Just remove the current session for worthy-premium
     * 
     * @access public
     * @return void
     **/
    public function premiumDebugDropSession () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      delete_user_meta ($this->getUserID (), 'worthy_premium_session');
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM));
      
      exit ();
    }
    // }}}
    
    // {{{ premiumDropRegistration
    /**
     * Drop worthy-premium registration
     * 
     * @access public
     * @return void
     **/
    public function premiumDropRegistration () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      // Remove options
      $userID = $this->getUserID ();
      
      if (
        isset ($_REQUEST ['wp-worthy-remove-premium-credentials']) &&
        ($_REQUEST ['wp-worthy-remove-premium-credentials'] == 1)
      ) {
        delete_user_meta ($userID, 'worthy_premium_username');
        delete_user_meta ($userID, 'worthy_premium_password');
        delete_user_meta ($userID, 'worthy_premium_status');
        delete_user_meta ($userID, 'worthy_premium_status_updated');
      }
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM));
      
      exit ();
    }
    // }}}
    
    // {{{ premiumSignup
    /**
     * Sign up for worthy premium
     * 
     * @access public
     * @return void
     **/
    public function premiumSignup () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      // Try to create a bootstrap-client
      try {
        $soapClient = $this->getSOAPClient (false);
      } catch (\SoapFault $soapError) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'soapException',
              'faultCode' => $soapError->faultcode,
              'faultString' => $soapError->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      // Try to sign up at worthy premium
      $vgWortUsername = stripslashes (sanitize_text_field ($_POST ['wp-worthy-username']));
      $vgWortPassword = stripslashes (sanitize_text_field ($_POST ['wp-worthy-password']));
      
      try {
        $signupResult = $soapClient->serviceSignup (
          $vgWortUsername,
          $vgWortPassword,
          (isset ($_POST ['wp-worthy-accept-tac']) && ($_POST ['wp-worthy-accept-tac'] == 1))
        );
      } catch (\SoapFault $soapError) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'soapException',
              'faultCode' => $soapError->faultcode,
              'faultString' => $soapError->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      // Try to store credentials on success
      $userID = $this->getUserID ();
      
      if ($signupResult ['Status'] != 'unregistered') {
        $credentialsStored = (
          ((get_user_meta ($userID, 'worthy_premium_username', true) == $vgWortUsername) || update_user_meta ($userID, 'worthy_premium_username', $vgWortUsername)) &&
          ((get_user_meta ($userID, 'worthy_premium_password', true) == $vgWortPassword) || update_user_meta ($userID, 'worthy_premium_password', $vgWortPassword))
        );
        
        // Store the status
        $signupResult ['ValidFrom'] = strtotime ($signupResult ['ValidFrom']);
        $signupResult ['ValidUntil'] = strtotime ($signupResult ['ValidUntil']);
        
        update_user_meta ($userID, 'worthy_premium_status', $signupResult);
        update_user_meta ($userID, 'worthy_premium_status_updated', time ());
        
        // Update/Synchronize markers for the first time
        # $this->premiumUpdatePixelStatus ();
      }
      
      // Redirect to status-page
      wp_redirect (
        $this->linkSection (
          $this::ADMIN_SECTION_PREMIUM,
          [
            'displayStatus' => 'signupDone',
            'status' => ($signupResult ['Status'] == 'unregistered' ? 0 : ($credentialsStored ? 1 : -1)),
          ]
        )
      );
      
      exit ();
    }
    // }}}
    
    // {{{ premiumSyncStatus
    /**
     * Synchronize our premium-subscription-status
     * 
     * @access public
     * @return void
     **/
    public function premiumSyncStatus () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      // Just force a status-update
      $this->premiumUpdateStatus (true);
      
      // Redirect to status-page
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('displayStatus' => 'syncStatusDone')));
      
      exit ();
    }
    // }}}
    
    // {{{ premiumSyncPixels
    /**
     * Syncronize pixels with VG WORT (Worthy Premium)
     * 
     * @access public
     * @return void
     **/
    public function premiumSyncPixels () {
      # This is not considered to be dangerous
      # $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      // Check if we are subscribed to premium
      if (!$this->hasPremium ()) {
        wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM));
        
        exit ();
      }
      
      // Check request-parameters
      if (isset ($_REQUEST ['wp-worthy-marker-status']))
        $updateStatis = array_map ('intval', array_unique (explode (',', $_REQUEST ['wp-worthy-marker-status'])));
      else
        $updateStatis = self::$defaultPixelStatis;
      
      // Try to do the sync
      if (($Count = $this->premiumUpdatePixelStatus ($updateStatis)) === false)
        wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('displayStatus' => 'syncMarkerDone', 'markerCount' => -1)));
      else
        wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('displayStatus' => 'syncMarkerDone', 'markerCount' => $Count)));
      
      exit ();
    }
    // }}}
    
    // {{{ premiumImportMarkers
    /**
     * Import markers using Worthy Premium
     * 
     * @access public
     * @return void
     **/
    public function premiumImportMarkers () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_CONVERT ]);
      
      // Check if we are subscribed to premium
      if (!$this->hasPremium ())
        exit (wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM)));
      
      // Try to order the pixels
      try {
        $orderResult = $this->premiumOrderPixels ((int)$_POST ['count']);
      } catch (\SoapFault $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            array (
              'displayStatus' => 'soapException',
              'faultCode' => $error->faultcode,
              'faultString' => $error->faultstring,
            )
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      if ($orderResult > 0) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_CONVERT,
            [
              'displayStatus' => 'premiumImportDone',
              'markerCount' => $orderResult,
            ]
          )
        );
        
        exit ();
      }
    }
    // }}}
    
    // {{{ premiumOrderPixels
    /**
     * Internal function to order pixels via Worthy Premium
     * 
     * @param int $pixelCount
     * 
     * @access public
     * @return int
     * @throws \SoapFault
     * @throws \Exception
     **/
    public function premiumOrderPixels (int $pixelCount) /* : int */ {
      // Check if we are subscribed to premium
      if (!$this->hasPremium ())
        throw new \Exception ('Current user is not subscribed to Worthy Premium');
      
      // Try to access the SOAP-Client
      $soapClient = $this->getSOAPClient ();
      $premiumSession = $this->premiumGetSession ();
      
      // Place the order
      $orderedPixels = $soapClient->markersCreate (
        $premiumSession,
        max (1, min (100, (int)$pixelCount))
      );
      
      // Generate import-query
      $insertQuery = 'INSERT INTO `' . $this->getTablename ('worthy_markers', 0) . '` (`userid`, `public`, `private`, `server`, `url`) VALUES ';
      $userID = $this->getUserID ();
      
      foreach ($orderedPixels as $orderedPixel)
        $insertQuery .= $GLOBALS ['wpdb']->prepare (
          '(%d, %s, %s, %s, %s), ',
          $userID,
          $orderedPixel->Public,
          $orderedPixel->Private,
          parse_url ($orderedPixel->URL, PHP_URL_HOST),
          $orderedPixel->URL
        );
      
      // Try to import the markers into database
      if ($GLOBALS ['wpdb']->query (substr ($insertQuery, 0, -2)) !== false) {
        // Update local statistics
        if (($pixelsCreated = $GLOBALS ['wpdb']->rows_affected) > 0) {
          update_option ('worthy_premium_markers_imported', get_option ('worthy_premium_markers_imported', 0) + $pixelsCreated);
          update_user_meta ($userID, 'worthy_premium_markers_imported', intval (get_user_meta ($userID, 'worthy_premium_markers_imported', true)) + $pixelsCreated);
        }
        
        return $pixelsCreated;
      }
      
      // Handle errors during import
      $imDir = dirname (__FILE__) . '/import';
      
      if (!is_dir ($imDir) && @wp_mkdir_p ($imDir))
        file_put_contents ($imDir . '/index.html', ':-)');
      
      // Try to store the markers on disk
      if (is_resource ($f = @fopen ($imDir . '/' . date ('Y-m-d-H-i-s') . '_' . rand (100, 999) . '.csv', 'w'))) {
        fputcsv ($f, array ('Private', 'Public', 'URL'));
        
        foreach ($orderedPixels as $orderedPixel)
          fputcsv ($f, array ($orderedPixel->Private, $orderedPixel->Public, $orderedPixel->URL));
        
        fclose ($f);
      }
      
      throw new \Exception('Failed to store pixels on database');
    }
    // }}}
    
    // {{{ premiumImportPrivate
    /**
     * @access public
     * @return void
     **/
    public function premiumImportPrivate () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      // Check if we are subscribed to premium
      if (!$this->hasPremium ())
        exit (wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM)));
      
      // Try to access the SOAP-Client
      try {
        $soapClient = $this->getSOAPClient ();
        $soapSession = $this->premiumGetSession ();
      } catch (\SoapFault $soapError) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'soapException',
              'faultCode' => $soapError->faultcode,
              'faultString' => $soapError->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      // Collect markers without private code
      $Markers = array ();
      
      foreach ($GLOBALS ['wpdb']->get_results ('SELECT public FROM `' . $this->getTablename ('worthy_markers', 0) . '` WHERE private IS NULL AND (userid="0" OR userid="' . (int)$this->getUserID () . '")') as $R)
        $Markers [$R->public] = $R->public;
      
      // Process all pixels
      $pixelsCompleted = 0;
      $total = count ($Markers);
      
      while (count ($Markers) > 0) {
        try {
          // Forward to worthy-premium
          $completedPixels = $soapClient->markersCompletePublic ($soapSession, array_splice ($Markers, 0, 10));
          
          if (!is_array ($completedPixels))
            continue;
          
          // Forward the result to our database
          foreach ($completedPixels as $completedPixel)
            $pixelsCompleted += $GLOBALS ['wpdb']->update (
              $this->getTablename ('worthy_markers', 0),
              [
                'private' => $completedPixel->Private,
              ],
              [
                'public' => $completedPixel->Public,
              ],
              [ '%s' ],
              [ '%s' ]
            );
        } catch (\Throwable $error) {
          # No-Op
        }
      }
      
      // Redirect back
      wp_redirect (
        $this->linkSection (
          $this::ADMIN_SECTION_PREMIUM,
          [
            'displayStatus' => 'privateImportDone',
            'total' => $total,
            'done' => $pixelsCompleted,
          ]
        )
      );
      
      exit ();
    }
    // }}}
    
    // {{{ premiumCreateWebareas
    /**
     * Create webareas for a set of posts (Worthy Premium)
     * 
     * @access public
     * @return void
     **/
    public function premiumCreateWebareas () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      // Check if we are subscribed to premium
      if (!$this->hasPremium ())
        exit (wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM)));
      
      // Check wheter to ignore this action
      if (
        isset ($_REQUEST ['filter_action']) &&
        ((int)$_REQUEST ['filter_action'] == 1)
      )
        return $this->redirectNoAction ();
      
      // Try to access the SOAP-Client
      try {
        $soapClient = $this->getSOAPClient ();
        $soapSession = $this->premiumGetSession ();
      } catch (\SoapFault $soapError) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'soapException',
              'faultCode' => $soapError->faultcode,
              'faultString' => $soapError->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      // Process each post
      $invalidIDs = array ();
      $failedIDs = array ();
      $successIDs = array ();
      
      if (
        !isset ($_REQUEST ['post']) ||
        !is_array ($_REQUEST ['post'])
      )
        $_REQUEST ['post'] = array ();
      
      foreach (array_unique ($_REQUEST ['post']) as $postAddress) {
        // Get site- and post-id
        if (($p = strpos ($postAddress, '/')) === false)
          continue;
        
        $siteId = (int)substr ($postAddress, 0, $p);
        $postID = (int)substr ($postAddress, $p + 1);
        
        // Try to retrive the post
        if (!is_object ($thePost = wp_worthy_post::fromID ($postID, $siteId))) {
          $invalidIDs [] = $postAddress;
          
          continue;
        }
        
        // Check permissions
        if (!$thePost->isOwnPost ()) {
          $failedIDs [$postAddress] = __ ('Post is not owned by you', $this->textDomain);
          
          continue;
        }
        
        // Issue the request
        try {
          if (
            is_object ($thePixel = $thePost->getPixel ()) &&
            $thePixel->createWebrange ()
          )
            $successIDs [] = $postAddress;
          else
            $failedIDs [] = __ ('Failed to create webrange', $this->textDomain);
        } catch (Exception $error) {
          $failedIDs [$postAddress] = $error->getMessage ();
        }
      }
      
      // Redirect to summary
      wp_redirect (
        $this->linkSection (
          $this::ADMIN_SECTION_PREMIUM,
          array (
            'displayStatus' => 'webareasDone',
            'sR' => $successIDs,
            'fR' => $failedIDs,
            'iI' => $invalidIDs,
          )
        )
      );

      exit ();
    }
    // }}}
    
    // {{{ premiumReportPostsPreview
    /**
     * Redirect to preview-view for post-reports
     * 
     * @access public
     * @return void
     **/
    public function premiumReportPostsPreview () {
      // Check wheter to ignore this action
      if (
        isset ($_REQUEST ['filter_action']) &&
        ((int)$_REQUEST ['filter_action'] == 1)
      )
        return $this->redirectNoAction ();
      
      // Remove some parameters  
      unset ($_REQUEST ['action2']);
      
      // Reset some parameters
      $_REQUEST ['action'] = 'wp-worthy-premium-report-posts-preview';
      
      // Redirect
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM, $_REQUEST));
      
      exit ();
    }
    // }}}
    
    // {{{ premiumReportPosts
    /**
     * Report selected posts to VG WORT (Worthy Premium)
     * 
     * @access public
     * @return void
     **/
    public function premiumReportPosts () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_MARKERS, $this::ADMIN_SECTION_POSTS, $this::ADMIN_SECTION_PREMIUM ]);
      
      // Check if we are subscribed to premium
      if (!$this->hasPremium ())
        exit (wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM)));
      
      // Check wheter to ignore this action
      if (
        isset ($_REQUEST ['filter_action']) &&
        ((int)$_REQUEST ['filter_action'] == 1)
      )
        return $this->redirectNoAction ();
      
      // Try to access the SOAP-Client
      try {
        $soapClient = $this->getSOAPClient ();
        $soapSession = $this->premiumGetSession ();
      } catch (\SoapFault $soapError) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'soapException',
              'faultCode' => $soapError->faultcode,
              'faultString' => $soapError->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      // Process each post
      $invalidIDs = array ();
      $successfulReports = array ();
      $failedReports = array ();
      
      if (
        !isset ($_REQUEST ['post']) ||
        !is_array ($_REQUEST ['post'])
      )
        $_REQUEST ['post'] = array ();
      
      foreach ($this->unpackPostIDs ($_REQUEST ['post']) as $postID) {
        // Try to retrive the post
        if (!is_object ($thePost = wp_worthy_post::fromID ($postID ['postid'], $postID ['siteid']))) {
          $invalidIDs [] = $this->packPostID ($postID);
          
          continue;
        }
        
        // Check ownership of the post
        if (!$thePost->isOwnPost ()) {
          $failedReports [$this->packPostID ($postID)] = __ ('Post is not owned by you', $this->textDomain);
          
          continue;
        }
        
        // Collect informations
        if (!is_object ($postPixel = $thePost->getPixel ())) {
          $failedReports [$this->packPostID ($postID)] = __ ('No pixel assigned to post', $this->textDomain);
          
          continue;
        }
        
        if (isset ($_REQUEST ['wp-worthy-title-' . $postID ['siteid'] . '-' . $postID ['postid']]))
          $postTitle = $_REQUEST ['wp-worthy-title-' . $postID ['siteid'] . '-' . $postID ['postid']];
        else
          $postTitle = $thePost->getTitle (true);
        
        if (isset ($_REQUEST ['wp-worthy-content-' . $postID ['siteid'] . '-' . $postID ['postid']]))
          $postContent = $_REQUEST ['wp-worthy-content-' . $postID ['siteid'] . '-' . $postID ['postid']];
        else
          $postContent = $thePost->getContent ();
        
        // Sanity-Check
        if (
          ($postPixel->status < 2) ||
          (!$thePost->isLyric () && (strlen ($postContent) < ($postPixel->status == 2 ? $this::EXTRA_LENGTH : $this::MIN_LENGTH)))
        ) {
          $failedReports [$this->packPostID ($postID)] = __ ('Not qualified', $this->textDomain);
          
          continue;
        }
        
        // Check consents
        if (
          !isset ($_REQUEST ['aiDisclaimer']) ||
          ($_REQUEST ['aiDisclaimer'] !== 'accepted')
        ) {
          $failedReports [$this->packPostID ($postID)] = __ ('Missing AI-Disclaimer', $this->textDomain);
          
          continue;
        }
        
        // Create a document-spec
        $Document = new stdClass;
        $Document->publisherConsent = [ 'aiDisclaimer' ];
        $Document->Title = $postTitle;
        $Document->Content = $postContent;
        $Document->Type = ($thePost->isLyric () ? 'lyric' : 'default');
        $Document->Preprocess = isset ($_REQUEST ['wp-worthy-content-' . $postID ['siteid'] . '-' . $postID ['postid']]);
        # $Docuemnt->Comment = '';
        
        $Document->Webarea = array ($Webarea = new stdClass);
        $Webarea->OwnSite = true;
        $Webarea->URL = $thePost->getURL ();
        $Webarea->Restricted = (strlen ($thePost->postPassword) > 0);
        
        $Document->Author = array ($Author = new stdClass);
        $Author->Forename = get_user_meta ($thePost->authorId, 'wp-worthy-forename', true);
        $Author->Lastname = get_user_meta ($thePost->authorId, 'wp-worthy-lastname', true);
        $Author->CardID = get_user_meta ($thePost->authorId, 'wp-worthy-cardid', true);
        $Author->Involvement = 'author'; # TODO: This is hard-coded
        
        // Check wheter to inherit names from user-preferences
        if (
          (strlen ($Author->Forename) == 0) ||
          (strlen ($Author->Lastname) == 0)
        ) {
          $User = get_userdata ($thePost->authorId);
          
          if (strlen ($Author->Forename) == 0)
            $Author->Forename = $User->first_name;
          
          if (strlen ($Author->Lastname) == 0)
            $Author->Lastname = $User->last_name;
        }
        
        // Issue the request
        $userID = $this->getUserID ();
        
        try {
          // Push the post to server
          $rc = $soapClient->reportCreate (
            $soapSession,
            $postPixel->private,
            $Document
          );
          
          // Sanity-Check the result
          if (!is_array ($rc))
            throw new Exception ('Invalid response from soap-server');
          
          // Check if there was an error
          if (!$rc ['Status'])
            throw new Exception ($rc ['errorMessage']);
          
          // Mark the marker as reported
          $GLOBALS ['wpdb']->update (
            $this->getTablename ('worthy_markers', 0),
            [
              'status' => 4,
              'reportable' => 0,
            ],
            [
              'siteid' => $postID ['siteid'],
              'postid' => $postID ['postid'],
            ],
            [ '%d' ],
            [ '%d', '%d' ]
          );
          
          // Decrease the number of reports
          if (
            is_array ($Status = get_user_meta ($userID, 'worthy_premium_status', true)) &&
            isset ($Status ['ReportLimit'])
          ) {
            $Status ['ReportLimit'] = max (0, $Status ['ReportLimit'] - 1);
            
            update_user_meta ($userID, 'worthy_premium_status', $Status);
          }
          
          // Push to successfull queue
          $successfulReports [] = $this->packPostID ($postID);
        } catch (\Throwable $error) {
          $rc = false;
          $failedReports [$this->packPostID ($postID)] = $error->getMessage ();
        }
      }
      
      // Redirect to summary
      wp_redirect (
        $this->linkSection (
          $this::ADMIN_SECTION_PREMIUM,
          [
            'displayStatus' => 'reportDone',
            'iI' => $invalidIDs,
            'sR' => $successfulReports,
            'fR' => $failedReports,
          ]
        )
      );
      
      exit ();
    }
    // }}}
    
    // {{{ premiumPurchase
    /**
     * Purchase something for worthy-premium
     * 
     * @access public
     * @return void
     **/
    public function premiumPurchase () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      // Try to access the SOAP-Client
      try {
        $soapClient = $this->getSOAPClient ();
        $soapSession = $this->premiumGetSession (true);
      } catch (\SoapFault $soapError) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'soapException',
              'faultCode' => $soapError->faultcode,
              'faultString' => $soapError->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      // Collect all goods
      $Goods = array ();
      
      foreach ($_REQUEST as $Key=>$Value)
        if (substr ($Key, 0, 15) == 'wp-worthy-good-') {
          if ($Value == 'none')
            continue;
          
          $Goods [intval (substr ($Key, 15))] = $Good = new stdClass;
          
          $Good->ID = intval (substr ($Key, 15));
          $Good->Options = array ($Option = new stdClass);
          $Option->ID = intval ($Value);
        }
      
      if (count ($Goods) == 0)
        exit (wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('shopping' => 'isfun', 'displayStatus' => 'noGoods'))));
      
      // Setup payment
      $Payment = new stdClass;
      $Payment->Type = 'paypal';
      
      // Try to start the purchase
      try {
        $Result = $soapClient->servicePurchaseGoods ($soapSession, $Goods, $Payment, $this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('shopping' => 'isfun')), $_REQUEST ['wp-worthy-accept-tac']);
      } catch (\SoapFault $soapError) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'shopping' => 'isfun',
              'displayStatus' => 'soapException',
              'faultCode' => $soapError->faultcode,
              'faultString' => $soapError->faultstring,
            ]
          )
        );
        
        exit ();
      } catch (\Throwable $error) {
        wp_redirect (
          $this->linkSection (
            $this::ADMIN_SECTION_PREMIUM,
            [
              'displayStatus' => 'genericException',
              'exceptionMessage' => $error->getMessage (),
              'exceptionCode' => $error->getCode (),
            ]
          )
        );
        
        exit ();
      }
      
      if ($Result ['Status'])
        exit (wp_redirect ($Result ['PaymentURL']));
      
      exit (wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM, array ('shopping' => 'isfun', 'displayStatus' => 'paymentError', 'Error' => $Result ['Message']))));
    }
    // }}}
    
    // {{{ premiumDebugSetServer
    /**
     * Change the server used for worthy premium
     * 
     * @access public
     * @return void
     **/
    public function premiumDebugSetServer () {
      $this->verifyNonce ([ $this::ADMIN_SECTION_PREMIUM ]);
      
      // Set server and remove current status
      $userID = $this->getUserID ();
      
      if (in_array ($_REQUEST ['wp-worthy-server'], array ('production', 'devel')))
        update_user_meta ($userID, 'worthy_premium_server', $_REQUEST ['wp-worthy-server']);
      
      delete_user_meta ($userID, 'worthy_premium_status');
      delete_user_meta ($userID, 'worthy_premium_status_updated');
      delete_user_meta ($userID, 'worthy_premium_session');
      
      // Redirect back
      wp_redirect ($this->linkSection ($this::ADMIN_SECTION_PREMIUM));
      
      exit ();
    }
    // }}}
    
    // {{{ verifyNonce
    /**
     * Check the nonce for an incoming action
     * 
     * @param array $validActions List of valid action-names
     * 
     * @access private
     * @return void
     * 
     * @throws Exception if no valid nonce was found
     **/
    private function verifyNonce ($validActions) {
      if (!isset ($_REQUEST ['wp-worthy-nonce']))
        // TODO: We might want to improve this
        throw new Exception ('Missing Nonce');
      
      foreach ($validActions as $actionName)
        if (wp_verify_nonce ($_REQUEST ['wp-worthy-nonce'], $actionName))
          return;
      
      // TODO: We might want to improve this
      throw new Exception ('Invalid Nonce');
    }
    // }}}
  }
  
  // Register generic hooks
  register_activation_hook (__FILE__, array ('wp_worthy', 'onActivate'));
  
  // Create a new plugin-handle
  global $wp_plugin_worthy;
  
  if (
    !isset ($wp_plugin_worthy) ||
    !is_object ($wp_plugin_worthy)
  )
    $wp_plugin_worthy = wp_worthy::singleton ();
