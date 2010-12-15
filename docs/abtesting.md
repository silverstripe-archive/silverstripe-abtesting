# A/B Testing Module

The A/B Testing Module lets you set up experiments on your site for testing variations of a page, to determine whether
one variation has a better rate of response from users than another.

## Installation

* Install the module code into the root directory of your project

* Add the following line to the Page class in Page.php:

  var $templates;

* Optionally install the dynamictemplates module

* Perform a dev/build

## Setting up an Experiment

### Base Setup

To perform a test, you need to set up an **experiment**. Each experiment has a **tested page**, which is the page that you are testing variations of.
You also need to set up **variations** of the tested page. When users visit a tested page, they will randomly get either the tested page, or one of its
variations (the tested page is assumed to be one of the variations). Typically, the variations all have an action that the user takes, which leads to a
**conversion page**. The module is measuring the proportion of users who take the action, for each variation.

The tested page displays it's normal contents. Each variation can be configured to render data in one of the following ways:

* Using an alternative page - this is used if the same template is to be displayed, but different content is going to be displayed.
  This requires no coding changes, and can be set up completely in the CMS.
* Using an alternative template - this is used to render the same content, but with a different template. This typically requires
  design, coding and deployment for the new template.
* Using the dynamictemplate module - this allows you to have a template designed that you can upload to your site, and apply it to a page
  or a variation.

To create an experiment, go to the AB Testing menu in the CMS, and do the following:

* Add a new experiment
* Give it a name
* Provide a value for the State Variable Value. A recommended value is 'a' (without quotes)
* Select the page you are testing
* Select the conversion page
* Make sure the status is set to 'suspended'
* Save the experiment
### Variation Using Another Page

### Variation Using a Different Template

### Variation Using a Dynamic Template


## Reporting on an Experiment