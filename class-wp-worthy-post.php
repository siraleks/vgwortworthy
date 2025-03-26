<?php

  class wp_worthy_post {
    /* public */ const TITLE_MAX_LENGTH = 100;
    
    /* Check wheter init() was called before */
    private static $initialized = false;
    
    /* Cached post-instances */
    private static $postCache = [];
    
    /* Wordpress' Instance of this post */
    private $thePost = null;
    
    /* ID of site/blog this post is present on */
    private $siteId = 1;
    
    /* Faked instance of our assigned pixel */
    private $synteticPixel = null;
    
    // {{{ init
    /**
     * Initialize post-related functions
     * 
     * @access public
     * @return void
     **/
    public static function init () {
      // Don't do this twice
      if (self::$initialized)
        return;
      
      // Initialized REST-functions
      add_action (
        'rest_api_init',
        function () {
          register_rest_field (
            wp_worthy::singleton ()->getUserPostTypes (),
            'wp-worthy-pixel',
            [
              'get_callback'    => function ($thePost) {
                // Sanatize $thePost
                if (is_array ($thePost) && isset ($thePost ['id']))
                  $thePost = get_post ($thePost ['id']);
                
                if (!is_object ($thePost))
                  throw new Exception ('Invalid post-argument');
                
                // Create interface to the post
                $worthyPost = new static ($thePost);
                
                // Try to get a pixel for this post
                if (!is_object ($postPixel = $worthyPost->getPixel ()))
                  return [
                    'ignored' => $worthyPost->isIgnored (),
                    'public'  => null,
                    'server'  => null,
                    'url'     => null,
                  ];
                
                // Return public part of pixel
                return [
                  'ignored' => $worthyPost->isIgnored (),
                  'public'  => $postPixel->public,
                  'server'  => $postPixel->server,
                  'url'     => wp_worthy::singleton ()->pixelURL ($postPixel, null, $thePost),
                ];
              },
              'update_callback' => [ __CLASS__, 'restSetMarker' ],
              'schema'          => [ 'type' => 'array' ],
            ]
          );
          
          register_rest_field (
            wp_worthy::singleton ()->getUserPostTypes (),
            'wp-worthy-type',
            [
              'get_callback'    => function ($thePost) {
                // Sanatize $thePost
                if (is_array ($thePost) && isset ($thePost ['id']))
                  $thePost = get_post ($thePost ['id']);
                
                if (!is_object ($thePost))
                  return null;
                
                return (get_post_meta ($thePost->ID, 'worthy_lyric', true) == 1 ? 'lyric' : 'normal');
              },
              'update_callback' => [ __CLASS__, 'restSetPostType' ],
              'schema'          => [ 'type' => 'string' ],
            ]
          );
        }
      );
      
      // Mark this as initialized
      self::$initialized = true;
    }
    // }}}
    
    // {{{ restSetMarker
    /**
     * Set markter-status of a post via REST-API
     * 
     * @param array $newValue
     * @param object $thePost
     * 
     * @access public
     * @return bool
     **/
    public static function restSetMarker ($newValue, $thePost) {
      // Sanatize the new value
      if (is_object ($newValue))
        $newValue = (array)$newValue;
      elseif (!is_array ($newValue))
        return false;
      
      // Create interface to the post
      $worthyPost = new static ($thePost);
      
      // Check wheter to mark the post as ignored
      if ($newValue ['ignored'] === true)
        $worthyPost->isIgnored (true);
      else
        $worthyPost->isIgnored (false);
      
      // Check if we should try to assign a pixel to this post
      if (!$newValue ['public'])
        return true;
      
      // Assign a pixel to the post
      return ($worthyPost->assignPixel () !== null);
    }
    // }}}
    
    // {{{ restSetPostType
    /**
     * Set type of text via REST
     * 
     * @param mixed $newValue
     * @param object $thePost
     * 
     * @access public
     * @return bool
     **/
    public static function restSetPostType ($newValue, $thePost) {
      if ($newValue == 'lyric')
        update_post_meta ($thePost->ID, 'worthy_lyric', 1);
      else
        delete_post_meta ($thePost->ID, 'worthy_lyric', 1);
      
      return true;
    }
    // }}}
    
    // {{{ fromID
    /**
     * Retrive Worthy-Post-Object from given post-id
     * 
     * @param int $postID
     * @param int $siteId (optional)
     * @param bool $filterPostType (optional)
     * 
     * @access public
     * @return wp_worthy_post
     **/
    public static function fromID (int $postID, int $siteId = null, bool $filterPostType = true) /* : wp_worthy_post */ {
      // Make sure site-id is valid
      $currentSiteID = (int)get_current_blog_id ();
      
      if ($siteId === null)
        $siteId = $currentSiteID;
      
      // Check our cache
      if (
        isset (self::$postCache [$siteId]) &&
        isset (self::$postCache [$siteId][$postID])
      )
        return self::$postCache [$siteId][$postID];
      
      // Retrive the post from database
      if (($siteId != $currentSiteID) && is_multisite ())
        switch_to_blog ($siteId);
      
      $thePost = get_post ($postID);
      
      if (($siteId != $currentSiteID) && is_multisite ())
        restore_current_blog ();
      
      if (!$thePost)
        return null;
      
      // Check wheter to filter by post-type
      if ($filterPostType && !in_array ($thePost->post_type, wp_worthy::singleton ()->getUserPostTypes ()))
        return null;
      
      // Create new instance
      return static::fromObject ($thePost, $siteId);
    }
    // }}}
    
    // {{{ fromObject
    /**
     * Retrive Worthy-Post-Object from any given object if possible
     * 
     * @param object $thePost
     * @param int $siteId (optional)
     * @param bool $bypassCache (optional)
     * 
     * @access public
     * @return wp_worthy_post
     **/
    public static function fromObject ($thePost, int $siteId = null, bool $bypassCache = false) /* : wp_worthy_post */ {
      // Just pass our own post-instances
      if ($thePost instanceof wp_worthy_post)
        return $thePost;
      
      // Check our cache
      if ($siteId === null)
        $siteId = (isset ($thePost->siteId) ? $thePost->siteId : get_current_blog_id ());
      
      if (
        !$bypassCache &&
        isset (self::$postCache [$siteId]) &&
        isset (self::$postCache [$siteId][$thePost->ID])
      )
        return self::$postCache [$siteId][$thePost->ID];
      
      // Create new instance
      return new wp_worthy_post ($thePost, $siteId);
    }
    // }}}
    
    // {{{ __construct
    /**
     * Create Worthy-Interface to a Wordpress-post
     * 
     * @param object $thePost
     + @param int $siteId (optional)
     * 
     * @access friendly
     * @return void
     **/
    function __construct ($thePost, int $siteId = null) {
      // Make sure we have a valid site-id
      if ($siteId === null)
        $siteId = get_current_blog_id ();
      
      // Validate $thePost
      if (!is_object ($thePost))
        throw new Exception ('Invalid thePost-parameter');
      
      // Setup ourself
      $this->siteId = $siteId;
      $this->thePost = $thePost;
      
      // Push ourself to cache if neccessary
      if (!isset (self::$postCache [$siteId]))
        self::$postCache [$siteId] = [ $thePost->ID => $this ];
      elseif (!isset (self::$postCache [$siteId][$thePost->ID]))
        self::$postCache [$siteId][$thePost->ID] = $this;
    }
    // }}}
    
    // {{{ __get
    /**
     * Emulate some attributes
     * 
     * @param string $attributeName
     * 
     * @access public
     * @return mixed
     **/
    function __get (string $attributeName) {
      switch ($attributeName) {
        case 'ID':
          return $this->thePost->ID;
        
        case 'siteId':
          return $this->siteId;
        
        case 'dateCreated':
          return strtotime ($this->thePost->post_date);
        
        case 'authorId':
          return $this->thePost->post_author;
        
        case 'postType':
          return $this->thePost->post_type;
        
        case 'postStatus':
          return $this->thePost->post_status;
        
        case 'postPassword':
          return $this->thePost->post_password;
      }
      
      return null;
    }
    // }}}
    
    // {{{ isIgnored
    /**
     * Get/Set wheter to ignore this post in Worthy
     * 
     * @param bool $toggleState (optional)
     * 
     * @access public
     * @return bool TRUE if the post should be ignored
     **/
    public function isIgnored (bool $toggleState = null) : bool {
      // Check for a fast lane
      if (
        ($toggleState === null) &&
        isset ($this->thePost->post_is_ignored) &&
        ($this->thePost->post_is_ignored !== null)
      )
        return ($this->thePost->post_is_ignored == 1);
      
      // Switch to site
      if (is_multisite ())
        switch_to_blog ($this->siteId);
      
      // Check wheter to change the state
      if ($toggleState === true)
        update_post_meta ($this->thePost->ID, 'worthy_ignore', 1);
      elseif ($toggleState === false)
        delete_post_meta ($this->thePost->ID, 'worthy_ignore');  
      
      // Retrive current state
      $metaValue = get_post_meta ($this->thePost->ID, 'worthy_ignore', true);
      
      // Reset site
      if (is_multisite ())
        restore_current_blog ();
      
      // Return current state
      return ($metaValue == 1);
    }
    // }}}
    
    // {{{ isReportable
    /**
     * Check if this post may be reported to VG WORT
     * 
     * @access public
     * @return bool
     **/
    public function isReportable (): bool {
      // Never try to report anything that's ignored
      if ($this->isIgnored ())
        return false;

      // Make sure there is a pixel assigned
      if (!is_object ($postPixel = $this->getPixel ()))
        return false;
      
      if ($postPixel->status == wp_worthy::MARKER_STATUS_PARTIAL)
        return ($this->getLength () >= wp_worthy::EXTRA_LENGTH);
      elseif ($postPixel->status == wp_worthy::MARKER_STATUS_REACHED)
        return ($this->getLength () >= wp_worthy::MIN_LENGTH);
      
      return false;
    }
    // }}}
    
    // {{{ isLyric
    /**
     * Check/Set wheter content is lyric
     * 
     * @param bool $toggleState (optional)
     * 
     * @access public
     * @return bool
     **/
    public function isLyric (bool $toggleState = null) : bool {
      // Check for a fast lane
      if (
        ($toggleState === null) &&
        isset ($this->thePost->is_lyric) &&
        ($this->thePost->is_lyric !== null)
      )
        return ($this->thePost->is_lyric == 1);
        
      // Switch to site
      if (is_multisite ())
        switch_to_blog ($this->siteId);
      
      // Check wheter to change the state
      if ($toggleState === true)
        update_post_meta ($this->thePost->ID, 'worthy_lyric', 1);
      elseif ($toggleState === false)
        delete_post_meta ($this->thePost->ID, 'worthy_lyric');  
      
      // Retrive current state
      $isLyric = (get_post_meta ($this->thePost->ID, 'worthy_lyric', true) == 1);
      
      // Reset site
      if (is_multisite ())
        restore_current_blog ();
      
      return $isLyric;
    }
    // }}}
    
    // {{{ isOwnPost
    /**
     * Check if a post is owned by the a user
     * 
     * @param int $userID (optional)
     * 
     * @access public
     * @return bool
     **/
    public function isOwnPost (int $userID = null) : bool {
      if ($userID === null)
        $userID = wp_worthy::singleton ()->getUserID ();
      
      return in_array ($userID, wp_worthy::singleton ()->getUserIDs ($this->thePost->post_author));
    }
    // }}}
    
    // {{{ isRelevant
    /**
     * Check if this post is relevant for VG WORT
     * 
     * @access public
     * @return bool
     **/
    public function isRelevant () : bool {
      return (
        ($this->getLength () >= wp_worthy::MIN_LENGTH) ||
        $this->isLyric ()
      );
    }
    // }}}
    
    // {{{ isIndexed
    /**
     * Check if this post is present on our index
     * 
     * @access public
     * @return bool
     **/
    public function isIndexed () : bool {
      return (
        (isset ($this->thePost->post_length) && ($this->thePost->post_length !== null)) ||
        (get_post_meta ($this->thePost->ID, wp_worthy::META_LENGTH, true) > 0)
      );
    }
    // }}}
    
    // {{{ hasPixel
    /**
     * Check if this post has a pixel assigned
     * 
     * @access public
     * @return bool
     **/
    public function hasPixel () : bool {
      return (
        (isset ($this->thePost->public) && (strlen ($this->thePost->public) > 0)) ||
        is_object ($this->getPixel ())
      );
    }
    // }}}
    
    // {{{ getPixel
    /**
     * Retrive instance of the pixel assigned to this post
     * 
     * @param bool $bypassCache (optional)
     * 
     * @access public
     * @return wp_worthy_pixel|null
     **/
    public function getPixel (bool $bypassCache = false) /* : ?wp_worthy_pixel */ {
      if ($this->synteticPixel)
        return $this->synteticPixel;
      
      if (
        isset ($this->thePost->pixelId) &&
        isset ($this->thePost->userId) &&
        isset ($this->thePost->public)
      )
        return $this->synteticPixel = new wp_worthy_pixel ((object)[
          'id' => $this->thePost->pixelId,
          'userid' => $this->thePost->userId,
          'public' => $this->thePost->public,
          'private' => (isset ($this->thePost->private) ? $this->thePost->private : null),
          'server' => (isset ($this->thePost->server) ? $this->thePost->server : null),
          'url' => (isset ($this->thePost->url) ? $this->thePost->url : null),
          'siteid' => $this->siteId,
          'postid' => $this->thePost->ID,
          'disabled' => (isset ($this->thePost->disabled) ? $this->thePost->disabled : null),
          'status' => (isset ($this->thePost->status) ? $this->thePost->status : null),
          'status_date' => (isset ($this->thePost->status_date) ? $this->thePost->status_date : null),
          'reportable' => (isset ($this->thePost->reportable) ? $this->thePost->reportable : null),
          'loadSuccess' => (isset ($this->thePost->loadSuccess) ? $this->thePost->loadSuccess : null),
          'loadFailure' => (isset ($this->thePost->loadFailure) ? $this->thePost->loadFailure : null),
        ]);
      
      return wp_worthy_pixel::getPixelForPost ($this->thePost->ID, $this->siteId, $bypassCache);
    }
    // }}}
    
    // {{{ assignPixel
    /**
     * Assign a pixel to this post
     * 
     * @access public
     * @return object
     **/
    public function assignPixel () {
      // Don't do anything if there is already a pixel assigned
      if (is_object ($postPixel = $this->getPixel ()))
        return $postPixel;
      
      // Assign a pixel
      # TODO: Improve this
      return wp_worthy::singleton ()->adminSavePost ($this->thePost->ID, $this->thePost, true, true);
    }
    // }}}
    
    // {{{ getLength
    /**
     * Retrive length of this post
     * 
     * @param bool $useCache (optional)
     * 
     * @access public
     * @return int
     **/
    public function getLength (bool $useCache = true) : int {
      // Look for cached value
      if ($useCache) {
        if (isset ($this->thePost->post_length) && ($this->thePost->post_length !== null))
          return $this->thePost->post_length;
        
        if (($metaLength = get_post_meta ($this->thePost->ID, wp_worthy::META_LENGTH, true)) > 0)
          return $metaLength;
      }
      
      // Check if we did this before
      if ($useCache && isset ($this->thePost->__worthy_length))
        return $this->thePost->__worthy_length;
      
      // Extract content
      $postContent = $this->getContent ();
      
      // Remove HTML-Elements and unescape entities
      $domID = '__worthy' . time ();
      $domDocument = new DOMDocument ('1.0', 'utf-8');
      
      if (
        !@$domDocument->loadHTML ('<?xml encoding="utf-8"><div id="' . $domID . '">' . $postContent . '</div>') ||
        !($contentElement = $domDocument->getElementById ($domID))
      ) {
        // Remove HTML from content (strip_tags() is unreliable if there are markup-errors)
        $postContent = strip_tags ($postContent);
        
        // Remove escaping from special characters
        $postContent = html_entity_decode ($postContent, ENT_COMPAT, 'utf-8');
      } else
        $postContent = $contentElement->textContent;
      
      // Convert breaks and tabs into spaces
      $postContent = trim (
        str_replace (
          [ "\r", "\n", "\t" ],
          [ ' ', ' ', ' ' ],
          $postContent
        )
      );
      
      // Remove double spaces
      $postContent = preg_replace ('/\s{2,}/', ' ', $postContent);
      
      // Count characters on post
      if (extension_loaded ('mbstring'))
        $postLength = mb_strlen ($postContent);
      else
        $postLength = strlen (utf8_decode ($postContent));
      
      // Store a hint on the object
      $this->thePost->__worthy_length = $postLength;
      
      return $postLength;
    }
    // }}}
    
    // {{{ updateLength
    /**
     * Update the length-index for this post
     * 
     * @access public
     * @return void
     **/
    public function updateLength () /* : void */ {
      // Retrive length of this post
      $postLength = $this->getLength (false);
      
      // Store the value on post's meta
      if (is_multisite ())
        switch_to_blog ($this->siteId);
      
      update_post_meta ($this->thePost->ID, wp_worthy::META_LENGTH, $postLength);
      
      if (is_multisite ())
        restore_current_blog ();
      
      // Try to update reportable-flag (if there is any pixel assigned)
      if (!is_object ($postPixel = $this->getPixel ()))
        return;
      
      $reportableFlag = ($this->isReportable () ? 1 : 0);
      
      if ($postPixel->reportable == $reportableFlag)
        return;
      
      $postPixel->reportable = $reportableFlag;
      
      $GLOBALS ['wpdb']->update (
        wp_worthy_pixel::getTableName (),
        [ 'reportable' => $reportableFlag ],
        [ 'id' => $postPixel->id ],
        [ '%d' ],
        [ '%d' ]
      );
    }
    // }}}
    
    // {{{ getTitle
    /**
     * Retrive the title of this post
     * 
     * @param bool $truncateTitle (optional)
     * 
     * @access public
     * @return string
     **/
    public function getTitle (bool $truncateTitle = false) : string {
      // Retrive the title
      $postTitle = (string)$this->thePost->post_title;
      
      if (!$truncateTitle)
        return $postTitle;
      
      // Check wheter to do anything
      if (strlen ($postTitle) < $this::TITLE_MAX_LENGTH)
        return $postTitle;
      
      // Get the users preference for this
      $truncatePreference = get_user_meta ($this->thePost->post_author, 'wp-worthy-overlong-titles', true);
      
      if (
        ($truncatePreference === false) ||
        (is_string ($truncatePreference) && (strlen ($truncatePreference) == 0)) ||
        ($truncatePreference == -1)
      )
        $truncatePreference = (int)get_option ('wp-worthy-overlong-titles', 0);
      else
        $truncatePreference = (int)$truncatePreference;
      
      // Check wheter to do anything
      if ($truncatePreference < 1)
        return $postTitle;
      
      // Check for hard cuts
      if (extension_loaded ('mbstring')) {
        $strlen = 'mb_strlen';
        $substr = 'mb_substr';
      } else {
        $strlen = 'strlen';
        $substr = 'substr';
      }
      
      if ($truncatePreference == 1)
        return trim ($substr ($postTitle, 0, $this::TITLE_MAX_LENGTH));
      
      // Split into words
      $titleWords = explode (' ', $postTitle);
      $titleLength = $strlen ($postTitle);
      
      // Remove words at designated position
      do {
        if ($truncatePreference > 2) {
          // Remove word at middle
          $wordIndex = (int)(count ($titleWords) / 2);
          $titleLength -= $strlen ($titleWords [$wordIndex]) + 1;
          
          unset ($titleWords [$wordIndex]);
          
          // Reindex
          $titleWords = array_values ($titleWords);
        } else
          $titleLength -= $strlen (array_pop ($titleWords)) + 1;
      } while ($titleLength > $this::TITLE_MAX_LENGTH - 4);
      
      // Add dots
      if ($truncatePreference > 2)
        $titleWords = array_merge (
          array_slice ($titleWords, 0, (int)(count ($titleWords) / 2)),
          [ '...' ],
          array_slice ($titleWords, (int)(count ($titleWords) / 2))
        );
      else
        $titleWords [] = '...';
      
      // Reconstruct title
      return implode (' ', $titleWords);
    }
    // }}}
    
    // {{{ getContent
    /**
     * Retrive the full content of this post
     * 
     * @access public
     * @return string
     **/
    public function getContent () : string {
      // Get the content of the post
      $postContent = $this->thePost->post_content;
      
      // Apply shortcode-filters
      if (($shortcodeFilters = get_option ('wp-worthy-filter-shortcodes', false)) && (strlen ($shortcodeFilters) > 0))
        $postContent = preg_replace ('/' . get_shortcode_regex (explode (',', $shortcodeFilters)) . '/', '', $postContent);
      
      // Return parsed content
      return apply_filters ('the_content', $postContent);
    }
    // }}}
    
    // {{{ getURL
    /**
     * Retrieve the link to this post
     * 
     * @access public
     * @return string
     **/
    public function getURL () : string {
      if (($this->siteId !== null) && is_multisite ())
        switch_to_blog ($this->siteId);
      
      $postLink = get_permalink ($this->thePost->ID);
      
      if (($this->siteId !== null) && is_multisite ())
        restore_current_blog ();
      
      return $postLink;
    }
    // }}}
  }
