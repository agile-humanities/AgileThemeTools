class agileComponent extends HTMLElement {
  constructor() {
    super();
    this.generalStylesheetLink = null;
  }

  connectedCallback() {

    // connectedCallback fires when the COMPONENT is ready. We need to attach a listener that fires a function when the DOCUMENT is ready.
    // Most work is done in connectedCallback() and it's best practice to do everything here, but there are instances when you want to wait until the document itself is fully loaded.

    var component = this;

    window.addEventListener('load', function () {
      component.documentReady();
    })

    /* This bit of shenanigans injects the general stylesheet into the Shadow DOM so that general styles can be applied inside the element.
       It requires that the general stylesheet be give a "title" attribute. From what I can gather there is no proper way to do this, though
       that may change as the specification evolves.

       The notion is that “internal” styles should mostly be structural and should exist right in the shadow DOM. Some styles like font-faces
       and background colors seem to “get through”, though most don't and I can't find good documentation on what styles penetrate the shadowDom by default.

       This technique could be mimicked for component-specific styles.
    */

    if (this.hasAttribute('usegeneralStyles')) {
      var stylepath = this.getRootNode().querySelector('head *[title="general-stylesheet"]').getAttribute('href');
      var link = document.createElement('link');
      link.setAttribute('rel','stylesheet');
      link.setAttribute('href',stylepath);
      this.generalStylesheetLink();
    }


    // Manage the sort mechanism. When a user clicks it adds their selection as an attribute on the item browser component. This triggers the attributeChanged Callback (below).

    this.shadowRoot.querySelectorAll('a').forEach(function(element,i){
      element.addEventListener('click', event => {
        var anchorElement = event.currentTarget;
        var selection = anchorElement.getAttribute('data-sort-type');
        component.setAttribute('data-browser-sorting',selection)

      });

    });

  }

  /*
   *  @function Appends the general stylesheet to the shadowRoot.
   *
   *  Use: four conditions must be met to append a stylesheet:
   *
   *  1. The general stylesheet must exist as a <link> in the root DOM with a title attribute of
   *     “general-stylesheet”, i.e. <link title='general-stylesheet', href='...'></link>
   *  2. The web component must have an attribute of “useGeneralStyles”, eg. <my-component useGeneralStyles>
   *  3. The web component must have a shadowDom attached.
   *  4. This function must be called (i.e. this.prependGeneralStylesheet)
   *
   */

  prependGeneralStylesheet() {
    if (this.shadowRoot && this.generalStylesheetLink !== null) {
      this.shadowRoot.prepend(this.generalStylesheetLink);
    }
  }

  /*
   *  This emulates jQuery’s documentReady(). Use instead of connectedCallback() if you need to source
   *  elements from the root DOM.
   */

  documentReady() {
    this.classList.add('component-ready');
  }


  /*
   *  Standard web component call. Tells the component what attributes to “listen” for.
   *  When a listed attribute changes, it triggers the attributeChangedCallback.
   */

  static get observedAttributes() {
    return['data-async-ready'];
  }

  /*
   * Standard web component callback.
   *
   * Can be passed an "data-async-ready" attribute for systems that may be adding DOM
   * elements after $(document).ready() or window.load() methods are fired (e.g.
   * Drupal / BigPipe loads elements asynchronously).
   *
   * Note that the "data-async-ready" attribute must be added to each child component’s
   * get ObservedAttributes() return array.
   *
   * As well, every child component’s attributeChangedCallback must be set to
   * call its parent function (i.e. super.attributeChangedCallback(attrName, oldVal, newVal))
   * to enable asynchronous loading.
   *
   */

  async attributeChangedCallback(attrName, oldVal, newVal) {

    if(attrName==="data-async-ready") {
      if (this.classList.contains('component-ready') !== true) {
        this.documentReady();
      }
    }

  }

  /*
   *  Attaches a listener for an event or an array of events.
   *  @param obj | The listening object
   *  @param event | A DOM event or array of events
   *  @param func | A function to handle the event(s) when triggered. The handler will be passed both the
   *   event object (e) and the global this object.
   *
   */

  attachEventListener(obj, event, func) {

    var component = this;

    event = Array.isArray(event) ? event : [event];

    for (var i=0;i<event.length;i++) {
      obj.addEventListener(event[i], function(e) { func(e,component)}); // The event listener passes in the component object and event info,
    }
  }

  /*
   * Utility function used primary for dynamically generated functions
   *
   */

  capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
  }

  /*
   * Utility function to generate a unique id.
   *
   */

  generateUniqueId() {
    // Math.random should be unique because of its seeding algorithm.
    // Convert it to base 36 (numbers + letters), and grab the first 9 characters
    // after the decimal.
    return '_' + Math.random().toString(36).substr(2, 9);
  };

}
