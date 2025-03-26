(function ($) {
  window.wp = window.wp || { };
  window.wp.worthy = window.wp.worthy || { };
  
  // {{{ postNotice
  /**
   * Display a notice to the user
   * 
   * @param string message
   * @param string classes
   * @param mixed dismissAction (optional)
   * 
   * @access public
   * @return void
   **/
  window.wp.worthy.postNotice = function (message, classes, dismissAction = null) {
    // Make sure we have a notices-area
    if (!(p = document.getElementById ('worthy-notices')))
      return;
    
    // Embed message into a paragraph
    if (message.indexOf ('<p>') < 0)
      message = '<p><strong>Worthy:</strong> ' + message + '</p>';
    
    // Append the message to output
    c = document.createElement ('div');
    c.className = 'worthy-notice notice fade ' + classes + (dismissAction ? ' is-dismissible' : '');
    c.innerHTML = message;
    
    if (dismissAction && (dismissAction !== true))
      c.setAttribute ('data-worthy-dismiss-action', dismissAction);
    
    p.appendChild (c);
  };
  // }}}
  
  $(document).ready (function () {
    // Enqueue another callback to be at the end of the queue
    $(document).ready (
      function () {
        // Check if we have a dismissable notice
        $('.worthy-notice button.notice-dismiss').click (
          function () {
            // Find action to trigger
            let dismissAction = $(this).parent ('.worthy-notice').attr ('data-worthy-dismiss-action');
            
            if (!dismissAction || (dismissAction.length < 3))
              return;
            
            let adminUrl = location.href.substr (0, location.href.lastIndexOf ('/'));
            
            if (adminUrl.substr (-8, 8) == '/network')
              adminUrl = adminUrl.substr (0, adminUrl.length - 8);
            
            $.post (
              adminUrl + '/admin-post.php',
              {
                'action' : dismissAction
              }
            );
          }
        );
      }
    );
  });
}(jQuery));
