<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals/license/
 */

namespace dlds\rels;

/**
 * Behavior class which provides model to be translated to different languages
 *
 * Settings example:
 * -----------------
 *  'Behavior' => array(
 *      'class' => 'common.extensions.g-multilang-behavior.GMultilangBehavior',
 *      'relations' => array('languages', 'hasLanguages', 'currentLanguage'),
 *      'partial' => true,
 *  ),
 *
 * @author Svobik7
 */
class Behavior extends \yii\base\Behavior {

    private $_interpreter = null;

    /**
     * @var array sets relations params
     */
    public $relations;

    /**
     * @var array defined scopes to use when finding possible relations
     */
    public $scopes;

    /**
     * @var array indicated if partial save would be allowed
     */
    public $partial = false;

    /**
     * @var array indicated which attributes would be check to determine relation activations
     */
    public $activationTriggers = array();

    /**
     * Validates interpreter together with owner
     */
    public function beforeValidate($event)
    {
        parent::beforeValidate($event);

        return $this->interpreter()->isValid();
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
        $this->interpreter()->setAvailableInterpretations($data, $index, $this->owner);
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
     * Retrieves modified/added relations which should be saved
     * @return array current set relations
     */
    public function getModifiedInterpretations()
    {
        return $this->interpreter()->getModifiedInterpretations();
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
     * Sets model interpretetions
     * @param type $data
     */
    public function setAllInterpretations($data)
    {
        $this->interpreter()->setAllInterpretations($data);
    }

    /**
     * Retrieves active options
     * @return array active options
     */
    public function getActiveOptions()
    {
        return array(
            0 => Yii::t('app', 'No'),
            1 => Yii::t('app', 'Yes'),
        );
    }

    /**
     * Sets model attributes together with related models attributes
     * @param array $data model and relations data to be set and save
     */
    public function setAllAttributes($data)
    {
        if (isset($data[get_class($this->owner)]))
        {
            $this->owner->setAttributes($data[get_class($this->owner)]);
        }

        $this->interpreter()->setAvailableInterpretations($data, $this->owner);
    }

    /**
     * Retrieves instance to multilang interperter
     */
    private function interpreter()
    {
        if (null === $this->_interpreter)
        {
            $this->_interpreter = new Interpreter($this->owner, $this->relations, $this->scopes, $this->partial, $this->activationTriggers);
        }

        return $this->_interpreter;
    }

}

?>
