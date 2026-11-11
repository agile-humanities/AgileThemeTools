# Agile patches to third-party modules

The Agile stack runs on modules by other authors (IIIF Server, Image Server, Search Solr…). A few of them fail in
ways that matter to an Agile site, mostly when media are stored on Amazon S3. Agile Theme Tools ships small, exact
fixes for those failures, the **Agile patches**, with the means to see whether they are in place and to apply them.

Updating one of these modules replaces its files, and the patches with them, silently. That is the reason this
exists: after any update of a patched module, **apply the patches again, before running the module upgrade**.

## The patches

| Patch | Module | Level | Without it |
|---|---|---|---|
| `iiifserver-portable-manifest-cache` | IIIF Server | required | A manifest cache copied between environments points viewers at the wrong host. |
| `iiifserver-keep-media-identifier` | IIIF Server | required | IIIF image urls can silently become file paths with `%2F`, which Apache refuses: every viewer breaks. |
| `iiifserver-media-dimension-null-id` | IIIF Server | required | Creating items with media from a url (CSV Import) fails. |
| `iiifserver-no-synthetic-tiles` | IIIF Server | recommended | Deep zoom of untiled images on S3 downloads the whole original for every tile. |
| `iiifserver-free-variant-without-access` | IIIF Server (from 3.6.34) | required | No manifest is cacheable by a CDN or a browser, and the cache job deletes the cache the site reads. |
| `imageserver-remote-source-readable` | Image Server | required | With S3, every IIIF image that needs processing fails. |
| `imageserver-tiler-s3-upload` | Image Server | required | Tiles are built but never uploaded to S3. |
| `imageserver-no-fast-tile-on-remote-store` | Image Server | required | With S3, opening the Image Server config page breaks every IIIF image. |
| `imageserver-config-form-optional-url` | Image Server (before 3.6.26) | required | The Image Server config form silently does not save. |
| `searchsolr-s3-text-extraction` | Search Solr | required | With S3, transcripts and other text files are not indexed. |
| `advancedsearch-property-value-array` | Advanced Search (before 3.4.65) | required | A property search from the item search page fails. |
| `vips-pdf-no-dpi` | Vips | recommended | PDF thumbnails fail with the vips command line tool of the Agile container. |
| `amazons3-archiverepertory-interface` | Amazon S3 (with Archive Repertory) | required | Media file management on S3 fails with Archive Repertory 3.15.17+. |

Each patch is a file in `data/patches/<id>.php`: why it exists, what breaks without it, the version that fixed it
upstream if any, and its **variants**. A variant lists the module versions it was verified on, from the oldest
supported one (the `minimum` of `config/dependencies.php`) to the tested one, and its edits: exact text to find in the
stock file and its replacement. Every replacement carries the marker `AGILE-PATCH <id>`, so the patched code can be
found with `grep -rn AGILE-PATCH modules/`.

## Where to see them

- **Modules page and dashboard:** the Agile Theme Tools panel lists every required patch that is not in place, with
  what breaks and how to fix it.
- **Agile Theme Tools > Configure:** the table "Patches to third-party modules", every patch and its state.
- **Advanced Media > Configure:** "Integration checks", live requests to the site that show whether what the patches
  protect actually works (an S3 original processed by Image Server, a cacheable manifest, no fast tile rule, the media
  identifier, no tile grid for untiled images). They catch what the code check cannot: a server rule, a CDN, a lost
  patch in a file replaced some other way.
- **Easy Admin > Check and fix > Agile modules > Check:** the same, in the job log.

States: **applied**; **not applied** (missing, or partly applied); **not needed** (fixed upstream in this version, or the
version predates the issue); **modified locally** (the code was changed by hand: reinstall the module files, then
apply); **older form applied** (a hand-made earlier version of the patch is there: reinstall the module files, then
apply); **not verified on this version** (a newer module release, but a variant fits its code cleanly: review, then
apply with `--allow-unverified`); **no variant for this version** (the code changed: check upstream, write a variant).

## Applying them

After copying the files of a new module version, **before** running its upgrade (the upgrade then runs patched code):

- **Easy Admin > Check and fix > Agile modules > "Apply the Agile patches"**. The job applies every missing patch
  whose variant was verified on the installed version. The web server user must be able to write the module files
  and their directories (a file is written to a temporary file next to it, then renamed). When it cannot, the job
  changes nothing for that patch and logs the files, their owner and mode, and the command to run instead.
- **On the server**, as the owner of the module files (often root in a container), from the Omeka root:

  ```bash
  php modules/AgileThemeTools/data/scripts/patches.php            # state of every patch (exit 1 if a required one is not in place)
  php modules/AgileThemeTools/data/scripts/patches.php apply --dry-run
  php modules/AgileThemeTools/data/scripts/patches.php apply      # or: apply <id> [<id>…]
  ```

  The script needs no Omeka bootstrap: it works before the module upgrade, and in a deployment script.

Before writing, every patched file is linted (`php -l`) and the originals are copied to
`files/agile-patches/backups/<date>-<id>/` (denied to the web by an `.htaccess`; nginx needs its own rule). Restart php
(or the container) afterwards if its opcache does not revalidate files.

## Maintaining them

- **A new module release.** Unzip the releases into a folder (`<stock>/<Module>-<version>/<Module>/…`), add the
  version to the variant that fits (or write a new variant), then check every variant against every version it lists:

  ```bash
  php modules/AgileThemeTools/data/scripts/patches.php verify <stock>
  ```

  It applies each variant to each stock release, checks that it is then seen as applied, and lints the result.
- **Fixed upstream.** Set `fixed_in` to the first fixed version: the patch is then reported as not needed from that
  version, and kept for the older supported ones.
- **A new patch.** Copy a file of `data/patches/`, keep the edits minimal and exact, put the marker in every
  replacement, add `legacy` signatures if a hand-made form of it exists in the wild, and run `verify`.

Report each patch upstream: the fewer patches, the fewer ways an update can break a site.
