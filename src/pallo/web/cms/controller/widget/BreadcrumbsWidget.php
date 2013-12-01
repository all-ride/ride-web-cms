<?php

namespace pallo\web\cms\controller\widget;

use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;

/**
 * Widget to show the breadcrums of the current page
 */
class BreadcrumbsWidget extends AbstractWidget {

	/**
	 * Machine name of this widget
	 * @var string
	 */
    const NAME = 'breadcrumbs';

    /**
     * Path to the icon of this widget
     * @var string
     */
    const ICON = 'img/cms/widget/breadcrumbs.png';

    /**
     * Path to the template of the widget view
     * @var string
     */
    const TEMPLATE = 'cms/widget/breadcrumbs/breadcrumbs';

    /**
     * Setting key for the filter value
     * @var string
     */
    const PROPERTY_LABEL = 'label';

    /**
     * Setting key for the filter value
     * @var string
     */
    const PROPERTY_FILTER = 'filter';

    /**
     * Sets a title view to the response
     * @return null
     */
    public function indexAction(NodeModel $nodeModel) {
        $label = $this->getLabel();

        $filter = $this->getFilter();
        foreach ($filter as $nodeId => $null) {
            $node = $nodeModel->getNode($nodeId);

            $filter[$nodeId] = $node->getName($this->locale);
        }

        $this->setTemplateView(self::TEMPLATE, array(
        	'label' => $label,
        	'filter' => $filter,
        ));

        if ($this->properties->isAutoCache()) {
            $this->properties->setCache(true);
        }
    }

    /**
     * Gets a preview for the properties of this widget
     * @return string
     */
    public function getPropertiesPreview() {
        $translator = $this->getTranslator();
        $preview = '';

        $label = $this->getLabel();
        if ($label) {
            $preview .= $translator->translate('label.breadcrumbs.label') . ': ' . $label . '<br />';
        }

        $filter = $this->getFilter();
        if ($filter) {
            $nodeModel = $this->dependencyInjector->get('pallo\\library\\cms\\node\\NodeModel');
            $preview .= $translator->translate('label.breadcrumbs.filter') . ': ';

            $filterPreview = '';
            foreach ($filter as $node) {
                try {
                    $node = $nodeModel->getNode($node);
                    $filterPreview .= ($filterPreview ? ', ' : '') . $node->getName($this->locale);
                } catch (NodeNotFoundException $e) {

                }
            }

            $preview .= $filterPreview;
        }

        return $preview;
    }

    /**
     * Gets the callback for the properties action
     * @return null|callback Null if the widget does not implement a properties
     * action, a callback for the action otherwise
     */
    public function getPropertiesCallback() {
        return array($this, 'propertiesAction');
    }

    /**
     * Action to handle and show the properties of this widget
     * @return null
     */
    public function propertiesAction(NodeModel $nodeModel) {
        $translator = $this->getTranslator();

        $node = $this->properties->getNode();
        $rootNodeId = $node->getRootNodeId();
        $rootNode = $nodeModel->getNode($rootNodeId, null, true);

        $nodeList = $nodeModel->getListFromNodes(array($rootNode), $this->locale, false);
        $nodeList = array($rootNode->getId() => '/' . $rootNode->getName($this->locale)) + $nodeList;

        $data = array(
            self::PROPERTY_LABEL => $this->getLabel(),
            self::PROPERTY_FILTER => $this->getFilter(),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow(self::PROPERTY_LABEL, 'string', array(
            'label' => $translator->translate('label.breadcrumbs.label'),
            'description' => $translator->translate('label.breadcrumbs.label.description'),
            'filters' => array(
                'trim' => array(),
            )
        ));
        $form->addRow(self::PROPERTY_FILTER, 'select', array(
            'label' => $translator->translate('label.breadcrumbs.filter'),
            'description' => $translator->translate('label.breadcrumbs.filter.description'),
            'options' => $nodeList,
            'multiselect' => true,
        ));
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                $this->response->setRedirect($this->getUrl('cms.node.layout.region', array(
                    'locale' => $this->locale,
                    'site' => $rootNodeId,
                    'node' => $node->getId(),
                    'region' => $this->region,
                )));

                return;
            }

            try {
                $form->validate();

                $data = $form->getData();

                $this->setLabel($data[self::PROPERTY_LABEL]);
                $this->setFilter($data[self::PROPERTY_FILTER]);

                return true;
            } catch (ValidationException $e) {

            }
        }

        $this->setTemplateView('cms/widget/breadcrumbs/properties', array(
        	'form' => $form->getView(),
        ));

        return false;
    }

    /**
     * Get the label value from the settings
     * @return string
     */
    private function getLabel() {
        return $this->properties->getWidgetProperty(self::PROPERTY_LABEL . '.' . $this->locale);
    }

    /**
     * Set the label value to the settings
     * @param string $label
     * @return null
     */
    private function setLabel($label) {
        $this->properties->setWidgetProperty(self::PROPERTY_LABEL . '.' . $this->locale, $label);
    }

    /**
     * Get the filter value from the settings
     * @return array Array with node ids as key and the node as value
     */
    private function getFilter() {
        $filter = $this->properties->getWidgetProperty(self::PROPERTY_FILTER);
        if (!$filter) {
        	return array();
        }

        $filters = explode(',', $filter);
        $filter = array();
        foreach ($filters as $id) {
            $filter[$id] = $id;
        }

        return $filter;
    }

    /**
     * Set the filter value to the settings
     * @param array $filter Array with node ids as key
     * @return null
     */
    private function setFilter(array $filter) {
        $filter = implode(',', array_keys($filter));

        $this->properties->setWidgetProperty(self::PROPERTY_FILTER, $filter);
    }

}