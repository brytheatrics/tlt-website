<?php
/**
 * Season term archive — delegates to archive-tlt_show.php which handles
 * the is_tax( 'tlt_season' ) branch. Without this file, WP falls through
 * to archive.php / index.php and the show-grid layout is bypassed.
 */
require __DIR__ . '/archive-tlt_show.php';
