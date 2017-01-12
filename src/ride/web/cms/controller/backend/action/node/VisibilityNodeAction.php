<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\NodeProperty;
use ride\library\cms\node\Node;
use ride\library\i18n\translator\Translator;
use ride\library\security\SecurityManager;
use ride\library\validation\exception\ValidationException;

use ride\web\base\form\DateTimeComponent;
use ride\web\cms\Cms;

use \DateTime;

/**
 * Controller of the visibility node action
 */
class VisibilityNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
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
    public function indexAction(Cms $cms, SecurityManager $securityManager, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $this->setContentLocale($locale);
        $cms->setLastAction(self::NAME);

        $locales = $cms->getLocales();
        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $security = $node->get(Node::PROPERTY_SECURITY, Cms::OPTION_INHERITED, false);
        switch ($security) {
            case Cms::OPTION_INHERITED:
            case Node::AUTHENTICATION_STATUS_EVERYBODY:
            case Node::AUTHENTICATION_STATUS_ANONYMOUS:
                $permissions = null;

                break;
            case Node::AUTHENTICATION_STATUS_AUTHENTICATED:
            default:
                $permissions = array_flip(explode(',', $security));
                $security = Node::AUTHENTICATION_STATUS_AUTHENTICATED;

                break;
        }

        $data = array(
            'availableLocales' => $cms->getLocalesValue($node->get(Node::PROPERTY_LOCALES, '', false), $node->hasParent()),
            'published' => $node->get(Node::PROPERTY_PUBLISH, 'inherit', false),
            'publishStart' => $node->get(Node::PROPERTY_PUBLISH_START, null, false),
            'publishStop' => $node->get(Node::PROPERTY_PUBLISH_STOP, null, false),
            'security' => $security,
            'permissions' => $permissions,
        );

        if ($data['publishStart']) {
            $data['publishStart'] = DateTime::createFromFormat(NodeProperty::DATE_FORMAT, $data['publishStart']);
            if ($data['publishStart']) {
                $data['publishStart'] = $data['publishStart']->getTimestamp();
            }
        }

        if ($data['publishStop']) {
            $data['publishStop'] = DateTime::createFromFormat(NodeProperty::DATE_FORMAT, $data['publishStop']);
            if ($data['publishStop']) {
                $data['publishStop'] = $data['publishStop']->getTimestamp();
            }
        }

        $securityModel = $securityManager->getSecurityModel(false);
        if ($securityModel) {
            $permissions = $securityModel->getPermissions();
            foreach ($permissions as $index => $permission) {
                $permissions[$index] = $translator->translate('permission.' . $permission->getCode()) . ' (<small>' . $permission->getCode() . '</small>)';
            }
            ksort($permissions);
        }

        $nodeType = $cms->getNodeType($node);

        $inheritPublished = $node->get(Node::PROPERTY_PUBLISH, true, true, true);

        $isFrontendNode = $nodeType->getFrontendCallback() || $node->getLevel() === 0 ? true : false;
        if ($isFrontendNode) {
            $data['hide'] = array();
            if ($node->hideInMenu()) {
                $data['hide']['menu'] = 'menu';
            }
            if ($node->hideInBreadcrumbs()) {
                $data['hide']['breadcrumbs'] = 'breadcrumbs';
            }
            if ($node->hideForAnonymousUsers()) {
                $data['hide']['anonymous'] = 'anonymous';
            }
            if ($node->hideForAuthenticatedUsers()) {
                $data['hide']['authenticated'] = 'authenticated';
            }
        }

        $form = $this->createFormBuilder($data);
        if ($site->isLocalizationMethodCopy()) {
            $form->addRow('availableLocales', 'select', array(
                'label' => $translator->translate('label.locales'),
                'description' => $translator->translate('label.locales.available.description'),
                'options' => $cms->getLocalesOptions($translator, $locales, $node->getParentNode()),
                'multiple' => true,
                'validators' => array(
                    'required' => array(),
                )
            ));
        }
        $form->addRow('published', 'option', array(
            'label' => $translator->translate('label.publish'),
            'attributes' => array(
                'data-toggle-dependant' => 'option-published',
            ),
            'options' => $this->getPublishedOptions($node, $translator),
        ));
        $form->addRow('publishStart', 'component', array(
            'label' => $translator->translate('label.publish.start'),
            'attributes' => array(
                'class' => 'option-published option-published-1' . ($inheritPublished ? ' option-published-inherit' : ''),
            ),
            'component' => new DateTimeComponent(),
        ));
        $form->addRow('publishStop', 'component', array(
            'label' => $translator->translate('label.publish.stop'),
            'attributes' => array(
                'class' => 'option-published option-published-1' . ($inheritPublished ? ' option-published-inherit' : ''),
            ),
            'component' => new DateTimeComponent(),
        ));
        $form->addRow('security', 'option', array(
            'label' => $translator->translate('label.allow'),
            'attributes' => array(
                'data-toggle-dependant' => 'option-security',
            ),
            'options' => $this->getSecurityOptions($node, $translator),
            'validators' => array(
                'required' => array(),
            ),
        ));
        if ($permissions) {
            $form->addRow('permissions', 'option', array(
                'label' => $translator->translate('label.permissions.required'),
                'attributes' => array(
                    'class' => 'option-security option-security-authenticated',
                ),
                'multiple' => true,
                'options' => $permissions,
            ));
        }
        if ($isFrontendNode) {
            $form->addRow('hide', 'option', array(
                'label' => $translator->translate('label.hide'),
                'options' => array(
                    'menu' => $translator->translate('label.hide.menu'),
                    'breadcrumbs' => $translator->translate('label.hide.breadcrumbs'),
                    'anonymous' => $translator->translate('label.hide.anonymous'),
                    'authenticated' => $translator->translate('label.hide.authenticated'),
                ),
                'multiple' => true,
            ));
        }
        $form = $form->build();

        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                if ($site->isLocalizationMethodCopy()) {
                    $node->setAvailableLocales($cms->getOptionValueFromForm($data['availableLocales']));
                } else {
                    $node->setAvailableLocales($locale);
                }

                $security = $cms->getOptionValueFromForm($data['security']);
                if ($security == Node::AUTHENTICATION_STATUS_AUTHENTICATED && $permissions && $data['permissions']) {
                    $security = implode(',', $data['permissions']);
                }

                $publishStart = $data['publishStart'] ? $data['publishStart'] : null;
                if ($publishStart) {
                    $publishStart = date('Y-m-d H:i:s', $publishStart);
                }

                $publishStop = $data['publishStop'] ? $data['publishStop'] : null;
                if ($publishStop) {
                    $publishStop = date('Y-m-d H:i:s', $publishStop);
                }

                $node->set(Node::PROPERTY_PUBLISH, $this->getPublishedValue($data['published']));
                $node->set(Node::PROPERTY_PUBLISH_START, $publishStart);
                $node->set(Node::PROPERTY_PUBLISH_STOP, $publishStop);
                $node->set(Node::PROPERTY_SECURITY, $security);

                if ($isFrontendNode) {
                    if ($node->getLevel() === 0) {
                        $inherit = false;
                    } else {
                        $inherit = null;
                    }

                    $node->setHideInMenu(isset($data['hide']['menu']), $inherit);
                    $node->setHideInBreadcrumbs(isset($data['hide']['breadcrumbs']), $inherit);
                    $node->setHideForAnonymousUsers(isset($data['hide']['anonymous']), $inherit);
                    $node->setHideForAuthenticatedUsers(isset($data['hide']['authenticated']), $inherit);
                }

                $cms->saveNode($node, 'Set visibility of ' . $node->getName());

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

        $view = $this->setTemplateView('cms/backend/node.visibility', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $locales,
        ));

        $form->processView($view);
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
            $options[Cms::OPTION_INHERITED] = $translator->translate('label.inherited') . $this->getSecurityInheritSuffix($parentNode, $translator);
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
