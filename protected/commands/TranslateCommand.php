<?php

/**
 * TranslateCommand
 * Searchs models that using TranslateBehavior and creates new migrations
 */

class TranslateCommand extends CConsoleCommand 
{

	public $search_str ='application.extensions.behaviors.STranslateableBehavior';
	public $models = array();
	public $langs = null;
	public $up = array();
	public $down = array();

	// Array from columns to create
	public $columns = array();

	public function run($args)
	{
		$this->_loadLanguages();

		$path = realpath(dirname(__FILE__).'/../');
		$files = CFileHelper::findFiles($path, array('fileTypes'=>array('php')));
		
		if (!sizeof($files)) return false;
		
		// Try to find all models with translate behavior enabled.
		foreach ($files as $file)
		{
			$content = file_get_contents($file);
			if (strpos($content, $this->search_str) && basename($file) != 'TranslateCommand.php')
			{
				$this->_loadModelFile($file);
			}
		} 
	
		if (sizeof($this->models) > 0)
		{
			$this->_createMigration();
		}
	}

	protected function _createMigration()
	{
		foreach ($this->models as $modelName=>$modelClass)
		{
			foreach ($this->langs as $lang)
			{
				$this->_processLang($lang, $modelClass);
			}
		}

		$this->_createMigrationFile();
	}

	protected function _processLang($lang, $model)
	{
		foreach($model->translate() as $attribute)
		{
			$newName = $attribute.'_'.$lang; 
			if (!isset($model->metaData->columns[$newName]) && $this->_checkColumnExists($model, $attribute))
			{
				// Create new column for lang, based on original column
				$this->up[] = '$this->addColumn(\''.$model->tableName().'\', \''.$newName.'\', \''.$this->_getColumnDbType($model, $attribute).'\');';
				$this->down[] = '$this->dropColumn(\''.$model->tableName().'\', \''.$newName.'\');';
			}
		}
	}

	protected function _checkColumnExists($model, $column)
	{
		return isset($model->metaData->columns[$column]);
	}

	protected function _getColumnDbType($model, $column)
	{
		$data = $model->metaData->columns[$column];
		$isNull = $data->allowNull ? "null" : "not null"; 

		return $data->dbType.' '.$isNull;
	}

	/**
	 * Load model file and create new instance 
	 * 
	 * @param mixed $path Path to model
	 * @access protected
	 */
	protected function _loadModelFile($path)
	{
		$class_name = str_replace('.php', '', basename($path));
		if (!class_exists($class_name, false))
		{
			include($path);
			$model = new $class_name; 
			if (method_exists($model, 'translate'))
				$this->models[$class_name] = $model;
		}
	}

	/**
	 * Load languages from main config. 
	 * 
	 * @access protected
	 */
	protected function _loadLanguages()
	{
		// Load main.php config file
		$file = realpath(dirname(__FILE__).'/../config/main.php');
		if (!file_exists($file))
			exit("Error loading config file main.php.\n");
		else
			$config = require($file);

		if (!isset($config['params']['languages']))
			exit("Please, define possible languages in config file.\n");

		$this->langs = $config['params']['languages'];
	}

	protected function _createMigrationFile()
	{
		if (!sizeof($this->up))
		{
			exit("Database up to date\n");
		}

		$migrationName='m'.gmdate('ymd_His').'_translate'; 

$phpCode = '<?php 
	class '.$migrationName.' extends CDbMigration
	{
		public function up()
		{
			'.implode("\n\t\t\t", $this->up).'
		}
 
		public function down()
		{
			'.implode("\n\t\t\t", $this->down).'
		}
	}'."\n";

		$migrationFile = realpath(dirname(__FILE__).'/../migrations').'/'.$migrationName.'.php';
		$f = fopen($migrationFile, 'w') or die("Can't open file");
		fwrite($f, $phpCode);
		fclose($f);

		print "Migration successfully created.\n";	
		print "See $migrationName\n";
		print "To apply migration enter: ./yiic migration\n";
	}

}
