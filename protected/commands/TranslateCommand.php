<?php

/**
 * TranslateCommand
 * Searchs models that using TranslateBehavior and creates new migrations
 */

class TranslateCommand extends CConsoleCommand 
{

	public $models = array();
	public $langs = null;
	public $up = array();
	public $down = array();

	// Array from columns to create
	public $columns = array();

	public function run($args)
	{
		$this->_loadLanguages();

		$this->models = $this->getModels();

		if (sizeof($this->models) > 0)
		{
			$this->_createMigration();
		} else {
			echo "Found no models with a translate() method";
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
		$file = realpath(Yii::app()->basePath.'/config/main.php');
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

		$migrationFile = realpath(Yii::app()->basePath.'/migrations').'/'.$migrationName.'.php';
		$f = fopen($migrationFile, 'w') or die("Can't open file");
		fwrite($f, $phpCode);
		fclose($f);

		print "Migration successfully created.\n";	
		print "See $migrationName\n";
		print "To apply migration enter: ./yiic migrate\n";
	}

	// Originally from gii-template-collection / fullCrud / FullCrudGenerator.php
	protected function getModels() {
		$models = array();
		$aliases = array();
		$aliases[] = 'application.models';
		foreach (Yii::app()->getModules() as $moduleName => $config) {
			if($moduleName != 'gii')
				$aliases[] = $moduleName . ".models";
		}

		foreach ($aliases as $alias) {
			if (!is_dir(Yii::getPathOfAlias($alias))) continue;
			$files = scandir(Yii::getPathOfAlias($alias));
			Yii::import($alias.".*");
			foreach ($files as $file) {
				if ($fileClassName = $this->checkFile($file, $alias)) {
						$classname = sprintf('%s.%s',$alias,$fileClassName);
						Yii::import($classname);
					try {
						$model = @new $fileClassName;
						if (method_exists($model, 'translate')) {
							if (method_exists($model, 'behaviors')) {
								$behaviors = $model->behaviors();
								if (isset($behaviors['translate']) && strpos($behaviors['translate']['class'], 'STranslateableBehavior') !== false) {
									$models[$classname] = $model;
								}
							}
						}
					} catch (ErrorException $e) {
						break;
					} catch (CDbException $e) {
						break;
					} catch (Exception $e) {
						break;
					}
				}
			}
		}

		return $models;
	}

	// Imported from gii-template-collection / fullCrud / FullCrudGenerator.php
	private function checkFile($file, $alias = '') {
		if (substr($file, 0, 1) !== '.'
				&& substr($file, 0, 2) !== '..'
				&& substr($file, 0, 4) !== 'Base'
			&& $file != 'GActiveRecord'
			&& strtolower(substr($file, -4)) === '.php') {
			$fileClassName = substr($file, 0, strpos($file, '.'));
			if (class_exists($fileClassName)
					&& is_subclass_of($fileClassName, 'CActiveRecord')) {
				$fileClass = new ReflectionClass($fileClassName);
				if ($fileClass->isAbstract())
					return null;
				else
					return $models[] = $fileClassName;
			}
		}
	}
	
}
