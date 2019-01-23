<?php

namespace ride\web\cms\controller\frontend;

use ride\library\http\Response;

use ride\web\cms\Cms;

/**
 * Controller for the frontend of the expired routes
 */
class ExpiredController extends AbstractController {

	/**
     * Dispatches the frontend of a expired route
     * @param \ride\web\cms\Cms $cms Facade of the CMS
     * @param string $site Id of the site
     * @param string $node Id of the node
     * @return null
	 */
	public function indexAction(Cms $cms, $site, $node) {
        if (!$cms->resolveNode($site, null, $node)) {
            return;
        }

	    $locale = $this->getLocale();

	    $redirectUrl = $this->request->getBaseScript() . $node->getRoute($locale);

	    if (func_num_args() > 3) {
	        $arguments = func_get_args();
	        array_shift($arguments);
	        array_shift($arguments);
	        array_shift($arguments);

	        $redirectUrl .= '/' . implode('/', $arguments);
	    }

	    $this->response->setRedirect($redirectUrl, Response::STATUS_CODE_MOVED_PERMANENTLY);
	}

}
