<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals/license/
 */

namespace dlds\rels\components;

use dlds\rels\components\Interpreter;

/**
 * Behavior class which handles many to many relaitons
 *
 * Settings example:
 * -----------------
 *  'BehaviorName' => [
 *      'class' => \dlds\rels\components\Behavior::classname(),
 *      'config' => ['viaModelClassName', 'primaryRelationName', 'secondaryRelationName'],
 *  ],
 *
 * @author Jiri Svoboda <jiri.svoboda@dlds.cz>
 */
class Behavior extends \yii\base\Behavior {

    /**
     * @var \dlds\rels\components\Interpreter current interpreter
     */
    private $_interpreter = null;

    /**
     * @var array config
     */
    public $config;

    /**
     * @var array attr to be auto-interpreted
     */
    public $attrs;

    /**
     * @var boolean indicates interpretation validity
     */
    private $valid;

    /**
     * Returns the value of an object property.
     * @param stirng $name the property name
     */
    public function __get($name)
    {
        if (!in_array($name, $this->attrs))
        {
            return parent::__get($name);
        }
        
        return $this->getInterpretation($name);
    }

    /**
     * Indicates whether a property can be read.
     * @param string $name the property name
     * @param boolean $checkVars whether to treat member variables as properties
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if (!in_array($name, $this->attrs))
        {
            return parent::canGetProperty($name, $checkVars);
        }

        return true;
    }

    /**
     * Validates interpreter together with owner
     */
    public function events()
    {
        return [
            \yii\db\ActiveRecord::EVENT_BEFORE_VALIDATE => 'handleValidate',
            \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => 'handleBeforeSave',
            \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => 'handleBeforeSave',
            \yii\db\ActiveRecord::EVENT_AFTER_INSERT => 'handleAfterSave',
            \yii\db\ActiveRecord::EVENT_AFTER_UPDATE => 'handleAfterSave',
        ];
    }

    /**
     * Handles validation interpretetaions given in post
     * @param \yii\base\Event $event
     */
    public function handleValidate()
    {
        $this->valid = $this->setAvailableInterpretations(\Yii::$app->request->post())->validate();
    }

    /**
     * Handles saving of interpretation
     * @param type $event
     */
    public function handleBeforeSave($event)
    {
        $event->isValid = $this->valid;
    }

    /**
     * Handles saving of interpretation
     * @param type $event
     */
    public function handleAfterSave()
    {
        $this->interpreter()->save();
    }

    /**
     * Retrieves attribute interpretation for current hasMany relation
     * @param string $attribute attribute name
     * @return mixed Attribute value if interpretation exists otherwise NULL
     */
    public function getInterpretation($attribute, $index = null)
    {
        return $this->interpreter()->interpretAttribute($attribute, $index);
    }

    /**
     * Sets model interpretetion attributes (related model attributes)
     * @param type $data
     */
    public function setInterpretation($data, $index)
    {
        return $this->interpreter()->setAvailableInterpretations($data, $index, $this->owner);
    }

    /**
     * Retrieves available owner's hasMany relations.
     * @return array hasMany relation models array
     */
    public function getAvailableInterpretations()
    {
        return $this->interpreter()->getAvailableInterpretations();
    }

    /**
     * Sets model interpretetions
     * @param type $data
     */
    public function setAvailableInterpretations($data)
    {
        return $this->interpreter()->setAvailableInterpretations($data);
    }

    /**
     * Retrieves all possible model interpretations
     * @return type
     */
    public function getAllInterpretations()
    {
        return $this->interpreter()->getAllInterpretations();
    }

    /**
     * Retrieves instance to multilang interperter
     */
    private function interpreter()
    {
        if (null === $this->_interpreter)
        {
            $this->_interpreter = new Interpreter($this->owner, $this->config);
        }

        return $this->_interpreter;
    }

}

?>
