/**
 * Agile Theme Tools — search support for the Agile search page (search/agile/*): facet collapsing, the mobile
 * filter panel, display-mode controls. Moved from the Advanced Agile Base Theme (source/js/advancedSearchSupport.js)
 * so the behaviour travels with the markup. Loaded by search/agile/form-headers.phtml. Needs jQuery.
 */
/**
 *  @file advancedSearchSupport.js
 *  @description Supports the transformation of the advanced search module’s UI into the Agile Collection Browser interface
 *
 */


// The view (grid or list) is the visitor's stored choice, else the configuration's default: Advanced Search's own
// script reads the stored value, or the view-type button the template marks active. Nothing is stored here.


(function($) {

  $(document).ready(function() {
    const mobileFilterButton = $('#mobile-filter-button');
    const mobileCloseButton = $('#browser--mobile-close-btn');
    const mobileBrowseControls = $('#mobile-browse-controls');
    const facetSidebar = $('#browser-sidebar');
    const searchResultsHeader = $('.search-results-header');

    // Mobile browse controls: below the desktop breakpoint the toolbar (#mobile-browse-controls) is visible and the
    // facets sidebar is a full-screen panel opened by its Filter button. The results header (count, per page, sort)
    // moves into that panel so every control is in one scrolling column; it goes back to the results when the
    // viewport grows past the breakpoint. "Mobile" is decided by the toolbar's visibility, which the stylesheet owns.

    const isMobile = () => mobileBrowseControls.length > 0 && mobileBrowseControls.is(':visible');
    const headerHome = $('<!-- results header -->');

    function placeResultsHeader() {
      if (searchResultsHeader.length === 0 || facetSidebar.length === 0) { return; }
      const inPanel = searchResultsHeader.parent().is(facetSidebar);
      if (isMobile() && !inPanel) {
        searchResultsHeader.replaceWith(headerHome);
        mobileCloseButton.after(searchResultsHeader);
      } else if (!isMobile() && inPanel) {
        headerHome.replaceWith(searchResultsHeader);
      }
    }

    function setFilterOverlay(open) {
      if (facetSidebar.length === 0) { return; }
      facetSidebar.toggleClass('open', open);
      mobileFilterButton.attr('aria-expanded', open ? 'true' : 'false').toggleClass('active', open);
      $('body').toggleClass('filter-overlay-open', open);
    }

    mobileFilterButton.on('click', e => { e.preventDefault(); setFilterOverlay(!facetSidebar.hasClass('open')); });
    mobileCloseButton.on('click', e => { e.preventDefault(); setFilterOverlay(false); });
    $(document).on('keydown', e => { if (e.key === 'Escape' && facetSidebar.hasClass('open')) { setFilterOverlay(false); } });

    // No facets: nothing to open.
    if (facetSidebar.length === 0) {
      mobileFilterButton.hide();
    }

    // Position Mobile Paginator
    if (mobileBrowseControls.length > 0) {
      $('.pagination--mobile').appendTo(mobileBrowseControls);
    }

    placeResultsHeader();
    $(window).on('resize', () => {
      placeResultsHeader();
      if (!isMobile()) { setFilterOverlay(false); }
    });

    // Collection browser support

    let browseViewMode = localStorage.getItem('search_view_type');


    // The results list (ul#collection-list, search/agile/results.phtml) carries the configured default mode as a
    // data attribute. Its wrapper shares the search-results-list class, so the list is addressed by id.

    const itemBrowser = $('#collection-list');


    if (browseViewMode === null && itemBrowser.length > 0) {
      browseViewMode = itemBrowser.attr('data-gridlistmode') ?? null;
    }

    if (browseViewMode !== null) {
      const browserUIbuttons = $('.search-results-header .search-view-type, #mobile-browse-controls .search-view-type');
      browserUIbuttons.each(function(){
        // Manage active state classes and icons.
        $(this).removeClass('active');
        $(this).find('img').trigger('switchtoinactive').removeClass('active'); // custom trigger in js/rollover.js

        // (Advanced Search Module) browse mode is handled via Javascript. This swaps the icons.

        $(this).on('click',function(e){
          $(this).find('img').trigger('switchtoactive').addClass('active');
          $(this).siblings().each(function(){
            // @todo: img.trigger('switchtoinactive') should work, but it does not.
            const img = $(this).find('img');
            img.attr('src',img.attr('data-src'));
            img.removeClass('active');
          });
        });

        if ($(this).hasClass('search-view-type-' + browseViewMode)) {
          $(this).addClass('active');
          $(this).find('img').trigger('switchtoactive').addClass('active'); // custom trigger in js/rollover.js
        }

      });
    }


    // Modify browse collection search button to conform to design
    //$('#form-search').find('button.search-submit').attr('aria-describedby','Submit search request').html('');


    // Reset button is not fully implemented. See advanced search module search.js
    $('#facets-reset, #search-reset').on('click', function () {
      const url = window.location.href;
      window.history.pushState({}, '', url.split(/[?#]/)[0]);
      window.location.reload();
    });

    // Open facet details if an item is checked.

    $('.facet details').each(function() {
      var open = false;
      $(this).find('input').each(function() {
        if ($(this).is(':checked')) {
          open = true;
        }
      });

      if(open === true) {
        $(this).attr('open','');
      }
    })

    // See more/less facet options

    $('.facet').each(function (i) {
      const facet = $(this);
      const visibleOptions = parseInt(facet.attr('data-visible-options'));
      const facetOptions = facet.find('.search-facet-items > *');

      // A facet with no values (a list that came back empty) is hidden rather than shown as an empty heading.
      if (facet.find('.search-facet-items').length > 0 && facetOptions.length === 0) {
        facet.hide();
        return;
      }

      // All items are marked inactive by default
      // Show active options
      if (visibleOptions > 0) {
        facetOptions.each(function (i) {
          if (i<visibleOptions) {
            $(this).css('display','flex');
            $(this).removeClass('inactive');
          }
        });
      } else {
        facetOptions.css('display','flex');
      }

      const facetExpandBtn = facet.find('.facet--expand');
      const facetCollapseBtn = facet.find('.facet--collapse');

      // Show a set of options on click

      facetExpandBtn.on('click',function(e) {
        facet.find('.inactive').each(function (i) {
          if (i<visibleOptions) {
            $(this).css('display','flex');
            $(this).removeClass('inactive');
          }
        });

        // Hide button if there are no more inactive options to show
        if(facet.find('.inactive').length == 0) {
          $(this).hide();
        }

        // Show the “Fewer options” button
        facetCollapseBtn.show()

      })

      // Hide a set of options on click
      facetCollapseBtn.on('click',function(e) {
        const activeOptions = facetOptions.not('.inactive');
        const activeCount = activeOptions.length;
        activeOptions.each(function (i) {
          if (i > activeCount - visibleOptions) {
            $(this).addClass('inactive');
            $(this).css('display','none');
          }
        });

        // Hide collapse button if the minimal set of options are visible
        if(facetOptions.not('.inactive').length <= visibleOptions) {
          $(this).hide();
        }

        // Make sure the “More options” is visible
        facetExpandBtn.show()

      })

    });

    /* Select2 on the Agile search page's selects: the form's fields, the advanced filter rows (including the
     * rows Advanced Search's script adds later) and the results header's sort and per-page selects. One
     * enhancer per page: selects marked .no-select2 or .chosen-select are left to their own script. The theme's
     * formElements.js only enhances selects that opt in with .agile-select, so nothing is enhanced twice. */

    function updateSelect(element) {
      if (!(element instanceof HTMLSelectElement)) { return; }
      const select = $(element);
      if (select.data('select2') || select.is('.no-select2, .chosen-select')) { return; }
      // The container takes its cell's width and follows it when the window is resized or the phone rotated. A
      // pixel width ('element'), measured at init, spills out of the cell as soon as the cell gets narrower.
      // Fit cells (the filter rows, the sort selector) set the container's width in the stylesheet instead.
      select.select2({
        width: '100%',
        minimumResultsForSearch: 40
      });
    }

    const searchPage = document.getElementById('search-page');

    if (searchPage instanceof Node) {
      searchPage.querySelectorAll('select').forEach(updateSelect);

      // Advanced Search's script clones the filter row template when a row is added.
      const observer = new MutationObserver(mutations => {
        for (const mutation of mutations) {
          for (const node of mutation.addedNodes) {
            if (!(node instanceof HTMLElement)) continue;
            updateSelect(node);
            node.querySelectorAll('select').forEach(updateSelect);
          }
        }
      });
      const searchFilters = document.getElementById('search-filters');
      if (searchFilters instanceof Node) {
        observer.observe(searchFilters, {childList: true, subtree: true});
      }
    }
  });

})(jQuery);
