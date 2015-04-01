<?php
/**
 * @copyright (C) FIT-Media.com (fit-media.com), {@link http://tanitacms.net}
 * Date: 23.03.15, Time: 13:01
 *
 * @author Dmitrij "m00nk" Sheremetjev <m00nk1975@gmail.com>
 * @package
 */

namespace m00nk\dynmodels\models;

use yii\base\Model;
use \Yii;

class DynField extends Model
{
	const TYPE_TEXT = 1;
	const TYPE_CHECKBOX = 2;
	const TYPE_EMAIL = 3;
	const TYPE_TEXTAREA = 4;
	const TYPE_DROPDOWN = 5;
	const TYPE_MULTISELECT = 6;
	const TYPE_RADIOS = 7;
	const TYPE_DATE = 8;

	public $id = '';
	public $label = '';
	public $hint = '';

	/** @var int тип поля */
	private $type = self::TYPE_TEXT;

	private $options = array();

	//-----------------------------------------
	const OPTION_MAX_LEN = 'maxLen';
	const OPTION_UNIQUE = 'unique';
	const OPTION_ENCODED = 'encoded';
	const OPTION_CHECKED = 'checked'; // checkboxes only
	const OPTION_WYSIWYG = 'wysiwyg'; // textarea only
	const OPTION_ITEMS = 'items'; // multiselect, dropdown and radios
	const OPTION_FIRST_YEAR = 'firstYear'; // date only
	const OPTION_LAST_YEAR = 'lastYear';  // date only

	public function attributeLabels()
	{
		return [
			'id' => Yii::t('modelDynamicField', 'Идентификатор'),
			'label' => Yii::t('modelDynamicField', 'Название'),
			'hint' => Yii::t('modelDynamicField', 'Описание'),
			'type' => Yii::t('modelDynamicField', 'Тип'),

			//-----------------------------------------
			self::OPTION_MAX_LEN => self::getOptionTitle(self::OPTION_MAX_LEN),
			self::OPTION_UNIQUE => self::getOptionTitle(self::OPTION_UNIQUE),
			self::OPTION_ENCODED => self::getOptionTitle(self::OPTION_ENCODED),
			self::OPTION_CHECKED => self::getOptionTitle(self::OPTION_CHECKED),
			self::OPTION_WYSIWYG => self::getOptionTitle(self::OPTION_WYSIWYG),
			self::OPTION_ITEMS => self::getOptionTitle(self::OPTION_ITEMS),
			self::OPTION_FIRST_YEAR => self::getOptionTitle(self::OPTION_FIRST_YEAR),
			self::OPTION_LAST_YEAR => self::getOptionTitle(self::OPTION_LAST_YEAR),
		];
	}

	public static function getOptionTitle($optionCode)
	{
		$_ = self::getOptionTitles();
		return array_key_exists($optionCode, $_) ? $_[$optionCode] : '';
	}

	public static function getOptionTitles()
	{
		return array(
			self::OPTION_ENCODED => Yii::t('modelDynamicField', 'Шифруемое'),
			self::OPTION_MAX_LEN => Yii::t('modelDynamicField', 'Максимальная длина'),
			self::OPTION_UNIQUE => Yii::t('modelDynamicField', 'Уникальное'),
			self::OPTION_CHECKED => Yii::t('modelDynamicField', 'Начальное состояние'),
			self::OPTION_WYSIWYG => Yii::t('modelDynamicField', 'Визуальный редактор'),
			self::OPTION_ITEMS => Yii::t('modelDynamicField', 'Элементы'),
			self::OPTION_FIRST_YEAR => Yii::t('modelDynamicField', 'Первый год'),
			self::OPTION_LAST_YEAR => Yii::t('modelDynamicField', 'Последний год'),
		);
	}

	public static function getTypeTitles()
	{
		return array(
			self::TYPE_TEXT => Yii::t('modelDynamicField', 'Текстовое поле'),
			self::TYPE_CHECKBOX => Yii::t('modelDynamicField', 'Флажок'),
			self::TYPE_EMAIL => Yii::t('modelDynamicField', 'Адрес электропочты'),
			self::TYPE_TEXTAREA => Yii::t('modelDynamicField', 'Текстовый блок'),
			self::TYPE_DROPDOWN => Yii::t('modelDynamicField', 'Выпадающий список'),
			self::TYPE_MULTISELECT => Yii::t('modelDynamicField', 'Список с мульти-выбором'),
			self::TYPE_RADIOS => Yii::t('modelDynamicField', 'Радио-кнопки'),
			self::TYPE_DATE => Yii::t('modelDynamicField', 'Дата'),
		);
	}

	public static function getTypeTitle($typeCode)
	{
		$_ = self::getTypeTitles();
		return array_key_exists($typeCode, $_) ? $_[$typeCode] : '';
	}

	public function rules()
	{
		return [
			[['id', 'label', 'type'], 'required'],
			['type', 'in', 'range' => [
				self::TYPE_TEXT,
				self::TYPE_CHECKBOX,
				self::TYPE_EMAIL,
				self::TYPE_TEXTAREA,
				self::TYPE_DROPDOWN,
				self::TYPE_MULTISELECT,
				self::TYPE_RADIOS,
				self::TYPE_DATE
			]],

			//-----------------------------------------
			['maxLen', 'integer'],
			[['unique', 'checked', 'encoded', 'wysiwyg'], 'in', 'range' => [0, 1]],
			[['id', 'firstYear', 'lastYear', 'items', 'hint'], 'safe'], // проверяются в validateOptions
		];
	}


	public function beforeValidate()
	{
		//======================================================
		// дополнительные валидации
		//======================================================

		if(preg_match('/^[a-zA-Z_0-9]+$/', $this->id) != 1)
			$this->addError('id', Yii::t('modelDynamicField', 'Разрешены только латинский буквы, цифры и подчеркивание'));

		switch ($this->type)
		{
			case self::TYPE_TEXT:
			case self::TYPE_EMAIL:
				if($this->getOption(self::OPTION_MAX_LEN) < 1)
					$this->addError(self::OPTION_MAX_LEN, Yii::t('modelDynamicField', 'Неверная длина поля'));
				break;

			//-----------------------------------------
			case self::TYPE_DROPDOWN:
			case self::TYPE_MULTISELECT:
			case self::TYPE_RADIOS:
				$_ = trim($this->getOption(self::OPTION_ITEMS));
				if(!$_ || empty($_))
					$this->addError(self::OPTION_ITEMS, Yii::t('modelDynamicField', 'Необходимо задать значения'));
				break;

			//-----------------------------------------
			case self::TYPE_DATE:
				$this->setOption(self::OPTION_FIRST_YEAR, preg_replace('/[^0-9\-\+]/', '', $this->getOption(self::OPTION_FIRST_YEAR)));
				if(preg_match('/^[+\-]{0,1}[0-9]{1,4}$/', $this->getOption(self::OPTION_FIRST_YEAR)) != 1)
					$this->addError(self::OPTION_FIRST_YEAR, Yii::t('modelDynamicField', 'Формат: [+-]<год>'));

				$this->setOption(self::OPTION_LAST_YEAR, preg_replace('/[^0-9\-\+]/', '', $this->getOption(self::OPTION_LAST_YEAR)));
				if(preg_match('/^[+\-]{0,1}[0-9]{1,4}$/', $this->getOption(self::OPTION_LAST_YEAR)) != 1)
					$this->addError(self::OPTION_LAST_YEAR, Yii::t('modelDynamicField', 'Формат: [+-]<год>'));

		}

		return parent::beforeValidate();
	}

	/**
	 * Заполняет массив $options опциями, соответствующими выбранному типу поля
	 */
	protected function _setupOptions()
	{
		switch ($this->type)
		{
			case self::TYPE_TEXT:
				$this->options = array(
					self::OPTION_ENCODED => 0,
					self::OPTION_UNIQUE => 0,
					self::OPTION_MAX_LEN => 80,
				);
				break;

			//-----------------------------------------
			case self::TYPE_CHECKBOX:
				$this->options = array(
					self::OPTION_ENCODED => 0,
					self::OPTION_CHECKED => 0,
				);
				break;

			//-----------------------------------------
			case self::TYPE_EMAIL:
				$this->options = array(
					self::OPTION_ENCODED => 0,
					self::OPTION_UNIQUE => 0,
					self::OPTION_MAX_LEN => 80,
				);
				break;

			//-----------------------------------------
			case self::TYPE_TEXTAREA:
				$this->options = array(
					self::OPTION_ENCODED => 0,
					self::OPTION_WYSIWYG => 0,
				);
				break;

			//-----------------------------------------
			case self::TYPE_DROPDOWN:
			case self::TYPE_MULTISELECT:
			case self::TYPE_RADIOS:
				$this->options = array(
					self::OPTION_ENCODED => 0,
					self::OPTION_ITEMS => '',
				);
				break;

			//-----------------------------------------
			case self::TYPE_DATE:
				$this->options = array(
					self::OPTION_FIRST_YEAR => '',
					self::OPTION_LAST_YEAR => '',
				);
				break;
		}
	}
	/**
	 * Загружает данные
	 *
	 * @param array $src параметры в виде массива
	 */
	public function unpackField($src)
	{
		$src = (array)$src;
		$src['options'] = (array)$src['options'];

		$this->id = $src['id'];
		$this->label = $src['label'];
		$this->hint = $src['hint'];
		$this->type = $src['type'];

		$this->_setupOptions();

		foreach (array_keys($this->options) as $k)
			if(array_key_exists($k, $src['options'])) $this->options[$k] = $src['options'][$k];
	}

	/**
	 * Упаковывает данные в массив
	 *
	 * @return array
	 */
	public function packField()
	{
		return array(
			'id' => $this->id,
			'label' => $this->label,
			'hint' => $this->hint,
			'type' => $this->type,
			'options' => $this->options
		);
	}

	public function setOption($option, $value)
	{
		if(array_key_exists($option, $this->options))
			$this->options[$option] = $value;
	}

	public function getOption($option, $default = null)
	{
		return array_key_exists($option, $this->options) ? $this->options[$option] : $default;
	}

	/**
	 * @return int
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param int $type
	 */
	public function setType($type)
	{
		$this->type = $type;
		$this->_setupOptions();
	}

	public function getOptions()
	{
		return $this->options;
	}

	//===============================================
	public function getMaxLen()
	{
		return $this->getOption(self::OPTION_MAX_LEN, 0);
	}

	public function setMaxLen($val)
	{
		$this->setOption(self::OPTION_MAX_LEN, intval($val));
	}

	public function getUnique()
	{
		return $this->getOption(self::OPTION_UNIQUE, 0);
	}

	public function setUnique($val)
	{
		$this->setOption(self::OPTION_UNIQUE, $val == 0 ? 0 : 1);
	}

	public function getEncoded()
	{
		return $this->getOption(self::OPTION_ENCODED, 0);
	}

	public function setEncoded($val)
	{
		$this->setOption(self::OPTION_ENCODED, $val == 0 ? 0 : 1);
	}

	public function getChecked()
	{
		return $this->getOption(self::OPTION_CHECKED, 0);
	}

	public function setChecked($val)
	{
		$this->setOption(self::OPTION_CHECKED, $val == 0 ? 0 : 1);
	}

	public function getWysiwyg()
	{
		return $this->getOption(self::OPTION_WYSIWYG, 0);
	}

	public function setWysiwyg($val)
	{
		$this->setOption(self::OPTION_WYSIWYG, $val == 0 ? 0 : 1);
	}

	public function getItems()
	{
		return $this->getOption(self::OPTION_ITEMS, '');
	}

	public function setItems($val)
	{
		$this->setOption(self::OPTION_ITEMS, trim($val));
	}

	public function getLastYear()
	{
		return $this->getOption(self::OPTION_LAST_YEAR, '');
	}

	public function setLastYear($val)
	{
		$this->setOption(self::OPTION_LAST_YEAR, trim($val));
	}

	public function getFirstYear()
	{
		return $this->getOption(self::OPTION_FIRST_YEAR, '');
	}

	public function setFirstYear($val)
	{
		$this->setOption(self::OPTION_FIRST_YEAR, trim($val));
	}

	/**
	 * Декодирует список параметров текущего поля и возвращает в виде массива, пригодного для использования в дропдаунах
	 *
	 * @return array
	 */
	public function decodeItems()
	{
		$items = $this->getOption(self::OPTION_ITEMS, '');
		$items = str_replace("\r", '', $items);
		$items = array_map(create_function('$a', 'return trim($a);'), explode("\n", $items));

		$out = array();
		for ($i = 0, $_c = count($items); $i < $_c; $i++)
		{
			$_ = explode('=', $items[$i]);
			if(count($_) == 1)
				$out[trim($_[0])] = trim($_[0]);
			else
				$out[trim($_[0])] = trim($_[1]);
		}
		return $out;
	}
}