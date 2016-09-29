<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;

use ride\web\cms\Cms;

/**
 * Controller of the go node action
 */
class SettingsNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'settings';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.page.edit';

    /**
     * Instance of the CMS facade
     * @var \ride\web\cms\Cms
     */
    protected $cms;

    /**
     * Sets the CMS
     * @param \ride\web\cms\Cms $cms
     * @return null
     */
    public function setCms(Cms $cms) {
        $this->cms = $cms;
    }

    /**
     * Checks if this action is available for the node
     * @param \ride\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
           return $node->hasParent();
    
    }

    /**
     * Perform the go node action
     * @return null
     */
    public function indexAction($locale, $site, $revision, $node) {
        
        if (!$this->cms->resolveNode($site, $revision, $node)) {
            return;
        }
        
        if ($node->getType() === 'site') {
            $url = $node->getBaseUrl($locale);
            if (!$url) {
                $url = $this->request->getBaseScript();
            }
        } else {
            $url = $this->getUrl('cms.front.' . $site->getId() . '.' . $node->getId() . '.' . $locale);
        }

        $this->response->setRedirect($url);
    }

}
