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

		// Determines which alternative presentation will be used. 'None' can be used if only alternate page
		// is used. Otherwise we're changing the template system behaviour.
		"Presentation" => "Enum('None,AlternateTemplate,DynamicTemplate', 'None')",
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

		$presentationOptions = array("None" => "None", "AlternateTemplate" => "AlternateTemplate");

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

			$presentationOptions["DynamicTemplate"] = "DynamicTemplate";
		}

		// Default makes this a simple combo, but we need it to be a tree.
		$fields->removeByName('AlternatePageID');
		$fields->addFieldToTab(
			"Root.Main",
			new TreeDropdownField("AlternatePageID", "Alternate Page", 'SiteTree'),
			"AlternateTemplate"
		);

		// We need to regenerate presentation based on what options are actually available.
		$fields->removeByName('Presentation');
		$presentation = new DropdownField('Presentation', 'Presentation (how layout is varied)', $presentationOptions);
		$fields->insertAfter($presentation, "AlternatePageID");

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
