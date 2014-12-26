<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals/license/
 */

namespace dlds\rels;

/**
 * Interpreter handles many many interpretations
 */
class Interpreter {

    const RELATION_TYPE = 0;
    const RELATION_CLASS = 1;
    const RELATION_KEY = 2;
    const RELATION_INDEX = 'index';

    /**
     * @var CModel owner model
     */
    public $owner;

    /**
     * @var array given relations
     */
    public $relations;

    /**
     * @var array given scopes
     */
    public $scopes;

    /**
     * @var boolean indicates if interpretation is partial
     */
    public $partial = false;

    /**
     * @var array holds attributes which determines relation activation
     */
    public $activationTriggers = array();

    /**
     * @var string default attr name
     */
    private $_attrDefault = 'default';

    /**
     * @var string active attr name
     */
    private $_attrActive = 'active';

    /**
     * @var string manyMany relation name
     */
    private $_manyManyRelationKey;

    /**
     * @var string hasMany relation name
     */
    private $_hasManyRelationKey;

    /**
     * @var string current hasMany relation name
     */
    private $_currentHasManyRelationKey = null;

    /**
     * @var array names of relations to be saved
     */
    private $_relationsToSave = array();

    /**
     * @var array model scopes to be applied
     */
    private $_scopesToApply = array();

    /**
     * Construcor
     * @param CModel $owner owner model
     * @param array $config given configs
     * @param array $scopes given scopes
     */
    public function __construct($owner, $relations, $scopes, $partial = false, $activationTriggers = array())
    {
        $this->owner = $owner;
        $this->relations = $relations;
        $this->scopes = $scopes;
        $this->partial = (boolean) $partial;
        $this->activationTriggers = $activationTriggers;

        $this->_loadInterpreterConfig();
    }

    /**
     * Indicates if current interpretations are valid
     * @return boolean true if is valid otherwise false
     */
    public function isValid()
    {
        if ($this->owner->isNewRecord && !Yii::app()->request->isAjaxRequest)
        {
            $availables = $this->getAvailableInterpretations();

            if (empty($availables))
            {
                if ($this->partial)
                {
                    $this->owner->addError($this->_hasManyRelationKey, Yii::t('error', 'At least one language must be provided!'));
                }
                else
                {
                    $this->owner->addError($this->_hasManyRelationKey, Yii::t('error', 'All languages must be provided!'));
                }

                return false;
            }
            elseif ($this->partial)
            {
                $first = reset($availables);
                $first->{$this->_attrDefault} = 1;
            }
        }

        return true;
    }

    /**
     * Retrieves attribute interpretation for current hasMany relation
     * @param string $attribute attribute name
     * @return mixed Attribute value if interpretation exists otherwise NULL
     */
    public function interpretAttribute($attribute, $index = null)
    {
        if ($index)
        {
            $availables = $this->getAvailableInterpretations();

            if (isset($availables[$index]))
            {
                return $availables[$index]->{$attribute};
            }

            return null;
        }
        elseif ($this->getCurrentInterpretation())
        {
            return $this->getCurrentInterpretation()->{$attribute};
        }

        return null;
    }

    /**
     * Default method for getting owner's current interpretation.
     * This can be overloaded and changed in owner's model class.
     * @return mixed current interpretation
     */
    public function getCurrentInterpretation()
    {
        return $this->owner->{$this->_currentHasManyRelationKey};
    }

    /**
     * Default method for getting owner's available hasMany relation models.
     * This can be overloaded and changed in owner's model class.
     * @returns mixed available hasMany relation models
     */
    public function getAvailableInterpretations()
    {
        return $this->owner->{$this->_hasManyRelationKey};
    }

    /**
     * Retrieves interpreted relations which should be saved
     * @return array current set relations
     */
    public function getModifiedInterpretations()
    {
        return $this->_relationsToSave;
    }

    /**
     * Retrieves all possible hasMany relation models for owner.
     * @return array hasMany relation models array
     */
    public function getAllInterpretations()
    {
        $config = $this->_loadRelationConfig($this->_hasManyRelationKey);

        $possibles = $this->getPossibleInterpretations();

        $availables = $this->getAvailableInterpretations();

        $missings = array_diff_key($possibles, $availables);

        foreach ($missings as $interpretation)
        {
            $this->_assignInterpretation($interpretation, $config);
        }

        $availables = $this->getAvailableInterpretations();

        ksort($availables);

        return $availables;
    }

    public function setAllInterpretations($data)
    {
        $relationConfig = $this->_loadRelationConfig($this->_hasManyRelationKey);

        $all = $this->getAllInterpretations();

        foreach ($all as $interpretation)
        {
            $interpretation->setAttributes($data);
        }

        $this->_relationsToSave[] = $this->_hasManyRelationKey;
    }

    /**
     * Default method for getting all owner's possible hasMany relation models.
     * This can be overloaded and changed in owner's model class.
     */
    protected function getPossibleInterpretations()
    {
        $config = $this->_loadRelationConfig($this->_manyManyRelationKey);

        $model = $this->_applyScopes($config[self::RELATION_CLASS]::model());

        $data = CHtml::listData($model->findAll(), function() {
                    return $this->_loadRelationConfigPkName($this->_loadRelationConfig($this->_hasManyRelationKey));
                }, 'id', 'id');

        $config = $this->_loadRelationConfig($this->_hasManyRelationKey);

        $possibles = $config[self::RELATION_CLASS]::model()->populateRecords($data, true, $config[self::RELATION_INDEX]);

        foreach ($possibles as $possible)
        {
            $possible->setIsNewRecord(true);
        }

        return $possibles;
    }

    /**
     * Sets model relations attributes
     * @param array $data relations data to be set
     */
    public function setAvailableInterpretations($data)
    {
        $relationConfig = $this->_loadRelationConfig($this->_hasManyRelationKey);

        if (isset($data[$relationConfig[self::RELATION_CLASS]]))
        {
            foreach ($data[$relationConfig[self::RELATION_CLASS]] as $attributes)
            {
                $interpretation = $this->_createInterpretation($attributes, $relationConfig);

                if ($this->_isInterpretationValid($interpretation, $relationConfig))
                {
                    $this->_assignInterpretation($interpretation, $relationConfig);
                }
            }

            $this->_relationsToSave[] = $this->_hasManyRelationKey;
        }
    }

    /**
     * Indicates if given relations is valid to be assigned to owner
     * @param CActiveRecord $interpretation given interpretation
     * @param array $relationConfig interpretation relation config
     * @return boolean TRUE if is valid, FALSE otherwise
     */
    private function _isInterpretationValid($interpretation, $relationConfig)
    {
        if (!$this->partial)
        {
            return true;
        }

        $activationTrigger = false;

        foreach ($this->activationTriggers as $attr)
        {
            if (isset($interpretation->{$attr}) && !empty($interpretation->{$attr}))
            {
                $activationTrigger = true;
            }
        }

        return !$interpretation->isNewRecord || (boolean) $interpretation->{$this->_attrActive} || $activationTrigger || $interpretation->validate();
    }

    /**
     * Creates new interpretation and assigns it with owner
     * @param array $attributes Given attributes for creation
     */
    private function _createInterpretation($attributes, $config)
    {
        $interpretation = null;

        if (isset($attributes[$config[self::RELATION_INDEX]]))
        {
            $interpretation = $config[self::RELATION_CLASS]::model()->findByAttributes(array(
                $config[self::RELATION_INDEX] => $attributes[$config[self::RELATION_INDEX]],
                $config[self::RELATION_KEY] => $this->owner->primaryKey,
            ));
        }

        if (!$interpretation)
        {
            $interpretation = new $config[self::RELATION_CLASS];

            if (!$this->owner->isNewRecord)
            {
                $interpretation->{$config[self::RELATION_KEY]} = $this->owner->primaryKey;
            }
            else
            {
                $interpretation->{$config[self::RELATION_KEY]} = 0;
            }
        }

        $interpretation->setAttributes($attributes);

        return $interpretation;
    }

    /**
     * Assign new interpretation as relation to owner's model class
     * @param mixed $intepretation Interpretation to be added
     * @param mixed $config Config for adding interpretation
     */
    private function _assignInterpretation($interpretation, $config)
    {
        $this->owner->addRelatedRecord($this->_hasManyRelationKey, $interpretation, $interpretation->{$config[self::RELATION_INDEX]});
    }

    /**
     * Loads owner's realtion config based on relation key name
     * @param string $key Relation key name
     * @return array Relation config array
     * @throws Exception throw exception when requested relation does not exist
     */
    private function _loadRelationConfig($key)
    {
        $relations = $this->owner->relations();

        if (!isset($relations[$key]))
        {
            throw new Exception(Yii::t('ib', 'The relation {class}.{attribute} does not exits.', array('{class}' => $this->owner->tableSchema->name, '{attribute}' => $key)));
        }

        $relationConfig = $relations[$key];

        if (!isset($relationConfig[self::RELATION_INDEX]))
        {
            $relationConfig[self::RELATION_INDEX] = 'id';
        }

        return $relationConfig;
    }

    /**
     * Loads index attribute from given config
     * @param array $config Given config array
     * @return string Index value
     */
    private function _loadRelationConfigPkName($config)
    {
        $relationConfig = $config[self::RELATION_CLASS]::model()->tableSchema->primaryKey;

        if (is_array($relationConfig))
        {
            if (!in_array($config[self::RELATION_INDEX], $relationConfig))
            {
                throw new Exception('Relation config is invalid.');
            }

            return $config[self::RELATION_INDEX];
        }

        return $relationConfig;
    }

    /**
     * Loads interpreter config
     * @param string $key Given config key
     * @throws Exception throws when confign nor exists or is invalid
     */
    private function _loadInterpreterConfig()
    {
        if (count($this->relations) < 2)
        {
            throw new Exception(Yii::t('ib', 'Invalid config {configKey} for interpreter. Missing required key.'));
        }

        $this->_manyManyRelationKey = $this->relations[0];
        $this->_hasManyRelationKey = $this->relations[1];

        if (isset($this->relations[2]))
        {
            $this->_currentHasManyRelationKey = $this->relations[2];
        }

        if (isset($this->scopes))
        {
            $this->_scopesToApply = $this->scopes;
        }
    }

    /**
     * Applies scopes given in behavior definition
     * @param CActiveRecord $model given model scopes will be applied on
     * @return CActiveRecord model with applied scopes
     */
    private function _applyScopes(CActiveRecord $model)
    {
        if ($this->_scopesToApply)
        {
            foreach ($this->_scopesToApply as $scope)
            {
                $model = $model->$scope();
            }
        }

        return $model;
    }

}
