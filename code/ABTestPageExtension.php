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
			$experiment->redirectIfPageVariant();

			$variation = $experiment->getVariation();

// If we could use the alternate page as a customised object, then 
//			if ($variation && $variation->Presentation == "AlternatePage") {
//				$this->owner->setCustomisedObj($variation->AlternatePage());
//			}

			if ($variation && $variation->Presentation == "AlternateTemplate") {
				$templates = explode(",", $variation->AlternateTemplate);
				if (count($templates) > 1)
					$this->owner->templates['index'] = $templates;
				else if (count($templates) == 1)
					$this->owner->templates['index'] = array($variation->AlternateTemplate, "Page");

				Debug::show("templates being overridden are " . print_r($this->owner->templates, true));
			}
			else if ($variation && $variation->Presentation == "DynamicTemplate" && class_exists("DynamicTemplate")) {
				$this->owner->DynamicTemplateID = $variation->DynamicTemplateID;
			}

			// @todo 	If the page variant uses a dynamic template we force it here.
			// @todo 	If the page variant uses an alternative template, we just force it into $this->templates['index']
		}

		// If this is a conversion page for any experiments, update stats accordingly.
		ABTestExperiment::hit_conversion_pages($this->owner);
	}
}
