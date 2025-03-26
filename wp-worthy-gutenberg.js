(function ($) {
  // Worthy for Gutenberg
  $(document).ready (
    function () {
      // Make sure there is some stuff in place
      if (!wp.i18n)
        return;
      
      let __ = wp.i18n.__;
      
      // {{{ hookGutenbergCounter
      /**
       * Try to hook into Gutenbergs table-of-contents
       * 
       * @remark This is merely an ugly hack because the table-of-contents
       *    component is not able to be extended but we want our counter
       *    to be in the right place.
       **/
      let hookGutenbergCounter = function () {
        let $btn = $('.table-of-contents button');
        
        // Check if the popover-button is in place
        if ($btn.length < 1)
          return;
        
        if ($btn.prop ('wp-worthy-bound'))
          return;
        
        let hookGutenbergPopover = function () {
          let $table = $('.table-of-contents__counts');
          
          // Check if the table is in place
          if ($table.length < 1)
            return setTimeout (hookGutenbergPopover, 10);
          
          let $columns = $table.find ('.table-of-contents__count'),
              columnType = 'div';
          
          for (let $column of $columns) {
            columnType = $column.localName;
            
            for (let textNode of $column.childNodes)
              if (textNode instanceof Text) {
                if (textNode.textContent == __ ('Character'));
                  columnType = null;
                
                break;
              }
          }
          
          if (columnType === null)
            return;
          
          // Append our counter to the table
          $('<' + columnType + ' />').addClass ('table-of-contents__count').text (__ ('Characters', 'wp-worthy')).append (
            $('<span />').addClass ('table-of-contents__number wp-worthy-counter').text (wp.worthy.getCharacterCount (wp.data.select ('core/editor').getEditedPostAttribute ('content')))
          ).appendTo ($table);
          
          // Resize all children and the table itself
          $table.children ().css ('width', '20%');
          $table.width ($table.width () * 1.25);
        };
        
        // Bind ourself to the button
        $btn.prop ('wp-worthy-bound', true)
        $btn.click (hookGutenbergPopover);
      };
      
      setInterval (hookGutenbergCounter, 100);
      // }}}
      
      // Make sure we have the wp-API available
      if (!wp || !wp.plugins || !wp.element || !wp.plugins.registerPlugin || !wp.element.createElement)
        return;
      
      let wpWorthyPrepublish = function () {
        // Call parent constructor
        wp.element.Component.apply (this, arguments);
        
        // Initialize state
        this.state = {
          'hasPixelAssigned' : (this.props.pixelInfo && this.props.pixelInfo.public),
          'pixelInfo' : (this.props.pixelInfo || null),
          'type' : (this.props.postContentType || 'normal')
        };
      };
      
      let widget = null,
          pixelUserSelection = false;
      
      wpWorthyPrepublish.prototype = new wp.element.Component ();
      wpWorthyPrepublish.prototype.render = function () {
        let el = wp.element.createElement;
        
        if (
          (wp.editPost === undefined) ||
          (
            this.props.postType &&
            wp.worthy.postTypes &&
            (wp.worthy.postTypes.indexOf (this.props.postType) < 0)
          )
        )
          return null;
        
        widget = this;
        
        let typeMap = {
          'lyric' : 'Lyric',
          'normal' : 'Normal text'
        };
        
        return el (
          (!this.props.isPublished || (typeof wp.editPost.PluginDocumentSettingPanel == 'undefined') ?  wp.editPost.PluginPrePublishPanel : wp.editPost.PluginDocumentSettingPanel),
          {
            'initialOpen' : true,
            'className' : 'wp-worthy-panel',
            'icon' : null,
            'title' : [
              __ ('Worthy:'),
              el (
                'span',
                {
                  'className' : 'editor-post-publish-panel__link text-bold',
                  'key': 'wp-worthy-article-status'
                },
                __ (this.props.minLengthReached || (this.state.type == 'lyric') ? 'Qualified' : 'Not qualified', 'wp-worthy')
              ),
            ]
          },
          [
            el (
              wp.components.PanelRow,
              { 'key': 'wp-worthy-article-length-panel' },
              [
                el ('span', { 'key': 'wp-worthy-article-length-title' }, __ ('Characters', 'wp-worthy')),
                el ('span', { 'key': 'wp-worthy-article-length-value' }, this.props.length)
              ]
            ),
            el (
              wp.components.PanelRow,
              { 'key': 'wp-worthy-article-pixel-panel' },
              [
                el ('span', { 'key': 'wp-worthy-article-pixel-title' }, __ ('Marker', 'wp-worthy')),
                el (
                  wp.components.Dropdown,
                  {
                    'key': 'wp-worthy-article-pixel-value',
                    'renderToggle' : function (props) {
                      return el (
                        wp.components.Button,
                        {
                          'onClick' : props.onToggle,
                          'aria-expanded' : props.isOpen,
                          'type' : 'button',
                          'isLink' : true
                        },
                        __ (!widget.state.pixelInfo || (widget.state.pixelInfo.public === true) ? 'Assign' : (widget.state.pixelInfo.ignored ? 'Ignore' : (widget.state.hasPixelAssigned ? 'Assigned' : 'Don\'t assign')), 'wp-worthy')
                      );
                    },
                    'contentClassName' : 'wp-worthy-gutenberg-dropdown',
                    'renderContent' : function () {
                      return el (
                        'fieldset',
                        { },
                        [
                          el (
                            'div',
                            { 'key': 'wp-worthy-pixel-assign' },
                            [
                              el (
                                'input',
                                {
                                  'key': 'wp-worthy-pixel-assign-option',
                                  'type' : 'radio',
                                  'name' : 'wp-worthy-pixel',
                                  'value' : '1',
                                  'id' : 'wp-worthy-gutenberg-marker-assign',
                                  'checked' : (!(widget.state.pixelInfo && widget.state.pixelInfo.ignored) && (widget.state.hasPixelAssigned || (widget.state.pixelInfo && widget.state.pixelInfo.public))),
                                  'onChange' : function () {
                                    pixelUserSelection = true;
                                    widget.setState ({
                                      'pixelInfo' : {
                                        'ignored' : false,
                                        'public' : (widget.state.pixelInfo && widget.state.pixelInfo.public ? widget.state.pixelInfo.public : true)
                                      }
                                    });
                                  }
                                }
                              ),
                              el (
                                'label',
                                {
                                  'key': 'wp-worthy-pixel-assign-label',
                                  'htmlFor' : 'wp-worthy-gutenberg-marker-assign'
                                },
                                __ ('Assign a marker', 'wp-worthy')
                              ),
                              el (
                                'p',
                                {
                                  'key': 'wp-worthy-pixel-assign-hint'
                                },
                                __ ('This is your destinaion! Write a post that respects all rules by VG WORT to be reported and rewarded.', 'wp-worthy')
                              )
                            ]
                          ),
                          (widget.state.hasPixelAssigned ? null :
                            el (
                              'div',
                              { 'key': 'wp-worthy-pixel-dont-assign' },
                              [
                                el (
                                  'input',
                                  {
                                    'key': 'wp-worthy-pixel-dont-assign-option',
                                    'type' : 'radio',
                                    'name' : 'wp-worthy-pixel',
                                    'value' : '0',
                                    'id' : 'wp-worthy-gutenberg-marker-dont-assign',
                                    'checked' : (!widget.state.pixelInfo || (!widget.state.hasPixelAssigned && !widget.state.pixelInfo.public && !widget.state.pixelInfo.ignored)),
                                    'onChange' : function () {
                                      pixelUserSelection = true;
                                      widget.setState ({
                                        'pixelInfo' : {
                                          'ignored' : false,
                                          'public' : null
                                        }
                                      });
                                    }
                                  }
                                ),
                                el (
                                  'label',
                                  {
                                    'key': 'wp-worthy-pixel-dont-assign-label',
                                    'htmlFor' : 'wp-worthy-gutenberg-marker-dont-assign'
                                  },
                                  __ ('Don\'t assign a marker', 'wp-worthy')
                                ),
                                el (
                                  'p',
                                  {
                                    'key': 'wp-worthy-pixel-dont-assign-hint',
                                  },
                                  __ ('If unsure you mag assign a marker later on, e.g. during a text-review.', 'wp-worthy')
                                )
                              ]
                            )
                          ),
                          el (
                            'div',
                            { 'key': 'wp-worthy-pixel-dont-ignore' },
                            [
                              el (
                                'input',
                                {
                                  'key': 'wp-worthy-pixel-dont-ignore-option',
                                  'type' : 'radio',
                                  'name' : 'wp-worthy-pixel',
                                  'value' : '2',
                                  'id' : 'wp-worthy-gutenberg-marker-ignore',
                                  'checked' : (widget.state.pixelInfo && widget.state.pixelInfo.ignored),
                                  'onChange' : function () {
                                    pixelUserSelection = true;
                                    widget.setState ({
                                      'pixelInfo' : {
                                        'ignored' : true,
                                        'public' : (widget.state.pixelInfo && (widget.state.pixelInfo.public !== true) ? widget.state.pixelInfo.public : null)
                                      }
                                    });
                                  }
                                }
                              ),
                              el (
                                'label',
                                {
                                  'key': 'wp-worthy-pixel-dont-ignore-label',
                                  'htmlFor' : 'wp-worthy-gutenberg-marker-ignore'
                                },
                                __ ('Ignore this post', 'wp-worthy')
                              ),
                              el (
                                'p',
                                {
                                  'key': 'wp-worthy-pixel-dont-ignore-hint'
                                },
                                __ ('If you don\'t want to report this post to VG WORT, just ignore it.', 'wp-worthy')
                              )
                            ]
                          )
                        ]
                      );
                    }
                  }
                )
              ]
            ),
            el (
              wp.components.PanelRow,
              {
                'key': 'wp-worthy-article-type'
              },
              [
                el (
                  'span',
                  {
                    'key': 'wp-worthy-article-type-title'
                  },
                  __ ('Type of post', 'wp-worthy')
                ),
                el (
                  wp.components.Dropdown,
                  {
                    'key': 'wp-worthy-article-type-value',
                    'renderToggle' : function (props) {
                      return el (
                        wp.components.Button,
                        { 'onClick' : props.onToggle, 'aria-expanded' : props.isOpen, 'type' : 'button', 'isLink' : true },
                        __ (typeMap [widget.state.type], 'wp-worthy')
                      );
                    },
                    'contentClassName' : 'wp-worthy-gutenberg-dropdown',
                    'renderContent' : function () {
                      return el (
                        'fieldset',
                        { },
                        [
                          el (
                            'div',
                            {
                              'key': 'wp-worthy-article-type-normal'
                            },
                            [
                              el (
                                'input',
                                {
                                  'key': 'wp-worthy-article-type-normal-option',
                                  'type' : 'radio',
                                  'name' : 'wp-worthy-type',
                                  'value' : 'normal',
                                  'id' : 'wp-worthy-gutenberg-texttype-normal',
                                  'checked' : (widget.state.type != 'lyric'),
                                  'onChange' : function () { widget.setState ({ 'type' : 'normal' }); }
                                }
                              ),
                              el (
                                'label',
                                {
                                  'key': 'wp-worthy-article-type-normal-label',
                                  'htmlFor' : 'wp-worthy-gutenberg-texttype-normal'
                                },
                                __ ('Normal text', 'wp-worthy')
                              ),
                              el (
                                'p',
                                {
                                  'key': 'wp-worthy-article-type-normal-hint'
                                },
                                __ ('A normal text is just a usual text where all default rules of VG WORT apply. There is nothing to respect except the typed characters.', 'wp-worthy')
                              )
                            ]
                          ),
                          el (
                            'div',
                            {
                              'key': 'wp-worthy-article-type-lyric'
                            },
                            [
                              el (
                                'input',
                                {
                                  'key': 'wp-worthy-article-type-lyric-option',
                                  'type' : 'radio',
                                  'name' : 'wp-worthy-type',
                                  'value' : 'lyric',
                                  'id' : 'wp-worthy-gutenberg-texttype-lyric',
                                  'checked' : (widget.state.type == 'lyric'),
                                  'onChange' : function () { widget.setState ({ 'type' : 'lyric' }); }
                                }
                              ),
                              el (
                                'label',
                                {
                                  'key': 'wp-worthy-article-type-lyric-label',
                                  'htmlFor' : 'wp-worthy-gutenberg-texttype-lyric'
                                },
                                __ ('Lyric', 'wp-worthy')
                              ),
                              el (
                                'p',
                                {
                                  'key': 'wp-worthy-article-type-lyric-hint'
                                },
                                __ ('For lyric there are special rules, e.g. thoses posts don\'t depend on length.', 'wp-worthy')
                              )
                            ]
                          ),
                        ]
                      );
                    }
                  }
                )
              ]
            )
          ]
        );
      };
      
      wpWorthyPrepublish.prototype.componentDidUpdate = function (prevProps, prevState) {
        // Check wheter to push anything back to server
        if (!this.state.pixelInfo ||
            !prevState.pixelInfo ||
            ((this.state.pixelInfo.ignored === prevState.pixelInfo.ignored) &&
             (this.state.pixelInfo.public === prevState.pixelInfo.public) &&
             (this.state.type === prevState.type)))
          return;
        
        this.props.editPost ({
          'wp-worthy-pixel' : this.state.pixelInfo,
          'wp-worthy-type' : this.state.type
        });
      };
      
      wp.plugins.registerPlugin (
        'wp-worthy-pre-publish',
        {
          render : wp.compose.compose ([
            wp.data.withSelect (
              function (select, opts) {
                let editor = select ('core/editor');
                
                if (!editor || !wp.worthy.getCharacterCount)
                  return { };
                let
                  postId = editor.getCurrentPostId (),
                  length = wp.worthy.getCharacterCount (editor.getEditedPostAttribute ('content')),
                  isLyric = (widget && (widget.state.type == 'lyric')),
                  minLengthReached = (length >= 1800);
                
                if (
                  widget &&
                  ((widget.state.pixelInfo === null) || (widget.state.pixelInfo.public === null) || (widget.state.pixelInfo.public === true)) &&
                  !pixelUserSelection &&
                  window.wp.worthy.autoAssign
                )
                  widget.setState ({
                    'pixelInfo' : {
                      'ignored' : false,
                      'public' : (minLengthReached || isLyric ? true : null)
                    }
                  });
                
                return {
                  'postId' : postId,
                  'postType' : editor.getCurrentPostType (),
                  'length' : length,
                  'isPublished' : editor.isCurrentPostPublished () || editor.isCurrentPostScheduled (),
                  'minLengthReached' : minLengthReached,
                  'pixelInfo' : editor.getCurrentPostAttribute ('wp-worthy-pixel'),
                  'postContentType' : editor.getCurrentPostAttribute ('wp-worthy-type')
               };
              }
            ),
            wp.data.withDispatch (
              function (dispatch, opts) {
                let editor = dispatch ('core/editor');
                
                if (!editor)
                  return { };
                
                return {
                  'editPost' : editor.editPost
                };
              }
            )
          ])(wpWorthyPrepublish)
        }
      );
    }
  );
}(jQuery));
