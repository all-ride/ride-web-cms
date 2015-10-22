<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\validation\exception\ValidationException;

use ride\web\cms\form\MetaComponent;
use ride\web\cms\Cms;

/**
 * Controller of the meta node action
 */
class AnalyticsNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'analytics';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.site.analytics';

    /**
     * Perform the meta node action
     */
    public function indexAction(Cms $cms, MetaComponent $metaComponent, $locale, $site, $revision, $node) {
        echo "Hello world!";
    }

}
