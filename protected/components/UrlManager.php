<?php

class UrlManager extends CUrlManager 
{

	public $appendLangPrefix = false;

	public function init()
	{
		parent::init();
	}

	// Detect language here
	// Delete lang identif if needed
	// and activate lang
	public function parseUrl($request)
	{
		$result = parent::parseUrl($request);
		$parts = explode('/', $result);
		
		if (in_array($parts[0], Yii::app()->params['languages']))
		{
			Yii::app()->setLanguage($parts[0]);
			$this->appendLangPrefix = true;
			unset($parts[0]);
			$result = implode($parts, '/');
		}
		
		return $result;
	}

	/**
	 * Create url based on current language.
	 * 
	 * @param mixed $route 
	 * @param array $params 
	 * @param string $ampersand 
	 * @access public
	 * @return string
	 */
	public function createUrl($route,  $params=array(),  $ampersand='&')	
	{
		$result = parent::createUrl($route,$params,$ampersand);
		if ($this->appendLangPrefix === true)
			$result = '/'.Yii::app()->language.$result;
		return $result;
	}
}
