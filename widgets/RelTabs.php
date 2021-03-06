<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals.cz/license/
 */

namespace dlds\rels\widgets;

/**
 * Widget handles many many input widgets
 */
class RelTabs extends \dlds\rels\components\Widget {

    /**
     * @var string widget calss
     */
    public $relViewClass = '\yii\jui\Tabs';

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
        return parent::run();
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
        if (empty($this->_relViews))
        {
            $message = \Yii::t('dlds/rels/widgets', 'RelTabs widget cannot be generated. No related models exist. Please create at least one relation model first.');
            
            echo \yii\helpers\Html::tag('div', $message, ['class' => 'alert alert-warning']);
        }
        else
        {
            $widget = $this->relViewClass;

            echo $widget::widget(array(
                'items' => $this->_relViews,
                'options' => array(
                    'collapsible' => true,
                ),
            ));
        }
    }

}
