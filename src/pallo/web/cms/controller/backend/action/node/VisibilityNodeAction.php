<?php

namespace pallo\web\cms\controller\backend\action\node;

use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;
use pallo\library\i18n\translator\Translator;
use pallo\library\i18n\I18n;
use pallo\library\validation\exception\ValidationException;

/**
 * Controller of the advanced node action
 */
class VisibilityNodeAction extends AbstractNodeAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'visibility';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.visibility';

    /**
     * Perform the advanced node action
     */
    public function indexAction(I18n $i18n, $locale, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $this->setLastAction(self::NAME);

        $data = array(
        	'published' => $node->get(Node::PROPERTY_PUBLISH, 'inherit', false),
        	'publishStart' => $node->get(Node::PROPERTY_PUBLISH_START, null, false),
        	'publishStop' => $node->get(Node::PROPERTY_PUBLISH_STOP, null, false),
        );

        $translator = $this->getTranslator();

        $form = $this->createFormBuilder($data);
        $form->addRow('published', 'option', array(
            'label' => $translator->translate('label.publish'),
            'options' => $this->getPublishedOptions($node, $translator),
        ));
        $form->addRow('publishStart', 'string', array(
            'label' => $translator->translate('label.publish.start'),
            'description' => $translator->translate('label.publish.start.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('publishStop', 'string', array(
            'label' => $translator->translate('label.publish.stop'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->set(Node::PROPERTY_PUBLISH, $this->getPublishedValue($data['published']));
                $node->set(Node::PROPERTY_PUBLISH_START, $data['publishStart']);
                $node->set(Node::PROPERTY_PUBLISH_STOP, $data['publishStop']);

                $nodeModel->setNode($node);

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale)
                ));

                $this->response->setRedirect($this->request->getUrl());

                return;
            } catch (ValidationException $exception) {
                $validationException = new ValidationException();

                $errors = $exception->getAllErrors();
                foreach ($errors as $field => $fieldErrors) {
                    if ($field == Node::PROPERTY_PUBLISH) {
                        $validationException->addErrors('published', $fieldErrors);
                    } elseif ($field == Node::PROPERTY_PUBLISH_START) {
                        $validationException->addErrors('publishStart', $fieldErrors);
                    } elseif ($field == Node::PROPERTY_PUBLISH_STOP) {
                        $validationException->addErrors('publishStop', $fieldErrors);
                    } else {
                        $validationException->addErrors($field, $fieldErrors);
                    }
                }

                $form->setValidationException($validationException);
            }
        }

        $this->setTemplateView('cms/backend/node.visibility', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

    /**
     * Gets the published value
     * @param string $published Form value
     * @return null|integer Node value
     */
    private function getPublishedValue($published) {
        if ($published == 'inherit') {
            return null;
        } elseif ($published == '1') {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Gets the publish options
     * @param pallo\library\i18n\translator\Translator $translator
     * @return array Array with the publish code as key and the translation as
     * value
     */
    protected function getPublishedOptions(Node $node, Translator $translator) {
        $options = array();

        $parentNode = $node->getParentNode();
        if ($parentNode) {
            $options['inherit'] = $translator->translate('label.inherited') . $this->getPublishedInheritSuffix($parentNode, $translator);
        }

        $options['1'] = $translator->translate('label.yes');
        $options['0'] = $translator->translate('label.no');

        return $options;
    }

    /**
     * Get a suffix for the publish inherit label based on the inherited settings
     * @param joppa\model\NodeSettings $nodeSettings
     * @param zibo\library\i18n\translation\Translator $translator
     * @return string if a publish setting is found the suffix will be " (Yes)" or " (No)"
     */
    protected function getPublishedInheritSuffix(Node $node, Translator $translator) {
        $published = $node->get(Node::PROPERTY_PUBLISH, Node::AUTHENTICATION_STATUS_EVERYBODY, true, true);

        $suffix = ' (';
        if ($published) {
            $suffix .= strtolower($translator->translate('label.yes'));
        } else {
            $suffix .= strtolower($translator->translate('label.no'));
        }
        $suffix .= ')';

        return $suffix;
    }

}