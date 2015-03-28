<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals.cz/license/
 */

namespace dlds\rels\components;

use yii\helpers\ArrayHelper;

/**
 * Widget handles many many input widgets
 */
abstract class Widget extends \yii\widgets\InputWidget {

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
    public $relView;

    /**
     * @var relView class
     */
    public $relViewClass;

    /**
     * @var mixed restriction condition
     */
    public $restriction = [];
    
    /**
     * @var array relations to be interpreted
     */
    protected $_relations = array();

    /**
     * @var array interpreter views to be rendered
     */
    protected $_relViews = array();

    /**
     * Initializes the menu widget.
     */
    public function init()
    {
        $this->initRelations();

        $this->initViews();

        parent::init();
    }

    /**
     * Runs widget
     */
    public function run()
    {
        $this->renderViews();
    }

    /**
     * Inits relations
     */
    protected function initRelations()
    {
        $this->_relations = $this->model->getAllInterpretations($this->restriction);
    }

    /**
     * Inits views
     */
    abstract protected function initViews();

    /**
     * Renders interpreter tabs
     * @return string tabs
     */
    abstract protected function renderViews();

    /**
     * Parses and retrieves header based on given template
     * @param CActiveRecord $relation given model
     * @param string $default default value to be retrieves
     * @return string header
     */
    protected function parseHeader($relation, $default = false)
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
