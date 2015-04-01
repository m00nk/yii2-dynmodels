<?php
/**
 * @copyright (C) FIT-Media.com (fit-media.com), {@link http://tanitacms.net}
 * Date: 23.03.15, Time: 13:21
 *
 * @author Dmitrij "m00nk" Sheremetjev <m00nk1975@gmail.com>
 * @package
 */

namespace m00nk\dynmodels\models;

use m00nk\dynmodels\DynModels;
use yii\base\Model;
use \Yii;
use yii\validators\DateValidator;
use yii\validators\EmailValidator;
use yii\validators\RangeValidator;
use yii\validators\SafeValidator;
use yii\validators\StringValidator;
//- use yii\base\DynamicModel;

class DynModel extends Model
{
	/** @var DynField[] */
	private $_fields = array();

	private $_fieldValues = array(); // id => value

	private $_labels = array();

	/** @var bool|DynModels */
	private $_component = false;
	private $_module = false;
	private $_scheme = false;
	private $_relatedId = false;
	private $_id = false;

	/*
	 * Закрываем конструктор, чтобы не было возможности создавать модели "вручную"
	 */
//?	private function __construct(){}

	/**
	 * Создает объект.
	 *
	 * @param DynModels $component объект компонента
	 * @param string $moduleId идентификатор модуля
	 * @param bool|string $scheme названием схемы или FALSE, если не нужно загружать схему (будет создана новая)
	 *
	 * @return DynModel|null
	 */
	public static function getInstance($component, $moduleId, $scheme = false)
	{
		if(get_class($component) == 'm00nk\dynmodels\DynModels')
		{
			$obj = new self();

			$obj->_component = $component;
			$obj->_module = $moduleId;

			if($scheme !== false)
			{
				if($obj->loadScheme($scheme))
					return $obj;
			}
			else
				return $obj;
		}

		return null;
	}

	/**
	 * Очистка модели
	 *
	 * @param bool $withScheme если TRUE - очищает все, включая схему и валидаторы. FALSE - удаляет только данные
	 */
	public function clear($withScheme = false)
	{
		if($withScheme)
		{
			$this->_id = false;
			$this->_scheme = false;
			$this->_fields = array();
			$this->_labels = array();
		}

		$this->_fieldValues = array();
		foreach ($this->_fields as $field)
			$this->_fieldValues[$field->id] = null;
	}

	/**
	 * Возвращает схему модели (массив)
	 *
	 * @return array
	 */
	public function getScheme()
	{
		$out = array();
		/** @var DynField $field */
		foreach ($this->_fields as $field)
			$out[] = $field->packField();

		return $out;
	}

	/**
	 * Загружает указанную схему в модель
	 *
	 * @param string $scheme
	 * @return bool
	 */
	public function loadScheme($scheme)
	{
		$this->clear(true);

		$r = Yii::$app->db->createCommand()->
		from($this->_component->tableSchemes)->
		where('module_id = :module AND title = :title', array(
			':module' => $this->_module,
			':title' => $scheme
		))->select()->queryRow();

		if($r)
		{
			$list = json_decode($r['fields']);
			foreach ($list as $item)
			{
				$fld = new DynField(/* '', $this */);
				$fld->unpackField($item);
				$this->_fields[] = $fld;
				$this->_createAttribute($fld);
			}

			$this->_scheme = $scheme;
			$this->_id = $r['id'];

			$this->clear(); // заполняем массив полей дефолтовыми значениями

			return true;
		}

		return false;
	}

	/**
	 * Сохраняет схему.
	 *
	 * Если имя схемы не указано, то метод попытается обновить уже сущетсвующую схему (будет использовать имя, переданное при загрузке модели).
	 * Если имя схемы указано, то метод попытается создать новую схему с указанным именем, в случае успеха привязка к старой схеме будет потеряна.
	 *
	 * @param bool|string $scheme имя схемы
	 *
	 * @return bool флаг успеха
	 */
	public function saveScheme($scheme = false)
	{
		$scheme = $scheme === false ? $this->_scheme : $scheme;

		$r = Yii::$app->db->createCommand()->
		from($this->_component->tableSchemes)->
		where('module_id = :module AND title = :title', array(
			':module' => $this->_module,
			':title' => $scheme
		))->select()->queryRow();

		if($r)
		{ // обновление
			if(Yii::$app->db->createCommand()->update(
				$this->_component->tableSchemes,
				array('fields' => json_encode($this->getScheme())),
				'title = :title AND module_id = :module',
				array(
					':title' => $this->_scheme,
					':module' => $this->_module
				))
			)
				return true;
		}
		else
		{ // добавление
			if(Yii::$app->db->createCommand()->insert(
				$this->_component->tableSchemes,
				array(
					'title' => $scheme,
					'module_id' => $this->_module,
					'fields' => json_encode($this->getScheme())
				))
			)
			{
				$this->_scheme = $scheme;
				$this->_id = Yii::$app->getDb()->getLastInsertId();
				return true;
			}
		}

		return false;
	}

	/**
	 * Возвращает массив имен атрибутов модели
	 *
	 * @return array
	 */
	public function attributeNames()
	{
		return array_keys($this->_fieldValues);
	}

	/**
	 * Возвращает ассоциативный массив заголовков атрибутов модели
	 *
	 * @return array
	 */
	public function attributeLabels()
	{
		return $this->_labels;
	}

	private function _addField($name, $label, $value)
	{
		$this->_fieldValues[$name] = $value;
		$this->_labels[$name] = $label;
	}

	public function __get($name)
	{
		if(array_key_exists($name, $this->_fieldValues))
			return $this->_fieldValues[$name];
		else
			return parent::__get($name);
	}

	public function __set($name, $value)
	{
		if(array_key_exists($name, $this->_fieldValues))
			return $this->_fieldValues[$name] = $value;
		else
			return parent::__set($name, $value);
	}

	/**
	 * Создает атрибуты и валидаторы для всех полей модели.
	 */
	private function _createAttributes()
	{
		$vals = $this->_fieldValues;

		$this->_labels = array();
		$this->_fieldValues = array();

		for ($i = 0, $_c = count($this->_fields); $i < $_c; $i++)
		{
			$this->_createAttribute($this->_fields[$i]);
			$this->_fieldValues[$this->_fields[$i]->id] = $vals[$this->_fields[$i]->id];
		}
	}

	private function _createAttribute(DynField $fld)
	{
		$this->_labels[$fld->id] = $fld->label;
		$this->_fieldValues[$fld->id] = null;

		switch ($fld->type)
		{
			case DynField::TYPE_TEXT:
//				$v = new StringValidator();
//				$v->attributes = array($fld->id);
//				$v->max = $fld->getOption(DynField::OPTION_MAX_LEN);
				break;

			//-----------------------------------------
			case DynField::TYPE_CHECKBOX:
//				$v = new RangeValidator();
//				$v->attributes = array($fld->id);
//				$v->range = array(0, 1);
				$this->_fieldValues[$fld->id] = $fld->getOption(DynField::OPTION_CHECKED);
				break;

			//-----------------------------------------
			case DynField::TYPE_EMAIL:
//				$v = new EmailValidator();
//				$v->attributes = array($fld->id);
				break;

			//-----------------------------------------
			case DynField::TYPE_TEXTAREA:
//				$v = new SafeValidator();
//				$v->attributes = array($fld->id);
				break;

			//-----------------------------------------
			case DynField::TYPE_RADIOS:
			case DynField::TYPE_DROPDOWN:
//				$v = new RangeValidator();
//				$v->attributes = array($fld->id);
//				$v->range = array_keys($fld->decodeItems());
				break;

			case DynField::TYPE_MULTISELECT:
//				$v = new SafeValidator();
//				$v->attributes = array($fld->id);
				break;

			case DynField::TYPE_DATE:
//				$v = new DateValidator();
//				$v->attributes = array($fld->id);
//				$v->format = 'yyyy-MM-dd';
				//todo добавить валидацию по указанному диапазону дат
				break;
		}
	}

	/**
	 * Возвращает порядковый индекс поля в модели или -1, если поля не существует
	 *
	 * @param string $id
	 * @return int
	 */
	public function indexOfField($id)
	{
		for ($i = 0, $_c = count($this->_fields); $i < $_c; $i++)
		{
			if($this->_fields[$i]->id == $id)
				return $i;
		}
		return -1;
	}

	/**
	 * Добавляет поле в модель. Поле будет добавлено только если в модели еще не существует поля с таким же ID
	 *
	 * @param DynField $field
	 * @return bool флаг успеха
	 */
	public function addField(DynField $field)
	{
		// проверяем на уникальность
		if($field->validate())
		{
			if($this->indexOfField($field->id) == -1)
			{
				$this->_fields[] = $field;
				$this->_createAttribute($field);
				return true;
			}
			else
				$field->addError('id', Yii::t('odelDynamicModel', 'Поле с таким идентификатором уже сущетсвует.'));
		}
		return false;
	}

	/**
	 * Обновляет поле с указанным ID
	 *
	 * @param string $oldId
	 * @param DynField $field
	 *
	 * @return bool флаг успеха
	 */
	public function updateField($oldId, DynField $field)
	{
		if($field->validate())
		{
			$index = $this->indexOfField($oldId);
			if($index != -1)
			{
				// проверяем на уникальность новое имя
				for ($i = 0, $_c = count($this->_fields); $i < $_c; $i++)
				{
					if($this->_fields[$i]->id == $field->id && $i != $index)
					{
						$field->addError('id', Yii::t('odelDynamicModel', 'Поле с таким идентификатором уже сущетсвует.'));
						return false;
					}
				}

				// обновляем поле
				$val = $this->_fieldValues[$oldId];
				$this->_fields[$index] = $field;
				$this->_fieldValues[$field->id] = $val;
				$this->_createAttributes();
				return true;
			}
		}
		return false;
	}

	/**
	 * Удаляет поле с указанным ID.
	 *
	 * @param string $id
	 *
	 * @return bool флаг успеха
	 */
	public function deleteField($id)
	{
		for ($i = 0, $_c = count($this->_fields); $i < $_c; $i++)
		{
			if($this->_fields[$i]->id == $id)
			{
				unset($this->_fields[$i]);
				$this->_fields = array_values($this->_fields);
				$this->_createAttributes();
				return true;
			}
		}
		return false;
	}

	/**
	 * Перемещает указанное поле выше по списку
	 *
	 * @param string $id
	 * @return bool флаг успеха
	 */
	public function moveFieldUp($id)
	{
		$index = $this->indexOfField($id);
		if($index > 0)
		{
			$f1 = $this->_fields[$index - 1];
			$this->_fields[$index - 1] = $this->_fields[$index];
			$this->_fields[$index] = $f1;
			$this->_createAttributes();
			return true;
		}
		return false;
	}

	/**
	 * Перемещает указанное поле ниже по списку
	 *
	 * @param string $id
	 * @return bool флаг успеха
	 */
	public function moveFieldDown($id)
	{
		$index = $this->indexOfField($id);
		if($index < count($this->_fields) - 1)
		{
			$f1 = $this->_fields[$index + 1];
			$this->_fields[$index + 1] = $this->_fields[$index];
			$this->_fields[$index] = $f1;
			$this->_createAttributes();
			return true;
		}
		return false;
	}

	/**
	 * Возвращает модель поля с указанным ID или NULL, если поля нет.
	 *
	 * @param string $id
	 * @return DynField|null
	 */
	public function getField($id)
	{
		$index = $this->indexOfField($id);
		return $index != -1 ? clone $this->_fields[$index] : null;
	}

	//======================================================

	/**
	 * Загрузка данных в модель
	 *
	 * @param int $relatedId идентификатор связанной записи (например ID юзера, для загрузки его профиля)
	 */
	public function load($relatedId)
	{
		$this->clear();

		$this->_relatedId = intval($relatedId);

		$items = Yii::$app->db->createCommand()
			->from($this->_component->tableData)
			->where('model_id = :model AND related_id = :relId', array(
				':model' => $this->_id,
				':relId' => $this->_relatedId
			))->select('field, value')->queryAll();

		$items = array_map('reset', App::convertQueryResult($items, 'field', 'value'));

		foreach ($this->_fieldValues as $f => $v)
			if(array_key_exists($f, $items))
				$this->_fieldValues[$f] = $items[$f]; //todo добавить расшифровку данных

	}

	/**
	 * Сохранение данных модели
	 *
	 * Если $relatedId не указан, то метод пытается перезаписать существующие данные (использует значение $relatedId, использованное при загрузке модели).
	 *
	 * @param bool|int $relatedId идентификатор связанной записи (например ID юзера, для загрузки его профиля)
	 * @param bool $validate если TRUE, то перед записью будет выполнена валидация данных
	 * @return bool флаг успеха
	 */
	public function save($relatedId = false, $validate = true)
	{
		if($validate !== true || $this->validate())
		{
			$this->_relatedId = intval($relatedId === false ? $this->_relatedId : $relatedId);

			// удаляем все значения полей, связанные с данной моделью
			Yii::$app->db->createCommand()->delete(
				$this->_component->tableData,
				'model_id = :model AND related_id = :relId', array(
				':model' => $this->_id,
				':relId' => $this->_relatedId
			));

			// сохраняем значения всех полей
			foreach ($this->_fieldValues as $f => $v)
			{
				//todo добавить шифрование данных
				Yii::$app->db->createCommand()->insert(
					$this->_component->tableData,
					array(
						'model_id' => $this->_id,
						'related_id' => $this->_relatedId,
						'field' => $f,
						'value' => $v
					)
				);
			}
			return true;

		}
		return false;
	}
}