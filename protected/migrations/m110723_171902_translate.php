<?php 
	class m110723_171902_translate extends CDbMigration
	{
		public function up()
		{
			$this->addColumn('content', 'content_ru', 'longtext not null');
			$this->addColumn('content', 'content_en', 'longtext not null');
		}
 
		public function down()
		{
			$this->dropColumn('content', 'content_ru');
			$this->dropColumn('content', 'content_en');
		}
	}
