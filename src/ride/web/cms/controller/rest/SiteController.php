<?php

namespace ride\web\cms\controller\rest;

use ride\library\rest\RestHelper;

use ride\web\rest\controller\AbstractRestController;
use ride\web\cms\Cms;

/**
 * Controller for the REST service of the CMS sites
 */
class SiteController extends AbstractRestController {

    public function __construct(RestHelper $restHelper, Cms $cms) {
        $defaultIndexFields = 'name,revision';
        $defaultDetailFields = $defaultIndexFields . ',locales';

        parent::__construct($restHelper, $defaultIndexFields, $defaultDetailFields);

        $this->cms = $cms;
    }

    protected function loadEntries() {
        return $this->cms->getSites();
    }

    protected function loadEntry($id) {
        return $this->cms->getNode($id);
    }

}
