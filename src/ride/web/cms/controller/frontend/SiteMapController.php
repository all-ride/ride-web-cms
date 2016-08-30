<?php

namespace ride\web\cms\controller\frontend;

use ride\library\StringHelper;

/**
 * Controller of the frontend for the redirect nodes
 */
class SiteMapController extends AbstractController {

    /**
     * Hosts the sitemap.xml for the requested host
     * @return null
     */
    public function indexAction() {
        $host = $this->request->getHeader('host');
        $directory = $this->dependencyInjector->get('ride\\library\\system\\file\\File', 'cms.sitemap');

        $file = $directory->getChild('sitemap-' . StringHelper::safeString($host) . '.xml');
        if (!$file->exists()) {
            return $this->response->setNotFound();
        }

        $this->setFileView($file, 'sitemap.xml', false, 'application/xml');
    }

}
