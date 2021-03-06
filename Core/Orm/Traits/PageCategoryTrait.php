<?php
//Last updated: 2019-04-18 11:46:48
namespace MillenniumFalcon\Core\Orm\Traits;

trait PageCategoryTrait
{
    /**
     * @param $pdo
     */
    static public function initData($pdo, $container)
    {
        $orm = new static($pdo);
        $orm->setTitle('Main nav');
        $orm->setCode('main');
        $orm->save();

        $orm = new static($pdo);
        $orm->setTitle('Footer nav');
        $orm->setCode('footer');
        $orm->save();
    }
}