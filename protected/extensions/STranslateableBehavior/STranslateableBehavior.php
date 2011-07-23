<?php

/**
 * STranslateableBehavior 
 * 
 * @uses CActiveRecordBehavior
 * @package Extensions 
 * @version $id$
 * @copyright firstrow@gmail.com
 * @author Andrej Bubis' <firstrow@gmail.com> 
 */
class STranslateableBehavior extends CActiveRecordBehavior {

    /**
     * Apply translated attributes to current lang 
     * 
     * @access public
     */
    public function afterFind()
    {
        $forTranslate = $this->owner->translate();
               
		if (sizeof($forTranslate) > 0)
		{
			foreach($forTranslate as $attr)
			{
				$attrName = $attr.'_'.Yii::app()->language;
				if (array_key_exists($attrName, $this->owner->attributes))
					$this->owner->$attr = $this->owner->$attrName;
			}
		}
    }

}
