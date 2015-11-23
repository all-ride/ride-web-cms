<?php

namespace ride\web\cms\controller\backend\action\widget;

use ride\library\cms\node\Node;
use ride\library\cms\widget\Widget;
use ride\library\i18n\translator\Translator;
use ride\library\security\SecurityManager;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

/**
 * Controller of the style widget action
 */
class VisibilityWidgetAction extends AbstractWidgetAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'visibility';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.content.widget.visibility';

    /**
     * Action to dispatch to the properties of a widget
     * @param \ride\web\cms\Cms $cms
     * @param \ride\library\security\SecurityManager $securityManager
     * @param string $locale
     * @param string $site
     * @param string $revision
     * @param string $node
     * @param string $region
     * @param string $widget
     * @return null
     */
    public function indexAction(Cms $cms, SecurityManager $securityManager, $locale, $site, $revision, $node, $region, $widget) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $widgetId = $widget;

        $widget = $site->getWidget($widgetId);
        if (!$this->getSecurityManager()->isPermissionGranted('cms.widget.' . $widget . '.' . self::NAME)) {
            throw new UnauthorizedException();
        }

        $widgetProperties = $node->getWidgetProperties($widgetId);

        $widget = $site->getWidget($widgetId);
        $widget = clone $cms->getWidget($widget);
        $widget->setRequest($this->request);
        $widget->setResponse($this->response);
        $widget->setProperties($widgetProperties);
        $widget->setLocale($locale);
        $widget->setRegion($region);
        if ($widget instanceof AbstractController) {
            $widget->setConfig($this->config);
            $widget->setDependencyInjector($this->dependencyInjector);
        }

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $security = $widgetProperties->getWidgetProperty(Node::PROPERTY_SECURITY, Node::AUTHENTICATION_STATUS_EVERYBODY);
        switch ($security) {
            case 'inherit':
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
            'published' => $widgetProperties->getWidgetProperty(Node::PROPERTY_PUBLISH, true),
            'publishStart' => $widgetProperties->getWidgetProperty(Node::PROPERTY_PUBLISH_START, null),
            'publishStop' => $widgetProperties->getWidgetProperty(Node::PROPERTY_PUBLISH_STOP, null),
            'security' => $security,
            'permissions' => $permissions,
        );

        $permissions = $securityManager->getSecurityModel()->getPermissions();

        foreach ($permissions as $index => $permission) {
            $permissions[$index] = $translator->translate('permission.' . $permission->getCode()) . ' (<small>' . $permission->getCode() . '</small>)';
        }

        $form = $this->createFormBuilder($data);
        $form->addRow('published', 'option', array(
            'label' => $translator->translate('label.publish'),
            'options' => $this->getPublishedOptions($translator),
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
            'options' => $this->getSecurityOptions($translator),
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
        $form = $form->build();

        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                if ($data['security'] == Node::AUTHENTICATION_STATUS_AUTHENTICATED && $permissions && $data['permissions']) {
                    $data['security'] = implode(',', $data['permissions']);
                }

                $widgetProperties->setWidgetProperty(Node::PROPERTY_PUBLISH, $data['published']);
                $widgetProperties->setWidgetProperty(Node::PROPERTY_PUBLISH_START, $data['publishStart']);
                $widgetProperties->setWidgetProperty(Node::PROPERTY_PUBLISH_STOP, $data['publishStop']);
                $widgetProperties->setWidgetProperty(Node::PROPERTY_SECURITY, $data['security']);

                $cms->saveNode($node, 'Updated visibility properties for widget ' . $widgetId . ' in ' . $node->getName());

                $this->addSuccess('success.widget.saved', array(
                    'widget' => $translator->translate('widget.' . $widget->getName()),
                ));

                $this->response->setRedirect($this->getUrl(
                    'cms.node.content.region',
                    array(
                        'locale' => $locale,
                        'site' => $site->getId(),
                        'revision' => $node->getRevision(),
                        'node' => $node->getId(),
                        'region' => $region,
                    )
                ));

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

        $this->setTemplateView('cms/backend/widget.visibility', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
            'region' => $region,
            'widget' => $widget,
            'widgetId' => $widgetId,
            'widgetName' => $translator->translate('widget.' . $widget->getName()),
            'form' => $form->getView(),
        ));
    }

    /**
     * Gets the publish options
     * @param \ride\library\i18n\translator\Translator $translator
     * @return array Array with the publish code as key and the translation as
     * value
     */
    protected function getPublishedOptions(Translator $translator) {
        $options = array();
        $options['1'] = $translator->translate('label.yes');
        $options['0'] = $translator->translate('label.no');

        return $options;
    }

    /**
     * Gets the security options
     * @param \ride\library\i18n\translator\Translator $translator
     * @return array Array with the publish code as key and the translation as
     * value
     */
    protected function getSecurityOptions(Translator $translator) {
        $options = array();
        $options[Node::AUTHENTICATION_STATUS_EVERYBODY] = $translator->translate('label.allow.everybody');
        $options[Node::AUTHENTICATION_STATUS_ANONYMOUS] = $translator->translate('label.allow.anonymous');
        $options[Node::AUTHENTICATION_STATUS_AUTHENTICATED] = $translator->translate('label.allow.authenticated');

        return $options;
    }

}
