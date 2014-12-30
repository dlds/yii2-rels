<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals/license/
 */

namespace dlds\rels\components;

/**
 * Interpreter handles many many interpretations
 * 
 * @author Jiri Svoboda <jiri.svoboda@dlds.cz>
 */
class Interpreter {
    /*
     * Relations indexes
     */

    const INDEX_VIA_CLASS = 0;
    const INDEX_REL_PRIMARY = 1;
    const INDEX_REL_SECONDARY = 2;

    /**
     * @var \yii\base\Model owner model
     */
    public $owner;

    /**
     * @var \yii\db\ActiveRecord via class name
     */
    public $viaModel;

    /**
     * @var string primary relation name
     */
    public $relPrimary;

    /**
     * @var string secondary relation name
     */
    public $relSecondary;

    /**
     * @var string primary relation name
     */
    public $relPrimaryKey;

    /**
     * @var string secondary relation name
     */
    public $relSecondaryKey;

    /**
     * @var array holds actual all interpretations
     */
    private $_allInterpretations;

    /**
     * Construcor
     * @param \yii\db\ActiveRecord $owner owner model
     * @param array $config given configs
     * @param array $scopes given scopes
     */
    public function __construct($owner, $config)
    {
        // TODO: implement scopes, partial, activationTriggers
        $this->owner = $owner;

        $this->_loadInterpreterConfig($config);
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
        }
        else
        {
            $current = $this->getCurrentInterpretation();

            if ($current)
            {
                return $current->{$attribute};
            }
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
        if (!$this->_currentHasManyRelation)
        {
            return null;
        }

        return $this->owner->{$this->_currentHasManyRelation};
    }

    /**
     * Default method for getting owner's available hasMany relation models.
     * This can be overloaded and changed in owner's model class.
     * @returns mixed available hasMany relation models
     */
    public function getAvailableInterpretations()
    {
        return $this->viaModel->find()
                        ->where([$this->relPrimaryKey => $this->owner->primaryKey])
                        ->indexBy($this->relSecondaryKey)
                        ->all();
    }

    /**
     * Retrieves all possible hasMany relation models for owner.
     * @return array hasMany relation models array
     */
    public function getAllInterpretations()
    {
        if (!$this->_allInterpretations)
        {
            $this->_allInterpretations = $this->pushMissingInterpretations($this->getAvailableInterpretations());
        }

        return $this->_allInterpretations;
    }

    /**
     * Default method for getting all owner's possible hasMany relation models.
     * This can be overloaded and changed in owner's model class.
     */
    protected function pushMissingInterpretations($availables)
    {
        $model = new $this->relSecondary->modelClass;

        foreach ($model->find()->all() as $secondary)
        {
            if (!isset($availables[$secondary->primaryKey]))
            {
                $availables[$secondary->primaryKey] = $this->_createInterpretation($secondary);
            }
        }

        ksort($availables);

        return $availables;
    }

    /**
     * Sets model relations attributes
     * @param array $data relations data to be set
     */
    public function setAvailableInterpretations($data)
    {
        $model = $this->viaModel;

        $model::loadMultiple($this->getAllInterpretations(), $data);

        return $this;
    }

    /**
     * Sets model relations attributes
     * @param array $data relations data to be set
     */
    public function validate()
    {
        $valid = true;

        foreach ($this->getAllInterpretations() as $model)
        {
            $model->{$this->relPrimaryKey} = 0;
            
            if (!$model->validate())
            {
                $valid = false;
            }
        }
        
        return $valid;
    }

    /**
     * Sets model relations attributes
     * @param array $data relations data to be set
     */
    public function save()
    {
        $valid = true;

        foreach ($this->getAllInterpretations() as $model)
        {
            $model->{$this->relPrimaryKey} = $this->owner->primaryKey;
            
            if (!$model->save())
            {
                $valid = false;
            }
        }

        return $valid;
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
     * @param \yii\db\ActiveRecord $secondary given secondary instance
     */
    private function _createInterpretation($secondary)
    {
        $model = new $this->viaModel;

        $model->{$this->relPrimaryKey} = $this->owner->primaryKey;
        $model->{$this->relSecondaryKey} = $secondary->primaryKey;

        return $model;
    }

    /**
     * Loads interpreter config
     * @param string $key Given config key
     * @throws Exception throws when confign nor exists or is invalid
     */
    private function _loadInterpreterConfig($config)
    {
        if (count($config) < 3)
        {
            throw new Exception(Yii::t('ib', 'Invalid config for interpreter. Missing required keys.'));
        }

        $this->viaModel = new $config[self::INDEX_VIA_CLASS];

        $this->relPrimary = $this->viaModel->getRelation($config[self::INDEX_REL_PRIMARY]);

        $this->relSecondary = $this->viaModel->getRelation($config[self::INDEX_REL_SECONDARY]);

        $this->relPrimaryKey = array_pop($this->relPrimary->link);

        $this->relSecondaryKey = array_pop($this->relSecondary->link);
    }

}
