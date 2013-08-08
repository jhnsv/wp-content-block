wp-content-block
================

Block plugin for WordPress.

Usage
=====

<?php echo wcb_output($block_class, $before, $after, $region); ?>

Parameters
==========

$block_class
	(string) (optional) Extra block classes, space separated.
	Default: None
	
$before
	(string) (optional) Text to place before the title.	If empty no title will be printed.
	Default: None
	
$after
	(string) (optional) Text to place after the title.
	Default: None
	
$region
	(string) (optional) What region this block will be shown in. Regions taken from register_sidebar.
	Default: Null

