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
  
  class wp_worthy_table_posts extends WP_List_Table {
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
        'singular' => 'post',
        'plural' => 'posts',
        'ajax' => false,
      ));
      
      if (isset ($_REQUEST ['wp-worthy-filter-length']) && ($_REQUEST ['wp-worthy-filter-length'] == 0) &&
          !isset ($_REQUEST ['orderby'])) {
        $_GET ['orderby'] = $_REQUEST ['orderby'] = 'characters';
        $_GET ['order'] = $_REQUEST ['order'] = 'desc';
      }
      
      $this->Parent = $Parent;
    }
    // }}}
    
    // {{{ getParent
    /**
     * Retrive the parented worthy-instance
     * 
     * @access private
     * @return wp_worthy
     **/
    private function getParent () {
      if ($this->Parent)
        return $this->Parent;
      
      return wp_worthy::singleton ();
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
        'label' => __ ('Posts', 'wp-worthy'),
        'default' => 20,
        'option' => 'wp_worthy_posts_per_page'
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
      $defaultColumns = [
        'cb' => '<input type="checkbox" />',
        'siteId' => __ ('Site', 'wp-worthy'),
        'title' => __ ('Title'),
        'author' => __ ('Author'),
        'categories' => __ (get_taxonomy ('category')->labels->name),
        'post_tag' => __ (get_taxonomy ('post_tag')->labels->name),
        'date' => __ ('Date'),
        'marker' => __ ('Marker', 'wp-worthy'),
        'size' => __ ('Total Size', 'wp-worthy'),
        'characters' => __ ('Relevant Characters', 'wp-worthy'),
        'status' => __ ('Status', 'wp-worthy'),
      ];
      
      if (!is_multisite () || !is_network_admin ())
        unset ($defaultColumns ['siteId']);
      
      return $defaultColumns;
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
      return array (
        'siteId' => 'siteId',
        'title' => 'post_title',
        'author' => 'author',
        'date' => 'post_date',
        'marker' => 'private',
        'size' => 'size',
        'characters' => 'characters',
      );
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
      if (is_object ($item) && isset ($item->Public) && (strlen ($item->Public) > 0))
        return '&nbsp;';
      
      return '<input id="cb-select-postid-' . (int)$item->siteId . '-' . (int)$item->ID . '" type="checkbox" name="post[]" value="' . (int)$item->siteId . '/' . (int)$item->ID . '" />';
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
      
      foreach (get_sites (array ('site__in' => [ $pixelRow->siteId ])) as $site)
        if (is_user_member_of_blog (null, $site->id)) {
          switch_to_blog ($site->id);
          
          $siteName = '<a href="' . esc_attr (admin_url ()) . '">' . esc_html ($site->blogname) . '</a>';
          
          restore_current_blog ();
        }
      
      return $siteName;
    }
    // }}}
    
    // {{{ column_title
    /**
     * Retrive content for the title-column
     * 
     * @param object $item
     * 
     * @access public
     * @return string
     **/
    public function column_title ($currentPost) {
      if (!is_object ($Parent = $this->getParent ()))
        return esc_html ($currentPost->post_title);
      
      if (
        !is_object ($currentPost) ||
        (isset ($currentPost->Public) && (strlen ($currentPost->Public) > 0)) ||
        (wp_worthy_post::fromObject ($currentPost)->getLength () < wp_worthy::MIN_LENGTH)
      )
        return $Parent->getPostAdminLink ($currentPost->ID, $currentPost->siteId);
      
      return '<strong>' . $Parent->getPostAdminLink ($currentPost->ID, $currentPost->siteId) . '</strong>';
    }
    // }}}
    
    // {{{ column_author
    /**
     * Retrive contents of author-column
     * 
     * @param object $item
     * 
     * @access public
     * @return string
     **/
    public function column_author ($item) {
      return
        esc_html ($item->author) . '<br />' .
        ($item->userId && (strlen ($vgWortUser = get_user_meta ($item->userId, 'worthy_premium_username', true)) > 0) ? '<nobr><strong>VG WORT:</strong> ' . esc_html ($vgWortUser) . '</nobr>' : '');
    }
    // }}}
    
    // {{{ column_categories
    /**
     * Retrive all categories for a given post
     * 
     * @param object $item
     * 
     * @access public
     * @return string
     **/
    public function column_categories ($item) {
      if (!is_object ($Parent = $this->getParent ()))
        return;
      
      $items = $GLOBALS ['wpdb']->get_results (
        $GLOBALS ['wpdb']->prepare (
          'SELECT t.`name` ' .
          'FROM ' .
            '`' . $Parent->getTablename ('terms', $item->siteId) . '` t, ' .
            '`' . $Parent->getTablename ('term_taxonomy', $item->siteId) . '` tt, ' .
            '`' . $Parent->getTablename ('term_relationships', $item->siteId) . '` tr ' .
          'WHERE ' .
            't.`term_id`=tt.`term_id` AND ' .
            'tt.`term_taxonomy_id`=tr.`term_taxonomy_id` AND ' .
            'tr.`object_id`=%d AND ' .
            'tt.`taxonomy`="category"',
          $item->ID
        )
      );
      
      if (count ($items) == 0)
        return '&#8212;';
      
      foreach ($items as $k=>$item)
        $items [$k] = esc_html ($item->name);
      
      return implode (__ (', '), $items);
    }
    // }}}
    
    // {{{ column_post_tag
    /**
     * Retrive all tags for a given post
     * 
     * @param object $item
     * 
     * @access public
     * @return string
     **/
    public function column_post_tag ($item) {
      if (!is_object ($Parent = $this->getParent ()))
        return;
      
      $items = $GLOBALS ['wpdb']->get_results (
        $GLOBALS ['wpdb']->prepare (
          'SELECT t.`name` ' .
          'FROM ' .
            '`' . $Parent->getTablename ('terms', $item->siteId) . '` t, ' .
            '`' . $Parent->getTablename ('term_taxonomy', $item->siteId) . '` tt, ' .
            '`' . $Parent->getTablename ('term_relationships', $item->siteId) . '` tr ' .
          'WHERE ' .
            't.`term_id`=tt.`term_id` AND ' .
            'tt.`term_taxonomy_id`=tr.`term_taxonomy_id` AND ' .
            'tr.`object_id`=%d AND ' .
            'tt.`taxonomy`="post_tag"',
          $item->ID
        )
      );
      
      if (count ($items) == 0)
        return '&#8212;';
     
      foreach ($items as $k=>$item)
        $items [$k] = esc_html ($item->name);
      
      return implode (__ (', '), $items);
    }
    // }}}
    
    // {{{ column_date
    /**
     * Retrive the date of a post
     * 
     * @param object $item
     * 
     * @access public
     * @return string
     **/
    public function column_date ($item) {
      if ($item instanceof wp_worthy_post)
        $postDate = $item->dateCreated;
      else
        $postDate = strtotime ($item->post_date);
      
      if ($postDate == strtotime ('0000-00-00 00:00:00'))
        return __ ('Unpublished');
      
      $timeDiff = time () - $postDate;
      
      if (($timeDiff > 0) && ($timeDiff < DAY_IN_SECONDS))
        $humanTime = sprintf (__ ('%s ago'), human_time_diff ($postDate));
      else
        $humanTime = mysql2date (__ ('Y/m/d'), $postDate);
      
      return '<abbr title="' . date (__ ('Y/m/d g:i:s A'), $postDate) . '">' . apply_filters ('post_date_column_time', $humanTime, $item, 'date', 'list') . '</abbr>';
    }
    // }}}
    
    // {{{ column_marker
    /**
     * Generate marker-column
     * 
     * @param object $item
     * 
     * @access public
     * @return string
     **/
    public function column_marker ($item) {
      if (!$item->public)
        return;
      
      if (isset ($_REQUEST ['wp-worthy-demo'])) {
        if ($item->private)
          $item->private = substr ($item->private, 0, -6) . 'xxxxxx';
        
        if ($item->public)
          $item->public = substr ($item->public, 0, -6) . 'xxxxxx';
      }
      
      return
        '<abbr title="' . __ ('Private Marker', 'wp-worthy') . '">' . __ ('Priv', 'wp-worthy') . '</abbr>: ' . esc_html ($item->private) . '<br />' .
        '<abbr title="' . __ ('Public Marker', 'wp-worthy') . '">' . __ ('Publ', 'wp-worthy') . '</abbr>: ' . esc_html ($item->public) . '<br />' .
        ($item->server ? '<abbr title="' . __ ('Server', 'wp-worthy') . '">' . __ ('Serv', 'wp-worthy') . '</abbr>: ' . esc_html ($item->server) : '');
    }
    // }}}
    
    // {{{ column_size
    /**
     * Retrive the total size of a post
     * 
     * @access public
     * @return string
     **/
    public function column_size ($item) {
      return  sprintf (__ ('%d chars', 'wp-worthy'), strlen ($item->post_content));
    }
    // }}}
    
    // {{{ column_characters
    /**
     * Retrive the number of characters for a post
     * 
     * @param object $currentPost
     * 
     * @access public
     * @return string
     **/
    public function column_characters ($currentPost) : string {
      $thePost = wp_worthy_post::fromObject ($currentPost);
      
      if (
        ($thePost->getLength () < wp_worthy::MIN_LENGTH) &&
        !$thePost->isLyric ()
      )
        return
          sprintf (__ ('%d chars', 'wp-worthy'), $thePost->getLength ()) . '<br />' .
          '<small>(' . sprintf (__ ('%d chars missing', 'wp-worthy'), wp_worthy::MIN_LENGTH - $thePost->getLength ()) . ')</small>';
      
      return sprintf (__ ('%d chars', 'wp-worthy'), $thePost->getLength ());
    }
    // }}}
    
    // {{{ column_status
    /**
     * Retrive the worthy-status of a post
     * 
     * @param object $currentPost
     * 
     * @access public
     * @return string
     **/
    public function column_status ($currentPost, bool $withButtons = true) {
      $worthy = wp_worthy::singleton ();
      
      if (isset ($_REQUEST ['displayPostsForMigration'])) {
        static $inlineIDs = null;
        static $vgwIDs = null;
        static $wpvgIDs = null;
        static $wppvgwIDs = null;
        
        // Check if we already collected IDs
        if (($inlineIDs === null) && $worthy) {
          $inlineIDs = wp_worthy_migration::migrateInline (false, true);
          $vgwIDs = wp_worthy_migration::migrateByMeta ([ 'vgwpixel' ], false, true);
          $wpvgIDs = wp_worthy_migration::migrateByMeta ([ get_option ('wp_vgwortmetaname', 'wp_vgwortmarke') ], false, true);
          $wppvgwIDs = wp_worthy_migration::migrateProsodia (false, true);
        }
        
        return
          '<ul>'.
            (in_array ($currentPost->ID, $inlineIDs) ? '<li>' . __ ('Contains an inlined marker', 'wp-worthy') . '</li>' : '') .
            (in_array ($currentPost->ID, $vgwIDs) ? '<li>' . __ ('Is managed by VGW', 'wp-worthy') . '</li>' : '') .
            (in_array ($currentPost->ID, $wpvgIDs) ? '<li>' . __ ('Is managed by WP VG-Wort', 'wp-worthy') . '</li>' : '') .
            (in_array ($currentPost->ID, $wppvgwIDs) ? '<li>' . __ ('Is managed by Prosodia VGW', 'wp-worthy') . '</li>' : '') .
            (strlen ($currentPost->public) > 0 ? '<li><strong>' . __ ('Already managed by Worthy', 'wp-worthy') . '</strong></li>' : '') .
            '<li><button data-action="wp-worthy-bulk-migrate" data-siteid="' . (int)$currentPost->siteId . '" data-postid="' . (int)$currentPost->ID . '">' . __ ('Migrate post', 'wp-worthy') . '</a></li>' .
          '</ul>';
      }
      
      $postStatus = [];
      $Links = '';
      
      // Sanity-check length of title
      $thePost = wp_worthy_post::fromObject ($currentPost);
      $thePixel = $thePost->getPixel ();
      
      if (
        $thePost->isRelevant () &&
        (strlen ($thePost->getTitle ()) > wp_worthy_post::TITLE_MAX_LENGTH)
      )
        $postStatus [] = '<span class="wp-worthy-' . (strlen ($thePost->getTitle (true)) > wp_worthy_post::TITLE_MAX_LENGTH ? 'warning' : 'notice') . '">' . __ ('Title is too long', 'wp-worthy') . '</span>';
      
      if (!$thePost->isIndexed ())
        $postStatus [] = '<span class="wp-worthy-warning">' . __ ('Not indexed', 'wp-worthy') . '</span>';
      
      // Sanity-check user-ids
      $postUserIDs = $worthy->getUserIDs ($thePost->authorId);
      $validMarker = ($thePixel && $thePixel->userId && in_array ($thePixel->userId, $postUserIDs));
      $ownArticle = in_array ($worthy->getUserID (), $postUserIDs);
      
      if (!$ownArticle)
        $postStatus [] =
          '<span class="wp-worthy-notice" title="' . __ ('You are not the author of this post or assigned to him/her, you cannot assign a marker to this post', 'wp-worthy') . '">' .
            __ ('Foreign User-ID', 'wp-worthy') .
          '</span>';
      
      if ($thePost->hasPixel () && !$validMarker)
        $postStatus [] = '<span class="wp-worthy-warning" title="' . __ ('The author of the post does not match the owner of the marker', 'wp-worthy') . '">' . __ ('User-ID conflict', 'wp-worthy') . '</span>';
      
      if (
        ($thePost->hasPixel () && !$validMarker) ||
        get_option ('wp-worthy-enable-burn', false)
      )
        $Links .= '<li><button data-action="wp-worthy-bulk-burn-post-pixel" data-siteid="' . (int)$thePost->siteId . '" data-postid="' . (int)$thePost->ID . '" class="wp-worthy-danger">' . __ ('Burn this pixel', 'wp-worthy') . '</button></li>';
      
      // Check artickle-status
      if (!in_array ($thePost->postType, $worthy->getUserPostTypes ()))
        $postStatus [] = '<span class="wp-worthy-notice" title="' . __ ('This post is of a type that is not handled by worthy', 'wp-worthy') . '">' . __ ('Filtered post-type', 'wp-worthy') . '</span>';
      
      if (
        ($thePost->postStatus == 'future') ||
        ($thePost->postStatus == 'draft')
      )
        $postStatus [] = '<span class="wp-worthy-notice" title="' . __ ('This post is not published yet', 'wp-worthy') . '">' . __ ('Not published', 'wp-worthy') . '</span>';
      elseif ($thePost->postStatus != 'publish')
        $postStatus [] = '<span class="wp-worthy-notice" title="' . __ ('This post is not published', 'wp-worthy') . '">' . __ ('Invalid status', 'wp-worthy') . '</span>';
      
      if ($thePost->isRelevant () == $thePost->hasPixel ()) {
        array_unshift ($postStatus, '<span class="' . ($thePost->isRelevant () ? 'wp-worthy-relevant wp-worthy-marker' : 'wp-worthy-neutral') . '">OK' . ($thePost->isIgnored () ? ' (' . __ ('Ignored', 'wp-worthy') . ')' : '') . '</span>');
        
        if (
          wp_worthy::singleton ()->hasPremium () &&
          $thePost->isRelevant ()
        ) {
          static $Map = array (
             0 => '', 
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
          
          array_push (
            $postStatus,
            '<span class="wp-worthy-status-' . (int)$thePixel->status . '" title="' . esc_attr (__ ($tooltipMap [$thePixel->status === null ? -1 : (int)$thePixel->status], 'wp-worthy')) . '">' .
              __ ($Map [(int)$thePixel->status], 'wp-worthy') .
            '</span>'
          );
          
          if ($thePixel->private && ($thePixel->status < 4)) {
            $postLength = $thePost->getLength ();
            
            $Links .=
              (get_option ('wp-worthy-enable-webarea', false) ? '<li><button data-action="wp-worthy-premium-create-webareas" data-siteid="' . (int)$thePost->siteId . '" data-postid="' . (int)$thePost->ID . '">' . __ ('Create webarea', 'wp-worthy') . '</button></li>' : '') .
              ((($thePixel->status == 3) && ($postLength >= wp_worthy::MIN_LENGTH)) || (($thePixel->status == 2) && ($postLength >= wp_worthy::EXTRA_LENGTH)) ?
                '<li><button data-action="wp-worthy-premium-report-posts-preview" data-siteid="' . (int)$thePost->siteId . '" data-postid="' . (int)$thePost->ID . '">' . __ ('Preview report for VG WORT', 'wp-worthy') . '</a></li>' . 
                (strlen ($thePost->getTitle (true)) > wp_worthy_post::TITLE_MAX_LENGTH ? '' :
                '<li><button data-action="wp-worthy-premium-report-posts" data-siteid="' . (int)$thePost->siteId . '" data-postid="' . (int)$thePost->ID . '">' . __ ('Report directly to VG WORT', 'wp-worthy') . '</button></li>') : ''
              );
          } else
            $Links = '';
        }
      } elseif ($thePost->isRelevant () && !$thePost->isIgnored ()) {
        $postStatus [] = '<span class="wp-worthy-relevant worthy-nomarker wp-worthy-warning">' . __ ('Needs marker', 'wp-worthy') . '</span>';
        $Links .= '<li><button data-action="wp-worthy-bulk-assign" data-siteid="' . (int)$thePost->siteId . '" data-postid="' . (int)$thePost->ID . '">' . __ ('Assign marker', 'wp-worthy') . '</button></li>';
      } else {
        array_unshift (
          $postStatus,
          '<span class="wp-worthy-neutral wp-worthy-marker">OK</span>'
        );
        
        array_push (
          $postStatus,
          '<span class="wp-worthy-notice">' . __ (($thePost->isIgnored () ? 'Ignored' : 'Marker assigned without need'), 'wp-worthy') . '</span>'
        );
      }
      
      if (!$thePost->isIgnored ())
        $Links .=
          '<li>' .
            '<button data-action="wp-worthy-bulk-ignore" data-siteid="' . (int)$thePost->siteId . '" data-postid="' . (int)$thePost->ID . '" class="wp-worthy-danger">' .
              __ ('Ignore this post', 'wp-worthy') .
            '</button>' .
          '</li>';
      
      // Remove links from foreign articles
      if (!$ownArticle)
        $Links = '';
      
      // Append debug-options
      if (!$thePost->isIndexed () || (defined ('WP_DEBUG') && WP_DEBUG))
        $Links .= '<li><button data-action="wp-worthy-reindex" data-siteid="' . (int)$thePost->siteId . '" data-postid="' . (int)$thePost->ID . '" class="wp-worthy-danger">' . __ ('Reindex this post', 'wp-worthy') . '</button></li>';
      
      return
        implode ('', $postStatus) .
        ($withButtons && (strlen ($Links) > 0) ? '<ul>' . $Links . '</ul>' : '');
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
      $Actions = [];
      
      if (isset ($_REQUEST ['displayPostsForMigration']))
        $Actions ['wp-worthy-bulk-migrate'] = __ ('Migrate posts', 'wp-worthy');
      
      $Actions ['wp-worthy-bulk-assign'] = __ ('Assign markers', 'wp-worthy');
      $Actions ['wp-worthy-bulk-ignore'] = __ ('Ignore posts', 'wp-worthy');
      
      if (wp_worthy::singleton ()->hasPremium ()) {
        $Actions ['wp-worthy-premium-report-posts-preview'] = __ ('Report with preview', 'wp-worthy');
        $Actions ['wp-worthy-premium-report-posts'] = __ ('Report without preview', 'wp-worthy');
        
        if (get_option ('wp-worthy-enable-webarea', false))
          $Actions ['wp-worthy-premium-create-webareas'] = __ ('Create webareas', 'wp-worthy');
      }
      
      $Actions ['wp-worthy-reindex'] = __ ('Reindex posts', 'wp-worthy');
      $Actions ['wp-worthy-bulk-burn-post-pixel'] = __ ('Burn pixels', 'wp-worthy');
      
      return $Actions;
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
      $per_page = $this->get_items_per_page ('wp_worthy_posts_per_page');
      $page = $this->get_pagenum ();
      $Parent = $this->getParent ();
      
      // Handle sorting of items
      if (
        !isset ($_REQUEST ['orderby']) ||
        !($sortField = sanitize_key ($_REQUEST ['orderby'])) ||
        !in_array ($sortField, $this->get_sortable_columns ())
      )
        $sortField = 'ID';
      
      if (
        !isset ($_REQUEST ['order']) ||
        !($sortOrder = strtoupper (sanitize_key ($_REQUEST ['order']))) ||
        !in_array ($sortOrder, array ('ASC', 'DESC'))
      )
        $sortOrder = 'DESC';
      
      if (!($sortClause = sanitize_sql_orderby ($sortField . ' ' . $sortOrder)))
        $sortClause = 'ID DESC';
      
      // Override the clause if a function-call is needed
      if ($sortField == 'characters')
        $sortClause = 'post_length ' . $sortOrder;
      elseif ($sortField == 'size')
        $sortClause = 'LENGTH(post_content) ' . $sortOrder;
      
      unset ($sortField, $sortOrder);
      
      // Handle filters
      $Where = '';
      
      if (isset ($_REQUEST ['worthy-filter-author']) && ((int)$_REQUEST ['worthy-filter-author'] >= 0))
        $Where .= ' AND post_author="' . intval ($_REQUEST ['worthy-filter-author']) . '"';
      
      if (isset ($_REQUEST ['m']) && ((int)$_REQUEST ['m'] > 99999)) {
        $yearMonth = (int)$_REQUEST ['m'];
        
        $Where .= $GLOBALS ['wpdb']->prepare (' AND (YEAR(post_date)=%d AND MONTH(post_date)=%d)', floor ($yearMonth / 100), $yearMonth % 100);
      }
      
      if ($haveCategory = (isset ($_REQUEST ['cat']) && ((int)$_REQUEST ['cat'] != 0)))
        $Where .= $GLOBALS ['wpdb']->prepare (' AND tt.term_id=%d ', (int)$_REQUEST ['cat']);
      
      if (isset ($_REQUEST ['wp-worthy-filter-length']) && ((int)$_REQUEST ['wp-worthy-filter-length'] >= 0))
        $Where .= ' AND (pm.meta_value' . ((int)$_REQUEST ['wp-worthy-filter-length'] == 0 ? '<' : '>=') . ((int)$_REQUEST ['wp-worthy-filter-length'] == 2 ? strval (wp_worthy::EXTRA_LENGTH) : strval (wp_worthy::MIN_LENGTH)) . ')';
      
      if (isset ($_REQUEST ['wp-worthy-filter-post-type']) && $_REQUEST ['wp-worthy-filter-post-type'])
        $displayPostTypes = [ $GLOBALS ['wpdb']->prepare ('%s', $_REQUEST ['wp-worthy-filter-post-type']) ];
      else
        $displayPostTypes = array_map (
          function ($postType) {
            return $GLOBALS ['wpdb']->prepare ('%s', $postType);
          },
          $Parent->getUserPostTypes ()
        );
      
      $displayPostStatus = [ 'publish', 'future' ];
      
      if (isset ($_REQUEST ['wp-worthy-filter-marker']))
        switch ($_REQUEST ['wp-worthy-filter-marker']) {
          case '0':
          case '1':
            $Where .= ' AND ' . ((int)$_REQUEST ['wp-worthy-filter-marker'] % 2 == 1 ? 'NOT ' : '') . '(public IS NULL)';
            
            break;
          case '2':
            $Where .= ' AND `pmi`.`meta_value`="1"';
            
            break;
          case 's0':
          case 's1':
          case 's2':
          case 's3':
          case 's4':
            $Where .= ' AND (status="' . intval ($_REQUEST ['wp-worthy-filter-marker'][1]) . '")'; 
            
            break;
          case 'sr':
            $Where .= ' AND ' .
              '(' .
                '(' .
                  '(`status`=3 AND (`pm`.`meta_value`>=' . wp_worthy::MIN_LENGTH . ' OR `l`.`meta_value`="1")) OR ' .
                  '(`status`=2 AND (`pm`.`meta_value`>=' . wp_worthy::EXTRA_LENGTH . '))' .
                ') AND ' .
                '(`pmi`.`meta_value`="0" OR `pmi`.`meta_value` IS NULL)' .
              ')';
            
            break;
        }
      
      if (isset ($_REQUEST ['s']) && (strlen ($searchTerm = sanitize_text_field ($_REQUEST ['s'])) > 0))
        $Where .= $GLOBALS ['wpdb']->prepare (
          ' AND (private LIKE %s OR public LIKE %s OR post_title LIKE %s)',
          '%' . $GLOBALS ['wpdb']->esc_like ($searchTerm) . '%',
          '%' . $GLOBALS ['wpdb']->esc_like ($searchTerm) . '%',
          '%' . $GLOBALS ['wpdb']->esc_like ($searchTerm) . '%'
        );
      
      // Prepare the query
      $listQuery = '';
      
      if (!is_network_admin ())
        $siteIds = [ get_current_blog_id () ];
      elseif (isset ($_REQUEST ['wp-worthy-filter-site']) && ($_REQUEST ['wp-worthy-filter-site'] > 0))
        $siteIds = [ (int)$_REQUEST ['wp-worthy-filter-site'] ];
      else
        $siteIds = $this->Parent->getSiteIDs ();
      
      if (isset ($_REQUEST ['displayPostsForMigration']))
        $postIDs = $Parent->unpackPostIDs ($_REQUEST ['displayPostsForMigration']);
      else
        $postIDs = null;
      
      foreach ($siteIds as $siteId) {
        if (!is_user_member_of_blog (null, $siteId))
          continue;
        
        if ($postIDs !== null) {
          $localPostIDs = array ();
          
          foreach ($postIDs as $idIndex=>$postID)
            if ($postID ['siteid'] == $siteId) {
              $localPostIDs [] = $postID ['postid'];
              unset ($postIDs [$idIndex]);
            }
          
          if (count ($localPostIDs) == 0)
            continue;
        } else
          $localPostIDs = null;
        
        $listQuery .=
          '(' .
            'SELECT ' .
              '"' . $siteId . '" AS `siteId`, ' .
              '`p`.*, ' .
              '`wm`.`id` AS `pixelId`, ' .
              'wm.`public`, ' .
              'wm.`private`, ' .
              'wm.`status`, ' .
              'wm.`server`, ' .
              'wm.`userId`, ' .
              'CONVERT(pm.meta_value, UNSIGNED INTEGER) AS `post_length`, ' .
              'u.`display_name` AS `author`, ' .
              '`pmi`.`meta_value` AS `post_is_ignored`, ' .
              'l.`meta_value` AS `is_lyric` ' .
            'FROM ' .
              '`' . $Parent->getTablename ('posts', $siteId)  . '` p ' .
                'LEFT JOIN `' . $Parent->getTablename ('worthy_markers', 0) . '` wm ON (p.`ID`=wm.`postid` AND wm.`siteId`="' . $siteId . '") ' .
                'LEFT JOIN `' . $Parent->getTablename ('users') . '` u ON (p.`post_author`=u.`ID`) ' .
                'LEFT JOIN `' . $Parent->getTablename ('postmeta', $siteId) . '` `pmi` ON (p.`ID`=`pmi`.`post_id` AND `pmi`.`meta_key`="worthy_ignore") ' .
                'LEFT JOIN `' . $Parent->getTablename ('postmeta', $siteId) . '` l ON (p.`ID`=l.`post_id` AND l.`meta_key`="worthy_lyric") ' .
                'LEFT JOIN `' . $Parent->getTablename ('postmeta', $siteId) . '` pm ON (p.ID=pm.post_id AND pm.meta_key="' . wp_worthy::META_LENGTH . '") ' .
                ($haveCategory ? 'LEFT JOIN `' . $Parent->getTablename ('term_relationships', $siteId) . '` tr ON (tr.object_id=p.ID) LEFT JOIN `' . $Parent->getTablename ('term_taxonomy', $siteId) . '` tt ON (tr.term_taxonomy_id=tt.term_taxonomy_id AND tt.taxonomy="category") ' : '').
            'WHERE ' .
              ($displayPostTypes ? 'post_type IN (' . implode (',', $displayPostTypes) . ') ' : '1 ') .
              ($displayPostStatus ? 'AND (post_status IN ("' . implode ('","', $displayPostStatus) . '") OR NOT (wm.`public` IS NULL)) ' : '') .
              ($localPostIDs !== null ? 'AND p.`ID` IN (' . implode (', ', $localPostIDs) . ') ' : '') .
              $Where . ' ' .
            'GROUP BY `siteId`, `p`.`ID`' .
          ') UNION ';
      }
      
      if ($listQuery) {
        $this->items = $GLOBALS ['wpdb']->get_results (
          substr ($listQuery, 0, strrpos ($listQuery, ')') + 1) .
          'ORDER BY ' . $sortClause . ' ' .
          'LIMIT ' . (int)(($page - 1) * $per_page) . ',' . (int)$per_page
        );
        
        $recordCount = $GLOBALS ['wpdb']->get_var ('SELECT COUNT(*) FROM (' . substr ($listQuery, 0, strrpos ($listQuery, ')') + 1) . ') posts');
      } else {
        $this->items = [];
        $recordCount = 0;
      }
      
      // Setup this table
      $this->set_pagination_args (array (
        'total_items' => $recordCount,
        'per_page' => $per_page,
        'total_pages' => ceil ($recordCount / $per_page),
      ));
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
      
      $Parent = $this->getParent ();
      
      echo '<div class="alignleft actions">';
      
      // Output filter for sites
      if (is_multisite () && is_network_admin ()) {
        $selectedSite = (isset ($_REQUEST ['wp-worthy-filter-site']) ? (int)$_REQUEST ['wp-worthy-filter-site'] : 0);
        
        echo
          '<select name="wp-worthy-filter-site">',
            '<option value="-1">', __ ('Display all sites', 'wp-worthy'), '</option>';
        
        foreach (get_sites () as $filterSite)
          if (is_user_member_of_blog (null, $filterSite->id))
            echo '<option value="', (int)$filterSite->id, '"', ($selectedSite == $filterSite->id ? ' selected': ''), '>', esc_html ($filterSite->blogname), '</option>';
        
        echo '</select>';
      }
      
      if (is_multisite () && is_network_admin ()) {
        $userList = array ();
        
        foreach (get_sites () as $site)
          if (is_user_member_of_blog (null, $site->id)) {
            $filterUsers = new WP_User_Query (
              array (
                'has_published_posts' => true,
                'blog_id' => $site->id,
                'exclude' => array_keys ($userList),
              )
            );
            
            foreach ($filterUsers->results as $filterUser)
              $userList [$filterUser->ID] = $filterUser;
          }
      } else {
        $filterUsers = new WP_User_Query (
          array (
            'has_published_posts' => true,
          )
        );
        
        $userList = $filterUsers->results;
      }
      
      if (count ($userList)) {
        $uid = (isset ($_REQUEST ['worthy-filter-author']) ? intval ($_REQUEST ['worthy-filter-author']) : -1);
        
        echo
          '<select name="worthy-filter-author">', 
            '<option value="-1">', __ ('Display all authors', 'wp-worthy'), '</option>';
        
        foreach ($userList as $User)
          echo '<option value="', esc_attr ($User->ID), '"', ($uid == $User->ID ? ' selected="1"' : ''), '>', esc_html ($User->display_name), ' (', esc_html ($User->user_login), ')</option>';
        
        echo '</select>';
      }
      
      // Display post-type-filter
      if (count ($Parent->getUserPostTypes ()) > 1) {
        echo
          '<select name="wp-worthy-filter-post-type">',
            '<option value="">', __ ('Display all post-types', 'wp-worthy'), '</option>';
        
        foreach ($Parent->getUserPostTypes () as $postType)
          if (is_object ($postType = get_post_type_object ($postType)))
            echo '<option value="', esc_attr ($postType->name), '"', (isset ($_REQUEST ['wp-worthy-filter-post-type']) && ($_REQUEST ['wp-worthy-filter-post-type'] == $postType->name) ? ' selected="1"' : ''), '>', esc_html ($postType->labels->name), '</option>';
        
        echo
          '</select>';
      }
      
      // Display month-filter
      if (!is_network_admin ())
        $this->months_dropdown ('post');
      
      // Display category-filter
      if (!is_network_admin ())
        wp_dropdown_categories (
          array (
            'show_option_all' => __ ('View all categories'),
            'hide_empty' => 0,
            'hierarchical' => 1,
            'show_count' => 0,  
            'orderby' => 'name',
            'selected' => (isset ($_REQUEST ['cat']) ? intval ($_REQUEST ['cat']) : null),
          )
        );
      
      // Display worthy-filter
      if (isset ($_REQUEST ['wp-worthy-filter-length']) && ((int)$_REQUEST ['wp-worthy-filter-length'] < 0))
        unset ($_REQUEST ['wp-worthy-filter-length']);
      
      if (isset ($_REQUEST ['wp-worthy-filter-marker']) && ((int)$_REQUEST ['wp-worthy-filter-marker'] < 0))
        unset ($_REQUEST ['wp-worthy-filter-marker']);
      
      echo
        '<select name="wp-worthy-filter-length">',
          '<option value="-1">', __ ('Display all posts', 'wp-worthy'), '</option>',
          '<option value="0"', (isset ($_REQUEST ['wp-worthy-filter-length']) && ($_REQUEST ['wp-worthy-filter-length'] == '0') ? ' selected="1"' : ''), '>', __ ('Posts that are not long enough', 'wp-worthy'), '</option>',
          '<option value="1"', (isset ($_REQUEST ['wp-worthy-filter-length']) && ($_REQUEST ['wp-worthy-filter-length'] == '1') ? ' selected="1"' : ''), '>', __ ('Posts that are suited for VG WORT', 'wp-worthy'), '</option>',
          '<option value="2"', (isset ($_REQUEST ['wp-worthy-filter-length']) && ($_REQUEST ['wp-worthy-filter-length'] == '2') ? ' selected="1"' : ''), '>', __ ('Posts that are extra long', 'wp-worthy'), '</option>',
        '</select>',
        '<select name="wp-worthy-filter-marker">',
          '<option value="-1">', __ ('Display all posts', 'wp-worthy'), '</option>',
          '<option value="0"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == '0') ? ' selected="1"' : ''), '>', __ ('Posts without marker assigned', 'wp-worthy'), '</option>',
          '<option value="1"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == '1') ? ' selected="1"' : ''), '>', __ ('Posts with marker assigned', 'wp-worthy'), '</option>',
          '<option value="2"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == '2') ? ' selected="1"' : ''), '>', __ ('Ignored posts', 'wp-worthy'), '</option>';
      
      if (wp_worthy::singleton ()->hasPremium ())
        echo
          '<option value="s0"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == 's0') ? ' selected="1"' : ''), '>', __ ('Markers that have not been counted', 'wp-worthy'), '</option>',
          '<option value="s1"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == 's1') ? ' selected="1"' : ''), '>', __ ('Markers that have not qualified yet', 'wp-worthy'), '</option>',
          '<option value="s2"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == 's2') ? ' selected="1"' : ''), '>', __ ('Markers that are partial qualified', 'wp-worthy'), '</option>',
          '<option value="s3"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == 's3') ? ' selected="1"' : ''), '>', __ ('Markers that are qualified', 'wp-worthy'), '</option>',
          '<option value="s4"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == 's4') ? ' selected="1"' : ''), '>', __ ('Markers that were reported', 'wp-worthy'), '</option>',
          '<option value="sr"', (isset ($_REQUEST ['wp-worthy-filter-marker']) && ($_REQUEST ['wp-worthy-filter-marker'] == 'sr') ? ' selected="1"' : ''), '>', __ ('Markers that may be reported', 'wp-worthy'), '</option>';
      
      echo
        '</select>',
        '<button type="submit" class="button action" name="filter_action" value="1">', __ ('Filter'), '</button>';
      
      echo '</div>';
    }
    // }}}
  }
  
  add_filter ('set-screen-option', function ($status, $option, $value) {
    if ($option == 'wp_worthy_posts_per_page')
      return $value;
    
    return $status;
  }, 10, 3);
  
  add_filter ('default_hidden_columns', function ($hidden, $screen) {
    if ($screen != 'worthy_page_wp_worthy-posts')
      return $hidden;
    
    $hidden [] = 'categories';
    $hidden [] = 'post_tag';
    $hidden [] = 'size';
    
    return $hidden;
  }, 10, 2);

?>