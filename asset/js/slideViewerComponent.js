/**
 *  An individual slide item.
 *
 *  Works with slide-viewer web component.
 *
 *  Accepts two slots:
 *   - “<div slot='main-content'></div>
 *
 *
 *  Note on Media Queries:
 *
 *  Media queries cannot use CSS variables, meaning that it is hard to
 *  create breakpoint consistency with the context without resorting to
 *  duct tape (e.g. using Javascript to set styles at various breakpoints).
 *
 *  For now standardized breakpoints are being used.
 *
 */


customElements.define('slide-item', class extends agileComponent {
  constructor() {
    super();
    const shadowRoot = this.attachShadow({mode: 'open'});
    // For debugging purposes:
    // this.shadowRoot.addEventListener('slotchange', event => {});
    shadowRoot.innerHTML = `
      <figure id='slide'>
        <div id='content'>
          <slot name='slide-content'></slot>
        </div>
        <figcaption>
          <slot name='slide-caption'></slot>
        </figcaption>
      </figure>
      <style>
        * {
         box-sizing: border-box;
         max-width: 100%
        }

        :host {
          display: inline-block;
          height: 100%;
          width: 100%;
        }

        figure {
          display: var(--slide-item-slide-display,flex);
          flex-direction: var(--slide-item-slide-flex-direction-mobile,column);
          flex-grow: var(--slide-item-slide-flex-grow,1);
          width: var(--slide-item-slide-width,100%);
          height: var(--slide-item-slide-height,100%);
          background: var(--slide-item-background,none);
          padding: var(--slide-item-slide-padding,0);
          margin: var(--slide-item-slide-margin,0);
          position: relative;
          opacity: 0;
          transition: opacity 0.2s;
          z-index: 2;
        }

        figcaption {
          display: var(--slide-item-figcaption-display,'flex');
          background: var(--slide-caption-background,none);
        }

        @media screen and (min-width: 46.5em) {
          figure {
            flex-direction: var(--slide-item-slide-flex-direction-tablet,column);
          }
        }

        @media screen and (min-width: 75em) {
          figure {
            flex-direction: var(--slide-item-slide-flex-direction,column);
          }
        }

        ::slotted([slot='slide-content']), ::slotted([slot='slide-caption']) {
          height: 100%;
        }

        #content {
          flex-shrink: 1;
          flex-grow: 1;
          overflow: var(--slide-item-content-overflow,'hidden');
          padding: var(--slide-item-content-padding,0);
          background: var(--slide-content-background,none);
          width: 100%;
          height: 100%;
        }

        @media screen and (min-width: 46.5em) {
          #content {
            width: var(--slide-item-content-width-tablet,100%);
          }
        }

        @media screen and (min-width: 75em) {
          #content {
            width: var(--slide-item-content-width,100%);
          }
        }

        figcaption {
          padding:  var(--slide-item-caption-padding,var(--slide-viewer-padding,1rem));
          height:  var(--slide-item-caption-height,auto);
          width: 100%;
          position: var(--slide-item-caption-position,absolute);
          bottom: var(--slide-item-caption-bottom,0);
        }

        @media screen and (min-width: 46.5em) {
          figcaption {
            width: var(--slide-item-caption-width-tablet,100%);
          }
        }

        @media screen and (min-width: 75em) {
          figcaption {
            width: var(--slide-item-caption-width,100%);
          }
        }

      </style>
    `;
  }


  attachGlobalStyles() {

    // Append styles to head

    if (document.querySelectorAll("style[data-name='slide-item-styles']").length === 0) {

      var styleElement = document.createElement('style');
      styleElement.setAttribute('data-name','slide-item-styles');
      styleElement.setAttribute('type','text/css');

      // Define global styles

      styleElement.appendChild(document.createTextNode(`

        slide-item *[slot="slide-content"] img {
          height: 100%;
        }

        slide-item *[slot="slide-content"] img.img-portrait {
          object-fit: var(--slide-item-portrait-object-fit,contain);
          object-position: var(--slide-item-portrait-position,center);
          padding: var(--slide-item-portrait-slide-padding,0 var(--slide-viewer-padding,1rem));
        }

        slide-item *[slot="slide-content"] img.img-landscape {
          object-fit: var(--slide-item-landscape-object-fit,cover);
          object-position: var(--slide-item-landscape-position,center);
          padding: var(--slide-item-landscape-slide-padding, 0);
          width: 100%;
          height: var(--slide-item-landscape-slide-height, 100%);
        }

        slide-item *[slot="slide-content"] img.img-square {
          object-fit: var(--slide-item-landscape-object-fit,contain);
          object-position: var(--slide-item-landscape-position,center);
          padding: var(--slide-item-landscape-slide-padding, var(--slide-viewer-padding,1rem));
        }

      `));

      document.querySelector('head').appendChild(styleElement);
    }
  }

  /*
   *  Standard web component call. Tells the component what attributes to “listen” for.
   *  When a listed attribute changes, it triggers the attributeChangedCallback.
   */

  static get observedAttributes() {
    return ['aspect','data-async-ready'];

  }

  /*
   * Standard web component callback..
   */

  async attributeChangedCallback(attrName, oldVal, newVal) {
    super.attributeChangedCallback(attrName, oldVal, newVal);

    if (attrName === 'aspect') {
      this.shadowRoot.querySelector('figure').classList.add('figure-' + newVal);

      var img = this.querySelector('img');

      if (!img.classList.contains('img-' + newVal)) {
        img.classList.add('img-' + newVal);
      }
    }
  }

  documentReady() {
    super.documentReady();
    this.attachGlobalStyles();
    this.shadowRoot.querySelector('figure').setAttribute('style','opacity: 1');
    this.classList.add('element-ready');
  }

  connectedCallback() {
    super.connectedCallback();
  }


});


class slideViewerInterface extends agileComponent {
  constructor() {
    super();
    this.id; // The unique ID of the component. If the component does not have an ID one will be assigned.
  }


  connectedCallback() {
    super.connectedCallback();
    this.attachGlobalStyles();
  }

  /*
   *  Implements the agileComponent class’ documentReady method, which emulates jQuery’s document
   *  ready class, firing when all DOM objects have been loaded.
   *
   *  The method should check for the existence of the stylesheet before appending it to the body.
   */

  documentReady() {
    super.documentReady();
  }

  async attributeChangedCallback(attrName, oldVal, newVal) {
    super.attributeChangedCallback(attrName, oldVal, newVal);
  }



    /*
     *  Attach a stylesheet to the main DOM to provide default styles for LightDOM and user-provided structured elements.
     *
     *  The method should check for the existence of the stylesheet before appending it to the body.
     */


  attachGlobalStyles() {

  }

  /*
   *  Provide a default ID for this component. This ID must be assigned this.id.
   *
   *  Use this boilerplate, which call’s generateUniqueID();
   *
   *  if (!this.hasAttribute('id')) {
   *    this.setAttribute('id',this.generateUniqueId());
   *  }
   *
   */

  getId() {

  }

  /*
   *  Provide the default configuration for the viewer.
   *
   *  The configuration must return an object with the following keys:
   *
   *   - src – URL or absolute path to the library;
   *.  - options - an object withj keyed options to pass to the slider library
   */

  getConfiguration() {

  }


  /*
   *  Replaces configuration options with those provided as component attributes.
   *
   *  The method should check for the existence of the stylesheet before appending it to the body.
   */

  prepareConfiguration() {

  }

  /*
   *  Load the slider library and its dependencies.
   *
   *  The method should check for the existence of those libraries before appending them to the body.
   */

  loadDependencies() {

  }

  /*
   *  Apply the slide viewer to the slide element.
   *
   *  It must use the values from this.getConfiguration()
   */

  instantiateSlideViewer() {


  }

  /*
   *  Advance the slides.
   *
   *  This function is passed to an event listener as per the agileComponent’s
   *  attachEventListener() method. That method will passed two arguments, the
   *  triggered event object and the component object (“this” in class scope).
   */

  nextSlideBehaviour(e,component) {

  }

  /*
   *  Go to previous slide.
   *
   *  This function is passed to an event listener as per the agileComponent’s
   *  attachEventListener() method. That method will passed two arguments, the
   *  triggered event object and the component object (“this” in class scope).
   */

  previousSlideBehaviour(e, component) {

  }

  goToSlideBehaviour(index) {

  }

  createPagination() {
  }

  createSequentialNavigation() {

  }

}

class slideViewerSlick extends agileComponent { // Implements slideViewerInterface()
  constructor() {
    super();
    this.id;
    this.library = 'Slick';
    this.librarySrc;
    this.libraryConfiguration;
    this.slideList;
    this.currentSlide;
    this.totalSlides;
    this.initialized = false;

    const shadowRoot = this.attachShadow({mode: 'open'});



  }

  /**
   * Creates the base Shadow DOM
   * Supports a valueless attribute called “integratedcontrols”, which will nest the page controls
   * within the sequential navigation, allowing for a greater range of design implementations
   *
   */

  attachShadowDom() {

    var controls = `
      <div id='slide-nav'></div>
      <div id='sequential-nav'>
        <a class="sequential-control previous" aria-label="Previous"></a>
        <a class="sequential-control next" aria-label="Next"></a>
      </div>`;

    if (this.hasAttribute('integratedcontrols')) {
      controls = `<div id='sequential-nav' class='integrated'>
        <a class="sequential-control previous" aria-label="Previous"></a>
        <div id='slide-nav'></div>
        <a class="sequential-control next" aria-label="Next"></a>
      </div>`;

    }

    this.shadowRoot.innerHTML = `
      <slot id='slide-list' name='slides'></slot>
      ${controls}
      <div id='fullscreen'></div>
      <style>
        * {
         box-sizing: border-box;
        }

        :host {
          display: block;
          width: 100%;
          height: 100%;
          min-height: 50vh;
          position: relative;
          padding: var(--slide-viewer-nav-clearance, 0);
          background: var(--slide-viewer-background, black);
        }

        @media screen and (min-width: 46.5em) {
          :host {
            min-height: 500px;
          }
        }

        ::slotted(div), {
          width: 100%;
          height: 100%;
        }

        #slide-nav.nav-hide {
          display: var(--nav-hide-display,none);
        }


        #sequential-nav.nav-hide {
          display: var(--nav-hide-display,none);
        }

        #slide-nav {
           position: absolute;
           bottom: var(--slide-nav-bottom,var(--slide-viewer-padding,1rem));
           top: var(--slide-nav-top,initial);
           left: var(--slide-nav-left,initial);
           right: var(--slide-nav-right,initial);
           height: var(--slide-nav-height,1.5rem);
           width: 100%;
           z-index: 3;
           display: flex;
           flex-direction: var(--slide-nav-flex-direction,row);
           flex-wrap: var(--slide-nav-flex-wrap,wrap);
           justify-content: var(--slide-nav-justify-content,center);
           align-items: var(--slide-nav-align-items,center);
           border: var(--slide-nav-border,0);
           border-radius: var(--slide-nav-border-radius,0);
           background: var(--slide-nav-background,none);
        }

        #sequential-nav {
          position: absolute;
          top: var(--sequential-nav-top,inital);
          bottom: var(--sequential-nav-bottom,0);
          left: var(--sequential-nav-left, var(--slide-viewer-padding,1rem));
          right: var(--sequential-nav-right, var(--slide-viewer-padding,1rem));
          display: flex;
          flex-direction: row;
          justify-content: var(--sequential-nav-justify-content,space-between);
          align-items: var(--sequential-nav-align-items,center);
          pointer-events: none;
          z-index: 3;
          transform: var(--sequential-nav-transform,none);
          border: var(--sequential-nav-border,0);
          border-radius: var(--sequential-nav-border-radius,0);
          background: var(--sequential-nav-background,none);

        }
        
        /* Integrated Navigation (Sequential and page navigation in same container) */
        
         #sequential-nav.integrated {
            pointer-events: initial;
         }
        
        .integrated #slide-nav {
            position: relative;
            bottom: initial;
            top: initial;
            left: initial;
            right: initial;
        }

        .page-marker {
          cursor: pointer;
          width: var(--page-marker-width,var(--slide-viewer-marker-size,1rem));
          height: var(--page-marker-height,var(--slide-viewer-marker-size,1rem));
          border-radius: var(--page-marker-border-radius,50%);
          border-style: solid;
          border-width: var(--page-marker-border-width,0);
          border-color: var(--page-marker-border-color, white);
          background: var(--page-marker-background,#DDDDDD);
          margin: var(--page-marker-margin,0 var(--slide-marker-spacing,0.5rem) 0 0);
          transition: background 0.2s
        }
        
        .page-marker:focus {
          background: var(--page-marker-background-hover,#BBBBBB);
          transform: var(--page-marker-transform-hover,none);
        }


        .page-marker:hover {
          background: var(--page-marker-background-hover,#BBBBBB);
          transform: var(--page-marker-transform-hover,none);

        }

        .page-marker.active {
          background: var(--page-marker-background-active,#BBBBBB);
          transform: var(--page-marker-transform-active,none);
        }

        .sequential-control {
          cursor: pointer;
          text-decoration: var(--sequential-control-text-decoration,none);
          color: var(--sequential-control-color,#222222);
          font-size: var(--sequential-control-font-size,2rem);
          font-weight: var(--sequential-control-font-weight,700);
          opacity: var(--sequential-control-initial-opacity,0);
          transition: opacity 0.2s;
          pointer-events: all;
          display: block;
          height: var(--sequential-control-btn-height,1.5rem);
          width: var(--sequential-control-btn-width,2rem);
        }

        :host(:hover) .sequential-control {
          opacity: var(--sequential-control-slide-hover-opacity,0.5)
        }

        :host(:hover) .sequential-control:hover {
          color: var(--sequential-control-color-hover,#222222);
          opacity: var(--sequential-control-hover-opacity,1)
        }

        .next {
          margin-left: var(--sequential-control-spacing,0);
          background: var(--next-btn-background,no-repeat url("data:image/svg+xml;base64,PHN2ZyBpZD0ibmV4dC1idG4iIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDE4LjQ1IDIwIj48ZGVmcz48c3R5bGU+LmNscy0xe2ZpbGw6IzAwMTcxYTt9PC9zdHlsZT48L2RlZnM+PHBhdGggY2xhc3M9ImNscy0xIiBkPSJNMCwyMFYxNC45TDEzLjYzLDEwLDAsNS4xVjBMMTguNDUsNy4zNXY1LjNaIi8+PC9zdmc+"));
          background-size: var(--sequential-control-background-size,100%);
          background-position: center
        }

        .previous {
          margin-right: var(--sequential-control-spacing,0);
          background: var(--previous-btn-background,no-repeat right url("data:image/svg+xml;base64,PHN2ZyBpZD0iY2hhcmFjdGVyIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxOC40NSAyMCI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiMwMDE3MWE7fTwvc3R5bGU+PC9kZWZzPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTE4LjQ1LDBWNS4xTDQuODIsMTAsMTguNDUsMTQuOVYyMEwwLDEyLjY1VjcuMzVaIi8+PC9zdmc+"));
          background-size: var(--sequential-control-background-size,100%);
          background-position: center
        }
        
        /* Screen Reader */
        
        .screen-reader-text {
          clip: rect(1px, 1px, 1px, 1px);
          clip-path: inset(50%);
          height: 1px;
          width: 1px;
          margin: -1px;
          overflow: hidden;
          padding: 0;
          position: absolute;
        }


      </style>
    `;
  }

  attachGlobalStyles() {

    // Append styles to body

    if (document.querySelectorAll("style[data-name='slide-viewer-styles']").length === 0) {
      var styleElement = document.createElement('style');
      styleElement.setAttribute('data-name','slide-viewer-styles');
      styleElement.setAttribute('type','text/css');
      // Define global styles

      styleElement.appendChild(document.createTextNode(`
        
        .slide-container {
          height: 100%;
        }

        .slick-list, .slick-slider, .slick-track, .slick-slide, .slick-slide > div {
          height: 100%;
        }

        .slick-list, .slick-slide, .slick-slide > div {
          width: 100%;
        }
        
        
        /* FROM slick.css */
        
        .slick-slider
        {
            position: relative;
        
            display: block;
            box-sizing: border-box;
        
            -webkit-user-select: none;
               -moz-user-select: none;
                -ms-user-select: none;
                    user-select: none;
        
            -webkit-touch-callout: none;
            -khtml-user-select: none;
            -ms-touch-action: pan-y;
                touch-action: pan-y;
            -webkit-tap-highlight-color: transparent;
        }
        
        .slick-list
        {
            position: relative;
        
            display: block;
            overflow: hidden;
        
            margin: 0;
            padding: 0;
        }
        .slick-list:focus
        {
            outline: none;
        }
        .slick-list.dragging
        {
            cursor: pointer;
            cursor: hand;
        }
        
        .slick-slider .slick-track,
        .slick-slider .slick-list
        {
            -webkit-transform: translate3d(0, 0, 0);
               -moz-transform: translate3d(0, 0, 0);
                -ms-transform: translate3d(0, 0, 0);
                 -o-transform: translate3d(0, 0, 0);
                    transform: translate3d(0, 0, 0);
        }
        
        .slick-track
        {
            position: relative;
            top: 0;
            left: 0;
        
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .slick-track:before,
        .slick-track:after
        {
            display: table;
        
            content: '';
        }
        .slick-track:after
        {
            clear: both;
        }
        .slick-loading .slick-track
        {
            visibility: hidden;
        }
        
        .slick-slide
        {
            display: none;
            float: left;
        
            height: 100%;
            min-height: 1px;
        }
        [dir='rtl'] .slick-slide
        {
            float: right;
        }
        .slick-slide img
        {
            display: block;
        }
        .slick-slide.slick-loading img
        {
            display: none;
        }
        .slick-slide.dragging img
        {
            pointer-events: none;
        }
        .slick-initialized .slick-slide
        {
            display: block;
        }
        .slick-loading .slick-slide
        {
            visibility: hidden;
        }
        .slick-vertical .slick-slide
        {
            display: block;
        
            height: auto;
        
            border: 1px solid transparent;
        }
        .slick-arrow.slick-hidden {
            display: none;
        }

      `));

        document.querySelector('head').appendChild(styleElement);
    }
  }

  connectedCallback() {
    super.connectedCallback();
    this.attachShadowDom();
    this.getId();

  }

  documentReady() {
    super.documentReady();

    // connected callback / documentReady are being called multiple times. This prevents reinitialization.

    if (this.initialized !== true) {
      this.libraryConfiguration = this.getConfiguration();
      this.prepareConfiguration();
      this.attachGlobalStyles();
      this.loadDependencies();
      this.instantiateSlideViewer();
      this.createPagination();
      this.createSequentialNavigation();
      this.initialized = true;

      // Safari has been inconsistent in applying the pagination elements, likely due to an async problem
      // somewhere. This routine audits the paginator to make sure that it has the right number of pages,
      // and re-initializes it if it doesn’t,

      let _this = this;
      let auditPaginator = setInterval(function(){
        let pagerElement = _this.shadowRoot.getElementById('slide-nav');
        if (typeof _this.slideCount !== "undefined" && pagerElement.children.length !== _this.slideCount) {
          _this.createPagination();
          _this.createSequentialNavigation();
        } else {
          clearInterval(auditPaginator);
        }
      },200);
    }
  }


  static get observedAttributes() {
    return ['option-slidesperrow','option-slidestoshow','option-slidestoscroll','option-autoplay','data-async-ready'];
  }

  /*
   * @function attributeChangedCallback
   * Standard web component callback.
   *
   * This callback allows you to change Slick options on the fly by adjusting
   * an attribute via a javascript. The slider will be reinitialized when options
   * are changed.
   */

  async attributeChangedCallback(attrName, oldVal, newVal) {

    super.attributeChangedCallback(attrName, oldVal, newVal);

    // refreshes slide viewer if attribute-based options change

    if (attrName.indexOf('option') === 0 && this.initialized === true) {
      var slider = jQuery(this).find('[slot="slides"]');

      if (slider.length > 0) {
        this.prepareConfiguration();
        slider.slick('slickSetOption',this.libraryConfiguration.options,true);
        slider.slick('refresh');
        this.createPagination();
      }
    }
  }


  getId() {
    if (!this.hasAttribute('id')) {
      this.setAttribute('id',this.generateUniqueId());
    }
  }



  getConfiguration() {
    return {
      src: 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js',
      options: {
          accessibility: true,
          adaptiveHeight: false,
          appendArrows: true,
          appendDots: false,
          arrows: false,
          asNavFor: null,
          prevArrow: false,
          nextArrow: false,
          autoplay: false,
          autoplaySpeed: 3000,
          centerMode: false,
          centerPadding: '50px',
          cssEase: 'ease',
          //customPaging: this.createPagination,
          dots: true,
          dotsClass: 'page-marker',
          draggable: true,
          easing: 'linear',
          edgeFriction: 0.35,
          fade: true,
          focusOnSelect: false,
          focusOnChange: false,
          infinite: true,
          initialSlide: 0,
          lazyLoad: 'ondemand',
          mobileFirst: false,
          pauseOnHover: true,
          pauseOnFocus: true,
          pauseOnDotsHover: false,
          respondTo: 'window',
          responsive: null,
          rows: 1,
          rtl: false,
          slide: '',
          slidesPerRow: 1,
          slidesToShow: 1,
          slidesToScroll: 1,
          speed: 500,
          swipe: true,
          swipeToSlide: false,
          touchMove: true,
          touchThreshold: 5,
          useCSS: true,
          useTransform: true,
          variableWidth: false,
          vertical: false,
          verticalSwiping: false,
          waitForAnimate: true,
          zIndex: 1000
      }
    }
  }


  // Implements slideViewerInterface.prepareConfiguration() .

  prepareConfiguration() {

    // Data attributes enforce lowercase, but the configuration options do not.
    // We must create an index of options keyed by their data attribute value
    // to be able to look up the case-sensitive property value we’re setting.

    var optIndex = {}

    for (const property in this.libraryConfiguration.options) {
      const key = 'option-' + property.toLowerCase(); // the data attribute as a key
      optIndex[key] = property;
    }

    // Iterate over attributes to find options. Option attribute names are prefixed by "option-”.

    for (var att, i = 0, atts = this.attributes, n = atts.length; i < n; i++){

      var attName = atts[i].name;

      if (attName.indexOf('option-') !== -1) {

        // Check if configuration option exists in our option Index. See note on lowercase attribute names above.

        if (optIndex.hasOwnProperty(attName)) {

          var optValue = this.getAttribute(attName);

          // Cast values. Data attributes are strings by default, but configurations may require properly cast
          // booleans and numbers.

          optValue = optValue === "true" ? true : optValue;
          optValue = optValue === "false" ? false : optValue;
          optValue = typeof optValue === 'boolean' || isNaN(optValue) ? optValue : parseFloat(optValue);

          // Retrieve mixed case property name.

          var optKey = optIndex[attName];

          // Set the property with the supplied value.

          this.libraryConfiguration.options[optKey] = optValue;

        }
      }
    }
  }

  instantiateSlideViewer() {
    const script = document.createElement('script');

    script.appendChild(document.createTextNode(`
      var viewer = jQuery('#` + this.getAttribute('id') + `');
      var slides = viewer.find('[slot="slides"]');
      slides.not('.slick-initialized').slick(` + JSON.stringify(this.libraryConfiguration.options,null,2) + `);

      jQuery('slide-item').each(function() {
        const slideItem = jQuery(this);

        if (typeof jQuery(this).imagesLoaded === 'function') {
          jQuery(this).imagesLoaded(function(){
            jQuery(this.images).each(function(i,o) {

              function getImageAspectClass(h,w) {
                var aspectClass = 'square';

                if (w > h) {
                  aspectClass = 'landscape';
                } else if (h > w) {
                  aspectClass = 'portrait';
                }

                return aspectClass;
              }

              var img = jQuery(o.img);
              var h = o.img.naturalHeight;
              var w = o.img.naturalWidth

              slideItem.attr('aspect',getImageAspectClass(h,w));

            });
          });
        }
      });
    `));
    this.shadowRoot.appendChild(script);
  }



  // Loads slider library and it’s dependencies.

  loadDependencies() {

    // Audit for dependencies

    // jQuery. Used by Slick.

    if (typeof window.jQuery === 'undefined') {
      const jQuery = document.createElement('script');
      jQuery.setAttribute('type','text/javascript');
      jQuery.setAttribute('src','https://code.jquery.com/jquery-3.6.0.min.js');
      jQuery.setAttribute('integrity','sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=');
      jQuery.setAttribute('crossorigin','anonymous');
      document.querySelector('body').appendChild(jQuery);
    }

    // evEmitter. Used by Images Loaded.

    if (typeof window.EvEmitter === 'undefined') {
      const evEmitter = document.createElement('script');
      evEmitter.setAttribute('type','text/javascript');
      evEmitter.setAttribute('src','https://cdn.jsdelivr.net/npm/ev-emitter@2.1.0/ev-emitter.min.js');
      document.querySelector('body').appendChild(evEmitter);
    }

    // ImagesLoaded. Used to detect when images have been fully loaded, allowing javascript to make size calculations

    if (typeof window.jQuery.fn.imagesLoaded === 'undefined') {
      const ImagesLoaded = document.createElement('script');
      ImagesLoaded.setAttribute('type','text/javascript');
      ImagesLoaded.setAttribute('src','https://cdn.jsdelivr.net/npm/imagesloaded@4.1.4/imagesloaded.min.js');
      document.querySelector('body').appendChild(ImagesLoaded);
    }

   // Slick library

   if(typeof window.jQuery.fn.slick !== 'function') {
      const library = document.createElement('script');
      library.setAttribute('type','text/javascript');
      library.setAttribute('src', this.hasAttribute('librarySrc') ? this.getAttribute('librarySrc') : this.libraryConfiguration.src); // Use alternative library if supplied as librarySrc attribute
      document.querySelector('body').appendChild(library);
    }

  }

  nextSlideBehaviour(e,_this) {
    var element = document.getElementById(_this.getAttribute('id')).querySelector('.slick-slider');
    jQuery(element).slick('slickNext');
  }

  previousSlideBehaviour(e,_this) {
    var element = document.getElementById(_this.getAttribute('id')).querySelector('.slick-slider');
    jQuery(element).slick('slickPrev');
  }

  goToSlideBehaviour(e,_this,slider) {
    e.preventDefault();
    jQuery(slider).slick('slickGoTo',e.target.getAttribute('data-index'));
  }

  createPagination() {

    var _this = this;
    var pagerElement = this.shadowRoot.getElementById('slide-nav');
    var slider = this.querySelector('[slot="slides"]');
    this.currentSlide = this.libraryConfiguration.options.initialSlide; // init value
    this.slideCount = jQuery(slider).slick('getDotCount') + 1; // dot count is off by one after init (?).
    pagerElement.setAttribute('data-count',this.slideCount);

    if(this.slideCount < 2) {
      pagerElement.classList.add('nav-hide');
    }


    // Slick Carousel boilerplate listener ( see https://stackoverflow.com/questions/25847297/slick-js-get-current-and-total-slides-ie-3-5 )

    jQuery(slider).on('init reInit afterChange', function(event, slick, currentSlide, nextSlide){
      //currentSlide is undefined on init -- set it to 0 in this case (currentSlide is 0 based)
      var i = (currentSlide ? currentSlide : 0);
      _this.currentSlide = i;
      _this.slideCount = slick.slideCount;
      pagerElement.querySelectorAll('a').forEach(function(o,i){
        o.classList.remove('active');
      });

      // @todo: throws error when slidesToShow is greater than 1. See link above. This solution below may need hardening. Debug code left in for now.
      let idx = Math.ceil(_this.currentSlide / slick.options.slidesToShow);
      if (typeof pagerElement.querySelector('a[data-index="' + idx + '"]') !== 'undefined') {
        pagerElement.querySelector('a[data-index="' + idx + '"]').classList.add('active');
      }
    });

    // Generate the slides

    pagerElement.replaceChildren(); // Remove contents so pages do not get duplicated if element is cloned

    for(var i=0; i<this.slideCount; i++) {
      const pageMarker = document.createElement('a');
      const page = i + 1;

      var descriptiveMarker = document.createElement('span');
      descriptiveMarker.classList.add('screen-reader-text');
      descriptiveMarker.appendChild(document.createTextNode('Go to slide ' + page));
      pageMarker.appendChild(descriptiveMarker);

      pageMarker.classList.add(this.libraryConfiguration.options.dotsClass);
      pageMarker.setAttribute('title','Go to slide ' + page);
      pageMarker.setAttribute('data-index',i);
      pageMarker.setAttribute('tabindex','0');

      if (this.currentSlide === i) {
        pageMarker.classList.add('active');
      }

      this.attachEventListener(pageMarker, 'click', function(e) { _this.goToSlideBehaviour(e,_this,slider)});
      this.attachEventListener(pageMarker, 'keypress', function(e) {
        if (e.keyCode === 13 || e.keyCode === 32)
        {
          e.preventDefault();
          _this.goToSlideBehaviour(e, _this, slider)
        }
      });

      pagerElement.appendChild(pageMarker);

      this.attachEventListener(pagerElement, 'focus', function(e) { pageMarker.focus() });

    }

    pagerElement.setAttribute('tabindex','0');


  }


  createSequentialNavigation() {

    let _this = this;

    var sequentialNav = this.shadowRoot.querySelector('#sequential-nav');
    if (typeof this.slideCount !== "undefined") {
      sequentialNav.setAttribute('data-count',this.slideCount);

      if(this.slideCount < 2) {
        sequentialNav.classList.add('nav-hide');
      }
    }

    var prevArrow =  this.shadowRoot.querySelector('#sequential-nav .previous');
    var nextArrow =  this.shadowRoot.querySelector('#sequential-nav .next');

    var nextDescriptiveMarker = document.createElement('span');
    nextDescriptiveMarker.classList.add('screen-reader-text');
    nextDescriptiveMarker.appendChild(document.createTextNode('Next slide'));

    var previousDescriptiveMarker = document.createElement('span');
    previousDescriptiveMarker.classList.add('screen-reader-text');
    previousDescriptiveMarker.appendChild(document.createTextNode('Previous slide'));

    nextArrow.replaceChildren(nextDescriptiveMarker);
    prevArrow.replaceChildren(previousDescriptiveMarker);

    nextArrow.setAttribute('tabindex','0');
    prevArrow.setAttribute('tabindex','0');

    this.attachEventListener(prevArrow,['click'],this.previousSlideBehaviour);
    this.attachEventListener(nextArrow,['click'],this.nextSlideBehaviour);


    this.attachEventListener(prevArrow, 'keypress', function(e) {
      if (e.keyCode === 13 || e.keyCode === 32)
      {
        e.preventDefault();
        _this.previousSlideBehaviour(e,_this)
      }
    });

    this.attachEventListener(nextArrow, 'keypress', function(e) {
      if (e.keyCode === 13 || e.keyCode === 32)
      {
        e.preventDefault();
        _this.nextSlideBehaviour(e,_this)
      }
    });


    var otherPrev = this.querySelector('.prev-slide');
    var otherNext = this.querySelector('.next-slide');

    if(otherNext !== null) {
      otherNext.setAttribute('tabindex', '0');

      if (otherNext.childNodes.length === 0) {
        otherNext.replaceChildren(nextDescriptiveMarker);
      }
      this.attachEventListener(otherNext,['click'],this.nextSlideBehaviour);
    }
  }
}


/*
 * Factory to create custom slide viewers from different slide libraries.
 *
 * Defaults to the “slick carousel” library. Paves the way for alternative implementations.
 */

function slideViewerFactory(slideLibrary='Slick') {

    var slideViewerClassName ="slideViewer" + slideLibrary;

    try {
      if (typeof eval(slideViewerClassName) === 'function') {
        customElements.define('slide-viewer',eval(slideViewerClassName));
      } else {
        throw new Error("Cannot find an implementation for the " + slideLibrary  + " slide library.");
      }
    } catch (e) {
      console.log('<slide-viewer> web component not instantiated.');
    }
}




// Instantiate Slide Viewer using Slick library

slideViewerFactory('Slick');
