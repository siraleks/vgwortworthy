<?php

  class wp_worthy_maintenance {
    // {{{ getUnindexedCount
    /**
     * Retrive the number of not indexed posts
     * 
     * @access public
     * @return int
     **/
    public static function getUnindexedCount () {
      return array_sum (
        wp_worthy::singleton ()->querySites (
          'SELECT ' .
            'COUNT(DISTINCT p.ID) ' .
          'FROM ' .
            '`%tablePosts` p ' .
            'LEFT JOIN `%tablePostMeta` m ON (m.post_id=p.ID AND m.meta_key="' . wp_worthy::META_LENGTH . '") ' .
          'WHERE ' .
            'post_type IN ("' . implode ('","', wp_worthy::singleton ()->getUserPostTypes ()) . '") AND ' .
            'post_status="publish" AND ' .
            'meta_value IS NULL'
        )
      );
    }
    // }}}
    
    // {{{ getInvalidAuthorCount
    /**
     * Retrive the number of pixels that are assigned to non-existing users
     **/
    public static function getInvalidAuthorCount () {
      return (int)$GLOBALS ['wpdb']->get_var (
        'SELECT ' .
          'COUNT(*) ' .
        'FROM ' .
          '`' . wp_worthy_pixel::getTableName () . '` `wp` ' .
          'LEFT JOIN `' . $GLOBALS ['wpdb']->users . '` `u` ON (`u`.`ID`=`wp`.`userid`) ' .
        'WHERE ' .
          '`u`.`ID` IS NULL'
      );
    }
    // }}}
    
    // {{{ adoptOrphanedPixels
    /**
     * Assign orphaned pixels to a new owner
     * 
     * @param mixed $wordpressUser
     * 
     * @access public
     * @return int Number of adopted pixels
     **/
    public static function adoptOrphanedPixels ($wordpressUser) {
      if (
        !($wordpressUser instanceof WP_User) &&
        !is_object ($wordpressUser = get_user_by ('id', $wordpressUser))
      )
        throw new Exception ('User not found');
      
      // Issue the query
      return $GLOBALS ['wpdb']->query (
        'UPDATE ' .
          '`' . wp_worthy_pixel::getTableName () . '` ' .
        'SET ' .
          'userid="' . (int)$wordpressUser->ID . '" ' .
        'WHERE ' .
          'NOT (userid IN (SELECT ID FROM `' . $GLOBALS ['wpdb']->users . '`))'
      );
    }
    // }}}
  }
