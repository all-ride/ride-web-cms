<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
use ride\library\validation\exception\ValidationException;
use ride\library\StringHelper;

use ride\web\cms\Cms;

/**
 * Controller of the robots.txt action
 */
class RobotsNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'robots';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.site.robots';

    /**
     * Checks if this action is available for the node
     * @param \ride\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
        return !$node->hasParent();
    }

    /**
     * Perform the robots node action
     */
    public function indexAction(Cms $cms, $locale, $site, $revision) {
        $node = $site;
        if (!$cms->resolveNode($site, $revision, $node, null, true)) {
            return;
        }

        $this->setContentLocale($locale);
        $cms->setLastAction(self::NAME);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $url = $site->getBaseUrl($locale);
        if ($url) {
            $host = parse_url($url, PHP_URL_HOST);
        } else {
            $host = $this->request->getHeader('host');
        }

        $directory = $this->dependencyInjector->get('ride\\library\\system\\file\\File', 'cms.robot');

        $file = $directory->getChild('robots-' . StringHelper::safeString($host) . '.txt');
        if ($file->exists()) {
            $robots = $file->read();
        } else {
            $robots = '';
        }

        $form = $this->createFormBuilder(array('robots' => $robots));
        $form->addRow('robots', 'text', array(
            'label' => $translator->translate('label.site.robots'),
            'description' => $translator->translate('label.site.robots.description'),
            'attributes' => array(
               'rows' => 10,
            ),
            'filters' => array(
                'trim' => array(),
            ),
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                if ($data['robots'] != $robots) {
                    if ($data['robots']) {
                        $file->write($data['robots']);
                    } elseif ($file->exists()) {
                        $file->delete();
                    }
                }

                $this->addSuccess('success.node.saved', array(
                    'node' => $site->getName($locale)
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

        $this->setTemplateView('cms/backend/site.robots', array(
            'site' => $site,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

}
