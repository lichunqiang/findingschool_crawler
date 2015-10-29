<?php namespace app\models;

use yii\db\ActiveRecord;

class Admit extends ActiveRecord
{
	public static function tableName()
	{
		return 'middle_school_admit_info';
	}
}