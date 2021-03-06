<?php

namespace ride\web\cms\controller\frontend;

use ride\application\cache\control\CmsCacheControl;

use ride\library\cms\exception\NodeNotFoundException;
use ride\library\cms\node\type\SiteNodeType;
use ride\library\cms\node\Node;
use ride\library\dependency\exception\DependencyNotFoundException;
use ride\library\http\Response;
use ride\library\security\exception\AuthenticationException;
use ride\library\security\exception\UnauthorizedException;
use ride\library\template\TemplateFacade;

use ride\web\cms\node\dispatcher\NodeDispatcherFactory;
use ride\web\cms\Cms;

/**
 * Controller of the CMS frontend
 */
class NodeController extends AbstractController {

    /**
     * Dispatches the frontend of a node
     * @return null
     */
     public function indexAction(Cms $cms, CmsCacheControl $cacheControl, NodeDispatcherFactory $nodeDispatcherFactory, TemplateFacade $templateFacade, $site, $node, $locale = null) {
        $cache = null;
        if ($cacheControl->isEnabled()) {
            $cache = $this->dependencyInjector->get('ride\\library\\cache\\pool\\CachePool', 'cms');
        }

        $i18n = $this->getI18n();
        $siteLocale = null;

        try {
            $site = $cms->getNode($site, $cms->getDefaultRevision(), $site, SiteNodeType::NAME);
            // $site = $cms->getCurrentSite($this->request->getBaseUrl(), $siteLocale);
        } catch (NodeNotFoundException $exception) {
            // not found, try the public web controller
            return $this->chainWebRequest();
        }

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

        $nodeDispatcher = $nodeDispatcherFactory->createNodeDispatcher($site, $node, $this->request->getBaseScript(), $locale);
        if ($nodeDispatcher) {
            $node = $nodeDispatcher->getNode();
            if ($node->isPublished() && $node->isAvailableInLocale($locale)) {
                $securityManager = $this->getSecurityManager();
                if (!$node->isAllowed($securityManager)) {
                    throw new UnauthorizedException();
                }

                $nodeDispatcher->setIsDebug($this->getConfig()->get('cms.debug'));

                $nodeView = $nodeDispatcher->getView();
                $nodeView->setTemplateFacade($templateFacade);
                $nodeView->setLayouts($cms->getLayouts());

                $textParser = $this->dependencyInjector->get('ride\\library\\cms\\content\\text\\TextParser', 'chain');
                $textParser->setBaseUrl($this->request->getBaseUrl());
                $textParser->setSiteUrl($this->request->getBaseScript());

                $templateFacade->setThemeModel($cms->getThemeModel());
                $templateFacade->setDefaultTheme($nodeView->getTemplate()->getTheme());

                $this->dependencyInjector->setInstance($node, 'ride\\library\\cms\\node\\Node');

                $nodeDispatcher->dispatch($this->request, $this->response, $securityManager, $cache);
                if ($this->response->getStatusCode() != Response::STATUS_CODE_NOT_FOUND) {
                    $this->setHeaders($node, $locale);

                    return;
                }
            }
        }

        // not found, try the public web controller
        return $this->chainWebRequest();
    }

    /**
     * Sets the node headers to the response
     * @param \ride\library\cms\node\Node $node
     * @param string $locale
     * @return null
     */
    protected function setHeaders(Node $node, $locale) {
        $headers = $node->getHeader($locale);

        foreach ($headers as $name => $value) {
            if ($value === '') {
                continue;
            }

            switch ($name) {
                case 'max-age':
                    $this->response->setMaxAge($this->parseAgeValue($node, $value));

                    break;
                case 's-maxage':
                    $this->response->setSharedMaxAge($this->parseAgeValue($node, $value));

                    break;
                default:
                    $this->response->setHeader($name, $value);

                    break;
            }
        }
    }

    /**
     * Parses the age value to seconds
     * @param mixed $value Value to parse
     * @return integer Age in seconds
     */
    protected function parseAgeValue(Node $node, $value) {
        $time = time();

        switch ($value) {
            case 'end-of-day':
                $value = mktime(23, 59, 59) - $time;
            default:
                 $value = (integer) $value;
        }

        $dateExpires = $node->getDateExpires();
        if ($dateExpires) {
            $maximum = $dateExpires - $time;

            $value = min($value, $maximum);
        }

        return $value;
    }

}
