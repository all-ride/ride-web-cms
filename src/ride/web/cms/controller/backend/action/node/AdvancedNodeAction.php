<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\cms\node\NodeProperty;
use ride\library\i18n\I18n;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\ValidationError;

/**
 * Controller of the advanced node action
 */
class AdvancedNodeAction extends AbstractNodeAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'advanced';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.advanced';

    /**
     * Perform the advanced node action
     */
    public function indexAction(I18n $i18n, $locale, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $this->setLastAction(self::NAME);

        $ini = $this->getIniFromNodeProperties($node->getProperties());
        $translator = $this->getTranslator();

        $form = $this->createFormBuilder(array('properties' => $ini));
        $form->addRow('properties', 'text', array(
            'label' => $translator->translate('label.node.properties'),
            'description' => $translator->translate('label.node.properties.description'),
            'attributes' => array(
        	   'rows' => 10,
            ),
            'filters' => array(
                'trim' => array(),
            )
        ));
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $nodeProperties = $this->getNodePropertiesFromIni($data['properties']);

                $node->setProperties($nodeProperties);

                $nodeModel->setNode($node);

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale)
                ));

                $this->response->setRedirect($this->request->getUrl());

                return;
            } catch (ValidationException $saveException) {
            	$form->setValidationException($validationException);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/node.advanced', array(
            'site' => $site,
            'node' => $node,
            'nodeProperties' => $this->getHtmlFromNode($node),
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

    /**
     * Gets an INI from the provided NodeProperty instances
     * @param array $properties
     * @return string
     */
    protected function getIniFromNodeProperties(array $properties) {
        ksort($properties);

        $ini = '';
        foreach ($properties as $key => $nodeProperty) {
            $ini .= $nodeProperty->getIniString() . "\n";
        }

        return $ini;
    }

    /**
     * Parses the provided INI in an array of NodeProperty instances
     * @param string $ini
     * @return array
     * @throws ride\library\validation\exception\ValidationException when the
     * ini is not valid
     */
    protected function getNodePropertiesFromIni($ini) {
        $properties = @parse_ini_string($ini);

        if ($properties === false) {
            $error = error_get_last();
            $error = new ValidationError('error', '%error%', array('error' => $error['message']));

            $exception = new ValidationException();
            $exception->addErrors('properties', array($error));

            throw $exception;
        }

        $inheritPrefixLength = strlen(NodeProperty::INHERIT_PREFIX);
        foreach ($properties as $key => $value) {
            $inherit = false;

            unset($properties[$key]);

            if (strpos($key, NodeProperty::INHERIT_PREFIX) === 0) {
                $key = substr($key, $inheritPrefixLength);
                $inherit = true;
            }

            $properties[$key] = new NodeProperty($key, $value, $inherit);
        }

        return $properties;
    }

    /**
     * Get a HTML representation of a Node instance
     * @param ride\library\cms\node\Node $node
     * @return string HTML representation of the Node
     */
    protected function getHtmlFromNode(Node $node) {
        $properties = array();

        $parentNode = $node->getParentNode();
        while ($parentNode) {
            $parentProperties = $parentNode->getProperties();
            foreach ($parentProperties as $key => $property) {
                if ($property->getInherit() && !isset($properties[$key])) {
                    $properties[$key] = $property;
                }
            }

            $parentNode = $parentNode->getParentNode();
        }

        $nodeProperties = $node->getProperties();
        $properties = $nodeProperties + $properties;

        ksort($properties);

        $html = '';
        foreach ($properties as $key => $property) {
            $value = $property->getIniString(true);
            if ($property->getInherit()) {
                $value = substr($value, 1);
            }

            if (isset($nodeProperties[$key])) {
                $html .= '<strong>' . $value . '</strong>';
            } else {
                $html .= $value;
            }

            $html .= "<br />\n";
        }

        return $html;
    }

}