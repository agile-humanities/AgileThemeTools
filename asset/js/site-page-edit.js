(function ($) {

    function wysiwyg(context) {
        config.toolbar = [
                          { "name" : "advanced", "items" :
                              ['Sourcedialog', '-',
                               'Link', 'Unlink', 'Anchor', '-',
                               'Format', 'PasteFromWord'
                               ]
                          },
                          "/",
                          { "items" :
                              ['Bold', 'Italic', 'Underline', 'Strike', '-',
                               'NumberedList', 'BulletedList', 'Indent', 'Outdent', 'Blockquote',
                              ]
                          }
                         ];
    
        // Disable content filtering
        config.allowedContent = true;
        config.extraPlugins = 'sourcedialog';
        
        // Set  block elements.
        config.format_tags = 'p;h2;h3;h4;h5;h6;pre';



        context.find('.wysiwyg').each(function () {
            if ($(this).is('.caption')) {
                editor = CKEDITOR.inline(this, config)
            } else {
            }
            $(this).data('ckeditorInstance', editor);
        })
    }
    
})(window.jQuery);