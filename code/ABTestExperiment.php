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
		"Comments" => "Text",

		"Status" => "Enum('Suspended,Active,Complete', 'Suspended')",

		"StartDate" => "SSDatetime",

		"EndDate" => "SSDatetime",

		"StateMechanism" => "Enum('Session,Cookie','Session')",
//		"StateMechanism" => "Enum('Session,Cookie,QueryVariable','Session')",

		// if StateMechanism is Cookie, this is the name of the cookie. If left blank, name is
		// allocated automatically.
		"CookieName" => "Varchar",

		// Name of the state variable
		"StateVariable" => "Varchar",

		// Value given to the state variable for the tested page. Must be unique with the values in the variations
		"StateVariableValue" => "Varchar",

		// If provided, and if the URL contains a query variable of this name, it can be used to determine
		// the variation, instead of usual random assignment.
		"VariationQueryVariable" => "Varchar",

		"ConversionType" => "Enum('TargetPage','TargetPage')" // other options might include specifying an arbitrary target URL, which might need simple coding to register a hit.
	);

	static $defaults = array(
		'StateVariable' => 'q',
		'StateVariableValue' => 'a'
	);

	static $has_one = array(
		"TestedPage" => "Page",
		"ConversionPage" => "Page"
	);

	static $has_many = array(
		"Variations" => "ABTestVariation"
	);

	function getCMSFields() {
		$fields = parent::getCMSFields();

		// These are removed for now, until the features they support are fully implemented.
		$fields->removeByName('StateVariable');
		$fields->removeByName('StateMechanism');

		$fields->removeByName('TestedPageID');
		$fields->removeByName('ConversionPageID');

		$fields->removeByName('StartDate');
		$fields->removeByName('EndDate');

		$fields->removeByName('ConversionType');

		$fields->addFieldToTab(
			"Root.Main",
			new ReadonlyField("StartDate", "Start date", $this->StartDate)
		);

		$fields->addFieldToTab(
			"Root.Main",
			new ReadonlyField("EndDate", "End date", $this->EndDate)
		);

		$fields->addFieldToTab(
			"Root.Main",
			new TreeDropdownField("TestedPageID", $this->fieldLabel('Tested Page'), 'SiteTree')
		);
		$fields->addFieldToTab(
			"Root.Main",
			new TreeDropdownField("ConversionPageID", $this->fieldLabel('Conversion Page'), 'SiteTree')
			);
		$fields->addFieldToTab(
			"Root.Reports",
			new ExperimentReportField("ConversionRate", "Conversion Rate", $this->ID)
			);
		$fields->addFieldToTab(
			"Root.Reports",
			new ExperimentReportField("ConversionRaw", "Conversion Raw Clicks", $this->ID)
			);
		$fields->addFieldToTab(
			"Root.Reports",
			new ExperimentReportField("RenderRaw", "Test Page Raw Hits", $this->ID)
			);
		return $fields;
	}

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
	 * Returns all the variations and their title, as a map.
	 * @returns Map		A map whose keys are the variation variable value, and whose value is the title of the variation.
	 */
	function getAllVariations() {
		$result = array();
		$result[$this->StateVariableValue] = "Test page default variation";
		$vars = $this->Variations();
		if ($vars) foreach ($vars as $v) $result[$v->StateVariableValue] = $v->Title;
		return $result;
	}

	/**
	 * Ensure that a variation has been selected for this session. If not, calculate one and stick it in the session.
	 */
	function determineVariation() {
		// @todo If experiment uses query variable, test the query variable for the variation value.
		$v = $this->getVariationValue();

		// @todo If its not value, clear it.

		if (!$v) {
			if ($this->VariationQueryVariable && isset($_REQUEST[$this->VariationQueryVariable]))
				$v = $_REQUEST[$this->VariationQueryVariable];
			else
				$v = ABTestVariation::choose_random_variant($this);
			$this->setVariationValue($v);
		}
//		Debug::show("Variant: $v");
	}

	// set the value to where it is stored
	function setVariationValue($value) {
		switch ($this->StateMechanism) {
			case 'Session':
				Session::set("Experiment_{$this->ID}", $value);
				break;
			case 'Cookie':
				$cookieLifetime = 30; // days
				if (!$this->CookieName) $this->CookieName = "E_{$this->ID}";
				setcookie($this->CookieName, $value, time()+60*60*24* $cookieLifetime);
				break;
		}

	}

	// get the value from where it is stored
	function getVariationValue() {
		switch ($this->StateMechanism) {
			case 'Session':
				return Session::get("Experiment_{$this->ID}");

			case 'Cookie':
				if (!$this->CookieName) $this->CookieName = "E_{$this->ID}";
				if (isset($_COOKIE[$this->CookieName])) return $_COOKIE[$this->CookieName];
				return null;
		}
		return null;
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
		$expIDs = array();
		$experiments = DataObject::get("ABTestExperiment", "\"ConversionPageID\"={$page->ID} and \"Status\"='Active'");
		if ($experiments) foreach ($experiments as $experiment) {
			$experiment->hitConversionPage();
			$expIDs[$experiment->ID] = 1;
		}

		// also, check if this page is a conversion page for any variation of an experiment, and hit them too. Make sure
		// we don't double count for an experiment, which could happen if a variation specifies the same conversion
		// as the experiment.
		$variations = DataObject::get("ABTestVariation", "\"ConversionPageID\"={$page->ID}");
		if ($variations) foreach ($variations as $var) {
			$experiment = $var->Experiment();
			if (!isset($expIDs[$experiment->ID]) && $experiment->Status == 'Active') $experiment->hitConversionPage();
		}
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

	function onBeforeWrite() {
		parent::onBeforeWrite();

		if ($this->Status == 'Active' && !$this->StartDate) $this->StartDate = date('Y-m-d H:i:s');
		if ($this->Status == 'Complete' && !$this->EndDate) $this->EndDate = date('Y-m-d H:i:s');
	}
}

/**
 * A formfield that presents the results of an experiment. The $value on the constructor is the experiment ID.
 */
class ExperimentReportField extends FormField {
	function Field() {
		$result = '<div id="chart-placeholder-' . $this->Name() . '" style="width:800px;height:300px;float:left;"></div>';
		$result .= '<div id="chart-legend-' . $this->Name() . '" style="width:300px;height:300px;float:left;"></div>';

		if (!$this->value) return $result;

		$experiment = DataObject::get_by_id("ABTestExperiment", $this->value);
		$variations = $experiment->getAllVariations();

		$result .= '<script type="text/javascript">' . "\n";

		// Generate a javascript array that contains the series
		$result .= 'var series = [];';

		foreach ($variations as $variation => $title) {
			$vardata = DB::query("select DATE(Created) as DateCreated,count(ID) as CountRenders, sum(Assertiveness) as SumAssertiveness from ABTestData where StateVariableValue='$variation' and ExperimentID={$this->value} group by DAY(Created), StateVariableValue order by DATE(Created),StateVariableValue");
			$series = array();
			foreach ($vardata as $row) {
				switch ($this->Name()) {
					case "ConversionRate":
						$series[] = array(strtotime($row['DateCreated']) * 1000, (($row['SumAssertiveness'] / 100) / $row['CountRenders']) * 100);
						break;
					case "ConversionRaw":
						$series[] = array(strtotime($row['DateCreated']) * 1000, $row['SumAssertiveness'] / 100);
						break;
					case "RenderRaw":
						$series[] = array(strtotime($row['DateCreated']) * 1000, $row['CountRenders']);
						break;
				}
			}
			$jsondata = Convert::raw2json($series);
			$result .= "series.push({data: $jsondata, label: '$variation: $title'});\n";
		}

		$result .= <<<EOT
(function ($) {
	$(function () {
	    $.plot($("#chart-placeholder-{$this->Name()}"),
			series,
			{
				series: {
					lines: { show: true },
					points: { show: true }
				},
				legend: {
					show: true,
					container: $("#chart-legend-{$this->Name()}")
				},
				xaxis: { mode: "time" },
				grid: { hoverable: true }
			}
		);
	});
})(jQuery);
</script>
EOT;
		return $result;
	}
}
