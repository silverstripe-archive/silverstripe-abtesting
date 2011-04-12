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

To perform a test, you need to set up an **experiment**. Each experiment has a **tested page**, which is the page that
you are testing variations of. You also need to set up **variations** of the tested page. When users visit a tested
page, they will randomly get either the tested page, or one of its variations (the tested page is assumed to be one of
the variations). Typically, the variations all have an action that the user takes, which leads to a
**conversion page**. The module is measuring the proportion of users who take the action, for each variation.

The tested page displays it's normal contents. Each variation needs to be configured with what content to display,
and how that content is to be rendered, as follows:

* The variation can either use the content from the tested page, or can take content from an alternate page. Typically
  the alternate page is a child page that is hidden
  from navigation. Alternate pages can be set up in the CMS without writing code, and without a deployment process.
* The variation can choose a different way to render it's results:

  * Using an alternative template - this is used to render the same content, but with a different template. This
    typically requires design, coding and deployment for the new template.
  * Using the dynamictemplate module - this allows you or someone with design implementation skills to create a
    template and apply it to a page or a variation.

To create an experiment, go to the AB Testing menu in the CMS, and do the following:

* Add a new experiment
* Give it a name
* Provide a value for the State Variable Value. A recommended value is 'a' (without quotes)
* Select the page you are testing
* Select the conversion page
* Make sure the status is set to 'suspended'
* Save the experiment

Generally the experiment should be suspended until it is completely set up, and then made active.

### Variation Using Another Page

If you want a variation to show different content (it may or may not use a different template), do the following:

* Create a page (we recommend making it a child page of the tested page) that holds the different content. Generally
  this will be the same page type as the tested page.
* On the behaviour tab of that page, untick "Show in menus"and "Show in search"
* Publish that page
* Back on the experiment, choose the variation to use this alternate content
* In the Alternate Page dropdown, select the page you just created, and save the variation

### Variation Using a Different Template

If you want a variation to use a different template out of the site's theme, do the following:

* Choose the variation
* Enter the name of the template in the "Alternate Template" edit box.

## Variation Using a Dynamic Template

## Reporting on an Experiment