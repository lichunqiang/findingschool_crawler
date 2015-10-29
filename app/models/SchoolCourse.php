<?php namespace app\models;

use yii\db\ActiveRecord;

class SchoolCourse extends ActiveRecord
{
	public static function tableName()
	{
		return 'middle_school_ap_courses';
	}
}