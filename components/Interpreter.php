<?php

/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals/license/
 */

namespace dlds\rels\components;

use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;

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
    const INDEX_VIA_CURRENT = 3;

    /**
     * @var \yii\base\Model owner model
     */
    public $owner;

    /**
     * @var \yii\db\ActiveRecord via class name
     */
    public $viaModel;

    /**
     * @var string current via relation name
     */
    public $viaCurrent;

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
     * @var array relations to save
     */
    private $_relationsToSave = [];

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
            $availables = $this->getInterpretations();

            if (isset($availables[$index]))
            {
                return $availables[$index]->{$attribute};
            }
        }

        $current = $this->getCurrentInterpretation();

        if ($current)
        {
            return $current->{$attribute};
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
        if (!$this->viaCurrent)
        {
            return null;
        }

        return $this->owner->{$this->viaCurrent};
    }

    /**
     * Retrieves interpretations based on gived data
     * @param array $data
     */
    public function getInterpretations($data = [])
    {
        $condition = [$this->relPrimaryKey => $this->owner->primaryKey];

        if ($data)
        {
            $secondaryKeys = ArrayHelper::getColumn($data, $this->relSecondaryKey);

            ArrayHelper::merge($condition, [$this->relSecondaryKey => $secondaryKeys]);
        }

        return $this->viaModel->find()
                        ->where($condition)
                        ->indexBy($this->relSecondaryKey)
                        ->all();
    }

    /**
     * Sets specific model relations attributes
     * @param array $data relations data to be set
     */
    public function setInterpretations($data)
    {
        /** @var \yii\db\ActiveRecord $model */
        $secondaryKeys = ArrayHelper::getColumn(ArrayHelper::getValue($data, $this->viaModel->formName(), []), $this->relSecondaryKey);

        if (!empty($secondaryKeys))
        {
            $this->_relationsToSave = $this->pushMissingInterpretations($this->getInterpretations($secondaryKeys), $secondaryKeys);

            $this->viaModel->loadMultiple($this->_relationsToSave, $data);
        }

        return $this;
    }

    /**
     * Retrieves all possible hasMany relation models for owner.
     * @return array hasMany relation models array
     */
    public function getAllInterpretations()
    {
        if (!$this->_allInterpretations)
        {
            $this->_allInterpretations = $this->pushMissingInterpretations($this->getInterpretations());
        }

        return $this->_allInterpretations;
    }

    /**
     * Sets all model relations attributes
     * @param array $data relations data to be set
     */
    public function setAllInterpretations($data)
    {
        $model = $this->viaModel;

        $this->_relationsToSave = $this->getAllInterpretations();

        $model::loadMultiple($this->_relationsToSave, $data);

        return $this;
    }

    /**
     * Sets model relations attributes
     * @param array $data relations data to be set
     */
    public function validate()
    {
        $valid = true;

        foreach ($this->_relationsToSave as $model)
        {
            $model->{$this->relPrimaryKey} = ($this->owner->primaryKey) ? $this->owner->primaryKey : 0;

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
        $saved = true;

        foreach ($this->_relationsToSave as $model)
        {
            $model->{$this->relPrimaryKey} = $this->owner->primaryKey;

            if (!$model->save())
            {
                $saved = false;
            }
        }

        return $saved;
    }

    /**
     * Default method for getting all owner's possible hasMany relation models.
     * This can be overloaded and changed in owner's model class.
     */
    protected function pushMissingInterpretations($availables, $keys = [])
    {
        $model = new $this->relSecondary->modelClass;

        if (!empty($keys))
        {
            $models = $model->findAll($keys);
        }
        else
        {
            $models = $model->find()->all();
        }

        foreach ($models as $secondary)
        {
            if (!isset($availables[$secondary->primaryKey]))
            {
                $availables[$secondary->primaryKey] = $this->_createInterpretation($secondary->primaryKey);
            }
        }

        ksort($availables);

        return $availables;
    }

    /**
     * Creates new interpretation and assigns it with owner
     * @param \yii\db\ActiveRecord $secondary given secondary instance
     */
    private function _createInterpretation($secondaryKey)
    {
        $model = new $this->viaModel;

        $model->{$this->relPrimaryKey} = $this->owner->primaryKey;
        $model->{$this->relSecondaryKey} = $secondaryKey;

        return $model;
    }

    /**
     * Loads interpreter config
     * @param string $key Given config key
     * @throws Exception throws when confign nor exists or is invalid
     */
    private function _loadInterpreterConfig($config)
    {
        if (count($config) < 4)
        {
            throw new Exception(Yii::t('ib', 'Invalid config for interpreter. Missing required keys.'));
        }

        $this->viaModel = new $config[self::INDEX_VIA_CLASS];

        $this->viaCurrent = $config[self::INDEX_VIA_CURRENT];

        $this->relPrimary = $this->viaModel->getRelation($config[self::INDEX_REL_PRIMARY]);

        $this->relSecondary = $this->viaModel->getRelation($config[self::INDEX_REL_SECONDARY]);

        $this->relPrimaryKey = array_pop($this->relPrimary->link);

        $this->relSecondaryKey = array_pop($this->relSecondary->link);
    }

}
