<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals.cz/license/
 */

namespace dlds\rels;

/**
 * This is the main module class for the Giixer module.
 *
 * @author Jiri Svoboda <jiri.svoboda@dlds.cz>
 */
class Module extends \yii\gii\Module {

    /**
     * Returns info about module
     * @return array infolist
     */
    protected function info()
    {
        return [
            'author' => 'Jiri Svoboda',
        ];
    }

}
