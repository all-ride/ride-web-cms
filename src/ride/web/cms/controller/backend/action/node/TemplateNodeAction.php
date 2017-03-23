<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\type\HomeNodeType;
use ride\library\cms\node\type\ReferenceNodeType;
use ride\library\cms\node\Node;
use ride\library\system\file\browser\FileBrowser;
use ride\library\template\TemplateFacade;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\form\TemplatesComponent;
use ride\web\cms\Cms;

/**
 * Controller of the template node action
 */
class TemplateNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'templates';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.templates';

    /**
     * Instance of the CMS facade
     * @var \ride\web\cms\Cms
     */
    protected $cms;

    /**
     * Sets the CMS instance
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
        if (!($node->getType() !== HomeNodeType::NAME && $node->getType() !== ReferenceNodeType::NAME)) {
            return false;
        } elseif (!$node->getParent()) {
            return true;
        }

        $nodeType = $this->cms->getNodeType($node);

        return $nodeType->getFrontendCallback() ? true : false;
    }

    /**
     * Perform the template node action
     */
    public function indexAction(TemplateFacade $templateFacade, FileBrowser $fileBrowser, $locale, $site, $revision, $node) {
        if (!$this->cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $this->setContentLocale($locale);
        $this->cms->setLastAction(self::NAME);

        $referer = $this->request->getQueryParameter('referer');
        $templateFacade->setThemeModel($this->cms->getThemeModel());
        $templates = array(
            'index' => 'cms/frontend/index',
        );

        if ($node->getId() == $site->getId()) {
            $id = null;
        } else {
            $id = $node->getId();
        }

        $component = new TemplatesComponent();
        $data = $component->createData($templateFacade, $templates, $node->getTheme(), $id);

        $form = $this->buildForm($component, $data);
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $applicationDirectory = $fileBrowser->getApplicationDirectory();
                foreach ($templates as $name => $template) {
                    $file = $applicationDirectory->getChild($data['path'][$name]);
                    $file->getParent()->create();
                    $file->write($data['content'][$name]);
                }

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale)
                ));

                $url = $this->getUrl(self::ROUTE, array(
                    'site' => $site->getId(),
                    'revision' => $node->getRevision(),
                    'locale' => $locale,
                    'node' => $node->getId(),
                ));
                if ($referer) {
                    $url .= '?referer=' . urlencode($referer);
                }

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $this->setTemplateView('cms/backend/node.templates', array(
            'site' => $site,
            'node' => $node,
            'templates' => $templates,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $this->cms->getLocales(),
        ));
    }

}
