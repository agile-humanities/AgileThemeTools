/**
 * @license Copyright (c) 2003-2015, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */
CKEDITOR.editorConfig = function (config) {
  // Define changes to default configuration here.
  // For complete reference see:
  // http://docs.ckeditor.com/#!/api/CKEDITOR.config

  config.toolbar = [
    {
      name: "advanced",
      items: ["Sourcedialog", "-", "Link", "Unlink", "Anchor", "-", "Format"],
    },
    "/",
    {
      items: [
        "Bold",
        "Italic",
        "Underline",
        "Strike",
        "-",
        "NumberedList",
        "BulletedList",
        "Indent",
        "Outdent",
        "Blockquote",
      ],
    },
  ];

  // Filter content
  config.allowedContent = false;
  config.extraPlugins = "sourcedialog";
  config.pasteFromWordRemoveStyles = true;
  config.pasteFromWordRemoveFontStyles = true;

  // Set  block elements.
  config.format_tags = "p;h1;h2;h3;h4;h5;h6;pre;div";
  config.extraAllowedContent =
    "dl;dt;dd;parallax-layers(*);parallax-layers[id];p(*);div(*);div[slot];div[id]";
};

// Ensure that custom elements aren’t wrapped in a paragraph tag

CKEDITOR.on("instanceReady", function (ev) {
  this.dtd.$block["parallax-layers"] = 1;
  this.dtd["parallax-layers"] = {
    "#": 1,
    div: 1,
    p: 1,
    section: 1,
  };
  console.log(this);
});
