WordPress Import
==================

Import data from WordPress data tables.

* Get the images from your WordPress installation and filter them e.g. with `find . -regextype posix-extended -regex '^.+?-[0-9]{3,4}x[0-9]{2,4}(_c)?(@2x)?(-tmp)?\.jpg(.prog)?' | xargs rm`
* Import the following data tables from WordPress into your TYPO3 database as additional tables:
  * wp_postmeta
  * wp_posts
  * wp_terms
  * wp_term_relationships
  * wp_yoast_indexable (from WP plugin "yoast")
* Copy all the images from the WordPress installation into the `fileadmin/user_uploads/wp_uploads` directory.
* Let TYPO3 index the uploads with the scheduler task "Update storage indexer"
* Start importer