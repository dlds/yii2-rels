<?php
/**
 * @link http://www.digitaldeals.cz/
 * @copyright Copyright (c) 2014 Digital Deals s.r.o. 
 * @license http://www.digitaldeals.cz/license/
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
     * @var array restriction condition
     */
    public $restriction;

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
     * @var boolean indicates if cache is allowed
     */
    protected $allowCache = true;

    /**
     * @var array holds actual all interpretations
     */
    private $_allInterpretations = [];

    /**
     * @var array relations to save
     */
    private $_relationsToSave = [];

    /**
     * Construcor
     * @param \yii\db\ActiveRecord $owner owner modela 
     * @param array $config given configs
     * @param array $scopes given scopes
     */
    public function __construct($owner, $config, $allowCache = true)
    {
        // TODO: implement scopes, partial, activationTriggers
        $this->owner = $owner;
        $this->allowCache = $allowCache;

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
    public function getInterpretations($data = null)
    {
        $condition = [$this->relPrimaryKey => $this->owner->primaryKey];

        $restriction = $this->_getRestriction($data, $this->relSecondaryKey, false);

        if ($restriction)
        {
            $condition = ArrayHelper::merge($condition, $restriction);
        }

        return ArrayHelper::merge($this->viaModel->find()
                    ->where($condition)
                    ->andWhere(['not in', $this->relSecondaryKey, array_keys($this->_relationsToSave)])
                    ->indexBy($this->relSecondaryKey)
                    ->all(), $this->_relationsToSave);
    }

    /**
     * Sets specific model relations attributes
     * @param array $data relations data to be set
     */
    public function setInterpretations($data)
    {
        if ($data && ArrayHelper::getValue($data, $this->viaModel->formName(), false))
        {
            $data = $this->pushSecondaryKeys($data, $this->viaModel->formName());

            $this->_relationsToSave = $this->pushMissingInterpretations($this->getInterpretations($data), $data);

            if ($this->_relationsToSave)
            {
                $this->viaModel->loadMultiple($this->_relationsToSave, $data, $this->viaModel->formName());
            }
        }

        return $this;
    }

    /**
     * Retrieves all possible hasMany relation models for owner.
     * @return array hasMany relation models array
     */
    public function getAllInterpretations()
    {
        $hash = $this->_getInterpreterHash();

        if (!$this->allowCache || empty($this->_allInterpretations[$hash]))
        {
            $this->_allInterpretations[$hash] = $this->pushMissingInterpretations($this->getInterpretations());
        }

        return $this->_allInterpretations[$hash];
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
     * Sets interpreter restriction
     * @param array $restriction given restriction
     */
    public function setRestriction(array $restriction)
    {
        $this->restriction = $restriction;
    }

    /**
     * Sets model relations attributes
     * @param array $data relations data to be set
     */
    public function validate()
    {
        /* @var $model \yii\db\ActiveRecord */
        $valid = true;

        foreach ($this->_relationsToSave as $model)
        {
            $model->{$this->relPrimaryKey} = ($this->owner->primaryKey) ? $this->owner->primaryKey : null;

            $attributes = array_diff($model->activeAttributes(), [$this->relPrimaryKey]);

            if (!$model->validate($attributes))
            {
                $this->owner->addErrors($model->getErrors());

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
                $this->owner->addErrors($model->getErrors());

                $saved = false;
            }
        }

        return $saved;
    }

    /**
     * Pushes secondary keys to given data
     * @param array $data
     * @param string $form
     */
    protected function pushSecondaryKeys($data, $form)
    {
        if (isset($data[$form]) && is_array($data[$form]))
        {
            foreach ($data[$form] as $secondaryKey => $values)
            {
                $data[$form][$secondaryKey][$this->relSecondaryKey] = $secondaryKey;
            }
        }

        return $data;
    }

    /**
     * Default method for getting all owner's possible hasMany relation models.
     * This can be overloaded and changed in owner's model class.
     */
    protected function pushMissingInterpretations($availables, $data = null)
    {
        /* @var $secondaryModel \yii\db\ActiveRecord */
        $secondaryModel = new $this->relSecondary->modelClass;

        $queryModel = $secondaryModel->find();

        $restriction = $this->_getRestriction($data, $this->_getPrimaryKeyName($secondaryModel));

        $viaRestriction = ArrayHelper::remove($restriction, $this->relSecondaryKey, false);

        if (false !== $viaRestriction)
        {
            $restriction[$this->_getPrimaryKeyName($secondaryModel)] = $viaRestriction;
        }

        if ($restriction)
        {
            $queryModel->where($restriction);
        }

        foreach ($queryModel->all() as $secondary)
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
     * Parses and retrieves keys from given data
     * @param type $data
     */
    private function _getRestriction($data, $secodaryKey, $allowGlobal = true)
    {
        $restriction = [];

        if ($data)
        {
            $restriction[$secodaryKey] = $this->_getSecondaryKeys($data, $this->viaModel->formName());
        }

        if ($allowGlobal && $this->restriction)
        {
            $restriction = ArrayHelper::merge($restriction, $this->restriction);
        }

        return $restriction;
    }

    /**
     * Retrieves secondary keys
     * @param type $data
     */
    private function _getSecondaryKeys($data, $key = null)
    {
        if ($key)
        {
            $data = ArrayHelper::getValue($data, $key, []);
        }

        return ArrayHelper::getColumn($data, $this->relSecondaryKey);
    }

    /**
     * Retrieves primary key name
     */
    private function _getPrimaryKeyName(\yii\db\ActiveRecord $model, $composite = false)
    {
        $primaryKey = $model->primaryKey();

        if (!$composite && count($primaryKey) > 1)
        {
            throw new Exception('Model has composit key which is not allowed. Relations cannot be established');
        }

        return array_shift($primaryKey);
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

    private function _getInterpreterHash()
    {
        return md5(serialize($this->restriction));
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
            throw new \yii\base\Exception(\Yii::t('ib', 'Invalid config for interpreter. Missing required keys.'));
        }

        $this->viaModel = new $config[self::INDEX_VIA_CLASS];

        if (isset($config[self::INDEX_VIA_CURRENT]))
        {
            $this->viaCurrent = $config[self::INDEX_VIA_CURRENT];
        }

        $this->relPrimary = $this->viaModel->getRelation($config[self::INDEX_REL_PRIMARY]);

        $this->relSecondary = $this->viaModel->getRelation($config[self::INDEX_REL_SECONDARY]);

        $this->relPrimaryKey = array_pop($this->relPrimary->link);

        $this->relSecondaryKey = array_pop($this->relSecondary->link);
    }
}