<?php

/**
 * Admin interface for AB testing.
 */
class ABTestAdmin extends ModelAdmin {
	static $managed_models = array(
		"ABTestExperiment",
		"ABTestData"
	);

	static $url_segment = 'abtesting';
	static $menu_title = "AB Testing";
}