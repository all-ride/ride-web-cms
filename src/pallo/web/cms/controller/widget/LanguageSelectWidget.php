<?php

namespace pallo\web\cms\controller\widget;

use pallo\library\i18n\I18n;

/**
 * Widget to change the current locale
 */
class LanguageSelectWidget extends AbstractWidget {

	/**
	 * Machine name of this widget
	 * @var string
	 */
    const NAME = 'language.select';

    /**
     * Path to the icon of this widget
     * @var string
     */
    const ICON = 'img/cms/widget/language-select.png';

    /**
     * Path to the template of the widget view
     * @var string
     */
    const TEMPLATE = 'cms/widget/language.select';

    /**
     * Sets a title view to the response
     * @return null
     */
    public function indexAction(I18n $i18n) {
        $locales = $i18n->getLocales();

        $node = $this->properties->getNode();

        $baseUrl = $node->getRootNode()->getBaseUrl($this->locale);
        if (!$baseUrl) {
            $baseScript = $this->request->getBaseScript();
        }

        $urls = array();

        foreach ($locales as $localeCode => $locale) {
            if (!$node->isAvailableInLocale($localeCode)) {
                continue;
            }

            $urls[$localeCode] = array(
                'url' => $baseScript . $node->getRoute($localeCode),
                'locale' => $locale,
            );
        }

        $this->setTemplateView(self::TEMPLATE, array(
        	'locales' => $urls,
        ));

    	if ($this->properties->isAutoCache()) {
    	    $this->properties->setCache(true);
    	}
    }

}