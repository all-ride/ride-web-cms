<?php

namespace pallo\web\cms\controller\frontend;

use pallo\library\cms\exception\CmsException;
use pallo\library\cms\node\exception\NodeNotFoundException;
use pallo\library\cms\node\NodeModel;
use pallo\library\i18n\I18n;

use pallo\web\base\controller\AbstractController;
use pallo\web\cms\node\type\RedirectNodeType;

/**
 * Controller of the frontend for the redirect nodes
 */
class RedirectController extends AbstractController {

	/**
     * Dispatches the frontend of a redirect node
     * @param integer $node Id of the node
     * @return null
	 */
	public function indexAction(NodeModel $nodeModel, I18n $i18n, $node, $locale = null) {
	    try {
	        $node = $nodeModel->getNode($node, RedirectNodeType::NAME);
	    } catch (NodeNotFoundException $exception) {
	        $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

	        return;
	    }

	    if ($locale === null) {
	        $locale = $i18n->getLocale()->getCode();
	    } else {
	        $i18n->setCurrentLocale($locale);
	    }

	    $url = $node->getRedirectUrl($locale);
	    if ($url) {
	        $this->response->setRedirect($url);

	        return;
	    }

	    $node = $node->getRedirectNode($locale);
	    if (!$node) {
	        throw new CmsException('No redirect properties set to this node for locale "' . $locale . '".');
	    }

	    try {
	        $node = $nodeModel->getNode($node);
	    } catch (NodeNotFoundException $exception) {
	        $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

	        return;
	    }

	    $redirectUrl = $this->request->getBaseScript() . $node->getRoute($locale);

	    $this->response->setRedirect($redirectUrl);
	}

}