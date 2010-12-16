<?php

/**
 * Admin interface for AB testing.
 *
 * This is basically a model admin, except we also add an additional tab for reporting, which is not a managed model.
 */
class ABTestAdmin extends ModelAdmin {
	static $managed_models = array(
		"ABTestExperiment" => array(
			"title" => "Experiments"
		),
		"ABTestData"
	);

	static $url_segment = 'abtesting';
	static $menu_title = "AB Testing";

	public function init() {
		parent::init();
		
		// security check for valid models
		if(isset($this->urlParams['Action']) && !in_array($this->urlParams['Action'], $this->getManagedModels())) {
			//user_error('ModelAdmin::init(): Invalid Model class', E_USER_ERROR);
		}

		Requirements::insertHeadTags('<!--[if IE]><script language="javascript" type="text/javascript" src="abtesting/thirdparty/flot/excanvas.min.js"></script><![endif]-->');
		Requirements::javascript('abtesting/thirdparty/flot/jquery.flot.js');
	}
}
