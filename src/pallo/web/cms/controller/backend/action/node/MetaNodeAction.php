<?php

namespace pallo\web\cms\controller\backend\action\node;

use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;
use pallo\library\i18n\translator\Translator;
use pallo\library\i18n\I18n;
use pallo\library\validation\exception\ValidationException;

use pallo\web\cms\form\MetaComponent;

/**
 * Controller of the meta node action
 */
class MetaNodeAction extends AbstractNodeAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'meta';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.meta';

    /**
     * Perform the advanced node action
     */
    public function indexAction(I18n $i18n, $locale, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $this->setLastAction(self::NAME);

        $data = array(
            'meta' => $node->getMeta($locale),
        );

        $translator = $this->getTranslator();
        $metaComponent = new MetaComponent();

        $form = $this->createFormBuilder($data);
        $form->addRow('meta', 'collection', array(
            'label' => $translator->translate('label.meta'),
            'type' => 'component',
            'options' => array(
                'component' => $metaComponent,
            ),
        ));
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->setMeta($locale, array_values($data['meta']));

                $nodeModel->setNode($node);

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale)
                ));

                $this->response->setRedirect($this->request->getUrl());

                return;
            } catch (ValidationException $exception) {

            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/node.meta', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

}