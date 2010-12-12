<?php

/**
 * This is created on the rendering of a test page. If the conversion page is hit, it
 * is updated.
 */
class ABTestData extends DataObject {
	static $db = array(
		// The PHP session ID is used as a key to uniquely identify visits.
		"PHPSessionID" => "Varchar",

		// When the page was rendered
		"WhenRendered" => "SSDatetime",

		// When the conversion page was hit
		"WhenConverted" => "SSDatetime",

		// The variation generated
		"StateVariableValue" => "Varchar",

		// A number between 0 and 100 that indicates the "assertiveness" with which the user
		// hit the conversion page. 0 means they haven't, 100 means they went straight from
		// the tested page to the conversion page.
		"Assertiveness" => "Int"
	);

	static $defaults = array(
		"Assertiveness" => 0
	);

	static $has_one = array(
		'Experiment' => 'ABTestExperiment',
	);
}
