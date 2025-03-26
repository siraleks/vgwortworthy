<?PHP

  /**
   * Copyright (C) 2013 Bernd Holzmueller <bernd@quarxconnect.de>
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
  
  // Avoid loading this twice
  if (class_exists ('qcWP'))
    return;
  
  abstract class qcWp {
    /* Plugin-Path */
    protected $pluginPath = null;
    
    /* Localization */
    protected $textDomain = null;
    
    /* Informations about plugin-pages */
    private $onPluginPage = false;
    private $pluginPages = array ();
    private $pluginPageHandlerInstalled = false;
    
    /* Registered admin-menus */
    private $adminMenus = array ();
    private $adminHandlerInstalled = false;
    
    /* Registered widgets */
    private $widgets = array ();
    private $widgetHandlerInstalled = false;
    
    /* Registered styles and scripts */
    private $Stylesheets = array ();
    private $Scripts = array ();
    private $scriptHandlerInstalled = false;
    
    /* Registered short-codes */
    private $Shortcodes = array ();
    private $shortcodeHandlerInstalled = false;
    
    /* Exposed Tables */
    private $exposedTables = array ();
    private $restInitialized = null;
    
    // {{{ __construct
    /**
     * Create a new wordpress-plugin
     * 
     * @access friendly
     * @return void
     **/
    function __construct ($Path = null) {
      // Check for an text-domain
      if ($Path === null)
        $Path = dirname ( __FILE__ );
      elseif (is_file ($Path))
        $Path = dirname ($Path);
      
      $this->pluginPath = $Path;
      $bN = basename ($Path);
      
      if (is_dir ($Path . '/lang'))
        $this->textDomain = $bN;
      
      // Register runtime-hooks
      add_action ('init', array ($this, 'onInit'));
    }
    // }}}
    
    // {{{ onInit
    /**
     * Wordpress is being initialized
     * 
     * @access public
     * @return void
     **/
    public function onInit () {
      // Check if we have language-files available
      if ($this->textDomain) {
        $locale = apply_filters ('plugin_locale', get_locale (), $this->textDomain);
        $mofile = WP_PLUGIN_DIR . '/' . $this->textDomain . '/lang/' . $this->textDomain . '-' . $locale . '.mo';
        
        if (!is_file ($mofile) || !load_textdomain ($this->textDomain, $mofile))
          load_plugin_textdomain ($this->textDomain, false, $this->textDomain . '/lang');
      }
    }
    // }}}
    
    // {{{ onEnqueueScripts
    /**
     * Action: Install all registered scripts and stylesheets
     * 
     * @access public
     * @return void  
     **/
    public function onEnqueueScripts () {
      // Enqueue all queued stylesheets
      foreach ($this->Stylesheets as $ID=>$File)
        wp_enqueue_style (get_class ($this) . '-' . $ID, $File, array (), false, 'all');
      
      // Enqueue all queued scripts
      foreach ($this->Scripts as $ID=>$Info) {
        wp_enqueue_script (get_class ($this) . '-' . $ID, $Info ['File'], array (), false, $Info ['onFooter']);
        
        foreach ($Info ['l10n'] as $k=>$v)
          $Info ['l10n'][$k] = __ ($v, $this->textDomain);
        
        if ($Info ['l10nVarname'] && (count ($Info ['l10n']) > 0))
          wp_localize_script (get_class ($this) . '-' . $ID, $Info ['l10nVarname'], $Info ['l10n']);
      }
    }
    // }}}
    
    // {{{ onAdminMenu
    /**
     * Install all queued admin-menus
     * 
     * @access public
     * @return void  
     **/
    public function onAdminMenu () {
      foreach ($this->adminMenus as $Menu) {
        if ((substr ($Menu [6], -4, 4) == '.svg') && ($Content = @file_get_contents ($Menu [6])))
          $Menu [6] = 'data:image/svg+xml;base64,' . base64_encode ($Content);
        
        $hook = add_menu_page (
          __ ($Menu [0], $this->textDomain),
          __ ($Menu [1], $this->textDomain) . (($Badge = $this->getAdminMenuBadge ()) ? ' <span class="qcWP-badge awaiting-mod"><span class="qcWP-badge-content">' . $Badge . '</span></span>' : ''),
          $Menu [2],
          $Menu [3],
          $Menu [4],
          $Menu [6],
          $Menu [8]
        );
        
        if ($Menu [5] !== null)
          add_action ('load-' . $hook, $Menu [5]);
        
        if (is_array ($Menu [7]))
          foreach ($Menu [7] as $Child) {
            $hook = add_submenu_page (
              $Menu [3],
              __ ($Child [0], $this->textDomain),
              __ ($Child [1], $this->textDomain),
              $Child [2],
              $Child [3],
              $Child [4]
            );
            
            if (isset ($Child [5]) && $Child [5])
              add_action ('load-' . $hook, $Child [5]);
            elseif ($Menu [5] !== null)
              add_action ('load-' . $hook, $Menu [5]);
          }
      }
    }  
    // }}}
    
    protected function getAdminMenuBadge () {
    
    }
    
    // {{{ addStylesheet
    /**
     * Register a stylesheet for this plugin
     * 
     * @param string $Path
     * 
     * @access protected
     * @return void
     **/
    protected function addStylesheet ($Path) {
      // Make sure the path points to a real file
      if ((strpos ($Path, '://') === false) && !is_file ($Path))
        $Path = untrailingslashit (plugins_url ($Path, $this->pluginPath . '/qcWp.php'));
      
      $this->Stylesheets [] = $Path;
      
      // Check wheter to install the script-handler
      if ($this->scriptHandlerInstalled)
        return;
      
      $this->scriptHandlerInstalled = true;
      
      add_action ('wp_enqueue_scripts', array ($this, 'onEnqueueScripts'));
      add_action ('admin_enqueue_scripts', array ($this, 'onEnqueueScripts'));
    }
    // }}}
    
    // {{{ addScript
    /**
     * Enqueue a script
     * 
     * @param string $scriptFilename
     * @param array $l10n (optional)
     * @param string $l10Varname (optional)
     * 
     * @access protected
     * @return string
     **/
    protected function addScript ($scriptFilename, $l10n = array (), $l10nVarname = null, $onFooter = false) {
      // Make sure the path points to a URL
      if ((strpos ($scriptFilename, '://') === false) && !is_file ($scriptFilename)) {
        // Convert into URL
        $scriptURL = untrailingslashit (plugins_url ($scriptFilename, $this->pluginPath . '/qcWp.php'));
        
        // Append mtime if it's a real file
        if (is_file ($this->pluginPath . '/' . $scriptFilename))
          $scriptURL .= '?' . filemtime ($this->pluginPath . '/' . $scriptFilename);
      } else
        $scriptURL = $scriptFilename;
      
      $this->Scripts [] = array (
        'File' => $scriptURL,
        'onFooter' => $onFooter,
        'l10n' => $l10n,
        'l10nVarname' => $l10nVarname,
      );
      
      // Check wheter to install the script-handler
      if ($this->scriptHandlerInstalled)
        return get_class ($this) . '-' . (count ($this->Scripts) - 1);
      
      $this->scriptHandlerInstalled = true;
      
      add_action ('wp_enqueue_scripts', array ($this, 'onEnqueueScripts'));
      add_action ('admin_enqueue_scripts', array ($this, 'onEnqueueScripts'));
      
      return get_class ($this) . '-' . (count ($this->Scripts) - 1);
    }
    // }}}
    
    // {{{ addAdminMenu
    /**
     * Register a handler for admin-menu
     * 
     * @param string $Caption
     * @param string $Title
     * @param string $Capability
     * @param string $Slug
     * @param string $Icon
     * @param callable $Handler
     * @param callable $updateHandler (optional)
     * @param array $Children (optional)
     * @param int $Position (optional)
     * 
     * @access portected
     * @return bool
     **/
    protected function addAdminMenu ($Caption, $Title, $Capability, $Slug, $Icon, $Handler, $updateHandler = null, $Children = null, $Position = null) {
      // Check if we are on administrator
      if (!is_admin ())
        return false;
      
      // Make sure the icon points to a real file
      if (!is_file ($Icon))
        $Icon = untrailingslashit (plugins_url ('', $this->pluginPath . '/qcWp.php')) . '/' . $Icon;
      
      // Register the menu
      $this->adminMenus [$Slug] = array ($Caption, $Title, $Capability, $Slug, $Handler, $updateHandler, $Icon, $Children, $Position);
      
      // Check wheter to register a handler for this
      if ($this->adminHandlerInstalled)
        return true;
      
      $this->adminHandlerInstalled = true;
      
      add_action ('admin_menu', array ($this, 'onAdminMenu'));
      add_action ('network_admin_menu', array ($this, 'onAdminMenu'));
      
      return true;
    }
    // }}}
    
    // {{{ getAdminMenu
    /**
     * @access protected
     * @return array
     **/
    protected function getAdminMenu () {
      // Check for a direct match
      if (isset ($this->adminMenus [$_REQUEST ['page']]))
        return $this->adminMenus [$_REQUEST ['page']];
      
      foreach ($this->adminMenus as $Slug=>$Info) {
        if (!current_user_can ($Info [2]) || !isset ($Info [7]) || !is_array ($Info [7]))
          continue;
        
        foreach ($Info [7] as $Page)
          if ($Page [3] == $_REQUEST ['page'])
            return $Info;
      }
    }
    // }}}
    
    // {{{ addWidget
    /**
     * Register a widget for this plugin
     * 
     * @param string $Classname
     * 
     * @access protected
     * @return bool
     **/
    protected function addWidget ($Classname) {
      if (!class_exists ($Classname))
        return false;
      
      if (!is_subclass_of ($Classname, 'WP_Widget'))
        return false;
      
      $this->widgets [] = $Classname;
      
      if (!$this->widgetHandlerInstalled) {
        add_action ('widgets_init', array ($this, 'installWidgets'));
        
        $this->widgetHandlerInstalled = true;
      }
      
      return true;
    }
    // }}}
    
    // {{{ addShortcode
    /**
     * Register a short-code-handler
     * 
     * @param string $Shortcode
     * @param callback $Callback
     * 
     * @access protected
     * @return bool
     **/
    protected function addShortcode ($Shortcode, $Callback) {
      // Validate the callback
      if (!is_callable ($Callback)) {
        $Callback = array ($this, $Callback);
        
        if (!is_callable ($Callback))
          return false;
      }
      
      $this->Shortcodes [$Shortcode] = $Callback;
      
      if ($this->shortcodeHandlerInstalled)
        return true;
      
      add_action ('plugins_loaded', array ($this, 'activateShortcodes'), 1);
      $this->shortcodeHandlerInstalled = true;
      
      return true;
    }
    // }}}
    
    // {{{ activateShortcodes
    /**
     * Activate all registered shortcodes
     * 
     * @access public
     * @return void
     **/
    public function activateShortcodes () {
      foreach ($this->Shortcodes as $Code=>$Handler)
        add_shortcode ($Code, $Handler);
    }
    // }}}
    
    // {{{ installWidgets
    /**
     * Install all registered widgets
     * 
     * @access public
     * @return void
     **/
    public function installWidgets () {
      foreach ($this->widgets as $Widget)
        register_widget ($Widget);
    }
    // }}}
    
    // {{{ setURLHandler
    /**
     * Redirect a custom URL to this plugin
     * 
     * @param string $URL
     * @param callable $Handler
     * 
     * @access protected
     * @return bool
     **/
    protected function setURLHandler ($URL, $Handler) {
      if (!is_callable ($Handler))
        return false;
      
      $this->pluginPages [$URL] = $Handler;
      
      if (!$this->pluginPageHandlerInstalled) {
        // Make sure claimed URLs are not rewritten
        add_filter ('redirect_canonical', array ($this, 'checkPluginPageURL'));
        
        // Make sure claimed URLs are processed here
        add_action ('parse_request', array ($this, 'claimPluginPage'));
        add_action ('wp', array ($this, 'handleClaimedRequest'));
        
        $this->pluginPageHandlerInstalled = true;
      }
      
      return true;
    }
    // }}}
    
    // {{{ checkPluginPageURL
    /**
     * Filter: Check if an URL to be rewritten is claimed by this plugin (and stop rewrite-process)
     * 
     * @param string $redirect_url
     * 
     * @access public
     * @return bool
     **/
    public function checkPluginPageURL ($redirect_url) {
      $base = get_site_url ();
      
      foreach ($this->pluginPages as $url=>$Handler)
        if ($base . '/' . $url == $redirect_url)
          return false;
    }
    // }}}
    
    // {{{ claimPluginPage
    /**
     * Action: Check if an incoming request matches an URL claimed by this plugin
     * 
     * @param object $wp
     * 
     * @access public
     * @return void
     **/
    public function claimPluginPage ($wp) {
      if (!isset ($this->pluginPages [$wp->request]))
        return;
      
      $this->onPluginPage = true;
      
      $wp->query_vars = array ('name' => '__plugin_page', 'page' => '');
      $wp->query_string = '';
      $wp->matched_rule = '([^/]+)(/[0-9]+)?/?$';
      $wp->matched_query = 'name=__plugin_page&page=';
    }
    // }}}
    
    // {{{ handleClaimedRequest
    /**
     * Action: Handle a request claimed by this plugin
     * 
     * @param object $wp
     * 
     * @access public
     * @return void
     **/
    public function handleClaimedRequest ($wp) {
      // Check if we are on page claimed by this plugin
      if (!$this->onPluginPage || !isset ($this->pluginPages [$wp->request]))
        return;
      
      // Dispatch the request
      $Handle = new WP_Post ((object)array ('ID' => 0xffffffff, 'post_type' => 'page', 'filter' => 'raw', 'comment_status' => 'closed', 'ping_status' => 'closed'));
      call_user_func ($this->pluginPages [$wp->request], $Handle);
      
      // Reset the HTTP-Status-Code
      header ('HTTP/1.1 200 Ok');
      
      // Override Query-Settings
      global $wp_query;
      
      $wp_query->is_page = true;
      $wp_query->is_404 = false;
      $wp_query->post_count = 1;
      $wp_query->posts = array ($Handle);
    }
    // }}}
    
    // {{{ wpLinkPost
    /**
     * Create a link to a post or page
     * 
     * @param mixed $Post
     * @param bool $frontend (optional) Should this be a link to the frontend
     * 
     * @access public
     * @return string
     **/
    public function wpLinkPost ($post, $frontend = null) {
      // Check where to link to
      if ($frontend === null)
        $frontend = !is_admin ();
      elseif (!is_admin ())
        $frontend = true;
      
      // Collect all information
      if (is_object ($post)) {
        $postID = (int)$post->ID;
        $postTitle = $post->post_title;
      } else {
        $postID = (int)$post;
        $postTitle = get_the_title ($post);
      }
      
      if ($postID < 1)
        return false;
      
      // Generate the link
      if ($frontend)
        $url = '';
      else
        $url = get_admin_url () . 'post.php?post=' . $postID . '&action=edit';
      
      // Return the HTML
      return '<a href="' . $url . '">' . esc_html ($postTitle) . (!$frontend ? ' (' . $postID . ')' : '') . '</a>';
    }
    // }}}
    
    // {{{ getTablename
    /**
     * Retrive the name of a table on wordpress database
     * 
     * @param string $Name
     * 
     * @access protected
     * @return string
     **/
    public function getTablename (string $Name) : string {
      // Ask Wordpress directly if there is a variable for this documented
      switch ($Name) {
        case 'posts':
        case 'postmeta':
        case 'comments':
        case 'commentmeta':
        case 'termmeta':
        case 'terms':
        case 'term_taxonomy':
        case 'term_relationships':
        case 'users':
        case 'usermeta':
        case 'links':
        case 'options':
          return $GLOBALS ['wpdb']->$Name;
      }
      
      if ($Global)
        return $GLOBALS ['wpdb']->base_prefix . $Name;
      
      return $GLOBALS ['wpdb']->prefix . $Name;
    }
    // }}}
    
    // {{{ exposeTable
    /**
     * Expose a given table via REST-API
     * 
     * @param string $Table
     * 
     * @access public
     * @return void
     **/
    public function exposeTable ($Table, $Namespace, $BaseURL, $Capability, array $Callbacks = array ()) {
      // Check if we have registered rest-initializer
      if (isset ($GLOBALS ['wp_rest_server']) && is_object ($GLOBALS ['wp_rest_server']))
        $this->restInitialized = true;
      elseif ($this->restInitialized === null) {
        add_action ('rest_api_init', array ($this, 'restExposeTables'));
        
        $this->restInitialized = false;
      }
      
      // Directly register the router
      if ($this->restInitialized !== false) {
        register_rest_route (
          $Namespace,
          $BaseURL,
          array (
            'methods' => array ('GET', 'POST'), // TODO: Add more methods, make this controllable
            'callback' => array ($this, 'requestExposedTableIndex'),
            'permission_callback' => array ($this, 'grantExposedTable'),
            'table' => $Table,
            'urlBase' => $Namespace . $BaseURL . '/',
            'capability' => $Capability,
            'callbacks' => $Callbacks,
          )
        );
        
        register_rest_route (
          $Namespace,
          $BaseURL . '/(?P<id>\d+)',
          array (
            'methods' => array ('GET'), // TODO: Add more methods, make this controllable
            'callback' => array ($this, 'requestExposedTable'),
            'permission_callback' => array ($this, 'grantExposedTable'),
            'table' => $Table,
            'urlBase' => $Namespace . $BaseURL . '/',
            'capability' => $Capability,
            'callbacks' => $Callbacks,
          )
        );
      }
      
      // Remember the table as exported
      $this->exposedTables [] = func_get_args ();
    }
    // }}}
    
    // {{{ restExposeTables
    /**
     * Callback: Register exposed tables on rest-init
     * 
     * @access public
     * @return void
     **/
    public function restExposeTables () {
      if ($this->restInitialized !== false)
        return;
      
      foreach ($this->exposedTables as $Table) {
        register_rest_route (
          $Table [1],
          $Table [2],
          array (
            'methods' => array ('GET', 'POST'),
            'callback' => array ($this, 'restExposeTableIndex'),
            'permission_callback' => array ($this, 'grantExposedTable'),
            'table' => $Table [0],
            'urlBase' => $Table [1] . $Table [2] . '/',
            'capability' => $Table [3],
            'callbacks' => $Table [4],
          )
        );
        
        register_rest_route (
          $Table [1],
          $Table [2] . '/(?P<id>\d+)',
          array (
            'methods' => array ('GET', 'POST'),
            'callback' => array ($this, 'restExposeTable'),
            'permission_callback' => array ($this, 'grantExposedTable'),
            'table' => $Table [0],
            'urlBase' => $Table [1] . $Table [2] . '/',
            'capability' => $Table [3],
            'callbacks' => $Table [4],
          )
        );
      }
      
      $this->restInitialized = true;
    }
    // }}}
    
    // {{{ grantExposedTable
    /**
     * Grant access to a exposed table
     * 
     * @param WP_REST_Request $Request
     * 
     * @access public
     * @retrun bool
     **/
    public function grantExposedTable (WP_REST_Request $Request) {
      $Attributes = $Request->get_attributes ();
      
      if (!isset ($Attributes ['capability']))
        return true;
      
      return current_user_can ($Attributes ['capability']);
    }
    // }}}
    
    // {{{ restExposeTableIndex
    /**
     * Callback: Handle REST-Request for an exposed table-listing
     * 
     * @param WP_REST_Request $Request
     * 
     * @access public
     * @return object
     **/
    public function restExposeTableIndex (WP_REST_Request $Request) {
      // Prepare a response
      $Response = rest_ensure_response (null);
      
      // Retrive attributes
      $Method = $Request->get_method ();
      $Attributes = $Request->get_attributes ();
      
      if (!isset ($Attributes ['table'])) {
        $Response->set_status (400);
        
        return $Response;
      }
       
      #// Retrive URL-Parameters
      #$URL = $Request->get_url_params ();
      
      // Create a new item without primary key
      if ($Method == 'POST') {
        // Prepare a record
        $Fields = $GLOBALS ['wpdb']->get_results ('SHOW FIELDS IN ' . $this->getTablename ($Attributes ['table']));
        $Record = $Format = array ();
        $Key = null;
        $Invalid = false;
        
        foreach ($Fields as $Field) {
          // Remember primary key of the table
          if ($Field->Key == 'PRI')
            $Key = $Field->Field;
          
          // Check if the field is on the request
          if (!isset ($Request [$Field->Field])) {
            if (($Field->Default === null) && ($Field->Null == 'NO') && ($Field->Key != 'PRI'))
              $Invalid = true;
              
            continue;
          }
          
          // Forward the field to the record
          $Record [$Field->Field] = $Request [$Field->Field];
          $Format [] = '%s';
        }
        
        if (isset ($Attributes ['callbacks']['beforeCreate']))
          $Attributes ['callbacks']['beforeCreate'] ($Record, $Format, $Invalid);
        
        // Check if we found an error
        if ($Invalid) {
          $Response->set_status (400);
          
          return $Response;
        }
        
        // Try to insert/replace the record
        if ($GLOBALS ['wpdb']->replace ($this->getTablename ($Attributes ['table']), $Record, $Format) === false) {
          $Response->set_status (500);
          
          return $Response;
        }
        
        if (($Key !== null) && !isset ($Record [$Key]))
          $Record [$Key] = $GLOBALS ['wpdb']->insert_id;
        
        if (isset ($Attributes ['callbacks']['afterCreate']))
          call_user_func ($Attributes ['callbacks']['afterCreate'], $Record);
        
        // Forward record to response
        $Response->set_data ($Record);
        $Response->set_status (201);
        
        if ($Key !== null)
          $Response->header ('Location', rest_url ($Attributes ['urlBase'] . $Record [$Key]));

        return $Response;
      } elseif ($Method == 'DELETE') {
        // Try to empty the table
        # THIS IS DANGEROUS!
        #if ($GLOBALS ['wpdb']->delete ($this->getTablename ($Attributes ['table']), array ()) !== false)
        #  $Response->setStatus (204);
        #else
        #  $Response->setStatus (500);
        
        // Forward the response
        return $Response;
      } elseif ($Method == 'PUT') {
        # TODO
        $Response->set_status (415);
        
        return $Response;
      } elseif ($Method != 'GET') {
        $Response->set_status (415);
        
        return $Response;
      }
       
      $Query = 'SELECT * FROM ' . $this->getTablename ($Attributes ['table']);
      
      # TODO: Add support for order=asc|desc and orderby=field
      
      if ($Limit = $perPage = $Request->get_param ('per_page'))
        $perPage = max (1, min (100, (int)$perPage));
      else
        $perPage = 100;
      
      if ($Offset = $Request->get_param ('offset'))
        $Query .= ' LIMIT ' . max (0, (int)$Offset) . ',' . $perPage;
      elseif ($Page = $Request->get_param ('page'))
        $Query .= ' LIMIT ' . (max ((int)$Page - 1, 0) * $perPage) . ',' . $perPage;
      elseif ($Limit)
        $Query .= ' LIMIT ' . $perPage;
      
      $Response->set_data ($GLOBALS ['wpdb']->get_results ($Query, OBJECT_K));
      
      $Total = (int)$GLOBALS ['wpdb']->get_var (
        'SELECT COUNT(*) ' .
        'FROM ' . $this->getTablename ($Attributes ['table'])
      );
      
      $Response->header ('X-WP-Total', $Total);
      $Response->header ('X-WP-TotalPages', (int)ceil ($Total / $perPage));
      
      return $Response;
    }
    // }}}
    
    // {{{ restExposeTable
    /**
     * Callback: Handle REST-Request for an exposed table
     * 
     * @param WP_REST_Request $Request
     * 
     * @access public
     * @return object
     **/
    public function restExposeTable (WP_REST_Request $Request) {
      // Prepare a response
      $Response = rest_ensure_response (null);
      
      // Retrive attributes
      $Method = $Request->get_method ();
      $Attributes = $Request->get_attributes ();
      
      if (!isset ($Attributes ['table'])) {
        $Response->set_status (400);
        
        return $Response;
      }
      
      // Retrive URL-Parameters
      $URL = $Request->get_url_params ();
      
      // Treat POST as PUT on resources
      if ($Method == 'POST')
        $Method = 'PUT';
      
      // Process as request for a resource
      return $GLOBALS ['wpdb']->get_row ('SELECT * FROM ' . $this->getTablename ($Attributes ['table']) . ' WHERE ID=' . (int)$URL ['id'], OBJECT);
    }
    // }}}
  }

?>