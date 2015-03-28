<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals.cz/license/
 */

namespace dlds\rels\widgets;

use yii\helpers\ArrayHelper;

/**
 * Widget handles many many input widgets
 */
class RelView extends \dlds\rels\components\Widget {

    /**
     * Initializes the menu widget.
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Runs widget
     */
    public function run()
    {
        parent::run();
    }

    /**
     * Inits tabs
     */
    protected function initViews()
    {
        foreach ($this->_relations as $id => $relation)
        {
            $this->_relViews[] = $this->render($this->relView, [
                'form' => $this->form,
                'model' => $relation,
                'id' => $id,
                    ], true);
        }
    }

    /**
     * Renders interpreter tabs
     * @return string tabs
     */
    protected function renderViews()
    {
        foreach ($this->_relViews as $view)
        {
            echo $view;
        }
    }

}
