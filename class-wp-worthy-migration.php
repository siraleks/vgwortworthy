<?php

  class wp_worthy_migration {
    // {{{ getMigrationStats
    /**
     * Retrive migration statistics
     * 
     * @param array $siteIds (optional)
     * @param bool $cacheOnly (optional) Don't try to regenerate stats if missing
     * 
     * @access public
     * @return array
     **/
    public static function getMigrationStats ($siteIds = null, $cacheOnly = false) {
      if (!is_array ($siteIds))
        $siteIds = wp_worthy::singleton ()->getSiteIDs ();
      
      $result = [];
      
      foreach ($siteIds as $siteId) {
        $transientKey = 'wp-worthy-migration-stats-' . $siteId;
        
        if (!is_array ($siteMigrationStatus = get_site_transient ($transientKey))) {
          if ($cacheOnly)
            return [];
          
          $siteMigrationStatus = [
            'inline pixels' => self::migrateInline (false, true, null, [ $siteId ]),
            'pixels from VGW (VG-Wort Krimskram)' => self::migrateByMeta ([ 'vgwpixel' ], false, true, null, [ $siteId ]),
            'pixels from WP VG-Wort' => self::migrateByMeta ([ get_option ('wp_vgwortmetaname', 'wp_vgwortmarke') ], false, true, null, [ $siteId ]),
            'pixels from Torben Leuschners VG-Wort' => self::migrateTlVGWort (false, true, null, [ $siteId ]),
            'pixels from Prosodia VGW' => self::migrateProsodia (false, true, null, [ $siteId ]),
          ];
          
          set_site_transient ($transientKey, $siteMigrationStatus, DAY_IN_SECONDS);
        }
        
        foreach ($siteMigrationStatus as $pluginHint=>$pluginPostIDs)
          if (isset ($result [$pluginHint]))
            $result [$pluginHint] = array_merge ($result [$pluginHint], $pluginPostIDs);
          else
            $result [$pluginHint] = $pluginPostIDs;
      }
      
      return $result;
    }
    // }}}
    
    // {{{ resetMigrationStats
    /**
     * Remove migration-statistics for all sites
     * 
     * @access public
     * @return void
     **/
    public static function resetMigrationStats () {
      foreach (wp_worthy::singleton ()->getSiteIDs () as $siteId)
        delete_site_transient ('wp-worthy-migration-stats-' . $siteId);
    }
    // }}}
    
    // {{{ regenerateMigrationStats
    /**
     * Regenerate migration-statistics for all sites
     * 
     * @access public
     * @return void
     **/
    public static function regenerateMigrationStats () {
      self::resetMigrationStats ();
      self::getMigrationStats ();
    }
    // }}}
    
    // {{{ migrateInline
    /**
     * Migrate posts to worthy that carry a marker on their content
     * 
     * @param bool $Repair (optional) Assign new marker if an existing marker could not be assigned
     * @param bool $onlyCollect (optional) Just collect post-ids that would be migrated
     * @param array $postIDs (optional) Only migrate Posts with this id
     * @param array $siteIds (optional) Only migrate Posts from this site-ids
     * 
     * @access public
     * @return array
     **/
    public static function migrateInline ($Repair = false, $onlyCollect = false, array $postIDs = null, array $siteIds = null) : array {
      $pixelsSeen = 0;
      $pixelsMigrated = 0;
      $postsDuplicate = [];
      $migrationErrors = [];
      
      foreach (wp_worthy::singleton ()->querySiteTables ('posts', false, $siteIds) as $postTable) {
        // Load all posts that seem to carry a VG WORT URL
        if ($postIDs) {
          $localPostIDs = [];
          
          foreach ($postIDs as $postID)
            if ($postID ['siteid'] == $postTable ['siteid'])
              $localPostIDs [] = (int)$postID ['postid'];
          
          if (count ($localPostIDs) == 0)
            continue;
          
          $queryWhere = 'ID IN (' . implode (',', $localPostIDs) . ')';
        } else
          $queryWhere = '(' .
            'post_content LIKE "%http://vg%.met.vgwort.de/na/%" OR ' .
            'post_content LIKE "%https://vg%.met.vgwort.de/na/%" OR ' .
            'post_content LIKE "%https://ssl-vg%.met.vgwort.de/na/%" OR ' .
            'post_excerpt LIKE "%http://vg%.met.vgwort.de/na/%" OR ' .
            'post_excerpt LIKE "%https://vg%.met.vgwort.de/na/%" OR ' .
            'post_excerpt LIKE "%https://ssl-vg%.met.vgwort.de/na/%"' .
          ')';
        
        $posts = $GLOBALS ['wpdb']->get_results (
          'SELECT `ID`, `post_excerpt`, `post_content`, `post_author` ' .
          'FROM `' . $postTable ['name'] . '` ' .
          'WHERE ' . $queryWhere
        );
        
        // Try to convert all posts
        $contentPixels = $excerptPixels = null;
        
        foreach ($posts as $post) {
          // Try to extract and remove markers from post_excerpt
          if (($content = wp_worthy::singleton ()->removeInlineMarkers ($post->post_excerpt, true, $excerptPixels)) !== null) {
            $allPixels = $excerptPixels;
            
            $post->post_excerpt = $content;
          } else
            $allPixels = [];
          
          // Try to extract and remove markers from post_content
          if (($content = wp_worthy::singleton ()->removeInlineMarkers ($post->post_content, true, $contentPixels)) !== null) {
            $allPixels = array_merge ($allPixels, $contentPixels);
            
            $post->post_content = $content;
          }
          
          // Check if any marker was extracted
          if (count ($allPixels) == 0) {
            if (!$onlyCollect)
              $migrationErrors [$postTable ['siteid'] . '/' . $post->ID] = 'No pixel found on post';
            
            continue;
          }
          
          if ($onlyCollect) {
            $postsDuplicate [] = [
              'siteid' => $postTable ['siteid'],
              'postid' => $post->ID,
            ];
            
            continue;
          }
          
          // Increase the counter
          $pixelsSeen += count ($allPixels);
          
          // Register the markers
          foreach ($allPixels as $URL=>$pixelPublic) {
            # TODO: If there is more than one pixel, subsequent attemps here will fail as siteid/postid is unique
            try {
              $rc = self::migrateDo (
                $postTable ['siteid'],
                $post->ID,
                $pixelPublic,
                null,
                null,
                $URL,
                $post->post_author,
                null,
                $Repair
              );
              
              if ($rc === null) {
                $postsDuplicate [] = [
                  'siteid' => $postTable ['siteid'],
                  'postid' => $post->ID,
                ];
                
                $migrationErrors [$postTable ['siteid'] . '/' . $post->ID] = 'Post is already known to worthy';
              }
            } catch (Exception $error) {
              $migrationErrors [$postTable ['siteid'] . '/' . $post->ID] = $error->getMessage ();
            }
          }
          
          // Update the post
          $rc = $GLOBALS ['wpdb']->update (
            $postTable ['name'],
            [
              'post_content' => $post->post_content,
              'post_excerpt' => $post->post_excerpt,
            ],
            [ 'ID' => $post->ID ],
            [ '%s', '%s' ],
            [ '%d' ]
          );
          
          if ($rc)
            $pixelsMigrated += count ($allPixels);
          else
            $migrationErrors [$postTable ['siteid'] . '/' . $post->ID] = 'Failed to update post-content';
        }
      }
      
      if ($onlyCollect)
        return $postsDuplicate;
      
      if ($pixelsMigrated > 0)
        self::resetMigrationStats ();
      
      return [
        'total' => $pixelsSeen,
        'migrated' => $pixelsMigrated,
        'duplicates' => $postsDuplicate,
        'errors' => $migrationErrors,
      ];
    }
    // }}}
    
    // {{{ migrateByMeta
    /**
     * Migrate posts that carry VG WORT pixels in a meta-field
     * 
     * @param array $Keys
     * @param bool $Repair (optional) Assign new marker if an existing marker could not be assigned
     * @param bool $onlyCollect (optional) Just collect post-ids that would be migrated
     * @param array $postIDs (optional) Only migrate Posts with this id
     * @param array $siteIds (optional) Only migrate Posts from this site-ids
     * 
     * @access public
     * @return array
     **/
    public static function migrateByMeta ($Keys, $Repair = false, $onlyCollect = false, array $postIDs = null, array $siteIds = null) : array {
      // Make sure there are keys requested
      if (!is_array ($Keys) || (count ($Keys) == 0))
        return ($onlyCollect ? [] : [ 'total' => 0, 'migrated' => 0, 'duplicates' => [], 'errors' => [] ]);
      
      $duplicates = [];
      $postsSeen = 0;
      $postsMigrated = 0;
      $migrationErrors = [];
      
      if (!$onlyCollect && $postIDs)
        foreach ($postIDs as $postID)
          $migrationErrors [$postID ['siteid'] . '/' . $postID ['postid']] = 'No meta for post found';
      
      foreach (wp_worthy::singleton ()->querySiteTables ('postmeta', false, $siteIds) as $metaTable) {
        // Generate the query
        $Query =
          'SELECT pm.meta_id, pm.post_id, pm.meta_value, p.post_author ' .
          'FROM `' . $metaTable ['name'] . '` pm ' .
          'LEFT JOIN `' . wp_worthy::singleton ()->getTablename ('posts', $metaTable ['siteid']) . '` p ON (p.ID=pm.post_id) ' .
          'WHERE pm.meta_key IN (';
        
        foreach ($Keys as $Key)
          $Query .= $GLOBALS ['wpdb']->prepare ('%s, ', $Key);
        
        $Query = substr ($Query, 0, -2) . ')';
        
        if ($postIDs) {
          $localPostIDs = [];
          
          foreach ($postIDs as $postID)
            if ($postID ['siteid'] == $metaTable ['siteid'])
              $localPostIDs [] = (int)$postID ['postid'];
            
          if (count ($localPostIDs) == 0)
            continue;
          
          $Query .= ' AND pm.post_id IN (' . implode (',', $localPostIDs) . ')';
        }
        
        // Load all metas matching this keys
        $metas = $GLOBALS ['wpdb']->get_results ($Query);
        
        // Convert all metas
        $metaIDs = [];
        
        foreach ($metas as $meta) {
          if ($postIDs)
            $postsSeen++;
          
          // Parse the VG WORT Pixel-Tag
          if (!($URL = wp_worthy_pixel::getURLFromHTML ($meta->meta_value))) {
            if (!$postIDs || isset ($migrationErrors [$metaTable ['siteid'] . '/' . $meta->post_id]))
              $migrationErrors [$metaTable ['siteid'] . '/' . $meta->post_id] = 'Failed to get pixel-url from meta';
            
            continue;
          }
          
          if ($onlyCollect) {
            $duplicates [] = [
              'siteid' => $metaTable ['siteid'],
              'postid' => $meta->post_id,
            ];
            
            continue;
          }
          
          if (!($pixelPublic = wp_worthy_pixel::getPixelPublicFromURL ($URL))) {
            if (!$postIDs || isset ($migrationErrors [$metaTable ['siteid'] . '/' . $meta->post_id]))
              $migrationErrors [$metaTable ['siteid'] . '/' . $meta->post_id] = 'Failed to get public code from pixel-url';
            
            continue;
          }
          
          if (!$postIDs)
            $postsSeen++;
          
          try {
            $rc = self::migrateDo (
              $metaTable ['siteid'],
              $meta->post_id,
              $pixelPublic,
              null,
              null,
              $URL,
              $meta->post_author,
              null,
              $Repair
            );
            
            if ($rc === null) {
              $duplicates [] = $meta->post_id;
              
              if (!$postIDs || isset ($migrationErrors [$metaTable ['siteid'] . '/' . $meta->post_id]))
                $migrationErrors [$metaTable ['siteid'] . '/' . $meta->post_id] = 'Post is already known to worthy';
              
              continue;
            }
          } catch (Exception $error) {
            if (!$postIDs || isset ($migrationErrors [$metaTable ['siteid'] . '/' . $meta->post_id]))
              $migrationErrors [$metaTable ['siteid'] . '/' . $meta->post_id] = $error->getMessage ();
            
            continue;
          }
          
          $postsMigrated++;
          $metaIDs [] = (int)$meta->meta_id;
          
          unset ($migrationErrors [$metaTable ['siteid'] . '/' . $meta->post_id]);
        }
        
        if ($onlyCollect)
          continue;
        
        // Remove all metas that have been converted
        $GLOBALS ['wpdb']->query ('DELETE FROM `' . $metaTable ['name'] . '` WHERE meta_id IN ("' . implode ('","', $metaIDs) . '")');
      }
      
      if ($onlyCollect)
        return $duplicates;
      
      if ($postsMigrated > 0)
        self::resetMigrationStats ();
      
      return [
        'total' => $postsSeen,
        'migrated' => $postsMigrated,
        'duplicates' => $duplicates,
        'errors' => $migrationErrors,
      ];
    }
    // }}}
    
    // {{{ migrateTlVGWort
    /**
     * Migrate markers from Tl-VG-Wort
     * 
     * @param bool $Repair (optional) Assign new marker if an existing marker could not be assigned
     * @param bool $onlyCollect (optional) Just collect post-ids that would be migrated
     * @param array $postIDs (optional) Only migrate Posts with this id
     * @param array $siteIds (optional) Only migrate Posts from this site-ids
     * 
     * @access public
     * @return array
     **/
    public static function migrateTlVGWort ($Repair = false, $onlyCollect = false, array $postIDs = null, array $siteIds = null) : array {
      if (!$onlyCollect) {
        $tlvgwortOptions = get_option (
          'tl-vgwort-options',
          [
            'domain' => 'vg01.met.vgwort.de',
            'limit' => 1000,
            'codes' => [],
            'usercodes' => [],
            'domaincodes' => [],
          ]
        );

        $attributeMap = [
          'vgwort-public' => 'public',
          'vgwort-private' => 'private',
          'vgwort-user' => 'userid',
          'vgwort-domain' => 'server',
        ];

        $postSeen = 0;
        $postsMigrated = 0;
        $migrationErrors = [];
        
        if ($postIDs)
          foreach ($postIDs as $postID)
            $migrationErrors [$postID ['siteid'] . '/' . $postID ['postid']] = 'No meta for post found';
      } else
        $collectedPostIDs = [];
      
      foreach (wp_worthy::singleton ()->querySiteTables ('postmeta', false, $siteIds) as $metaTable) {
        // Generate the query
        $Query = 'SELECT meta_id, post_id, meta_key, meta_value FROM `' . $metaTable ['name'] . '` WHERE meta_key IN ("vgwort-public"' . (!$onlyCollect ? ', "vgwort-private", "vgwort-user", "vgwort-domain"' : '') . ')';
        
        if ($postIDs) {
          $localPostIDs = [];
          
          foreach ($postIDs as $postID)
            if ($postID ['siteid'] == $metaTable ['siteid'])
              $localPostIDs [] = (int)$postID ['postid'];
          
          if (count ($localPostIDs) == 0)
            continue;
          
          $Query .= ' AND post_id IN (' . implode (',', $localPostIDs) . ')';
        }
        
        // Load all metas matching this keys
        $metas = $GLOBALS ['wpdb']->get_results ($Query);
        
        // Group by posts
        $posts = [];
        
        foreach ($metas as $meta) {
          if ($onlyCollect) {
            $collectedPostIDs [] = [
              'siteid' => $metaTable ['siteid'],
              'postid' => $meta->post_id,
            ];
            
            continue;
          }
          
          // Make sure the post is initialized
          if (!isset ($posts [$meta->post_id]))
            $posts [$meta->post_id] = [
              'public' => null,
              'private' =>  null,
              'userid' => null,
              'server' => null,
              'ids' => [],
            ];
          
          // Push the meta to post
          $posts [$meta->post_id][$attributeMap [$meta->meta_key]] = $meta->meta_value;
          
          // Remember the ID of this meta
          $posts [$meta->post_id]['ids'][] = intval ($meta->meta_id);
        }
        
        // Check if only post-ids where requested
        if ($onlyCollect)
          continue;
        
        // Migrate all posts from this table
        $metaIDs = [];
        $duplicatePosts = [];
        
        foreach ($posts as $postID=>$pixel) {
          // Make sure there is a domain set
          if ($pixel ['server'] === null) {
            if (isset ($tlvgwortOptions ['domaincodes'][$pixel ['public']]))
              $pixel ['server'] = $tlvgwortOptions ['domaincodes'][$pixel ['public']];
            else
              $pixel ['server'] = $tlvgwortOptions ['domain'];
          }
          
          // Check if there is a user not set correctly
          # TODO: We may fall back to post_author here
          if (($pixel ['userid'] === null) && isset ($tlvgwortOptions ['usercodes'][$pixel ['public']]))
            $pixel ['userid'] = $tlvgwortOptions ['usercodes'][$pixel ['public']];
          
          $postSeen++;
          
          // Try to migrate to post
          try {
            $rc = self::migrateDo (
              $metaTable ['siteid'],
              $postID,
              $pixel ['public'],
              $pixel ['private'],
              $pixel ['server'],
              null,
              $pixel ['userid'],
              null,
              $Repair
            );
            
            if ($rc === null) {
              $duplicatePosts [] = [
                'siteid' => $metaTable ['siteid'],
                'postid' => $postID,
              ];
              
              $migrationErrors [$metaTable ['siteid'] . '/' . $postID] = 'Post is already known to worthy';
            }
          } catch (Exception $error) {
            $migrationErrors [$metaTable ['siteid'] . '/' . $postID] = $error->getMessage ();
            
            continue;
          }
          
          // Increase the migration-counter
          $postsMigrated++;
          
          unset ($migrationErrors [$metaTable ['siteid'] . '/' . $postID]);
          
          // Collect the migrated meta-ids
          $metaIDs = array_merge ($metaIDs, $pixel ['ids']);
        }
        
        // Remove all metas that have been converted
        $GLOBALS ['wpdb']->query ('DELETE FROM `' . $metaTable ['name'] . '` WHERE meta_id IN ("' . implode ('","', $metaIDs) . '")');
      }
      
      // Return collected post-ids if only these were requested
      if ($onlyCollect)
        return $collectedPostIDs;
      
      // Migrate spare markers
      if ($postIDs === null) {
        foreach ($tlvgwortOptions ['codes'] as $public=>$private)
          $GLOBALS ['wpdb']->insert (
            wp_worthy_pixel::getTableName (),
            [
              'public' => $public,
              'private' => $private,
              'server' => (isset ($tlvgwortOptions ['domaincodes'][$public]) ? $tlvgwortOptions ['domaincodes'][$public] : $tlvgwortOptions ['domain']),
              'url' => 'http://' . (isset ($tlvgwortOptions ['domaincodes'][$public]) ? $tlvgwortOptions ['domaincodes'][$public] : $tlvgwortOptions ['domain']) . '/na/' . $public,
              'userid' => (isset ($tlvgwortOptions ['usercodes'][$public]) ? $tlvgwortOptions ['usercodes'][$public] : 0),
              'disabled' => '0',
            ],
            [
              '%s', '%s', '%s', '%s', '%d', '%d',
            ]
          );
        
        // Remove the markers from TL VG-Wort
        $tlvgwortOptions ['codes'] = $tlvgwortOptions ['domaincodes'] = $tlvgwortOptions ['usercodes'] = [];
        
        // Commit the changes
        update_option ('tl-vgwort-options', $tlvgwortOptions);
      }
      
      if ($postsMigrated > 0)
        self::resetMigrationStats ();
      
      return [
        'total' => $postSeen,
        'migrated' => $postsMigrated,
        'duplicates' => $duplicatePosts,
        'errors' => $migrationErrors,
      ];
    }
    // }}}
    
      // {{{ migrateProsodia
    /**
     * Migrate markers from prosodia VGW
     * 
     * @param bool $Repair (optional) Assign new marker if an existing marker could not be assigned
     * @param bool $onlyCollect (optional) Just collect post-ids that would be migrated
     * @param array $postIDs (optional) Only migrate Posts with this site-id/post-id
     * @param array $siteIds (optional) Only migrate Posts from this site-ids
     * 
     * @access public
     * @return array
     **/
    public static function migrateProsodia ($Repair = false, $onlyCollect = false, array $postIDs = null, array $siteIds = null) : array {
      // Check if prosodia is available
      $prosodiaTables = wp_worthy::singleton ()->querySiteTables ('wpvgw_markers', true);
      
      if (count ($prosodiaTables) == 0)
        return ($onlyCollect ? [] : [ 'total' => 0, 'migrated' => 0, 'duplicates' => [], 'errors' => [] ]);
      
      // Migrate pixels without a post assigned first
      if (!$onlyCollect && ($postIDs === null))
        foreach ($prosodiaTables as $prosodiaTable)
          if (!$siteIds || in_array ($prosodiaTable ['siteid'], $siteIds))
            $GLOBALS ['wpdb']->query (
              'INSERT IGNORE INTO `' . wp_worthy_pixel::getTableName () . '` (`userid`, `public`, `private`, `server`, `disabled`) ' .
              'SELECT ' .
                'IF(`user_id`>0,`user_id`,"' . wp_worthy::singleton ()->getUserID () . '") AS `user_id`, ' .
                '`public_marker`, ' .
                '`private_marker`, ' .
                'IF(LOCATE("/", `server`), LEFT(`server`, LOCATE("/", `server`) - 1), `server`) AS `server`, ' .
                '`is_marker_disabled` ' .
              'FROM `' . $prosodiaTable ['name'] . '` ' .
              'WHERE `post_id` IS NULL'
            );
      
      // Try to migrate posts
      if ($postIDs === null)
        $Where = 'NOT (post_id IS NULL)';
      
      $total = 0;
      $pixelsMigrated = 0;
      $migrationErrors = [];
      
      if (!$onlyCollect) {
        $duplicatePosts = [];
        
        if ($postIDs)
          foreach ($postIDs as $postID)
            $migrationErrors [$postID ['siteid'] . '/' . $postID ['postid']] = 'Post not found in Prosodia VGW';
      } else
        $collectedPostIDs = [];
      
      foreach ($prosodiaTables as $prosodiaTable) {
        if ($siteIds && !in_array ($prosodiaTable ['siteid'], $siteIds))
          continue;
        
        if ($postIDs) {
          $localPostIDs = [];
          
          foreach ($postIDs as $postID)
            if ($postID ['siteid'] == $prosodiaTable ['siteid'])
              $localPostIDs [] = (int)$postID ['postid'];
          
          if (count ($localPostIDs) == 0)
            continue;
          
          $Where = 'post_id IN (' . implode (',', $localPostIDs) . ')';
        }
        
        $resultSet = $GLOBALS ['wpdb']->get_results (
          'SELECT ' .
            '`post_id`, ' .
            '`public_marker`, ' .
            '`private_marker`, ' .
            'IF(LOCATE("/", `server`), LEFT(`server`, LOCATE("/", `server`) - 1), `server`) AS `server`, ' .
            '`user_id`, ' .
            '`is_marker_disabled` ' .
          'FROM `' . $prosodiaTable ['name'] . '` ' .
          'WHERE ' . $Where
        );
        
        foreach ($resultSet as $post) {
          if ($onlyCollect) {
            $collectedPostIDs [] = [
              'siteid' => $prosodiaTable ['siteid'],
              'postid' => $post->post_id,
            ];
            
            continue;
          }
          
          // Increate the counter
          $total++;
          try {
            $isMigrated = self::migrateDo (
              $prosodiaTable ['siteid'],
              $post->post_id,
              $post->public_marker,
              $post->private_marker,
              $post->server,
              null,
              $post->user_id,
              $post->is_marker_disabled,
              $Repair
            );
            
            if ($isMigrated === null) {
              $duplicatePosts [] = [
                'siteid' => $prosodiaTable ['siteid'],
                'postid' => $post->post_id,
              ];
              
              $migrationErrors [$prosodiaTable ['siteid'] . '/' . $post->post_id] = 'Post is already known to worthy';
            } else {
              $pixelsMigrated++;
              
              unset ($migrationErrors [$prosodiaTable ['siteid'] . '/' . $post->post_id]);
            }
          } catch (Exception $error) {
            $migrationErrors [$prosodiaTable ['siteid'] . '/' . $post->post_id] = $error->getMessage ();
          }
        }
      }
      
      if ($onlyCollect)
        return $collectedPostIDs;
      
      if ($pixelsMigrated > 0)
        self::resetMigrationStats ();
      
      // Return the result
      return [
        'total' => $total,
        'migrated' => $pixelsMigrated,
        'duplicates' => $duplicatePosts,
        'errors' => $migrationErrors,
      ];
    }
    // }}}
    
    // {{{ migrateDo
    /**
     * Create a database-entry for migration
     * 
     * @param int $siteId
     * @param int $postID
     * @param string $pixelPublic
     * @param string $pixelPrivate (optional)
     * @param string $Server (optional)
     * @param string $URL (optional)
     * @param int $userID (optional)
     * @param bool $Disabled (optional)
     * @param bool $Repair (optional) Assign new marker if an existing marker could not be assigned
     * 
     * @access private
     * @return bool
     **/
    private static function migrateDo ($siteId, $postID, $pixelPublic, $pixelPrivate, $Server, $URL, $userID = null, $Disabled = null, $Repair = false) {
      // Try to reconstruct some values
      if (($URL === null) && ($Server !== null) && ($pixelPublic !== null))
        $URL = 'http://' . $Server . '/na/' . $pixelPublic;
      elseif ((($Server === null) || ($pixelPublic === null)) && ($URL !== null) && is_array ($url = parse_url ($URL))) {
        if ($Server === null)
          $Server = $url ['host'];
        
        if ($pixelPublic === null)
          $pixelPublic = basename ($url ['path']);
      }

      if (($userID === null) || ($userID < 1)) {
        // Try to pick user-id from post to be migrated
        if ($thePost = wp_worthy_post::fromID ($postID, $siteId, false))
          $userID = $thePost->authorId;
        
        // Fallback to current user
        else
          $userID = wp_worthy::singleton ()->getUserID ();
      }
      
      // Sanity-Check if the post is already assigned
      if (
        ($presentPostPixel = $GLOBALS ['wpdb']->get_row ($GLOBALS ['wpdb']->prepare ('SELECT * FROM `' . wp_worthy_pixel::getTableName () . '` WHERE siteid=%d AND postid=%d', $siteId, $postID))) &&
        ($presentPostPixel->public != $pixelPublic)
      )
        throw new Exception ('Post already assigned to different pixel');
      
      // Sanity-Check if the pixel is already present
      if ($presentPixel = $GLOBALS ['wpdb']->get_row ($GLOBALS ['wpdb']->prepare ('SELECT * FROM `' . wp_worthy_pixel::getTableName () . '` WHERE public=%s', $pixelPublic))) {
        // Check ownership
        if (($presentPixel->userid != 0) && ($presentPixel->userid != $userID))
          throw new Exception ('Pixel already present at other user');
        
        // Check private
        if ($pixelPrivate && $presentPixel->private && ($pixelPrivate != $presentPixel->private))
          throw new Exception ('Private code mismatch');
        
        // Check assigned post
        if ($presentPixel->postid && (($presentPixel->postid != $postID) || ($presentPixel->siteid != $siteId)))
          throw new Exception ('Pixel already assigned to another post');
      }
      
      // Prepare data for pixel
      $pixelData = [
        'disabled' => ($Disabled ? 1 : 0),
        'userid' => $userID,
        'public' => $pixelPublic,
      ];
      
      $pixelDataFormat = [
        '%d',
        '%d',
        '%s',
      ];
      
      if ($pixelPrivate) {
        $pixelData ['private'] = $pixelPrivate;
        $pixelDataFormat [] = '%s';
      }
      
      if ($URL) {
        $pixelData ['url'] = $URL;
        $pixelDataFormat [] = '%s';
      }
      
      if ($Server) {
        $pixelData ['server'] = $Server;
        $pixelDataFormat [] = '%s';
      }
      
      if (!$presentPixel)
        foreach ([ 'private', 'server', 'url', 'postid' ] as $nullValue)
          if (!isset ($pixelData [$nullValue])) {
            $pixelData [$nullValue] = null;
            $pixelDataFormat [] = null;
          }
      
      // Make sure the marker is on the database
      if ($presentPixel)
        $pixelQuery = $GLOBALS ['wpdb']->update (
          wp_worthy_pixel::getTableName (),
          $pixelData,
          [ 'public' => $pixelPublic ],
          $pixelDataFormat,
          [ '%s' ]
        );
      else
        $pixelQuery = $GLOBALS ['wpdb']->insert (
          wp_worthy_pixel::getTableName (),
          $pixelData,
          $pixelDataFormat
        );
      
      if ($pixelQuery === false)
        throw new Exception ('Failed to insert migrated pixel');
      
      // Try to assign the marker to this post (this should never fail)
      $assignQuery = $GLOBALS ['wpdb']->query (
        $GLOBALS ['wpdb']->prepare (
          'UPDATE IGNORE `' . wp_worthy_pixel::getTableName () . '` ' .
          'SET ' .
            '`siteid`=%d, ' .
            '`postid`=%d ' .
          'WHERE ' .
            '`public`=%s AND ' .
            '(`postid` IS NULL OR (`siteid`=%d AND `postid`=%d))',
          $siteId,
          $postID,
          $pixelPublic,
          $siteId,
          $postID
        )
      );
      
      if ($assignQuery === false)
        throw new Exception ('Failed to assign post to migrated pixel');
      
      // Sanity-Check if the marker is assigned
      if (wp_worthy_pixel::getPixelForPost ($postID, $siteId, true))
        return true;
      
      if (!$Repair)
        return null;
      
      if (!wp_worthy_pixel::assignToPost (wp_worthy_post::fromID ($postID, $siteId), [ $userID ]))
        throw new Exception ('Failed to assign new pixel to post');
        
      return true;
    }
    // }}}
  }
