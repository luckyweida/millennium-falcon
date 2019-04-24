<?php

namespace MillenniumFalcon\Core\Orm\Traits;

use MillenniumFalcon\Core\Orm\PageTemplate;

trait PageTrait
{
    /**
     * @return string
     */
    public function getIcon()
    {
        return '';
    }

    /**
     * @return mixed|null|string
     */
    public function getTemplate()
    {
        return $this->objPageTempalte()->getFilename(); // TODO: Change the autogenerated stub
    }

    /**
     * @return PageTemplate|null
     */
    public function objPageTempalte()
    {
        /** @var PageTemplate $pageTemplate */
        $pageTemplate = PageTemplate::getById($this->getPdo(), $this->getTemplateFile());
        return $pageTemplate;
    }

    /**
     * @return mixed
     */
    public function objContent()
    {
        return json_decode($this->getContent());
    }

    /**
     * @return string
     */
    static public function getCmsOrmsTwig()
    {
        return 'cms/orms-custom-pages.html.twig';
    }
}