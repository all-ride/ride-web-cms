<?php

namespace pallo\web\cms\controller\widget;

use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;

/**
 * Widget to show a menu of the node tree or a part thereof
 */
class MenuWidget extends AbstractWidget {

	/**
	 * Machine name of this widget
	 * @var string
	 */
    const NAME = 'menu';

    /**
     * Path to the icon of this widget
     * @var string
     */
    const ICON = 'img/cms/widget/menu.png';

    /**
     * Path to the template of the widget view
     * @var string
     */
    const TEMPLATE = 'cms/widget/menu/menu';

    /**
     * Default depth value of a menu widget
     * @var int
     */
    const DEFAULT_DEPTH = 1;

    /**
     * Default parent value of a menu widget
     * @var string
     */
    const DEFAULT_PARENT = 'default';

    /**
     * Default show title value of a menu widget
     * @var boolean
     */
    const DEFAULT_SHOW_TITLE = false;

    /**
     * Parent prefix for a absolute parent
     * @var string
     */
    const PARENT_ABSOLUTE = 'absolute';

    /**
     * Parent value for the current node
     * @var string
     */
    const PARENT_CURRENT = 'current';

    /**
     * Parent prefix for a relative parent
     * @var string
     */
    const PARENT_RELATIVE = 'relative';

    /**
     * Setting key for the parent value
     * @var string
     */
    const PROPERTY_PARENT = 'node';

    /**
     * Setting key for the depth value
     * @var string
     */
    const PROPERTY_DEPTH = 'depth';

    /**
     * Setting key for the title value
     * @var string
     */
    const PROPERTY_SHOW_TITLE = 'title';

    /**
     * Sets a title view to the response
     * @return null
     */
    public function indexAction(NodeModel $nodeModel) {
        $parent = $this->getParent();
        $depth = $this->getDepth();
        $showTitle = $this->getShowTitle();

        if (!$parent) {
            return;
        }

        $parentNode = $nodeModel->getNode($parent, null, true, $depth);
        $nodes = $parentNode->getChildren();

        $title = null;
        if ($showTitle) {
            $title = $parentNode->getName($this->locale);
        }

        $this->setTemplateView(self::TEMPLATE, array(
        	'title' => $title,
            'nodeTypes' => $nodeModel->getNodeTypeManager()->getNodeTypes(),
        	'items' => $nodes,
        ));

        if ($this->properties->isAutoCache()) {
            $this->properties->setCache(true);
        }
    }

    /**
     * Get a preview of the properties of this widget
     * @return string
     */
    public function getPropertiesPreview() {
        $translator = $this->getTranslator();

        $parent = $this->getParent();
        $depth = $this->getDepth();
        $showTitle = $this->getShowTitle();

        if ($parent) {
            $nodeModel = $this->dependencyInjector->get('pallo\\library\\cms\\node\\NodeModel');
            $parentNode = $nodeModel->getNode($parent);
            $parent = $parentNode->getName($this->locale);
        } else {
            $parent = '---';
        }

        $preview = '';
        $preview .= $translator->translate('label.menu.parent') . ': ' . $parent . '<br />';
        $preview .= $translator->translate('label.menu.depth') . ': ' . $depth . '<br />';
        $preview .= $translator->translate('label.title.show') . ': ' . $translator->translate($showTitle ? 'label.yes' : 'label.no');

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
        $levels = $nodeModel->getChildrenLevels($rootNode) - 1;

        $nodeList = $nodeModel->getListFromNodes(array($rootNode), $this->locale, false);
        $nodeList = array($rootNode->getId() => '/' . $rootNode->getName($this->locale)) + $nodeList;
        $nodeList[self::PARENT_CURRENT] = $translator->translate('label.menu.parent.current');
        for ($i = 1; $i <= $levels; $i++) {
            $nodeList[self::PARENT_ABSOLUTE . $i] = $translator->translate('label.menu.parent.absolute', array('level' => $i));
        }
        for ($i = 0; $i < $levels; $i++) {
            $level = $i + 1;
            $nodeList[self::PARENT_RELATIVE . $level] = $translator->translate('label.menu.parent.relative', array('level' => '-' . $level));
        }

        $depths = array();
        for ($i = 1, $j = $levels + 1; $i <= $j; $i++) {
            $depths[$i] = $i;
        }

        $data = array(
            self::PROPERTY_PARENT => $this->getParent(false),
            self::PROPERTY_DEPTH => $this->getDepth(),
            self::PROPERTY_SHOW_TITLE => $this->getShowTitle(),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow(self::PROPERTY_PARENT, 'select', array(
            'label' => $translator->translate('label.menu.parent'),
            'description' => $translator->translate('label.menu.parent.description'),
            'options' => $nodeList,
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->addRow(self::PROPERTY_DEPTH, 'select', array(
            'label' => $translator->translate('label.menu.depth'),
            'description' => $translator->translate('label.menu.depth.description'),
            'options' => $depths,
        ));
        $form->addRow(self::PROPERTY_SHOW_TITLE, 'option', array(
            'label' => $translator->translate('label.title.show'),
            'description' => $translator->translate('label.menu.title.show.description'),
        ));

        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                return false;
            }

            try {
                $form->validate();

                $data = $form->getData();

                $this->properties->setWidgetProperty(self::PROPERTY_PARENT, $data[self::PROPERTY_PARENT]);
                $this->properties->setWidgetProperty(self::PROPERTY_DEPTH, $data[self::PROPERTY_DEPTH]);
                $this->properties->setWidgetProperty(self::PROPERTY_SHOW_TITLE, $data[self::PROPERTY_SHOW_TITLE]);

                return true;
            } catch (ValidationException $e) {

            }
        }

        $this->setTemplateView('cms/widget/menu/properties', array(
        	'form' => $form->getView(),
        ));

        return false;
    }

    /**
     * Get the value for the parent node
     * @param boolean $fetchNodeId Set to false to skip the lookup of the node id
     * @return string
     */
    private function getParent($fetchNodeId = true) {
        $parent = $this->properties->getWidgetProperty(self::PROPERTY_PARENT, self::DEFAULT_PARENT);

        if (!$fetchNodeId) {
            return $parent;
        }

        if ($parent === self::DEFAULT_PARENT) {
            return $this->properties->getNode()->getRootNodeId();
        }

        if ($parent === self::PARENT_CURRENT) {
            return $this->properties->getNode()->getId();
        }

        $path = $this->properties->getNode()->getPath();
        $tokens = explode(Node::PATH_SEPARATOR, $path);

        if (strpos($parent, self::PARENT_ABSOLUTE) !== false) {
            $level = str_replace(self::PARENT_ABSOLUTE, '', $parent);
        } elseif (strpos($parent, self::PARENT_RELATIVE) !== false) {
            $level = str_replace(self::PARENT_RELATIVE, '', $parent);
            $tokens = array_reverse($tokens);
        } else {
            return $parent;
        }

        if (!isset($tokens[$level])) {
            // not existant
            return null;
        }

        return $tokens[$level];
    }

    /**
     * Get the depth value
     * @return integer
     */
    private function getDepth() {
        return $this->properties->getWidgetProperty(self::PROPERTY_DEPTH, self::DEFAULT_DEPTH);
    }

    /**
     * Get the show title value
     * @return boolean
     */
    private function getShowTitle() {
        return $this->properties->getWidgetProperty(self::PROPERTY_SHOW_TITLE, self::DEFAULT_SHOW_TITLE);
    }

}