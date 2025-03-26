<?php

  class wp_worthy_premium {
    /* private */ const META_USERNAME = 'worthy_premium_username';
    /* private */ const META_STATUS = 'worthy_premium_status';
    
    /* public */ const PIXEL_UPDATE_INTERVAL = 604800;
    
    /* private */ const STATUS_CACHE_MAX_AGE = 86400; // one day
    
    /* Default pixel-statis to synchronize */
    private static $defaultPixelStatis = [
      wp_worthy::MARKER_STATUS_UNREACHED,
      wp_worthy::MARKER_STATUS_PARTIAL,
      wp_worthy::MARKER_STATUS_REACHED,
      wp_worthy::MARKER_STATUS_REPORTED,
    ];
    
    /* Instance of worthy */
    private $worthyInstance = null;
    
    /* User-ID on wordpress for our user */
    private $userID = null;
    
    /* Cached premium-status */
    private $premiumStatus = null;
    
    /* Cached instance of SOAP-Client */
    private $soapClient = null;
    
    // {{{ cronDaily
    /**
     * Run daily cron-jobs for premium
     * 
     * @access public
     * @return void
     **/
    public static function cronDaily () {
      // Sanity-Check that we are doing a cron
      if (!defined ('DOING_CRON') || !DOING_CRON)
        return;
      
      // Cycle through all available users with credentials
      $dayOfYear = (int)date ('z');
      
      foreach (get_users ([ 'meta_key' => self::META_USERNAME ]) as $wordpressUser) {
        try {
          // Create an instance for this user
          $premiumInstance = new static (wp_worthy::singleton (), (int)$wordpressUser->ID);
          
          // Force an update of premium-status
          # TODO: Move status-updates to hourly
          $premiumInstance->refreshStatus (true);
          
          // Check wheter to do an update of pixel-status
          $premiumUsername = $premiumInstance->getUsername ();
          
          if ($dayOfYear % 7 == ord ($premiumUsername [0]) % 7)
            $premiumInstance->refreshPixelStatus ();
        } catch (\Throwable $error) {
          // No-Op
        }
      }
    }
    // }}}
    
    // {{{ __construct
    /**
     * Create a new interface for Premium
     * 
     * @param wp_worthy $worthyInstance
     * @param int $userID
     * 
     * @access friendly
     * @return void
     **/
    function __construct (wp_worthy $worthyInstance, int $userID) {
      $this->worthyInstance = $worthyInstance;
      $this->userID = $userID;
    }
    // }}}
    
    // {{{ isPremium
    /**
     * Check if premium is enabled for this user
     * 
     * @access public
     * @return bool
     **/
    public function isPremium () {
      try {
        if ($this->premiumStatus === null)
          $this->refreshStatus ();
        
        return (
          ($this->premiumStatus == 'testing') ||
          ($this->premiumStatus == 'testing-pending') ||
          ($this->premiumStatus == 'registered')
        );
      } catch (\Throwable $error) {
        return null;
      }
    }
    // }}}
    
    // {{{ getUsername
    /**
     * Retrive VG WORT Username of this user
     * 
     * @access public
     * @return string NULL if none given
     **/
    public function getUsername () {
      $premiumUsername = get_user_meta ($this->userID, self::META_USERNAME, true);
      
      if (strlen ($premiumUsername) == 0)
        return null;
      
      return $premiumUsername;
    }
    // }}}
    
    // {{{ refreshStatus
    /**
     * Refresh status of user's worthy premium subscribtion
     * 
     * @param bool $forceRefresh (optional)
     * 
     * @access public
     * @return array
     * @throws \SoapFault
     **/
    public function refreshStatus ($forceRefresh = false) {
      // Check if there is a cached version of our status
      $orgForceRefresh = $forceRefresh;
      
      if (!is_array ($cachedStatus = get_user_meta ($this->userID, self::META_STATUS, true))) {
        $cachedStatus = [ 'Status' => 'unregistered' ];
        $forceRefresh = true;
      
      // Check age of cache
      } elseif (
        (($cacheLastUpdate = get_user_meta ($this->userID, 'worthy_premium_status_updated', true)) > 0) &&
        (time () - $cacheLastUpdate > self::STATUS_CACHE_MAX_AGE)
      )
        $forceRefresh = true;
      
      // Check wheter a refresh was forced
      if ($forceRefresh) {
        try {
          // Try to get instance of our SOAP-Client
          $soapClient = $this->getSOAPClient (true);
          
          // Try to retrive the status
          $latestStatus = $soapClient->serviceAccountStatus ($soapClient->Username, $soapClient->Password);
          
          // Check if we get an unregistered status - this should not happen if we have credentials stored
          if ($latestStatus ['Status'] == 'unregistered')
            $latestStatus = $soapClient->serviceSignup ($soapClient->Username, $soapClient->Password, true);
          
          // Convert time-stamps from result
          $latestStatus ['ValidFrom'] = strtotime ($latestStatus ['ValidFrom']);
          $latestStatus ['ValidUntil'] = strtotime ($latestStatus ['ValidUntil']);
        } catch (Throwable $error) {
          $latestStatus = $cachedStatus;
          
          // Forward the exception if refresh was forced
          if ($orgForceRefresh)
            throw $error;
        }
      } else
        $latestStatus = $cachedStatus;
      
      // Store the status
      // We need to do it *before* the unregistered-check in order to learn password-errors
      if ($forceRefresh || ($latestStatus != $cachedStatus)) {
        update_user_meta ($this->userID, self::META_STATUS, $latestStatus);
        update_user_meta ($this->userID, 'worthy_premium_status_updated', time ());
      }
      
      // Update internal state
      $this->premiumStatus = $latestStatus ['Status'];
      
      return $latestStatus;
    }
    // }}}
    
    // {{{ refreshPixelStatus
    /**
     * Refresh status of our pixels
     * 
     * @param array $pixelStatis (optional) Synchronize these marker-statuses only
     * 
     * @access private
     * @return int
     **/
    public function refreshPixelStatus (array $pixelStatis = null) {
      // Define how to sync each pixel-status
      static $statusParameterMap = [
        wp_worthy::MARKER_STATUS_UNCOUNTED => [ false, false, false, false, false, false ],
        wp_worthy::MARKER_STATUS_UNREACHED => [ false,  true, false,  true, false, false ],
        wp_worthy::MARKER_STATUS_PARTIAL   => [ false,  true, false, false,  true, false ],
        wp_worthy::MARKER_STATUS_REACHED   => [ false,  true, false, false, false,  true ],
        wp_worthy::MARKER_STATUS_REPORTED  =>  [ true,  true, false,  true,  true,  true ],
      ];
      
      // Try to get a handle of our SOAP-Client
      $soapClient = $this->getSOAPClient (true);
      
      // Retrive last updates
      $lastUpdates = get_user_meta ($this->userID, 'worthy_premium_markers_updated', true);
      
      // Make sure we have a session
      $soapSession = $this->getSession ();
      
      // Make sure lastUpdates is valid (we stored a single value for this in the past)
      if (!is_array ($lastUpdates)) {
        $lastTimestamp = (int)$lastUpdates;
        $lastUpdates = [];
        
        foreach (array_keys ($statusParameterMap) as $status)
          $lastUpdates [$status] = $lastTimestamp;
      }
      
      // Sanatize marker-stati to sync
      if (!is_array ($pixelStatis)) {
        $pixelStatis = self::$defaultPixelStatis;
        
        // Skip pixel-statis that were synchronized recently
        foreach ($pixelStatis as $index=>$pixelStatus)
          if (isset ($lastUpdates [$pixelStatus]) && (time () - $lastUpdates [$pixelStatus] < $this::PIXEL_UPDATE_INTERVAL))
            unset ($pixelStatis [$index]);
      }
      
      // Do the sync
      $pixelUpdates = 0;
      
      foreach ($pixelStatis as $pixelStatus) {
        // Make sure we can sync this status
        if (!isset ($statusParameterMap [$pixelStatus]))
          continue;
        
        // Prepare the parameters
        $updateParameters = $statusParameterMap [$pixelStatus];
        array_unshift ($updateParameters, $soapSession);
        
        // Request all pixels from webservice
        $pixelList = call_user_func_array ([ $soapClient, 'markersSearch' ], $updateParameters);
        
        // Update our database
        $pixelsUpdate = $this->updatePixelStatusSet ($pixelList, $pixelStatus);
        $pixelUpdates += $pixelsUpdate ['changed'];
        
        // Add a special check for pixels with reached-status
        if (($pixelStatus == wp_worthy::MARKER_STATUS_REACHED) && (count ($pixelList) > $pixelsUpdate ['matched']))
          update_user_meta ($this->userID, 'wp-worthy-unknown-reportable-pixels', count ($pixelList) - $pixelsUpdate ['matched']);
        else
          delete_user_meta ($this->userID, 'wp-worthy-unknown-reportable-pixels');
        
        // Update the time
        $lastUpdates [$pixelStatus] = time ();
        
        update_user_meta ($this->userID, 'worthy_premium_markers_updated', $lastUpdates);
      }
      
      // Update statistics
      if ($pixelUpdates > 0) {
        update_option ('worthy_premium_marker_updates', get_option ('worthy_premium_marker_updates', 0) + $pixelUpdates);
        update_user_meta ($this->userID, 'worthy_premium_marker_updates', intval (get_user_meta ($this->userID, 'worthy_premium_marker_updates', true)) + $pixelUpdates);
      }
      
      return $pixelUpdates;
    }
    // }}}
    
    // {{{ updatePixelStatusSet
    /**
     * Update a set of pixels on our database
     * 
     * @param array $pixelSet
     * @param int $pixelStatus
     * 
     * @access private
     * @return array
     **/
    private function updatePixelStatusSet (array $pixelSet = null, $pixelStatus) {
      // Check if there are any markers
      if (!$pixelSet || (count ($pixelSet) == 0))
        return [
          'changed' => 0,
          'matched' => 0,
        ];
      
      // Preprocess values
      foreach ($pixelSet as $pixelIndex=>$pixelCode)
        $pixelSet [$pixelIndex] = $GLOBALS ['wpdb']->prepare ('%s', $pixelCode);
      
      // Reindex pixels that will advance to an interesting status
      $possiblyReportable =
        ($pixelStatus == wp_worthy::MARKER_STATUS_PARTIAL) ||
        ($pixelStatus == wp_worthy::MARKER_STATUS_REACHED);
      
      // Update the database
      $GLOBALS ['wpdb']->query (
        'UPDATE `' . $this->worthyInstance->getTablename ('worthy_markers', 0) . '` ' .
        'SET ' .
          '`status`="' . (int)$pixelStatus . '", ' .
          '`status_date`="' . time () . '", ' .
          (!$possiblyReportable ? '`reportable`="0", ' : '') .
          '`userid`="' . (int)$this->userID . '" ' .
        'WHERE ' .
          '`private` IN (' . implode (',', $pixelSet) . ') AND ' .
          '((`status` IS NULL) OR NOT (`status`="' . (int)$pixelStatus . '")) AND ' .
          '(`userid`="' . (int)$this->userID . '" OR `userid`="0")'
      );
      
      // Reindex posts to make sure length is correct (and update reportable-status as well)
      if ($possiblyReportable) {
        $changedPosts = $GLOBALS ['wpdb']->get_results (
          'SELECT ' .
            '`siteid`, ' .
            '`postid` ' .
          'FROM `' . $this->worthyInstance->getTablename ('worthy_markers', 0) . '` ' .
          'WHERE ' .
            '`private` IN (' . implode (',', $pixelSet) . ') AND ' .
            '(NOT (`status`="' . (int)$pixelStatus . '")) AND ' .
            '(`userid`="' . (int)$this->userID . '" OR `userid`="0")',
          ARRAY_A
        );
        
        foreach ($changedPosts as $changedPost)
          try {
            $postLength = $this->worthyInstance->reindexPost ($changedPost ['postid'], $changedPost ['siteid']);
          } catch (Throwable $error) {
            // No-Op
          }
      }
      
      // Check for number of matches
      if ($pixelStatus == wp_worthy::MARKER_STATUS_REACHED)
        $pixelCount = $GLOBALS ['wpdb']->get_var (
          'SELECT COUNT(*) ' .
          'FROM `' . $this->worthyInstance->getTablename ('worthy_markers', 0) . '` ' .
          'WHERE private IN (' . implode (',', $pixelSet) . ')'
        );
      else
        $pixelCount = null;
  
      return [
        'changed' => $GLOBALS ['wpdb']->rows_affected,
        'matched' => $pixelCount,
      ];
    }
    // }}}
    
    // {{{ createWebrange
    /**
     * Create a new webrange for a given pixel
     * 
     * @param string $pixelPrivate
     * @param string $webrangeURL
     * @param bool $ownSite
     * 
     * @access public
     * @return void
     **/
    public function createWebrange (string $pixelPrivate, string $webrangeURL, bool $ownSite) /* : void */ {
      // Get interface to webservice
      $soapClient = $this->getSOAPClient ();
      $soapSession = $this->getSession ();
      
      // Just forward the call
      $soapClient->webareaCreate ($soapSession, $pixelPrivate, $webrangeURL, $ownSite);
    }
    // }}}
    
    // {{{ getSOAPClient
    /**
     * Retrive instance of a SOAP-Client for this user
     * 
     * @param bool $requireCredentials (optional)
     * 
     * @access public
     * @return \SoapClient
     * @throws \SoapFault
     * @throws \Exception
     **/
    public function getSOAPClient ($requireCredentials = true) {
      // Check for a cached client
      if ($this->soapClient)
        return $this->soapClient;
      
      // Check if SOAP-Support is available
      if (!class_exists ('\\SoapClient'))
        throw new \Exception ('You need to have the SOAP- and OpenSSL-Extension for PHP available to use Worthy Premium.');
      
      // Retrive credentials
      if (
        (
          !($premiumUsername = get_user_meta ($this->userID, self::META_USERNAME, true)) ||
          !($premiumPassword = get_user_meta ($this->userID, 'worthy_premium_password', true))
        ) && 
        $requireCredentials
      )
        throw new \Exception ('VG WORT User-Credentials unavailable');
      
      # TODO: Maybe encrypt/decrypt credentials in some way...
      
      if (
        !($premiumServer = get_user_meta ($this->userID, 'worthy_premium_server', true)) ||
        ($premiumServer != 'devel')
      )
        $soapURL = 'https://api.wp-worthy.de/soap/?wsdl';
      else
        $soapURL = 'http://sandbox.wp-worthy.de/api/?wsdl';
      
      // Sometimes SOAP-Calls take really long...
      ini_set ('default_socket_timeout', 600);
      $GLOBALS ['wpdb']->query ('SET wait_timeout=600');
      
      // Try to create the SOAP-Client
      $soapClient = new \SoapClient (
        $soapURL,
        [
          'features' => \SOAP_SINGLE_ELEMENT_ARRAYS,
          'trace' => (defined ('WORTHY_DEBUG') && WORTHY_DEBUG),
          'cache_wsdl' => (!defined ('WORTHY_DEBUG') || !WORTHY_DEBUG ? \WSDL_CACHE_DISK : \WSDL_CACHE_NONE),
        ]
      );
      
      // Store the credentials on the account
      if ($premiumUsername && $premiumPassword) {
        $soapClient->Username = $premiumUsername;
        $soapClient->Password = $premiumPassword;
        
        $this->soapClient = $soapClient;
      }
      
      // Return the client
      return $soapClient;
    }
    // }}}
    
    // {{{ getSession
    /**
     * Retrive the authorization-parameter for SOAP-Calls
     * 
     * @param bool $allowUC (optional) Allow session to contain user-credentials only
     * 
     * @access private
     * @return object
     * @throws \SoapFault
     **/
    public function getSession ($allowUC = false) {
      // Retrive instance of our SOAP-Client
      $soapClient = $this->getSOAPClient ();
      
      // Check for a cached session
      $cachedSession = get_user_meta ($this->userID, 'worthy_premium_session', true);
      
      if (
        is_object ($cachedSession) &&
        (($lastUpdate = (time () - $cachedSession->Last)) < 360)
      ) {
        // Check wheter to update last action on session
        if ($lastUpdate > 4) {
          $cachedSession->Last = time ();
          
          update_user_meta ($this->userID, 'worthy_premium_session', $cachedSession);
        }
        
        return $cachedSession->Authorization;
      }
      
      // Try to create a new session
      $sessionRequest = new stdClass ();
      
      $sessionRequest->Last = time ();
      $sessionRequest->Authorization = new stdClass ();
      $sessionRequest->Authorization->Username = $soapClient->Username;
      $sessionRequest->Authorization->Password = $soapClient->Password;
      $sessionRequest->Authorization->SessionID = null;

      // Try to log in
      try {
        $loginResult = $soapClient->serviceLogin (
          $soapClient->Username,
          $soapClient->Password
        );

        if (is_array ($loginResult)) {
          $sessionRequest->Authorization->SessionID = $loginResult ['SessionID'];
          
          // Patch additional information into cached status
          if ((isset ($loginResult ['MessagePending']) || isset ($loginResult ['Ready'])) &&
              is_array ($cachedStatus = get_user_meta ($this->userID, self::META_STATUS, true))) {
            if (isset ($loginResult ['MessagePending']))
              $cachedStatus ['MessagePending'] = $loginResult ['MessagePending'];

            if (isset ($loginResult ['Ready']))
              $cachedStatus ['Ready'] = $loginResult ['Ready'];

            update_user_meta ($this->userID, self::META_STATUS, $cachedStatus);
          }
        } else
          $sessionRequest->Authorization->SessionID = $loginResult;

        unset (
          $sessionRequest->Authorization->Username,
          $sessionRequest->Authorization->Password
        );
      } catch (\SoapFault $error) {
        // Catch login-errors
        if (
          is_array ($cachedStatus = get_user_meta ($this->userID, self::META_STATUS, true)) &&
          !in_array ($cachedStatus ['Status'], [ 'unregistered', 'expired', 'testing-expired' ]) &&
          ($error->getMessage () == 'Invalid login')
        ) {
          $cachedStatus ['Status'] = 'unregistered';
          
          update_user_meta ($this->userID, self::META_STATUS, $cachedStatus);
        }
        
        // Check wheter to return only user-credentials if a normal session-setup failed
        if ($allowUC)
          return $sessionRequest->Authorization;
        
        throw $error;
      }
      
      // Store new session on cache
      update_user_meta ($this->userID, 'worthy_premium_session', $sessionRequest);
      
      // Return the session-token
      return $sessionRequest->Authorization;
    }
    // }}}
  }
