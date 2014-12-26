<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals/license/
 */

namespace dlds\rels\widgets;

/**
 * Widget handles many many input widgets
 */
class RelTabs extends \yii\base\Widget {

    /**
     * @var CActiveRecord current model which use GMultilangBehavior
     */
    public $model;

    /**
     * @var string view file path
     */
    public $view;

    /**
     * @var mixed attribute name to be used as header title or FALSE to use relation index
     */
    public $header = false;

    /**
     * @var boolean indicates if sluggable assets should be assigned
     */
    public $sluggable = true;

    /**
     * @var array relations to be interpreted
     */
    private $_relations = array();

    /**
     * @var array interpreter tabs to be rendered
     */
    private $_tabs = array();

    /**
     * Initializes the menu widget.
     */
    public function init()
    {
        if (!($this->owner instanceof CActiveForm))
        {
            throw new Exception(Yii::t('app', '"{this}.owner" must be instance of "{instance}."', array(
                '{this}' => get_class($this),
                '{instance}' => 'CActiveForm',
            )));
        }

        $this->_registerJsFiles();

        $this->_initRelations();

        $this->_initTabs();

        parent::init();
    }

    /**
     * Runs widget
     */
    public function run()
    {
        $html = $this->owner->errorSummary($this->_relations);

        $html .= $this->renderTabs();

        echo $html;
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
    private function _initTabs()
    {
        foreach ($this->_relations as $id => $relation)
        {
            $this->_tabs[$this->_parseHeader($relation, $id)] = array(
                'content' => $this->controller->renderPartial($this->view, array(
                    'form' => $this->owner,
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
    private function renderTabs()
    {
        // fix path to to MJuiTabs
        return $this->widget(Yii::app()->metronic->getExtPath('widgets.jui.MJuiTabs'), array(
                    'tabs' => $this->_tabs,
                    'options' => array(
                        'collapsible' => true,
                    ),
                        ), true);
    }

    /**
     * Parses and retrieves header based on given template
     * @param CActiveRecord $header given model
     * @param string $default default value to be retrieves
     * @return string header
     */
    private function _parseHeader($header, $default = false)
    {
        $attributes = explode('.', $this->header);

        foreach ($attributes as $attribute)
        {
            if (isset($header->{$attribute}))
            {
                $header = $header->{$attribute};
            }
        }

        if (!is_string($header))
        {
            if ($default !== false)
            {
                return $default;
            }

            throw new Exception('Header cannot be parsed.');
        }

        return $header;
    }

    /**
     * Registeres required JS files
     */
    private function _registerJsFiles()
    {
        if ($this->sluggable)
        {
            AssetsHandler::instance(false)->registerJsFiles(array(
                Yii::app()->getAssetManager()->publish(dirname(__FILE__) . '/assets/js/jquery.slugit.js'),
                    ), CClientScript::POS_END);

            AssetsHandler::instance()->registerJs('mi.components.MActiveForm.' . $this->id, "
                var sluggables = $('.sluggable');

                $.each(sluggables, function(i, e) {
                    
                    var targetName = $(e).data('target');
                    
                    if(targetName.indexOf('#') == -1) {
                         targetName = '#' + targetName;
                    }
                    var target = $(targetName);

                    $(e).slugIt({
                        events:    'keyup',
                        output:    target,
                        separator: '-',
                    });

                });
            ", CClientScript::POS_READY);
        }
    }

}
