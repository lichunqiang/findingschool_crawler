<?php namespace app\models;

use yii\db\ActiveRecord;

class School extends ActiveRecord
{
	public static function tableName()
	{
		return 'middle_school';
	}
}