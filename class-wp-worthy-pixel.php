<?php

  class wp_worthy_pixel {
    /* Internal cache of pixels */
    private static $cachedPixels = [];
    
    /* Regular expression to check a pixel */
    const PIXEL_REGEX = '/^[a-zA-Z0-9]{20,32}$/';
    
    /* Instance of our pixel */
    private $thePixel = null;
    
    // {{{ setupDatabase
    /**
     * Ensure that our database-tables are set up properly
     * 
     * @access public
     * @return void
     **/
    public static function setupDatabase () {
      // Retrive name of our table
      $tableName = static::getTableName ();
      
      // Check if we have done this call before
      if (($databaseVersion = get_option ('wp-worthy-pixel-table-version', null)) === null) {
        // Check wheter to migrate from qcWP
        if (($oldDatabaseVersion = get_option ('db-' . $tableName, null)) !== null) {
          // Handle some very old cases
          if ($oldDatabaseVersion == 2)
            $GLOBALS ['wpdb']->query (
              'ALTER TABLE `' . $tableName . '` ' .
              'ADD COLUMN `userid` INT(11) NOT NULL AFTER `id`, ' .
              'ADD COLUMN `server` VARCHAR(32) DEFAULT NULL AFTER `private`, ' .
              'ADD COLUMN `siteid` INT(10) UNSIGNED NOT NULL DEFAULT "0" AFTER `url`, ' .
              'ADD COLUMN `disabled` INT(11) NOT NULL AFTER `postid`, ' .
              'ADD KEY `userid` (`userid`), ' .
              'DROP KEY `postid`, ' .
              'ADD UNIQUE KEY `postid` (`siteid`, `postid`)'
            );
          // Just add multi-site related fields
          else
            $GLOBALS ['wpdb']->query (
              'ALTER TABLE `' . $tableName . '` ' .
              'ADD COLUMN `siteid` INT(10) UNSIGNED NOT NULL DEFAULT "0" AFTER `url`, ' .
              'DROP KEY `postid`, ' .
              'ADD UNIQUE KEY `postid` (`siteid`, `postid`)'
            );
          
          delete_option ('db-' . $tableName);
          
        // Just create a new table
        } else
          $GLOBALS ['wpdb']->query (
            'CREATE TABLE IF NOT EXISTS `' . $tableName . '` (' .
              '`id` INT(11) NOT NULL AUTO_INCREMENT, ' .
              '`userid` INT(11) NOT NULL, ' .
              '`public` VARCHAR(32) NOT NULL, ' .
              '`private` VARCHAR(32) DEFAULT NULL, ' .
              '`server` VARCHAR(32) DEFAULT NULL, ' .
              '`url` VARCHAR(64) NOT NULL, ' .
              '`siteid` INT(10) UNSIGNED NOT NULL DEFAULT "0", ' .
              '`postid` INT(10) UNSIGNED DEFAULT NULL, ' .
              '`disabled` INT(11) NOT NULL, ' .
              '`status` INT(10) UNSIGNED DEFAULT NULL, ' .
              '`status_date` INT(10) UNSIGNED DEFAULT NULL, ' .
              'PRIMARY KEY (`id`), ' .
              'UNIQUE KEY `public` (`public`), ' .
              'UNIQUE KEY `private` (`private`), ' .
              'UNIQUE KEY `postid` (`siteid`, `postid`), ' .
              'KEY `status_status_date` (`status`,`status_date`), ' .
              'KEY `userid` (`userid`)' .
            ')'
          );
        
        // Store current version of our database
        $databaseVersion = 1;
        add_option ('wp-worthy-pixel-table-version', $databaseVersion, null, true);
      }
      
      // Append reportable-bit introduced in 1.6.3
      if ($databaseVersion < 2) {
        // Alter database-table
        $GLOBALS ['wpdb']->query (
          'ALTER TABLE `' . $tableName . '` ' .
          'ADD COLUMN `reportable` TINYINT(1) UNSIGNED NOT NULL DEFAULT "0" AFTER `status_date`'
        );
        
        $databaseVersion = 2;
        update_option ('wp-worthy-pixel-table-version', $databaseVersion);
        
        // Pre-fill the new field
        wp_worthy::singleton ()->querySites (
          'UPDATE ' .
            '`' . $tableName . '` pixel, ' .
            '`%tablePosts` p ' .
            'LEFT JOIN `%tablePostMeta` pm ON (p.ID=pm.post_id AND pm.meta_key="' . wp_worthy::META_LENGTH . '") ' .
            'LEFT JOIN `%tablePostMeta` pmi ON (p.ID=pmi.post_id AND pmi.meta_key="worthy_ignore") ' .
            'LEFT JOIN `%tablePostMeta` pml ON (p.`ID`=pml.`post_id` AND pml.`meta_key`="worthy_lyric") ' .
          'SET ' .
            'pixel.`reportable`="1" ' .
          'WHERE ' .
            'pixel.`postid`=p.`ID` AND ' .
            'pixel.`siteid`="%siteId" AND ' .
            '(pmi.`meta_value` IS NULL OR pmi.`meta_value`="0") AND ' .
            '(' .
              '(pixel.`status`="' . wp_worthy::MARKER_STATUS_REACHED . '" AND (CONVERT(pm.`meta_value`, UNSIGNED INTEGER)>="' . wp_worthy::MIN_LENGTH . '") OR pml.meta_value="1") OR ' .
              '(pixel.`status`="' . wp_worthy::MARKER_STATUS_PARTIAL . '" AND (CONVERT(pm.`meta_value`, UNSIGNED INTEGER)>="' . wp_worthy::EXTRA_LENGTH . '"))' .
            ')'
        );
      }
      
      // Fix reportable-bit for 1.7.2
      if ($databaseVersion < 3) {
        // Increase the version
        $databaseVersion = 3;
        update_option ('wp-worthy-pixel-table-version', $databaseVersion);

        // Reindex all reportable posts or those with a certain status
        $checkPosts = $GLOBALS ['wpdb']->get_results (
          'SELECT ' .
            '`siteid`, ' .
            '`postid` ' .
          'FROM `' . $tableName . '` ' .
          'WHERE ' .
            '`reportable`="1" OR ' .
            '`status` IN ("' . wp_worthy::MARKER_STATUS_REACHED . '", "' . wp_worthy::MARKER_STATUS_PARTIAL . '")',
          ARRAY_A
        );

        foreach ($checkPosts as $checkPost)
          try {
            $thePost = WP_Worthy_Post::fromID ($checkPost ['postid'], $checkPost ['siteid'], false);
            
            $thePost->updateLength ();
          } catch (Throwable $error) {
            // No-Op
          }
      }
      
      # TODO: Process changes/updates here
    }
    // }}}
    
    // {{{ getTableName
    /**
     * Retrive name of our table
     * 
     * @access public
     * @return string
     **/
    public static function getTableName () : string {
      return $GLOBALS ['wpdb']->base_prefix . 'worthy_markers';
    }
    // }}}
    
    // {{{ getURLFromHTML
    /**
     * Extract URL from a VG WORT Pixel-HTML-Element
     * 
     * @param string $htmlElement
     * 
     * @access public
     * @return string|null
     **/
    public static function getURLFromHTML ($htmlElement) /* : ?string */ {
      if (($p = strpos ($htmlElement, ' src=')) !== false)
        $URL = substr ($htmlElement, $p + 5);
      elseif (($p = strpos ($htmlElement, ' href=')) !== false)
        $URL = substr ($htmlElement, $p + 6);
      else
        return null;
      
      if (($URL [0] == '"') || ($URL [0] == "'"))
        $URL = substr ($URL, 1, strpos ($URL, $URL [0], 1) - 1);
      
      if (($p = strpos ($URL, '?')) !== false)
        $URL = substr ($URL, 0, $p);
      
      return $URL;
    }
    // }}}
    
    // {{{ getPixelPublicFromURL
    /**
     * Extract public part of a pixel from VG WORT Pixel-URL
     * 
     * @param string $URL
     * 
     * @access public
     * @return string|null
     **/
    public static function getPixelPublicFromURL ($URL) /* : ?string */ {
      // Extract the pixel from URL (always the last part)
      $pixelPublic = substr ($URL, strrpos ($URL, '/') + 1);
      
      // Sanatize the pixel
      if (preg_match (self::PIXEL_REGEX, $pixelPublic))
        return $pixelPublic;
      
      return null;
    }
    // }}}
    
    // {{{ getPixelForPost
    /**
     * Retrive instance of a pixel for a given post
     * 
     * @param mixed $thePost Instance or ID of the post to retrive a pixel for
     * @param int $siteId (optional) ID of the site to query the pixel from
     * @param bool $bypassCache (optional) Ignore local cache
     * 
     * @access public
     * @return wp_worthy_pixel|null
     **/
    public static function getPixelForPost ($thePost, int $siteId = null, bool $bypassCache = false) /* : ?wp_worthy_pixel */ {
      // Sanatize parameters
      if ($siteId === null)
        $siteId = get_current_blog_id ();
      
      if (is_object ($thePost))
        $postID = $thePost->ID;
      else
        $postID = (int)$thePost;
      
      // Check if the marker was cached before
      $cacheKey = $siteId . '/' . $postID;
      
      if (!$bypassCache && array_key_exists ($cacheKey, self::$cachedPixels))
        return self::$cachedPixels [$cacheKey];
      
      // Collect a set of post-ids to load from database
      $postIDs = [ $postID => $postID ];
      self::$cachedPixels [$cacheKey] = null;
      
      if (isset ($GLOBALS ['wp_query']) && is_array ($GLOBALS ['wp_query']->posts))
        foreach ($GLOBALS ['wp_query']->posts as $post)
          if (!array_key_exists ($cacheKey, self::$cachedPixels)) {
            $postIDs [$post->ID] = intval ($post->ID);
            self::$cachedPixels [$cacheKey] = null;
          }
      
      // Try to load these markers
      $loadedPixels = $GLOBALS ['wpdb']->get_results (
        'SELECT * ' .
        'FROM `' . static::getTablename ()  . '` ' .
        'WHERE ' .
          '`siteid`="' . (int)$siteId . '" AND ' .
          '`postid` IN ("' . implode ('","', $postIDs) . '")'
      );
      
      foreach ($loadedPixels as $loadedPixel)
        self::$cachedPixels [$siteId . '/' . $loadedPixel->postid] = new static ($loadedPixel);
      
      return self::$cachedPixels [$cacheKey];
    }
    // }}}
    
    // {{{ assignToPost
    /**
     * Try to assign a pixel to a given post
     * 
     * @param wp_worthy_post $thePost Instance or ID of the post to assign a pixel to
     * @param array $userIDs (optional) A set of user-IDs to take pixels from
     * 
     * @access public
     * @return wp_worthy_pixel|null
     **/
    public static function assignToPost (wp_worthy_post $thePost, array $userIDs = null) /* : ?wp_worthy_pixel */ {
      // Check if there is already a pixel assigned
      if ($thePost->hasPixel ())
        return $thePost->getPixel ();
      
      // Make sure we have user-IDs
      $worthyInstance = wp_worthy::singleton ();
      
      if (!$userIDs)
        $userIDs = $worthyInstance->getUserIDs (
          $worthyInstance->getUserIDforPost ($thePost->ID)
        );
      
      // Make sure we have pixels available
      if (static::availablePixels ($userIDs) < 1) {
        if (!$worthyInstance->hasPremium ())
          return null;
        
        // Try to order pixels via premium
        try {
          $worthyInstance->premiumOrderPixels (10);
        } catch (\Throwable $error) {
          // do nothing.
        }
      }
      
      // Assign a random pixel to this post
      foreach ($userIDs as $userID) {
        $affectedRows = $GLOBALS ['wpdb']->query (
          $GLOBALS ['wpdb']->prepare (
            'UPDATE IGNORE `' . static::getTablename () . '` ' .
            'SET ' .
              '`siteid`=%d, ' .
              '`postid`=%d ' .
            'WHERE ' .
              '`postid` IS NULL AND ' .
              '`userid`=%d AND ' .
              '(`status` IS NULL OR `status`<1) AND ' .
              'NOT (`disabled`>0) ' .
            'LIMIT 1',
            $thePost->siteId,
            $thePost->ID,
            $userID
          )
        );
        
        if ($affectedRows > 0) {
          // Retrieve pixel and refresh the cache
          $postPixel = $thePost->getPixel (true);
          
          try {
            if (
              $worthyInstance->hasPremium () &&
              ((int)get_user_meta ($userID, 'wp-worthy-autocreate-webranges', true) == 1)
            )
              $postPixel->createWebrange ();
          } catch (\Throwable $error) {
            // No-Op
          }
          
          return $postPixel;
        }
      }
      
      return null;
    }
    // }}}
    
    // {{{ availablePixels
    /**
     * Check the number of available pixels for a given set of User-IDs
     * 
     * @param array $userIDs (optional) Set of User-IDs
     * 
     * @access public
     * @return int
     **/
    public static function availablePixels (array $userIDs = null) : int {
      // Sanatize User-IDs
      if (!$userIDs)
        $userIDs = wp_worthy::singleton ()->getUserIDs ();
      
      return (int)$GLOBALS ['wpdb']->get_var (
        'SELECT COUNT(*) ' .
        'FROM `' . static::getTablename () . '` ' .
        'WHERE ' .
          '(`postid` IS NULL) AND ' .
          '(`status` IS NULL OR `status`<1) AND ' .
          'NOT (`disabled`>0) AND ' .
          '`userid` IN ("' . implode ('","', array_map ('intval', $userIDs)) . '")'
      );
    }
    // }}}
    
    // {{{ resetCache
    /**
     * Reset cache of pixels
     * 
     * @access public
     * @return void
     **/
    public static function resetCache () {
      self::$cachedPixels = [];
    }
    // }}}
    
    // {{{ fromObject
    /**
     * Retrive Worthy-Post-Object from any given object if possible
     * 
     * @param object $thePixel
     * 
     * @access public
     * @return wp_worthy_pixel
     **/
    public static function fromObject ($thePixel) : wp_worthy_pixel {
      if ($thePixel instanceof wp_worthy_pixel)
        return $thePixel;

      return new static ($thePixel);
    }
    // }}}

    
    // {{{ __construct
    /**
     * Create Worthy-Interface to a pixel
     * 
     * @param object $thePixel
     * 
     * @access friendly
     * @return void
     **/
    function __construct ($thePixel) {
      if (!is_object ($thePixel))
        throw new \Exception ('Invalid thePixel-parameter');

      $this->thePixel = $thePixel;
    }
    // }}}
    
    // {{{ __get
    /**
     * Expose some attributes of our pixel
     * 
     * @param string $attributeName
     * 
     * @access public
     * @return mixed
     **/
    function __get (string $attributeName) {
      switch ($attributeName) {
        case 'userId':
          return $this->thePixel->userid;
        
        case 'id':
        case 'status':
        case 'private':
        case 'public':
        case 'url':
        case 'server':
        case 'reportable':
          return $this->thePixel->$attributeName;
      }
      
      return null;
    }
    // }}}
    
    // {{{ getPost
    /**
     * Retrieve instance of assigned post
     * 
     * @access public
     * @return wp_worthy_post|null
     **/
    public function getPost () /* : ?wp_worthy_post */ {
      if ($this->thePixel->postid === null)
        return null;
      
      return wp_worthy_post::fromID ((int)$this->thePixel->postid, (int)$this->thePixel->siteid);
    }
    // }}}
    
    // {{{ createWebrange
    /**
     * Create webrange for this pixel (if owner has premium)
     * 
     * @access public
     * @return void
     **/
    public function createWebrange () /* : void */ {
      // Try to retrieve our post
      if (!is_object ($thePost = $this->getPost ()))
        return;
      
      // Retrive Interface to Worthy Premium
      if (!is_object ($premiumInterface = wp_worthy::singleton ()->getPremium ()))
        return;
      
      // Try to create the webrange
      $premiumInterface->createWebrange (
        $this->thePixel->private,
        $thePost->getURL (),
        true /* TODO #156 */
      );
    }
    // }}}
    
    // {{{ burn
    /**
     * Burn this pixel
     * 
     * @access public
     * @return void
     **/
    public function burn () /* PHP 7.1 : void */ {
      $GLOBALS ['wpdb']->update (
        $this::getTableName (),
        [
          'disabled' => 1,
          'siteid' => 0,
          'postid' => null,
        ],
        [
          'siteid' => $this->thePixel->siteid,
          'postid' => $this->thePixel->postid,
        ],
        [ '%d', '%d', null ],
        [ '%d', '%d' ]
      );
    }
    // }}}
  }
