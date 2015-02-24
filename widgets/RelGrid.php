<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals/license/
 */

namespace dlds\rels\widgets;

use yii\helpers\ArrayHelper;

/**
 * Widget handles many many input widgets
 */
class RelGrid extends \dlds\rels\components\Widget {

    /**
     * @var string widget calss
     */
    public $relViewClass = '\yii\grid\Column';

    /**
     * @var mixed holds RelGrid row actions 
     */
    public $actions;

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
            $this->_relViews[] = array(
                'label' => $this->parseHeader($relation, $id),
                'content' => $this->render($this->relView, array(
                    'form' => $this->form,
                    'model' => $relation,
                    'id' => $id,
                        ), true),
            );
        }
    }

    /**
     * Renders interpreter tabs
     * @return string tabs
     */
    protected function renderViews()
    {
        $columns = ArrayHelper::getColumn($this->_relViews, function($element) {
                    return sprintf('%s:raw', $element['label']);
                });

        if ($this->actions)
        {
            array_push($columns, $this->actions);
        }

        $provider = new \yii\data\ArrayDataProvider([
            'allModels' => [ArrayHelper::map($this->_relViews, 'label', 'content')],
        ]);

        echo \yii\grid\GridView::widget([
            'dataProvider' => $provider,
            'columns' => $columns,
            'layout' => '{items}',
            'pager' => false,
        ]);
    }

}
