<?php

/**
 *
 * Notes:
 * - once an experiment becomes active, it cannot be modified
 * - probably need to warn if multiple active experiments have the same target page.
 */

class ABTestExperiment extends DataObject {
	static $db = array(
		"Title" => "Varchar",
		"Status" => "Enum('Suspended,Active,Complete', 'Suspended')",
		"StateMechanism" => "Enum('Session,Cookie,QueryVariable','Session')",

		// Name of the state variable
		"StateVariable" => "Varchar",

		// Value given to the state variable for the tested page. Must be unique with the values in the variations
		"StateVariableValue" => "Varchar",

		"ConversionType" => "Enum('TargetPage','TargetPage')" // other options might include specifying an arbitrary target URL, which might need simple coding to register a hit.
	);

	static $has_one = array(
		"TestedPage" => "Page",
		"ConversionPage" => "Page"
	);

	static $has_many = array(
		"Variations" => "ABTestVariation"
	);

	/**
	 * Determine if the given page is subject to an active experiment.
	 * @return Return the experiment (or the first) or null if there isn't one.
	 */
	static function get_experiment_for_test_page($page) {
		return DataObject::get_one("ABTestExperiment", "\"TestedPageID\"={$page->ID} and \"Status\"='Active'", "ID");
	}

	/**
	 * Get a variation based on its state variable value.
	 * @return ABTestVariation in the case it's a variant, or null in the case that its the tested page itself.
	 */
	function getVariation($stateVariable = null) {
		if (!$stateVariable) $stateVariable = $this->getVariationValue();
		return DataObject::get_one("ABTestVariation", "\"ExperimentID\"={$this->ID} and \"StateVariableValue\"='{$stateVariable}'");
	}

	/**
	 * Ensure that a variation has been selected for this session. If not, calculate one and stick it in the session.
	 */
	function determineVariation() {
		// @todo If experiment uses query variable, test the query variable for the variation value.
		$v = $this->getVariationValue();

		// @todo If its not value, clear it.

		if (!$v) {
			$v = ABTestVariation::choose_random_variant($this);
			Session::set("Experiment_{$this->ID}", $v);
		}
		Debug::show("Variant: $v");
	}

	function getVariationValue() {
		return Session::get("Experiment_{$this->ID}");
	}

	/**
	 * If the selected variant uses an alternate page, then we redirect to that page.
	 */
	function redirectIfPageVariant() {
		$variation = $this->getVariation();
		if (!$variation) return;
		if ($variation->Presentation == "AlternatePage") Director::redirect($variation->AlternatePage()->Link());
	}

	/**
	 * Create a record that this page was hit.
	 */
	function hitTestedPage() {
		// check if its already been recorded
		$data = DataObject::get_one("ABTestData", "\"PHPSessionID\"='" . session_id() . "' and \"ExperimentID\"={$this->ID}");
		if ($data) return; // already there, don't want to say we've started again.
		
		$data = new ABTestData();
		$data->PHPSessionID = session_id();
		$data->WhenRendered = SS_Datetime::now();
		$data->StateVariableValue = $this->getVariationValue();
		$data->ExperimentID = $this->ID;
		$data->write();
	}

	/**
	 * If the given page is the conversion page for any active experiments, update all of those pages.
	 */
	static function hit_conversion_pages($page) {
		$experiments = DataObject::get("ABTestExperiment", "\"ConversionPageID\"={$page->ID} and \"Status\"='Active'");
		if ($experiments) foreach ($experiments as $experiment) $experiment->hitConversionPage();
	}

	/**
	 * Called when a conversion page detects it has been hit as part of an experiment.
	 * @todo 	If this doesn't find an ABTestData object, should it perhaps create one that records
	 *     		the conversion without the test page, to show the rate at which the conversion
	 *			is otherwise hit.
	 */
	function hitConversionPage($assertiveness = 100) {
		$data = DataObject::get_one("ABTestData", "\"PHPSessionID\"='" . session_id() . "' and \"ExperimentID\"={$this->ID}");

		if (!$data) return; // shouldn't happen, but we're tolerant.
		$data->WhenConverted = SS_Datetime::now();
		$data->Assertiveness = $assertiveness;
		$data->write();
	}
}
