<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\i18n\translator\Translator;
use ride\library\i18n\I18n;
use ride\library\validation\exception\ValidationException;

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
            'security' => $node->get(Node::PROPERTY_SECURITY, 'inherit', false),
        );

        $nodeTypeManager = $nodeModel->getNodeTypeManager();
        $nodeType = $nodeTypeManager->getNodeType($node->getType());
        $isFrontendNode = $nodeType->getFrontendCallback() || $node->getLevel() === 0 ? true : false;
        if ($isFrontendNode) {
            $data['hide'] = array();
            if ($node->hideInMenu()) {
                $data['hide']['menu'] = 'menu';
            }
            if ($node->hideInBreadcrumbs()) {
                $data['hide']['breadcrumbs'] = 'breadcrumbs';
            }
        }

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
            'validators' => array(
            	'regex' => array(
            	    'required' => false,
                    'regex' => '/2([0-9]){3}-([0-9]){2}-([0-9]){2} ([0-9]){2}:([0-9]){2}:([0-9]){2}/',
            	    'error.regex' => 'error.validation.date.cms',
                ),
            ),
        ));
        $form->addRow('publishStop', 'string', array(
            'label' => $translator->translate('label.publish.stop'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'regex' => array(
                    'required' => false,
                    'regex' => '/2([0-9]){3}-([0-9]){2}-([0-9]){2} ([0-9]){2}:([0-9]){2}:([0-9]){2}/',
                    'error.regex' => 'error.validation.date.cms',
                ),
            ),
        ));
        $form->addRow('security', 'option', array(
            'label' => $translator->translate('label.allow'),
            'options' => $this->getSecurityOptions($node, $translator),
            'validators' => array(
            	'required' => array(),
            ),
        ));

        if ($isFrontendNode) {
            $form->addRow('hide', 'option', array(
                'label' => $translator->translate('label.hide'),
                'options' => array(
            	    'menu' => $translator->translate('label.hide.menu'),
            	    'breadcrumbs' => $translator->translate('label.hide.breadcrumbs'),
                ),
                'multiple' => true,
            ));
        }
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->set(Node::PROPERTY_PUBLISH, $this->getPublishedValue($data['published']));
                $node->set(Node::PROPERTY_PUBLISH_START, $data['publishStart']);
                $node->set(Node::PROPERTY_PUBLISH_STOP, $data['publishStop']);
                $node->set(Node::PROPERTY_SECURITY, $this->getSecurityValue($data['security']));

                if ($isFrontendNode) {
                    if ($node->getLevel() === 0) {
                        $inherit = false;
                    } else {
                        $inherit = null;
                    }

                    $node->setHideInMenu(isset($data['hide']['menu']), $inherit);
                    $node->setHideInBreadcrumbs(isset($data['hide']['breadcrumbs']), $inherit);
                }

                $nodeModel->setNode($node, 'Set visibility of ' . $node->getName());

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

                $this->setValidationException($validationException, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/node.visibility', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
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
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\i18n\translator\Translator $translator
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
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\i18n\translator\Translator $translator
     * @return string if a publish setting is found the suffix will be " (Yes)" or " (No)"
     */
    protected function getPublishedInheritSuffix(Node $node, Translator $translator) {
        $published = $node->get(Node::PROPERTY_PUBLISH, true, true, true);

        $suffix = ' (';
        if ($published) {
            $suffix .= strtolower($translator->translate('label.yes'));
        } else {
            $suffix .= strtolower($translator->translate('label.no'));
        }
        $suffix .= ')';

        return $suffix;
    }

    /**
     * Gets the security value
     * @param string $security Form value
     * @return null|string
     */
    private function getSecurityValue($security) {
        if ($security == 'inherit') {
            return null;
        } else {
            return $security;
        }
    }

    /**
     * Gets the security options
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\i18n\translator\Translator $translator
     * @return array Array with the publish code as key and the translation as
     * value
     */
    protected function getSecurityOptions(Node $node, Translator $translator) {
        $options = array();

        $parentNode = $node->getParentNode();
        if ($parentNode) {
            $options['inherit'] = $translator->translate('label.inherited') . $this->getSecurityInheritSuffix($parentNode, $translator);
        }

        $options[Node::AUTHENTICATION_STATUS_EVERYBODY] = $translator->translate('label.allow.everybody');
        $options[Node::AUTHENTICATION_STATUS_ANONYMOUS] = $translator->translate('label.allow.anonymous');
        $options[Node::AUTHENTICATION_STATUS_AUTHENTICATED] = $translator->translate('label.allow.authenticated');

        return $options;
    }

    /**
     * Get a suffix for the security inherit label based on the inherited settings
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\i18n\translator\Translator $translator
     * @return string if a publish setting is found the suffix will be " (Yes)" or " (No)"
     */
    protected function getSecurityInheritSuffix(Node $node, Translator $translator) {
        $security = $node->get(Node::PROPERTY_SECURITY, Node::AUTHENTICATION_STATUS_EVERYBODY, true, true);

        $suffix = ' (';
        switch ($security) {
            case Node::AUTHENTICATION_STATUS_EVERYBODY:
                $suffix .= $translator->translate('label.allow.everybody');

                break;
            case Node::AUTHENTICATION_STATUS_ANONYMOUS:
                $suffix .= $translator->translate('label.allow.anonymous');

                break;
            case Node::AUTHENTICATION_STATUS_AUTHENTICATED:
                $suffix .= $translator->translate('label.allow.authenticated');

                break;
            default:
                $suffix .= $security;

                break;
        }
        $suffix .= ')';

        return strtolower($suffix);
    }

}
