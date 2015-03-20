<?php
/**
 * @copyright (C) FIT-Media.com (fit-media.com), {@link http://tanitacms.net}
 * Date: 20.03.15, Time: 12:41
 *
 * @author Dmitrij "m00nk" Sheremetjev <m00nk1975@gmail.com>
 * @package
 */

namespace m00nk\dynmodels;

use \yii\base\Object;
use \Yii;

class DynModels extends Object
{
	/** @var int максимальная длина значения поля */
	public $valueMaxLength = 32000;

	/** @var string имя таблицы БД для схем моделей */
	public $tableSchemes = '{{%dynamic_schemes}}';

	/** @var string имя таблицы БД для значений */
	public $tableData = '{{%dynamic_data}}';

	/** @var bool кэш-флаг существования таблиц в БД */
	private $_dbTablesChecked = false;

	public function init()
	{
		parent::init();

//		Yii::app()->setImport(array(
//			'application.components.dynamicModel.models.*',
//		));

		//-----------------------------------------
		$this->valueMaxLength = intval($this->valueMaxLength);
		if($this->valueMaxLength <= 0) $this->valueMaxLength = 32000;

		//-----------------------------------------
		$this->_createTables();
	}

	private function _createTables()
	{
		if($this->_dbTablesChecked) return;

		$connection = Yii::$app->getDb();

		$rez = $connection->createCommand("SHOW TABLE STATUS LIKE :name")->bindValues([
			':name' => '%'.str_replace(array('{{%', '}}'), '', $this->tableSchemes).'%'
		])->queryOne();

		if(!$rez)
		{
			$cmd = $connection->createCommand();
			$cmd->createTable($this->tableSchemes, array(
				'id' => 'pk',
				'module_id' => "varchar(60) not null default 'system' comment 'идентификатор модуля, создавшего модель'",
				'title' => "varchar(60) not null comment 'идентификатор модели, уникальный в пределах модуля'",
				'fields' => "varchar(10000) not null default '{}' comment 'JSON-кодированное описание полей модели'",
			), 'ENGINE=InnoDB DEFAULT CHARSET=utf8')->execute();

			$cmd->createTable($this->tableData, array(
				'id' => 'pk',
				'model_id' => "int not null",
				'related_id' => "int not null comment 'идентификатор связанной записи, определяется модулем'",
				'field' => "varchar(60) not null comment 'идентификатор поля'",
				'value' => "varchar(".intval($this->valueMaxLength).") not null default '' comment 'значение поля модели'",
			), 'ENGINE=InnoDB DEFAULT CHARSET=utf8')->execute();

			$_t1 = str_replace(array('{{%', '}}'), '', $this->tableSchemes);
			$_t2 = str_replace(array('{{%', '}}'), '', $this->tableData);

			$cmd->createIndex('i_'.$_t1.'_full', $this->tableSchemes, array('module_id', 'title'), true)->execute();

			$cmd->createIndex('i_'.$_t2.'_model_id', $this->tableData, 'model_id')->execute();
			$cmd->createIndex('i_'.$_t2.'_field', $this->tableData, 'field')->execute();
			$cmd->createIndex('i_'.$_t2.'_full', $this->tableData, array('model_id', 'related_id', 'field'), true)->execute();
			$cmd->createIndex('i_'.$_t2.'_value', $this->tableData, 'value')->execute();

			$cmd->addForeignKey('fk_'.$t2.'_'.$_t1, $this->tableData, 'model_id', $this->tableSchemes, 'id', 'CASCADE', 'CASCADE')->execute();
		}

		$this->_dbTablesChecked = true;
	}


	public function test()
	{
		return 1;
	}
}