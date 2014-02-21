<?php

namespace ride\web\cms\controller\backend;

use ride\library\cms\exception\CmsException;
use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\NodeModel;
use ride\library\cms\node\Node;
use ride\library\cms\theme\ThemeModel;
use ride\library\http\Response;

use ride\web\base\controller\AbstractController;

/**
 * Abstract CMS backend controller
 */
abstract class AbstractBackendController extends AbstractController {

    /**
     * Session key for the last action type
     * @var string
     */
    const SESSION_LAST_ACTION = 'cms.action.last';

    /**
     * Resolves the provided site and node
     * @param ride\library\cms\node\NodeModel $nodeModel Instance of the node
     * model
     * @param string $site Id of the site, will become the site Node instance
     * @param string $node Id of the node, if set will become the Node instance
     * @param string $type Expected node type
     * @param boolean|integer $depth Number of children levels to fetch, false
     * to fetch all child levels
     * @return boolean True when the node is succesfully resolved, false if
     * the node could not be found, the response code will be set to 404
     */
    protected function resolveNode(NodeModel $nodeModel, &$site, &$node = null, $type = null, $children = false) {
        try {
            $site = $nodeModel->getNode($site, 'site', $children);

            if ($node) {
                $node = $nodeModel->getNode($node, $type, $children);

                if ($node->getRootNodeId() != $site->getId()) {
                    throw new NodeNotFoundException($node->getId());
                }
            }
        } catch (CmsException $exception) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return false;
        }

        return true;
    }

    /**
     * Resolves the provided region
     * @param ride\library\cms\theme\ThemeModel $themeModel
     * @param ride\library\cms\layout\LayoutModel $layoutModel
     * @param ride\library\cms\node\Node $node
     * @param string $locale
     * @param string $region
     * @param string $theme
     * @param string $layout
     * @return boolean True when the region is available in the provided node,
     * false if the region is not available, the response code will be set to
     * 404
     */
    protected function resolveRegion(ThemeModel $themeModel, LayoutModel $layoutModel, Node $node, $locale, $region, &$theme = null, &$layout = null) {
        try {
            if (method_exists($node, 'getLayout') && $layout = $node->getLayout($locale)) {
                $layout = $layoutModel->getLayout($layout);
                $isLayoutRegion = $layout->hasRegion($region);
            } else {
                $layouts = $layoutModel->getLayouts();
                foreach ($layouts as $l) {
                    if ($l->hasRegion($region)) {
                        $isLayoutRegion = true;

                        break;
                    }
                }
            }

            $theme = $node->getTheme();
            if ($theme) {
                $theme = $themeModel->getTheme($theme);
            }

            $isThemeRegion = $theme->hasRegion($region);
            if (!$isThemeRegion && !$isLayoutRegion) {
                throw new CmsException();
            }
        } catch (CmsException $exception) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return false;
        }

        return true;
    }

    /**
     * Gets the last action type which has been executed.
     *
     * This is used to if a user changes node in the UI, he will stay in the
     * same type of backend page. For example, when the user is in the advanced
     * page, he will come in the advanced page of the other node
     * @param string $default Default action to return when the last action is
     * not set
     * @return string|null Action type if a node has been opened, null
     * otherwise
     */
    protected function getLastAction($default = null) {
        if (!$this->request->hasSession()) {
            return $default;
        }

        $session = $this->request->getSession();

        return $session->get(self::SESSION_LAST_ACTION, $default);
    }

    /**
     * Sets the last action type to the session
     * @param string $type Type of the last action
     * @return null
     */
    protected function setLastAction($type) {
        $session = $this->request->getSession();
        $session->set(self::SESSION_LAST_ACTION, $type);
    }

}