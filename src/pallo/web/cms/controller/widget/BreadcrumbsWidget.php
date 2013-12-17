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
    const TEMPLATE = 'cms/widget/breadcrumbs';

    /**
     * Sets a title view to the response
     * @return null
     */
    public function indexAction() {
        $this->setTemplateView(self::TEMPLATE);

        if ($this->properties->isAutoCache()) {
            $this->properties->setCache(true);
        }
    }

}