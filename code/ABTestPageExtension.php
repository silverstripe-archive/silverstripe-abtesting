<?php

/**
 * Extension to Page that hooks page rendering into experiments. The approach is to hook into
 * the page controller init extension method, and check if the page is either the target
 * for an experiment (choose and render an alternative), or if it's the conversion page for
 * an experiment. A page could be both, on different experiments.
 *
 * @todo Where a page is a conversion page for multiple experiments, update stats on all of them.
 */

class ABTestPageExtension extends DataObjectDecorator {
	function contentcontrollerInit($controller) {
		if ($experiment = ABTestExperiment::get_experiment_for_test_page($this->owner)) {
			$experiment->determineVariation();
			$experiment->hitTestedPage();
//			$experiment->redirectIfPageVariant();

			$variation = $experiment->getVariation();

			// @todo Separate the alternate page from the template selection in the model, so both
			//  	 alternative content and template can be selected.

			// If an alternate page is given, update the data of this page to the data of the
			// alternate page, without writing back.
			if ($variation && $variation->Presentation == "AlternatePage") {
				$this->updatePageContentFromAlternate($variation);
			}

			if ($variation && $variation->Presentation == "AlternateTemplate") {
				$templates = explode(",", $variation->AlternateTemplate);
				if (count($templates) > 1)
					$this->owner->templates['index'] = $templates;
				else if (count($templates) == 1)
					$this->owner->templates['index'] = array($variation->AlternateTemplate, "Page");

//				Debug::show("templates being overridden are " . print_r($this->owner->templates, true));
			}
			else if ($variation && $variation->Presentation == "DynamicTemplate" && class_exists("DynamicTemplate")) {
				$this->owner->DynamicTemplateID = $variation->DynamicTemplateID;
			}
		}

		// If this is a conversion page for any experiments, update stats accordingly.
		ABTestExperiment::hit_conversion_pages($this->owner);
	}

	/**
	 * When we're given an alternate page, we copy the attributes from the alternate page into $this->owner,
	 * to simulate the page. Certain things we don't copy, such as ID, URLSegment, ClassName and anything with
	 * a dot (because DataObject::update writes related objects, and we need to guarantee no state is changed.)
	 * We do this because we can't replace the owner of this extension with another object, and redirection to
	 * the alternate page typically causes the navigation to not work properly.
	 */
	public function updatePageContentFromAlternate($variation) {
		if (!$variation->AlternatePage()) return;
		$map = array();
		foreach ($variation->AlternatePage()->toMap() as $key => $value) {
			if (strpos($key, '.') === false && $key != "URLSegment" && $key != "ID")
				$map[$key] = $value;
		}
		$this->owner->update($map);
	}
}
