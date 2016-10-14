<?php

namespace ride\web\cms\controller\frontend;

use ride\library\cms\exception\NodeNotFoundException;
use ride\library\http\Response;
use ride\library\router\Route;

use ride\web\cms\Cms;

/**
 * Controller of the CMS frontend
 */
class FrontController extends AbstractController {

	/**
     * Dispatches the frontend of a node
     * @return null
	 */
	public function indexAction(Cms $cms, $node, $locale = null) {
        $siteLocale = null;

        try {
            $site = $cms->getCurrentSite($this->request->getBaseUrl(), $siteLocale);
        } catch (NodeNotFoundException $exception) {
            // not found, try the public web controller
            return $this->chainWebRequest();
        }

        $i18n = $this->getI18n();
        if ($siteLocale && $locale && $siteLocale != $locale) {
            // locale inconsistency, not found, try the public web controller
            return $this->chainWebRequest();
        } elseif ($siteLocale) {
            // set the locale of the site
            $i18n->setCurrentLocale($siteLocale);
        } elseif ($locale) {
            // set the requested locale
            $i18n->setCurrentLocale($locale);
        } else {
            // fallback locale
            $locale = $i18n->getLocale()->getCode();
        }

        // resolve the node
        $revision = $site->getRevision();
        $site = $site->getId();

        if (!$cms->resolveNode($site, $revision, $node)) {
            return $this->chainWebRequest();
        }

        // chain a request to the frontend callback
        $nodeType = $cms->getNodeType($node);

        $callback = $nodeType->getFrontendCallback();
        $arguments = ltrim($this->request->getBasePath(true), '/');

        $route = new Route('/', $callback);
        $route->setIsDynamic(true);
        $route->setArguments(explode('/', $arguments));
        $route->setPredefinedArguments(array(
            'site' => $site->getId(),
            'node' => $node->getId(),
            'locale' => $locale,
        ));

        $this->request->setRoute($route);
        $this->response->setStatusCode(Response::STATUS_CODE_OK);

        return $this->request;
    }

}
