<?php

namespace ride\web\cms\form\row;

use ride\library\form\exception\FormException;
use ride\library\form\row\OptionRow;
use ride\library\validation\factory\ValidationFactory;

use ride\web\cms\Cms;

/**
 * Cms node row
 */
class NodeRow extends OptionRow {

    /**
     * Name of the site option
     * @var string
     */
    const OPTION_SITE = 'site';

    /**
     * Name of the locale option
     * @var string
     */
    const OPTION_LOCALE = 'locale';

    /**
     * Name of the revision option
     * @var string
     */
    const OPTION_REVISION = 'revision';

    /**
     * Instance of the CMS
     * @var \ride\web\cms\Cms
     */
    protected $cms;

    /**
     * Sets the CMS instance
     * @param \ride\web\cms\Cms $cms
     * @return null
     */
    public function setCms(Cms $cms) {
        $this->cms = $cms;
    }

    /**
     * Performs necessairy build actions for this row
     * @param string $namePrefix Prefix for the row name
     * @param string $idPrefix Prefix for the field id
     * @param \ride\library\validation\factory\ValidationFactory $validationFactory
     * @return null
     */
    public function buildRow($namePrefix, $idPrefix, ValidationFactory $validationFactory) {
        $this->setNodeOptions();

        parent::buildRow($namePrefix, $idPrefix, $validationFactory);
    }

    /**
     * Sets the necessairy options for this row to work
     * @return null
     */
    protected function setNodeOptions() {
        $includeRootNode = true;
        $includeEmpty = true;
        $onlyFrontendNodes = true;

        $locale = $this->getOption(self::OPTION_LOCALE);
        $revision = $this->getOption(self::OPTION_REVISION, $this->cms->getDefaultRevision());
        $site = $this->getOption(self::OPTION_SITE);
        if ($site) {
            if (!$site instanceof SiteNode) {
                $site = $this->cms->getNode($site, $revision, $site, 'site', false);
            }
        } else {
            $sites = $this->cms->getSites();
            if (count($sites) != 1) {
                throw new FormException('Could not set CMS options: no site option set');
            }

            $site = array_pop($sites);
        }

        $options = $this->cms->getNodeList($site, $locale, $includeRootNode, $includeEmpty, $onlyFrontendNodes);

        $this->setOption(self::OPTION_OPTIONS, $options);
        $this->setOption(self::OPTION_WIDGET, 'select');
    }

}
