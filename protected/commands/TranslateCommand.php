<?php

/**
 * TranslateCommand
 * Searches models that using TranslateBehavior and creates new migrations
 */
class TranslateCommand extends CConsoleCommand
{

    /**
     * String that if found in a class denotes its translatability
     *
     * @var string
     */
    public $search_str = 'application.extensions.STranslateableBehavior.STranslateableBehavior';

    /**
     * @var array
     */
    public $models = array();

    /**
     * @var null
     */
    public $langs = null;

    /**
     * Source language
     *
     * @var string
     */
    public $sourceLang;

    /**
     * @var array
     */
    public $up = array();

    /**
     * @var array
     */
    public $down = array();

    /**
     * If we should be verbose
     *
     * @var bool
     */
    private $_verbose = false;

    /**
     * Array of columns to create
     *
     * @var array
     */
    public $columns = array();

    /**
     * Write a string to standard output if we're verbose
     *
     * @param $string
     */
    public function d($string)
    {
        if ($this->_verbose) {
            print $string;
        }
    }

    /**
     * Execute the command
     *
     * @param array $args
     * @return bool|int
     */
    public function run($args)
    {
        if (in_array('--verbose', $args)) {
            $this->_verbose = true;
        }
        $this->d("\033[37mLoading languages\n");
        $this->_loadLanguages();

        $path = realpath(dirname(__FILE__) . '/../');
        $files = CFileHelper::findFiles($path, array('fileTypes'=> array('php')));

        if (!sizeof($files)) {
            print "Nothing found.\n";
            return false;
        }

        // Try to find all models with translate behavior enabled.
        foreach ($files as $file) {
            $this->d("Reading $file...");
            $content = file_get_contents($file);
            if (strpos($content, $this->search_str) && basename($file) != 'TranslateCommand.php') {
                $this->d("\033[32mOK\033[37m\n");
                $this->_loadModelFile($file);
            } else {
                $this->d("\033[31mskip\033[37m\n");
            }
        }

        if (sizeof($this->models) > 0) {
            $this->_createMigration();
        }
    }

    /**
     * Create the migration files
     */
    protected function _createMigration()
    {
        $this->d("Creating the migration...\n");
        foreach ($this->models as $modelName => $modelClass) {
            $this->d("\t...$modelName: ");
            foreach ($this->langs as $lang) {
                $this->d($lang);
                $this->_processLang($lang, $modelClass);
            }
            $this->d("\n");
        }

        $this->_createMigrationFile();
    }

    /**
     * @param $lang
     * @param $model
     */
    protected function _processLang($lang, $model)
    {
        foreach ($model->translate() as $attribute) {
            $newName = $attribute . '_' . $lang;
            if (!isset($model->metaData->columns[$newName])
                && $this->_checkColumnExists($model, $attribute)) {
                // Rename columns back and forth
                if ($lang == $this->sourceLang) {
                    $this->d("Rename $attribute to $newName\n");
                    $this->up[] = '$this->renameColumn(\''. $model->tableName() . '\', \'' . $attribute
                        . '\', \'' . $newName . '\');';
                    $this->down[] = '$this->renameColumn(\'' . $model->tableName() . '\', \''
                        . $newName . '\', \'' . $attribute . '\');';
                } else {
                    $this->up[] = '$this->addColumn(\''. $model->tableName() . '\', \'' . $newName
                        . '\', \'' . $this->_getColumnDbType($model, $attribute) . '\');';
                    $this->down[] = '$this->dropColumn(\'' . $model->tableName() . '\', \''
                        . $newName . '\');';
                }
            }
        }
    }

    /**
     * @param $model
     * @param $column
     * @return bool
     */
    protected function _checkColumnExists($model, $column)
    {
        return isset($model->metaData->columns[$column]);
    }

    /**
     * @param $model
     * @param $column
     * @return string
     */
    protected function _getColumnDbType($model, $column)
    {
        $data = $model->metaData->columns[$column];
        $isNull = $data->allowNull ? "null" : "not null";

        return $data->dbType . ' ' . $isNull;
    }

    /**
     * Load model file and create new instance
     *
     * @param mixed $path Path to model
     * @access protected
     */
    protected function _loadModelFile($path)
    {
        $this->d("Loading model from $path\n");
        $class_name = str_replace('.php', '', basename($path));
        if (!class_exists($class_name, false)) {
            include($path);
            $model = new $class_name();
            if (method_exists($model, 'translate')) {
                $this->models[$class_name] = $model;
            }
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
        $file = realpath(dirname(__FILE__) . '/../config/main.php');
        if (!file_exists($file)) {
            print("Config not found\n");
            exit("Error loading config file main.php.\n");
        } else {
            $config = require($file);
            $this->d("Config loaded\n");
        }

        if (!isset($config['params']['languages'])) {
            exit("Please, define possible languages in config file.\n");
        }

        if (!isset($config['sourceLanguage'])) {
            exit("Please, define the source language in config file.\n");
        }

        $this->langs = $config['params']['languages'];
        $this->sourceLang = $config['sourceLanguage'];
    }

    /**
     * Create migration file
     */
    protected function _createMigrationFile()
    {
        if (count($this->up) == 0) {
            exit("Database up to date\n");
        }

        $migrationName = 'm' . gmdate('ymd_His') . '_translate';

        $phpCode = '<?php
class ' . $migrationName . ' extends CDbMigration
{
    public function up()
    {
        ' . implode("\n        ", $this->up) . '
    }

    public function down()
    {
      ' . implode("\n      ", $this->down) . '
    }
}' . "\n";

        $migrationsDir = dirname(dirname(__FILE__)) . '/migrations';
        if (!realpath($migrationsDir)) {
            die(sprintf('Please create migration directory %s first', $migrationsDir));
        }

        $migrationFile = realpath(dirname(__FILE__) . '/../migrations') . '/' . $migrationName . '.php';
        $f = fopen($migrationFile, 'w') or die("Can't open file");
        fwrite($f, $phpCode);
        fclose($f);

        print "Migration successfully created.\n";
        print "See $migrationName\n";
        print "To apply migration enter: ./yiic migrate\n";
    }

}
