// Supports the glossary block. Wraps contiguous term and definition tags in a proper "<dl>" tag.
// Term/Definition pairs are created as individual blocks in Omeka, with no straightforward mechanism
// to provide wrapping tags.
// Many thanks to http://jsbin.com/gonino/edit?html,js

(function($) {
    $(document).ready(function() {      
      $('.dl-item').not('.dl-item+.dl-item').each(function(){
        $(this).nextUntil(':not(.dl-item)').addBack().wrapAll('<dl />');
      });
    });
})(jQuery);
