<?php
//Last updated: 2020-04-17 14:52:17
namespace MillenniumFalcon\Core\Orm\Generated;

use MillenniumFalcon\Core\Orm;

class PageTemplate extends Orm
{
    /**
     * #pz text COLLATE utf8mb4_unicode_ci DEFAULT NULL
     */
    private $title;
    
    /**
     * #pz text COLLATE utf8mb4_unicode_ci DEFAULT NULL
     */
    private $filename;
    
    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }
    
    /**
     * @param mixed title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }
    
    /**
     * @return mixed
     */
    public function getFilename()
    {
        return $this->filename;
    }
    
    /**
     * @param mixed filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }
    
}