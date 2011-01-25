<?php

/*
 * Represents a variation on an experiment.
 * @todo Enforce business rules:
 *  	- StateVariableValue values must be unique within the experiment.
 *		- if variant Presentation is AlternateTemplate, validate the template value
 *		- if variant Presentation is DynamicTemplate, valid the template ID. This option should only be given when
 *		  the module is installed.
 */

class ABTestVariation extends DataObject {
	static $db = array(
		"Title" => "Varchar",
		"Presentation" => "Enum('AlternatePage,AlternateTemplate,DynamicTemplate', 'AlternatePage')",
		"StateVariableValue" => "Varchar",

		// Name of an alternative Layout template, or a comma-separated list of all the templates, required
		// when Presentation is AlternatePage
		"AlternateTemplate" => "Varchar",

		// ID of a dynamic template to use, required if Presentation is DynamicTemplate. This is affectively
		// a has_one, but we don't express it that way as the dynamic template module might not be installed.
		"DynamicTemplateID" => "Int"
	);

	static $has_one = array(
		"Experiment" => "ABTestExperiment",
		"AlternatePage" => "SiteTree"
	);

	function getCMSFields() {
		$fields = parent::getCMSFields();
		
		// Remove the default dynamic template ID field, and add in a combo of the dynamic templates, provided the
		// module is actually installed. Required because DynamicTemplateID is not a has_one, in case the dynamictemplate module
		// is not installed.
		$fields->removeByName('DynamicTemplateID');
		if (class_exists('DynamicTemplate')) {
			$ds = DataObject::get("DynamicTemplate", null, "Title");
			$items = array();
			$items = array("0" => "No template");
			if ($ds) foreach ($ds as $d) {
				$items[$d->ID] = $d->Title;
			}

			$fields->addFieldToTab(
				"Root.Main",
				new DropdownField(
					"DynamicTemplateID",
					"Dynamic template",
					$items
				));
		}

		$fields->removeByName('AlternatePageID');
		$fields->addFieldToTab(
			"Root.Main",
			new TreeDropdownField("AlternatePageID", "Alternate Page", 'SiteTree'));
		return $fields;
	}

	/*
	 * Select one of the variants in the experiment, and return it's state variable.
	 */
	static function choose_random_variant($experiment) {
		$count = DB::query("select count(*) from ABTestVariation where ExperimentID={$experiment->ID}")->Value();
		$rand = rand(0, $count); // generates the # variations, plus 1 for the tested page which is the default variation
		if ($rand == $count) return $experiment->StateVariableValue;
		else {
			$var = DataObject::get("ABTestVariation", "\"ExperimentID\"={$experiment->ID}", "ID", "", array("start" => $rand, "limit" => 1));
			if (!$var) return;
			return $var->First()->StateVariableValue;
		}
	}
	
}
