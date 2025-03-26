(function ($) {
  window.wp = window.wp || { };
  window.wp.worthy = {
    setup : false,
    elem : false,
    qualified : false,
    qualified_length : 1800,
    qualified_warn : 1600,
    contentEditor : null,
    'autoAssign' : null,
    
    counter : function () {
      if (!this.setup) {
        if (!(e = document.getElementById ('wp-word-count')))
          return;
      
        e.appendChild (document.createElement ('br'));
        e.appendChild (document.createTextNode (wpWorthyLang.counter + ': '));
        this.elem = document.createElement ('spam');
        this.elem.setAttribute ('class', 'character-count wp-worthy-counter');
        this.elem.innerHTML = '0';
        e.appendChild (this.elem);
        
        this.setup = true;
      }
      
      if (!this.contentEditor || this.contentEditor.isHidden ())
        text = $('#content').val ();
      else
        text = this.contentEditor.getContent ({ format: 'raw' });
      
      let len = 0;
      
      if (text !== false)
        len = window.wp.worthy.getCharacterCount (text);
      
      this.qualified = (((len >= this.qualified_length) || $('#wp-worthy-lyric').prop ('checked')) && !$('#wp-worthy-ignore').prop ('checked'));
      
      this.elem.innerHTML = len;
      
      if (this.qualified)
        this.elem.setAttribute ('class', 'character-count wp-worthy-counter wp-worthy-length-qualified');
      else if ((len > this.qualified_warn) && (len < this.qualified_length))
        this.elem.setAttribute ('class', 'character-count wp-worthy-counter wp-worthy-length-partial');
      else
        this.elem.setAttribute ('class', 'character-count wp-worthy-counter wp-worthy-length-short');
      
      if ($('#wp-worthy-embed').prop ('disabled') == this.qualified) {
        if (!this.qualified) {
          $('#wp-worthy-embed').prop ('ochecked', $('#wp-worthy-embed').prop ('checked'));
          $('#wp-worthy-embed').prop ('checked', false);
        } else
          $('#wp-worthy-embed').prop ('checked', $('#wp-worthy-embed').prop ('ochecked'));
          
        $('#wp-worthy-embed').prop ('disabled', !this.qualified);
      }
      
      if (this.qualified && ($('#wp-worthy-embed').attr ('data-worthy-autoassign') == 1))
        $('#wp-worthy-embed').prop ('checked', true).attr ('data-worthy-autoassign', '0');
      
      $('#wp-worthy-embed-label').css ('font-weight', this.qualified && !$('#wp-worthy-embed').prop ('checked') ? 'bold' : 'normal');
    },
    
    // {{{ getCharacterCount
    /**
     * Retrive the character-length of a given text-input
     * 
     * @param string text
     * 
     * @access public
     * @return int
     **/
    getCharacterCount : function (text) {
      // Remove filtered shortcodes
      for (let shortcode of wpWorthyLang.shortcode_filter.split (','))
        text = wp.shortcode.replace (
          shortcode,
          text,
          function () {
            // Return an empty string would result in shortcode being not replaced, so we return a space here
            return ' ';
          }
        );
      
      // Try to parse Shortcodes a bit
      let theShortcode = wp.shortcode.next ('\\w+', text);
      
      while (theShortcode) {
        text = wp.shortcode.replace (
          theShortcode.shortcode.tag,
          text,
          function (theShortcode) {
            try {
              // Return an empty string would result in shortcode being not replaced, so we return a space here
              return (theShortcode.attrs.named.title || '') + ' ' + (theShortcode.attrs.named.caption || '');
            } catch (e) {
              console.error (e);
            }
          }
        );
        
        theShortcode = wp.shortcode.next ('\\w+', text, theShortcode.index + 1);
      }
      
      // Get plaintext from html
      let pelem = document.createElement ('div');
      
      pelem.innerHTML = text.replace (/(<([^>]+)>)/ig, '').replace ("\r\n", ' ').replace ("\n", ' ').replace ("\r", ' ').replace ('  ', ' ').trim ();
      
      // Return the length of the result
      if (pelem.childNodes.length == 0)
        return 0;
      
      return (pelem.textContent || pelem.innerText).length;
    },
    // }}}
    
    // {{{ bulkSingle
    /**
     * Perform a bulk-action for a single post-id
     * 
     * @param enum action
     * @param int postid
     * 
     * @access public
     * @return void
     **/
    'bulkSingle' : function (action, postid, siteid, pixelid) {
      let selectElements = document.getElementsByName ('post[]'),
          elementSelected = false;
      
      for (let i = 0; i < selectElements.length; i++) {
        if (pixelid)
          selectElements [i].checked = (selectElements [i].id == 'cb-select-pixelid-' + pixelid);
        else
          selectElements [i].checked = (selectElements [i].value == siteid + '/' + postid);
        
        if (selectElements [i].checked)
          elementSelected = true;
      }
      
      if (!elementSelected)
        throw 'No element for selection found';
      
      let actionElement = document.getElementsByName ('action'),
          actionFound = false;
      
      if (!actionElement.length)
        throw 'No action-elements found';
      
      for (let i = 0; i < actionElement.length; i++)
        if (actionElement [i].localName == 'select')
          for (j = 0; j < actionElement [i].options.length; j++)
            if (actionElement [i].options [j].value == action) {
              actionElement [i].selectedIndex = j;
              actionFound = true;
              
              break;
            }
      
      if (!actionFound) {
        let dummyAction = document.createElement ('option');
        
        dummyAction.value= action;
        dummyAction.text = action;
        
        actionElement [0].add (dummyAction);
        actionElement [0].selectedIndex = actionElement [0].options.length - 1;
      }
      
      if (actionElement [0] && actionElement [0].form) {
        if (!actionElement [0].form.requestSubmit)
          actionElement [0].form.dispatchEvent (
            new SubmitEvent ('submit', { 'cancelable': true })
          );
        else
          actionElement [0].form.requestSubmit ();
      }
    }
    // }}}
  }
  
  $(document).ready (
    function () {
      $('div.worthy-signup form').on (
        'submit',
        function () {
          if (!$(this).find ('input#wp-worthy-accept-tac').prop ('checked')) {
            alert (wpWorthyLang.accept_tac);
            
            return false;
          }
        }
      );
      
      $('span.wp-worthy-inline-title').on (
        'click',
        function () {
          let box = document.createElement ('div'),
              textbox = document.createElement ('input'),
              label = document.createElement ('span');
          
          box.setAttribute ('class', 'wp-worthy-inline-title');
          
          textbox.setAttribute ('type', 'text');
          textbox.setAttribute ('name', this.getAttribute ('id'));
          textbox.setAttribute ('class', 'wp-worthy-inline-title');
          textbox.value = this.textContent.substr (0, this.textContent.lastIndexOf ("\n"));
          
          $(textbox).on (
            'change input',
            function () {
              label.textContent = '(' + this.value.length + ' ' + wpWorthyLang.characters + ')';
            }
          );
          
          label.setAttribute ('class', 'wp-worthy-inline-counter');
          
          box.appendChild (textbox);
          box.appendChild (label);
          
          this.parentNode.replaceChild (box, this);
          $(textbox).trigger ('change');
        }
      );
      
      $('span.wp-worthy-inline-content').on (
        'click',
        function () {
          let textbox = document.createElement ('textarea');
          
          textbox.setAttribute ('name', this.getAttribute ('id'));
          textbox.setAttribute ('class', 'wp-worthy-inline-content');
          textbox.value = this.textContent;
          textbox.style.height = this.clientHeight + 'px';
          
          this.parentNode.replaceChild (textbox, this);
        }
      );
      
      $('#content').on (
        'input keyup',
        function () {
          window.wp.worthy.counter ();
        }
      );
      
      $(document).on (
        'tinymce-editor-init',
        function (ev, editorInstance) {
          if (editorInstance.id != 'content')
            return;
          
          window.wp.worthy.contentEditor = editorInstance;
          
          editorInstance.on (
            'nodechange keyup',
            function () {
              tinyMCE.triggerSave ();
              window.wp.worthy.counter ();
            }
          );
        }
      );
      
      window.wp.worthy.counter ();
      
      // Insert context-navigation
      let wpw_subnav = $('<div />').addClass ('subnav');
      
      $('#wp-worthy .stuffbox h2[id]').each (
        function () {
          wpw_subnav.append ($('<a />').attr ('href', '#' + $(this).attr ('id')).text ($(this).text ()));
        }
      );
      
      if (wpw_subnav.children ().length > 1)
        $('#wp-worthy .nav-tab-wrapper').after (wpw_subnav)
      
      // Setup bulk-action-selector
      if ($('th#cb input[type=checkbox]').prop ('checked'))
        $('th.check-column input[type=checkbox]').prop ('checked', true);
      
      // Toggle display of settings related to account-sharing
      $('select#wp-worthy-account-sharing').on (
        'change',
        function () {
          $('form.worthy-form .wp-worthy-no-sharing').css ('display', (this.options [this.selectedIndex].value == 0 ? 'block' : 'none'));
        }
      ).trigger ('change');
      
      $('input#wp-worthy-enable-account-sharing').on (
        'change',
        function () {
          $('#wp-worthy-default-account-box, #wp-worthy-box-sharing-container').css ('display', (this.checked ? 'block' : 'none'));
        }
      ).trigger ('change');
    }
  );
  
  // Worthy Premium Store
  $(document).ready (
    function () {
      // {{{ wp-worthy-shop::submit
      /**
       * Place an order on worthy premium shop
       * 
       * @access ui
       * @return void
       **/
      $('#wp-worthy-shop').on (
        'submit',
        function () {
          // Make sure anything was selected
          let have_good = false;
          
          $('#wp-worthy-shop-goods input[type=radio]').each (
            function () {
              if (this.checked && (this.value != 'none'))
                have_good = true;
            }
          );
          
          if (!have_good) {
            alert (wpWorthyLang.no_goods);
            
            return false;
          }
          
          // Make sure terms-and-conditions were accepted
          if (!$(this).find ('input#wp-worthy-accept-tac').prop ('checked')) {
            alert (wpWorthyLang.accept_tac);
            
            return false;
          }
        }
      );
      // }}}
      
      // Setup totals-display on our premium-shop
      $('#wp-worthy-shop-goods input[type=radio]').on (
        'change',
        function () {
          let total = 0,
              total_tax = 0;
          
          $('#wp-worthy-shop-goods input[type=radio]').each (
            function () {
              if (!this.checked || (this.value == 'none'))
                return;
              
              total += parseFloat ($(this).attr ('data-value'));
              total_tax += parseFloat ($(this).attr ('data-tax'));
            }
          );
          
          $('#wp-worthy-shop-price').html (total.toFixed (2).replace ('.', ','));
          $('#wp-worthy-shop-tax').html (total_tax.toFixed (2).replace ('.', ','));
        }
      );
      
      // Update totals for pre-selected goods
      $('#wp-worthy-shop-goods input[type=radio][checked]').trigger ('change');
    }
  );
  
  // Premium-Tab
  $(document).ready (
    function () {
      $('button[value=wp-worthy-premium-sync-pixels]').on (
        'click',
        function (ev) {
          let button = $(this),
              syncStatus = [ 0, 1, 2, 3, 4 ],
              elemStatus = $('<span />').insertAfter (button);
          
          let syncFunc = function () {
            // Check if we are done
            if (syncStatus.length < 1) {
              button.show ();
              elemStatus.remove ();
              
              window.location.reload (true);
              
              return;
            }
            
            // Hide the button
            button.hide ();
            
            // Start the request
            let status = syncStatus.shift ();
            
            const statusMap = {
              '0' : wpWorthyLang.not_counted,
              '1' : wpWorthyLang.not_qualified,
              '2' : wpWorthyLang.partial_qualified, 
              '3' : wpWorthyLang.qualified,
              '4' : wpWorthyLang.reported
            }
            
            elemStatus.text (wpWorthyLang.syncronizing.replace ('%s', statusMap [status]));
            
            $.post (
              location.href.replace ('/admin.php', '/admin-post.php'),
              {
                'action': 'wp-worthy-premium-sync-pixels',
                'wp-worthy-marker-status': status
              }
            ).done (
              syncFunc
            ).fail (
              function () {
                if (status < 1)
                  return syncFunc ();
                
                syncStatus = [ ];
                elemStatus.text (wpWorthyLang.sync_error);
              }
            );
          };
          
          // Start the initial sync
          syncFunc ();
          
          // Stop the event
          ev.stopPropagation ();
          ev.preventDefault ();
        }
      );
    }
  );
  
  // Action-buttons
  $(document).ready (
    function () {
      $('#wp-worthy button[data-action]').on (
        'click',
        function (ev) {
          try {
            wp.worthy.bulkSingle (
              $(this).attr ('data-action'),
              $(this).attr ('data-postid'),
              $(this).attr ('data-siteid'),
              $(this).attr ('data-pixelid')
            );
          } catch (e) {
            console.error (e);
          }
          
          ev.preventDefault ();
        }
      );
      
      $('#wp-worthy select[data-action]').on (
        'change',
        function (ev) {
          try {
            wp.worthy.bulkSingle (
              $(this).attr ('data-action'),
              $(this).attr ('data-postid'),
              $(this).attr ('data-siteid'),
              $(this).attr ('data-pixelid')
            );
          } catch (e) {
            console.error (e);
          }
          
          ev.preventDefault ();
        }
      );
      
      let conditionSources = [ ];
      
      $('[data-worthy-if]').each (
        function () {
          let conditionElement = $('#' + $(this).attr ('data-worthy-if'));
          
          if (conditionSources.indexOf ($(this).attr ('data-worthy-if')) < 0)
            $('#' + $(this).attr ('data-worthy-if')).on (
              'change',
              function () {
                if (this.checked)
                  $('[data-worthy-if=' + this.id + ']').show ();
                else
                  $('[data-worthy-if=' + this.id + ']').hide ();
              }
            ).trigger ('change');
        }
      );
      
      function modal (modalContent, headerContent, footerContent) {
        let resolveFunc = () => null;
        let rejectFunc = () => null;
        
        let modalPromise = new Promise (
          function (resolve, reject) {
            resolveFunc = resolve;
            rejectFunc = reject;
          }
        );

        let modalContainer = $('<div />').css ({
          'position': 'fixed',
          'z-index': 10000,
          'display': 'flex',
          'left': 0,
          'top': 0,
          'width': '100%',
          'height': '100%',
          'overflow': 'auto',
          'background-color': 'rgba(0,0,0,0.4)',
          'justify-content': 'center',
          'align-items': 'center'
        });

        modalPromise.close = function (result) {
          // Remove from DOM
          modalContainer.remove ();

          // Forward the result
          resolveFunc (result);
        };

        modalPromise.cancel = function (reason) {
          // Remove from DOM
          modalContainer.remove ();

          // Forward the rejection
          rejectFunc (reason);
        };

        modalContainer.on (
          'click',
          function (ev) {
            if (
              ev.originalEvent &&
              ev.originalEvent.originalTarget !== modalContainer [0]
            )
              return;

            modalPromise.cancel ('Closed via background');
          }
        );

        let modalBox = $('<div />').css ({
          'position': 'relative',
          'background-color': '#fff',
          'padding': '20px',
          'border': '1px solid #888',
          'min-width': '100px',
          'min-height': '100px'
        }).appendTo (modalContainer);

        if (typeof headerContent === 'string')
          headerContent = $('<h2 />').text (headerContent);

        let closeButton = $('<button />').text ('×').css ({
          'position': 'absolute',
          'top': 0,
          'right': 0,
          'color': '#000',
          'font-size': '24px',
          'user-select': 'none',
          'border': 'none',
          'display': 'inline-block',
          'padding': '8px 16px',
          'cursor': 'pointer',
        }).on (
          'click',
          () => modalPromise.cancel ('Closed via button')
        );

        closeButton.appendTo (modalBox);

        if (headerContent)
          $(headerContent).appendTo (modalBox);

        $(modalContent).appendTo (modalBox);

        if (footerContent)
          $('<footer />').appendTo (modalBox).append ($(footerContent));

        modalContainer.appendTo (document.body);

        return modalPromise;
      }
      
      $('form.wp-worthy-might-message').on (
        'submit',
        function (ev) {
          // Only catch attempts to submit messages to VG WORT
          if ($(this.elements ['action']).val () !== 'wp-worthy-premium-report-posts')
            return;
          
          // Also do nothing if consent was already given
          if ($(this.elements ['aiDisclaimer']).val () === 'accepted')
            return;

          let form = this;
          let closeFunc = () => null;
          let confirmFunc = () => null;
          
          let modalPromise = modal (
            $('<p />').css (
              'max-width',
              '400px'
            ).text (
              wpWorthyLang.aiDisclaimer
            ),
            wpWorthyLang.aiDisclaimerTitle,
            $('<div />').css ('text-align', 'right').append (
              $('<button />').text (wpWorthyLang.Yes).on (
                'click',
                function () {
                  if (form.elements ['aiDisclaimer'])
                    form.elements ['aiDisclaimer'].value = 'accepted';
                  else
                    $('<input />').attr ({
                      'type': 'hidden',
                      'name': 'aiDisclaimer',
                      'value': 'accepted'
                    }).appendTo ($(form));

                  confirmFunc ();
                }
              )
            ).append (
              $('<button />').text (wpWorthyLang.No).css ('margin-left', '5px').on ('click', () => closeFunc ())
            )
          );

          modalPromise.then (
            () => this.submit (),
            () => null
          );

          confirmFunc = modalPromise.close;
          closeFunc = modalPromise.cancel;
          
          // Don't submit the form
          ev.preventDefault ();
          ev.stopPropagation ();
          
          return false;
        }
      );
    }
  );
}(jQuery));
