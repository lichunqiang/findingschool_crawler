<?php namespace app\models;

use yii\db\ActiveRecord;

class APCourses extends ActiveRecord
{
	public static function tableName()
	{
		return 'ap_courses';
	}
}