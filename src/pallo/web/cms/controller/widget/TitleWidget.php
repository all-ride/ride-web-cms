<?php

namespace pallo\web\cms\controller\widget;

/**
 * Widget to show the name of the current node as title
 */
class TitleWidget extends AbstractWidget {

	/**
	 * Machine name of this widget
	 * @var string
	 */
    const NAME = 'title';

    /**
     * Path to the icon of this widget
     * @var string
     */
    const ICON = 'img/cms/widget/title.png';

    /**
     * Path to the template of the widget view
     * @var string
     */
    const TEMPLATE = 'cms/widget/title';

    /**
     * Sets a title view to the response
     * @return null
     */
    public function indexAction() {
    	$node = $this->properties->getNode();
    	$title = $node->getName($this->locale);

        $this->setTemplateView(self::TEMPLATE, array(
        	'title' => $title,
        ));

    	if ($this->properties->isAutoCache()) {
    	    $this->properties->setCache(true);
    	}
    }

}