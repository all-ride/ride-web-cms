<?php

namespace ride\web\cms\controller\frontend;

use ride\library\cms\exception\CmsException;
use ride\library\cms\node\exception\NodeNotFoundException;
use ride\library\cms\node\NodeModel;
use ride\library\i18n\I18n;

use ride\web\cms\node\type\RedirectNodeType;
use ride\web\cms\Cms;

/**
 * Controller of the frontend for the redirect nodes
 */
class RedirectController extends AbstractController {

    /**
     * Dispatches the frontend of a redirect node
     * @param integer $node Id of the node
     * @return null
     */
    public function indexAction(Cms $cms, I18n $i18n, $site, $node, $locale = null) {
        if (!$cms->resolveNode($site, null, $node, RedirectNodeType::NAME)) {
            return;
        }

        if ($locale === null) {
            $locale = $i18n->getLocale()->getCode();
        } else {
            $i18n->setCurrentLocale($locale);
        }

        $path = $this->request->getBasePath(true);
        if (!$path || $path !== $node->getRoute($locale)) {
            return $this->chainWebRequest();
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

        if (!$cms->resolveNode($site->getId(), $site->getRevision(), $node)) {
            return;
        }

        $redirectUrl = $this->request->getBaseScript() . $node->getRoute($locale);

        $this->response->setRedirect($redirectUrl);
    }

}
