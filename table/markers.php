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
  
  if (!class_exists ('WP_List_Table'))
    require_once (ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
  
  class wp_worthy_table_markers extends WP_List_Table {
    private $Parent;
    
    // {{{ __construct
    /**
     * Setup new address table
     * 
     * @param plugin $Parent
     * 
     * @access friendly
     * @return void
     **/
    function __construct ($Parent) {
      parent::__construct (array (
        'singular' => 'worthy_marker',
        'plural' => 'worthy_markers',
        'ajax' => false,
      ));
      
      $this->Parent = $Parent;
    }
    // }}}
    
    // {{{ setupOptions
    /**
     * Setup screen-options for this table
     * 
     * @access public
     * @return void
     **/
    public static function setupOptions () {
      add_screen_option ('per_page', array ( 
        'label' => __ ('Markers', 'wp-worthy'),
        'default' => 20,
        'option' => 'wp_worthy_markers_per_page'
      ));
    }
    // }}}
    
    // {{{ setupColumns
    /**
     * Setup columns used in this table
     * 
     * @access public
     * @return array
     **/
    public static function setupColumns () {
      $columns = array (
        'cb' => '<input type="checkbox" />',
        'public' => __ ('Public Marker', 'wp-worthy'),
        'private' => __ ('Private Marker', 'wp-worthy'),
        'server' => __ ('Server', 'wp-worthy'),
        'author' => __ ('Author', 'wp-worthy'),
        'status' => __ ('Status', 'wp-worthy'),
        'siteId' => __ ('Site', 'wp-worthy'),
        'postid' => __ ('Post', 'wp-worthy'),
        'postlength' => __ ('Relevant Characters', 'wp-worthy'),
        'actions' => __ ('Actions', 'wp-worthy'),
      );
      
      if (!is_multisite ())
        unset ($columns ['siteId']);
      
      if (wp_worthy::singleton ()->hasPremium ())
        unset ($columns ['status']);
      
      return $columns;
    }
    // }}}
    
    // {{{ get_columns
    /**
     * Retrive all columns
     * 
     * @access public
     * @return array
     **/
    public function get_columns () {
      return get_column_headers (get_current_screen ());
    }
    // }}}
    
    // {{{ get_sortable_columns
    /**
     * Retrive a list of columns on this table that are sortable
     * 
     * @access public
     * @return array 
     **/
    public function get_sortable_columns () {
      $columns = array (
        'public' => 'public',
        'private' => 'private',
        'server' => 'server',
        'author' => 'author',
        'siteId' => 'siteId',
        'postid' => 'postid',
        'postlength' => 'postlength',
      );
      
      if (wp_worthy::singleton ()->hasPremium ())
        $columns ['status'] = 'status';
      
      return $columns;
    }
    // }}}
    
    // {{{ column_default
    /**
     * Retrive default data for any column
     * 
     * @param object $item
     * @param string $column_name
     * 
     * @access public
     * @return string
     **/
    public function column_default ($item, $column_name) {
      if (isset ($_REQUEST ['wp-worthy-demo']) && (($column_name == 'private') || ($column_name == 'public')) && $item->$column_name)
        return esc_html (substr ($item->$column_name, 0, -6) . 'xxxxxx');
      
      return esc_html ($item->$column_name);
    }
    // }}}
    
    // {{{ column_cb
    /**
     * Retrive marker for bulk-actions
     * 
     * @param object $item
     * 
     * @access public
     * @return string 
     **/
    public function column_cb ($item) {
      // Check if no post is assigned or the status does not allow actions
      if (!$item->postid)
        return '';
      
      // FIXME: This feature may be removed when enough time has passed like in 2021/Q3
      if ((!is_multisite () || ($item->siteId)) && (($item->status > 3) || ($item->postlength == null) || ($item->post_title == null) || ($item->worthy_ignored == 1)))
        return '';
      
      return '<input id="cb-select-pixelid-' . esc_attr ($item->id) . '" type="checkbox" name="post[]" value="' . esc_attr ($item->siteId . '/' . $item->postid) . '" />';
    }
    // }}}
    
    // {{{ column_server
    /**
     * Output server of this pixel
     * 
     * @param object $pixelItem
     * 
     * @access public
     * @return string
     **/
    public function column_server (/* object */ $pixelItem) /* : string */ {
      if (!$this->Parent)
        return esc_html ($pixelItem->server);
      
      return esc_html (parse_url ($this->Parent->pixelURL (wp_worthy_pixel::fromObject ($pixelItem)), PHP_URL_HOST));
    }
    // }}}
    
    // {{{ column_status
    /**
     * Generate status-text for a marker
     * 
     * @param array $item
     * 
     * @access public
     * @return string
     **/
    public function column_status ($item) {
      if (!wp_worthy::singleton ()->hasPremium ($item->userid))
        return '<span class="wp-worthy-status-neutral">' . __ ('No premium', 'wp-worthy') . '</span>';
      
      static $Map = array (
        -1 => 'not synced',
         0 => 'not counted',
         1 => 'not qualified',
         2 => 'partial qualified',
         3 => 'qualified',
         4 => 'reported',
      );
      
      static $tooltipMap = array (
        -1 => 'marker wasn\'t synced via Worthy Premium yet',
         0 => 'counter wasn\'t started at VG WORT yet, this might take some days',
         1 => 'this marker hasn\'t qualified yet because of it\'s age or it had to few readers',
         2 => 'this pixel has qualified partialy and may be reported to VG WORT if content is long enough',
         3 => 'this pixel has fully qualified and may be reported to VG WORT',
         4 => 'the marker has already been report, no further action has to be taken',
      );
      
      return '<span class="wp-worthy-status-' . intval ($item->status) . '" title="' . esc_attr (__ ($tooltipMap [$item->status === null ? -1 : (int)$item->status], 'wp-worthy')) . '">' . __ ($Map [$item->status === null ? -1 : (int)$item->status], 'wp-worthy') . '</span>';
    }
    // }}}
    
    // {{{ column_author
    public function column_author ($pixelRow) {
      if (!$pixelRow->author)
        return
          '<span class="wp-worthy-status-neutral">' .
            __ ('Invalid author', 'wp-worthy') .
          '</span>';
      
      return esc_html ($pixelRow->author);
    }
    // }}}
    
    // {{{ column_siteId
    /**
     * Retrive content for site-column
     * 
     * @param obect $pixelRow
     * 
     * @access public
     * @return string
     **/
    public function column_siteId ($pixelRow) {
      if (!$pixelRow->siteId || !is_multisite ())
        return '';
      
      $siteName = '-';
      
      foreach (get_sites (array ('site__in' => array ($pixelRow->siteId))) as $site)
        if (is_user_member_of_blog (null, $site->id)) {
          switch_to_blog ($site->id);
          
          $siteName = '<a href="' . esc_attr (admin_url ()) . '">' . esc_html ($site->blogname) . '</a>';
          
          restore_current_blog ();
        }
      
      return $siteName;
    }
    // }}}
    
    // {{{ column_postid
    /**
     * Retrive content for post-column
     * 
     * @access public
     * @return string
     **/
    public function column_postid ($item) {
      // Sanity-Check the post-id
      // FIXME: This feature may be removed when enough time has passed like in 2021/Q3
      if (is_multisite () && !$item->siteId && $item->postid)
        return '';
      
      // Check if no post is assigned
      if (!$item->postid || ($item->post_title == null))
        return '';
      
      // Make sure we have an intance of wp_worthy as parent
      if (!$this->Parent)
        return $this->column_default ($item, 'postid');
      
      return $this->Parent->getPostAdminLink ($item->postid, $item->siteId);
    }
    // }}}
    
    // {{{ column_postlength
    /**
     * Retrive the number of characters for a post
     *  
     * @param object $item
     * 
     * @access public
     * @return string
     **/
    public function column_postlength ($item) {
      // Check if no or invalid post is assigned
      if (!$item->postid || ($item->post_title == null))
        return '';
      
      // Check wheter to force the indexer to run
      if ((($length = $item->postlength) == 0) && $this->Parent)
        $length = $this->Parent->reindexPost ($item->postid, $item->siteId);
      
      // Output column-content
      return sprintf (__ ('%d chars', 'wp-worthy'), $length);
    }
    // }}}
    
    // {{{ column_actions
    /**
     * Generate content of action-column for a marker
     * 
     * @param array $item
     * 
     * @access public
     * @return string
     **/
    public function column_actions ($item) {
      $wpWorthy = wp_worthy::singleton ();
      
      // Check if the pixel is disabled
      if ($item->disabled)
        return '<span class="wp-worthy-warning">' . __ ('Burned', 'wp-worthy') . '</span>';
      
      // Check wheter to assign a new site here
      // FIXME: This feature may be removed when enough time has passed like in 2021/Q3
      if (is_multisite () && !$item->siteId && $item->postid) {
        $possibleSites = array ();
        
        foreach (get_sites () as $possibleSite) {
          // Make sure the current user may access this site
          if (!is_user_member_of_blog (null, $possibleSite->id))
            continue;
          
          // Check if there is a post on this site
          if ((!$thePost = wp_worthy_post::fromID ($item->postid, $possibleSite->id)))
            continue;
          
          // Push as possible option
          $possibleSites [$possibleSite->id] = $possibleSite->blogname . ' - '. $thePost->getTitle ();
        }
        
        if (count ($possibleSites) == 0)
          return '<span class="wp-worthy-warning">' . __ ('No suitable sites found', 'wp-worthy') . '</span>';
        
        $siteSelector = '';
        
        foreach ($possibleSites as $siteId=>$siteHint)
          $siteSelector .= '<option value="' . (int)$siteId . '">' . esc_html ($siteHint) . '</option>';
        
        return
          '<span class="wp-worthy-warning">' . __ ('Unknown site', 'wp-worthy') . '</span>' .
          '<select name="wp-worthy-siteid[' . esc_attr ($item->siteId . '/' . $item->postid) . ']" data-action="wp-worthy-assign-site" data-pixelid="' . (int)$item->id . '">' .
            '<option value="">' . esc_html (__ ('Select site', 'wp-worthy')) . '</option>' .
            $siteSelector .
          '</select>';
      }
      
      // Check if no post is assigned or the status does not allow actions
      if ($item->worthy_ignored == 1)
        return '<span class="wp-worthy-neutral">' . __ ('Ignored', 'wp-worthy') . '</span>';
      
      if (!$item->postid || ($item->status > 3) || ($item->postlength == null) || ($item->post_title == null))
        return '';
      
      if (
        !isset ($thePost) ||
        !$thePost
      ) {
        $item->ID = $item->postid;
        $thePost = wp_worthy_post::fromObject ($item);
      }
      
      $hasPremium = $wpWorthy->hasPremium ();
      
      if ($item->private && $hasPremium && get_option ('wp-worthy-enable-webarea', false))
        $Links =
          '<li>' .
            '<button data-action="wp-worthy-premium-create-webareas" data-pixelid="' . (int)$item->id . '" data-siteid="' . (int)$item->siteId . '" data-postid="' . (int)$item->postid . '">' .
              __ ('Create webarea', 'wp-worthy') .
            '</button>' .
          '</li>';
      else
        $Links = '';
      
      if ($item->private) {
        $postTitle = $thePost->getTitle ();
        
        if (strlen ($postTitle) > wp_worthy_post::TITLE_MAX_LENGTH)
          $Status = '<span class="wp-worthy-' . (strlen ($thePost->getTitle (true)) > wp_worthy_post::TITLE_MAX_LENGTH ? 'warning' : 'notice') . '">' . __ ('Title is too long', 'wp-worthy') . '</span>';
        else
          $Status = '';
        
        if ($hasPremium && ((($item->status == 3) && ($item->postlength >= wp_worthy::MIN_LENGTH)) || (($item->status == 2) && ($item->postlength >= wp_worthy::EXTRA_LENGTH)))) {
          $Links .=
            '<li>' .
              '<button data-action="wp-worthy-premium-report-posts-preview" data-pixelid="' . (int)$item->id . '" data-siteid="' . (int)$item->siteId . '" data-postid="' . (int)$item->postid . '">' .
                __ ('Preview report for VG WORT', 'wp-worthy') .
              '</button>' .
            '</li><li>' .
              '<button data-action="wp-worthy-premium-report-posts" data-pixelid="' . (int)$item->id . '" data-siteid="' . (int)$item->siteId . '" data-postid="' . (int)$item->postid . '">' .
                __ ('Report directly to VG WORT', 'wp-worthy') .
              '</button>' .
            '</li>';
        }
      } else
        $Status = '<span class="wp-worthy-warning">' . __ ('No private marker', 'wp-worthy') . '</span>';
      
      $Links .=
        '<li>' .
          '<button data-action="wp-worthy-bulk-ignore" data-pixelid="' . (int)$item->id . '" data-siteid="' . (int)$item->siteId . '" data-postid="' . (int)$item->postid . '" class="wp-worthy-danger wp-worthy-inline">' .
            __ ('Ignore this post', 'wp-worthy') .
          '</button>' .
        '</li>';
      
      // Check ownership of marker
      if (!in_array ($wpWorthy->getUserID (), $wpWorthy->getUserIDs ($item->userid)))
        $Links = '';
      
      return $Status . '<ul>' . $Links . '</ul>';
    }
    // }}}
    
    // {{{ get_bulk_actions
    /**
     * Retrive a list of all bulk-actions
     *    
     * @access public
     * @return array
     **/
    public function get_bulk_actions () {
      $Actions = array (
        'wp-worthy-bulk-ignore' => __ ('Ignore posts', 'wp-worthy')
      );
      
      if (wp_worthy::singleton ()->hasPremium ()) {
        $Actions ['wp-worthy-premium-report-posts-preview'] = __ ('Report with preview', 'wp-worthy');
        $Actions ['wp-worthy-premium-report-posts'] = __ ('Report without preview', 'wp-worthy');
        
        if (get_option ('wp-worthy-enable-webarea', false))
          $Actions ['wp-worthy-premium-create-webareas'] = __ ('Create webareas', 'wp-worthy');
      }
      
      return $Actions;
    }   
    // }}}
    
    // {{{ extra_tablenav
    /**
     * Output additional filters for navigation
     * 
     * @access public
     * @return void
     **/
    public function extra_tablenav ($which) {
      if ($which != 'top')
        return;
      
      echo
        '<div class="alignleft actions">';
      
      // Output filter for sites
      if (is_multisite ()) {
        $usedSites = $GLOBALS ['wpdb']->get_results (
          'SELECT DISTINCT `siteId` ' .
          'FROM `' . $this->Parent->getTablename ('worthy_markers', 0) . '` ' .
          'WHERE `siteId`>0'
        );
        
        foreach ($usedSites as $i=>$v)
          if (is_user_member_of_blog (null, $v->siteId))
            $usedSites [$i] = (int)$v->siteId;
          else
            unset ($usedSites [$i]);
        
        if (count ($usedSites) > 0) {
          $selectedSite = (isset ($_REQUEST ['wp-worthy-filter-site']) ? (int)$_REQUEST ['wp-worthy-filter-site'] : 0);
          
          echo
            '<select name="wp-worthy-filter-site">',
              '<option value="-1">', __ ('Display all sites', 'wp-worthy'), '</option>';
          
          foreach (get_sites (array ('site__in' => $usedSites)) as $filterSite)
            echo '<option value="', (int)$filterSite->id, '"', ($selectedSite == $filterSite->id ? ' selected': ''), '>', esc_html ($filterSite->blogname), '</option>';
          
          echo '</select>';
        }
      }
      
      // Output filter for authors
      $Users = $GLOBALS ['wpdb']->get_results (
        'SELECT ' .
          'm.`userid`, ' .
          'u.`display_name`, ' .
          'u.`user_login` ' .
        'FROM ' .
          '`' . $this->Parent->getTablename ('worthy_markers', 0)  . '` m, ' .
          '`' . $this->Parent->getTablename ('users')  . '` u ' .
        'WHERE m.`userid`=u.`ID` ' .
        'GROUP BY m.`userid`'
      );
      
      if (count ($Users) > 1) {
        $uid = (isset ($_REQUEST ['worthy-filter-author']) ? intval ($_REQUEST ['worthy-filter-author']) : -1);
        
        echo
          '<select name="worthy-filter-author">',
            '<option value="-1">', __ ('Display all authors', 'wp-worthy'), '</option>';
        
        foreach ($Users as $User)
          echo '<option value="', esc_attr ($User->userid), '"', ($uid == $User->userid ? ' selected="1"' : ''), '>', esc_html ($User->display_name), ' (', esc_html ($User->user_login), ')</option>';
        
        echo '</select>';
      }
      
      if (wp_worthy::singleton ()->hasPremium ())
        echo
          '<select name="wp-worthy-filter-marker">',
            '<option value="-1">', __ ('Display all marker-stati', 'wp-worthy'), '</option>',
            '<option value="null"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == 'null') ? ' selected="1"' : ''), '>', __ ('not synced', 'wp-worthy'), '</option>',
            '<option value="0"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == '0') ? ' selected="1"' : ''), '>', __ ('not counted', 'wp-worthy'), '</option>',
            '<option value="1"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == '1') ? ' selected="1"' : ''), '>', __ ('not qualified', 'wp-worthy'), '</option>',
            '<option value="2"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == '2') ? ' selected="1"' : ''), '>', __ ('partial qualified', 'wp-worthy'), '</option>',
            '<option value="3"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == '3') ? ' selected="1"' : ''), '>', __ ('qualified', 'wp-worthy'), '</option>',
            '<option value="4"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == '4') ? ' selected="1"' : ''), '>', __ ('reported', 'wp-worthy'), '</option>',
            '<option value="sr"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == 'sr') ? ' selected="1"' : ''), '>', __ ('reportable', 'wp-worthy'), '</option>',
          '</select>';
      
      echo
          '<select name="wp-worthy-filter-ignored">',
            '<option value="1"', (isset ($_REQUEST ['wp-worthy-filter-ignored']) && ($_REQUEST ['wp-worthy-filter-ignored'] == '1') ? ' selected="1"' : ''), '>', __ ('Display all markers that are not ignored', 'wp-worthy'), '</option>',
            '<option value="0"', (isset ($_REQUEST ['wp-worthy-filter-ignored']) && ($_REQUEST ['wp-worthy-filter-ignored'] == '0') ? ' selected="1"' : ''), '>', __ ('Display all markers', 'wp-worthy'), '</option>',
            '<option value="2"', (isset ($_REQUEST ['wp-worthy-filter-ignored']) && ($_REQUEST ['wp-worthy-filter-ignored'] == '2') ? ' selected="1"' : ''), '>', __ ('All Markers with posts assigned', 'wp-worthy'), '</option>',
            '<option value="3"', (isset ($_REQUEST ['wp-worthy-filter-ignored']) && ($_REQUEST ['wp-worthy-filter-ignored'] == '3') ? ' selected="1"' : ''), '>', __ ('Markers with posts assigned that are not ignored', 'wp-worthy'), '</option>',
            '<option value="4"', (isset ($_REQUEST ['wp-worthy-filter-ignored']) && ($_REQUEST ['wp-worthy-filter-ignored'] == '4') ? ' selected="1"' : ''), '>', __ ('Markers with posts assigned that are ignored', 'wp-worthy'), '</option>',
          '</select>',
          '<button type="submit" class="button action" name="filter_action" value="1">', __ ('Filter'), '</button>';
      
      echo '</div>';
    }
    // }}}
    
    // {{{ prepare_items
    /**
     * Preload all items displayed on this table
     * 
     * @access public
     * @return void
     **/
    public function prepare_items () {
      $per_page = $this->get_items_per_page ('wp_worthy_markers_per_page');
      $page = $this->get_pagenum ();
      
      if (!isset ($_REQUEST ['orderby']) ||
          !($sortField = sanitize_key ($_REQUEST ['orderby'])) ||
          !in_array ($sortField, array_keys ($this->get_sortable_columns ())))
        $sortField = 'ID';
      
      if (!isset ($_REQUEST ['order']) ||
          !($sortOrder = strtoupper (sanitize_key ($_REQUEST ['order']))) ||
          !in_array ($sortOrder, array ('ASC', 'DESC')))
        $sortOrder = 'DESC';
      
      if (!($sortClause = sanitize_sql_orderby ($sortField . ' ' . $sortOrder)))
        $sortClause = 'ID DESC';
      
      unset ($sortField, $sortOrder);
      
      // Prepare source-tables/-fields
      $tableCondition =
        '`' . $this->Parent->getTablename ('worthy_markers', 0)  . '` wm ' .
          'LEFT JOIN `' . $this->Parent->getTablename ('users') . '` u ON (wm.userid=u.ID) ';
      
      $postTitleField = '(CASE wm.`siteId` ';
      $postAuthorField = '(CASE wm.`siteId` ';
      $postLengthField = 'CONVERT((CASE wm.`siteId` ';
      $postIgnoredField = '(CASE wm.`siteId` ';
      
      if (isset ($_REQUEST ['wp-worthy-filter-site']) && ($_REQUEST ['wp-worthy-filter-site'] > 0)) {
        $siteIds = [ (int)$_REQUEST ['wp-worthy-filter-site'] ];
        $queryWhere .= ' AND (wm.`siteId`=' . (int)$_REQUEST ['wp-worthy-filter-site'] . ')';
      } else
        $siteIds = $this->Parent->getSiteIDs ();
      
      foreach ($siteIds as $siteId) {
        if (!is_user_member_of_blog (null, $siteId))
          continue;
        
        $tableCondition .=
          'LEFT JOIN `' . $this->Parent->getTablename ('posts', $siteId)  . '` p' . $siteId . ' ON (wm.`siteId`="' . $siteId . '" AND wm.`postid`=p' . $siteId . '.`ID`) ' .
          'LEFT JOIN `' . $this->Parent->getTablename ('postmeta', $siteId) . '` pm' . $siteId . ' ON (wm.`siteId`="' . $siteId . '" AND wm.`postid`=pm' . $siteId . '.`post_id` AND pm' . $siteId . '.`meta_key`="' . wp_worthy::META_LENGTH . '") ' .
          'LEFT JOIN `' . $this->Parent->getTablename ('postmeta', $siteId) . '` pmi' . $siteId . ' ON (wm.`siteId`="' . $siteId . '" AND wm.`postid`=pmi' . $siteId . '.`post_id` AND pmi' . $siteId . '.`meta_key`="worthy_ignore") ';
        
        $postTitleField .= 'WHEN ' . $siteId . ' THEN p' . $siteId . '.`post_title` ';
        $postAuthorField .= 'WHEN ' . $siteId . ' THEN p' . $siteId . '.`post_author` ';
        $postLengthField .= 'WHEN ' . $siteId . ' THEN pm' . $siteId . '.`meta_value` ';
        $postIgnoredField .= 'WHEN ' . $siteId . ' THEN pmi' . $siteId . '.`meta_value` ';
      }
      
      $postTitleField .= 'ELSE NULL END)';
      $postAuthorField .= 'ELSE NULL END)';
      $postLengthField .= 'ELSE NULL END), UNSIGNED INTEGER)';
      $postIgnoredField .= 'ELSE NULL END)';
      
      // Prepare query-conditions
      $queryWhere = 'WHERE 1=1';
      
      if (isset ($_REQUEST ['displayMarkers']))
        $queryWhere .= ' AND wm.`id` IN (' . implode (',', array_map ('intval', explode (',', $_REQUEST ['displayMarkers']))) . ')';
      
      if (isset ($_REQUEST ['status_since']) && is_numeric ($_REQUEST ['status_since']))
        $queryWhere .= ' AND wm.`status_date`>' . intval ($_REQUEST ['status_since']);
      
      if (isset ($_REQUEST ['worthy-filter-author']) && ($_REQUEST ['worthy-filter-author'] >= 0))
        $queryWhere .= ' AND wm.`userid`="' . intval ($_REQUEST ['worthy-filter-author']) . '"';
      
      if (isset ($_REQUEST ['wp-worthy-filter-marker'])) {
        if ($_REQUEST ['wp-worthy-filter-marker'] === 'null')
          $queryWhere .= ' AND (wm.`status` IS NULL)';
        elseif ($_REQUEST ['wp-worthy-filter-marker'] === 'sr')
          $queryWhere .= ' AND wm.`reportable`=1';
        elseif (
          ($_REQUEST ['wp-worthy-filter-marker'] > 0) ||
          ($_REQUEST ['wp-worthy-filter-marker'] === '0')
        )
          $queryWhere .= ' AND (wm.`status`="' . intval ($_REQUEST ['wp-worthy-filter-marker']) . '")';
      }
      
      // Show markers without ignored posts by default
      if (!isset ($_REQUEST ['wp-worthy-filter-ignored']))
        $_REQUEST ['wp-worthy-filter-ignored'] = 1;
      
      // Apply filter for ignored posts
      if ($_REQUEST ['wp-worthy-filter-ignored'] > 0) {
        // Make sure there is a post-title if filter demands an assigned post
        if ($_REQUEST ['wp-worthy-filter-ignored'] > 1)
          $queryWhere .= ' AND NOT (' . $postTitleField . ' IS NULL)';
        
        // Honor ignore-status of assigned post
        if ($_REQUEST ['wp-worthy-filter-ignored'] != 2) {
          if ($_REQUEST ['wp-worthy-filter-ignored'] != 4)
            $queryWhere .= ' AND (' . $postIgnoredField . ' IS NULL)';
          else
            $queryWhere .= ' AND (' . $postIgnoredField . '="1")';
        }
      }
      
      if (isset ($_REQUEST ['s']) && (strlen ($searchTerm = sanitize_text_field ($_REQUEST ['s'])) > 0))
        $queryWhere .= $GLOBALS ['wpdb']->prepare (
          ' AND (wm.`private` LIKE %s OR wm.`public` LIKE %s)',
          '%' . $GLOBALS ['wpdb']->esc_like ($searchTerm) . '%',
          '%' . $GLOBALS ['wpdb']->esc_like ($searchTerm) . '%'
        );
      
      // Retrive all records for current page
      $listQuery =
        'SELECT ' .
          '`wm`.*, ' .
          '`wm`.`siteid` AS `siteId`, ' .
          '`u`.`display_name` AS author, ' .
          $postTitleField . ' AS `post_title`, ' .
          $postAuthorField . ' AS `post_author`, ' .
          $postLengthField . ' AS `postlength`, ' .
          $postIgnoredField . ' AS `worthy_ignored` ' .
        'FROM ' . $tableCondition .
        $queryWhere . ' ' .
        'GROUP BY `wm`.`ID` ' .
        'ORDER BY ' . $sortClause . ' ' .
        'LIMIT ' . (int)(($page - 1) * $per_page) . ',' . (int)$per_page;
      
      $this->items = $GLOBALS ['wpdb']->get_results ($listQuery);
      
      // Check total number of matching records
      if (count ($this->items) >= $per_page)
        $recordCount = $GLOBALS ['wpdb']->get_var (
          'SELECT COUNT(DISTINCT `wm`.`ID`) ' .
          'FROM ' . $tableCondition .
          $queryWhere
        );
      else
        $recordCount = count ($this->items) + ($page - 1) * $per_page;
      
      $this->set_pagination_args ([
        'total_items' => $recordCount,
        'per_page' => $per_page,
        'total_pages' => ceil ($recordCount / $per_page),
      ]);
    }
    // }}}
  }
  
  add_filter ('set-screen-option', function ($status, $option, $value) {
    if ($option == 'wp_worthy_markers_per_page')
      return $value;
    
    return $status;
  }, 10, 3);
  
  add_filter ('default_hidden_columns', function ($hidden, $screen) {
    if ($screen != 'worthy_page_wp_worthy-markers')
      return $hidden;
    
    $hidden [] = 'url';
    
    return $hidden;
  }, 10, 2);

?>