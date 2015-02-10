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
class RelGrid extends \yii\widgets\InputWidget {

    /**
     * @var mixed attribute name to be used as header title or FALSE to use relation index
     */
    public $header = false;

    /**
     * @var string current form
     */
    public $form;

    /**
     * @var string tab view file path
     */
    public $colView;

    /**
     * @var string widget calss
     */
    public $colClass = '\yii\grid\Column';

    /**
     * @var array relations to be interpreted
     */
    private $_relations = array();

    /**
     * @var array interpreter tabs to be rendered
     */
    private $_cols = array();

    /**
     * Initializes the menu widget.
     */
    public function init()
    {
        $this->_initRelations();

        $this->_initGrid();

        parent::init();
    }

    /**
     * Runs widget
     */
    public function run()
    {
        //$html = $this->owner->errorSummary($this->_relations);

        $this->renderGrid();
    }

    /**
     * Inits relations
     */
    private function _initRelations()
    {
        $this->_relations = $this->model->getAllInterpretations();
    }

    /**
     * Inits tabs
     */
    private function _initGrid()
    {
        foreach ($this->_relations as $id => $relation)
        {
            $this->_cols[] = array(
                'label' => $this->_parseHeader($relation, $id),
                'content' => $this->render($this->colView, array(
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
    private function renderGrid()
    {
        $columns = ArrayHelper::getColumn($this->_cols, 'label');

        $provider = new \yii\data\ArrayDataProvider([
            'allModels' => [ArrayHelper::map($this->_cols, 'label', 'content')],
        ]);

        echo \yii\grid\GridView::widget([
            'dataProvider' => $provider,
            'columns' => [
                [
                    'label' => 'Datum',
                    'format' => 'raw',
                ]
            ],
            'layout' => '{items}',
            'pager' => false,
        ]);
    }

    /**
     * Parses and retrieves header based on given template
     * @param CActiveRecord $relation given model
     * @param string $default default value to be retrieves
     * @return string header
     */
    private function _parseHeader($relation, $default = false)
    {
        if (false !== $this->header)
        {
            $attributes = explode('.', $this->header);

            foreach ($attributes as $attribute)
            {
                if (isset($relation->{$attribute}))
                {
                    $relation = $relation->{$attribute};
                }
            }
        }

        if (!is_string($relation))
        {
            if ($default !== false)
            {
                return $default;
            }

            return $relation->primaryKey;
        }

        return $relation;
    }

}
