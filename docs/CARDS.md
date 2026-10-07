# Cards and display modes

A **display mode** is an alternative presentation of a resource (item, item set, media) or a site page for a
context other than its own page: a card in a browse list, a search result, a section listing. Display modes
are registered under `display_modes` in the module configuration, the way Omeka registers block layouts; the
data is prepared in PHP (`DisplayMode\Card::prepare()`) and a partial renders it, which a theme overrides for
markup alone. One mode ships: **card**.

```php
echo $this->card($item);                                             // a div.card
echo $this->card($item, ['containerElement' => 'li', 'cardStyle' => 'list-item']);
echo $this->card($page, ['label' => 'Exhibit']);                     // a site page
echo $this->displayMode('card', $itemSet);                           // the general form
```

The theme's own card markup lives in `view/common/display-mode/card.phtml`; the module's version, used by any
other theme, is plain and styled by the basic stylesheets when they are on (STYLING.md).

## What a card shows

Installation-wide defaults under **Settings > Agile Theme Tools > Cards**, and per-site overrides under the site's
**Settings > Cards** (a site value left empty uses the installation's):

| Setting | Default | Meaning |
|---|---|---|
| Title property | the site's browse heading property, else the resource's title | |
| Subtitle property | `dcterms:creator` | shown under the title |
| Label | the `dcterms:type` value | or the resource class label |
| Summary property | `dcterms:description` | `dcterms:abstract` is the usual alternative for a hand-written summary |
| Summary length | 300 characters | cut at the last sentence end that fits, else at a word |
| Metadata | `dcterms:created, dcterms:date` | the short list under the summary (the theme's "Card Terms" setting still counts where a site has not set this) |
| Representative image property | `foaf:depiction` | see below |
| Thumbnail size | large | |
| Collection line, rights line | shown | |

## The representative image

In order, the first that exists:

1. **The resource's own thumbnail** — Omeka's "Thumbnail" field on the item's edit form (an asset chosen from the
   asset library). The explicit choice Omeka itself offers; it wins over everything.
2. **A media designated through the representative-image property**, `foaf:depiction` by default. This is how
   to say "of this item's attached files, *this* image stands for it" without re-uploading anything:
   - Edit the item. Under its values, add the property **Depiction** (vocabulary FOAF, term `foaf:depiction`; it
     ships with Omeka, no vocabulary to import). If the property is not offered, the item's resource template
     limits the properties — either add `foaf:depiction` to the template, or switch the form to show all
     properties.
   - Choose the value type **Omeka resource**, then **Media**, and pick the attached media. A linked **Item** also
     works: its primary media is used.
   - Save. Cards now use that media. The value is ordinary metadata: visible in the API, exported with the item,
     searchable. The property is in the FOAF vocabulary because FOAF is bundled with Omeka and its meaning
     ("an image depicting this thing") is right; most installations use no other FOAF term, and that is fine.
   - The property can be changed in the settings (an installation that prefers `schema:image` after importing
     that vocabulary, say).
3. **The primary media** — the first attached media, in the order set on the item.
4. **The first attached media that has a thumbnail** (an image among documents).
5. None: the theme shows its placeholder.

## Site pages

Omeka's site pages carry no metadata, so a page describes itself for listings through one block, **Page
summary**: the representative image (attach the item or media whose image stands for the page), a summary, a
byline and a label. It renders nothing on the page unless "Show on the page" is ticked; section listings,
link cards and search results read it through the card mode. The older **Representative image**, **Excerpt**
and **Byline** blocks are still read when a page has no Page summary block, so existing pages keep working.
Without any of them the card uses the page's first text block, summarised, and the first attached media.

## Summaries

`DisplayMode\Summary::summarize($html, $max)`: tags removed, whitespace collapsed, cut to at most `$max`
characters at the last sentence end that fits (a sentence must be at least 30% of the limit, so a fragment is
not returned as a whole), failing that at a word boundary, with an ellipsis when something was cut.
