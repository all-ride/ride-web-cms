<?php

namespace ride\web\cms\controller\frontend;

use ride\library\StringHelper;

/**
 * Controller of the SEO frontend
 */
class SeoController extends AbstractController {

    /**
     * Hosts the robots.txt for the requested host
     * @return null
     */
    public function robotsAction() {
        $host = $this->request->getHeader('host');
        $directory = $this->dependencyInjector->get('ride\\library\\system\\file\\File', 'cms.robot');

        $file = $directory->getChild('robots-' . StringHelper::safeString($host) . '.txt');
        if (!$file->exists()) {
            return $this->response->setNotFound();
        }

        $this->setFileView($file, 'robots.txt', false, 'text/plain');
    }

    /**
     * Hosts the sitemap.xml for the requested host
     * @return null
     */
    public function sitemapAction() {
        $host = $this->request->getHeader('host');
        $directory = $this->dependencyInjector->get('ride\\library\\system\\file\\File', 'cms.sitemap');

        $file = $directory->getChild('sitemap-' . StringHelper::safeString($host) . '.xml');
        if (!$file->exists()) {
            return $this->response->setNotFound();
        }

        $this->setFileView($file, 'sitemap.xml', false, 'application/xml');
    }

}
