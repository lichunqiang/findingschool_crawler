<?php

use yii\db\Schema;
use app\components\Migration;

class m151027_064536_init extends Migration
{
    public function safeUp()
    {
        $this->createTable('middle_school', [
            'id' => $this->primaryKey(),
            'name' => $this->string(100)->notNull()->defaultValue(''),
            'name_en' => $this->string(200)->notNull()->defaultValue(''),

            'website' => $this->string(100)->notNull()->defaultValue(''),
            'found_at' => $this->string(20)->notNull()->defaultValue(''),
            'school_type' => $this->string(50)->notNull()->defaultValue(''),
            'grade' => $this->string(20)->notNull()->defaultValue(''),
            'is_percent' => $this->string(5)->notNull()->defaultValue('') . ' COMMENT "国际生占比"',
            'address' => $this->string(150)->notNull()->defaultValue(''),
            'email' => $this->string(50)->notNull()->defaultValue(''),
            'telphone' => $this->string(20)->notNull()->defaultValue(''),
            'fax' => $this->string(20)->notNull()->defaultValue(''),
            'state' => $this->string(100)->notNull()->defaultValue(''),
            'state_en' => $this->string(100)->notNull()->defaultValue(''),
            'enrollment' => $this->string(50)->notNull()->defaultValue(''),
            'acreage' => $this->string(50)->notNull()->defaultValue('') . ' COMMENT "校园面积"',
            'endowment' => $this->string(50)->notNull()->defaultValue(''),
            'admission_rate' => $this->string(5)->notNull()->defaultValue(''),
            'certification' => $this->string(50)->notNull()->defaultValue(''),

            'rank_tuition_rank' => $this->integer()->notNull()->defaultValue(0),
            'rank_tuition_info' => $this->string(100)->notNull()->defaultValue(''),
            'rank_enrollment_rank' => $this->integer()->notNull()->defaultValue(0),
            'rank_acreage_rank' => $this->integer()->notNull()->defaultValue(0),
            'rank_ap_course_num' => $this->integer(5)->notNull()->defaultValue(0),
            'rank_ap_course_num_rank' => $this->integer()->notNull()->defaultValue(0),
            'rank_is_percent_rank' => $this->integer()->notNull()->defaultValue(0),
            'rank_staff_stu_ratio' => $this->string(20)->notNull()->defaultValue(''),
            'rank_staff_stu_ratio_rank' => $this->integer()->notNull()->defaultValue(0),

            'application_date' => $this->string(20)->notNull()->defaultValue(''),
            'need_ssat' => $this->smallInteger(1)->notNull()->defaultValue(0) . ' COMMENT "是否需要ssat考试"',
            'ssat_percent' => $this->string(5)->notNull()->defaultValue(''),
            'provide_esl' => $this->smallInteger(1)->notNull()->defaultValue(0) . ' COMMENT "是否提供ESL"',
            'english_tests' => $this->string(50)->notNull()->defaultValue('') . ' COMMENT "要求英语测试"',
            'application_material_url' => $this->string(150)->notNull()->defaultValue(''),
            'application_qa_url' => $this->string(150)->notNull()->defaultValue(''),
            'send_points_code' => $this->string()->notNull()->defaultValue(''),

            'sat_score' => $this->string(100)->notNull()->defaultValue(''),

            'boarding_fee' => $this->integer()->notNull()->defaultValue(0),
            'boarding_fee_desc' => $this->string(500)->notNull()->defaultValue(0),
            'day_fee' => $this->integer()->notNull()->defaultValue(0),
            'day_fee_desc' => $this->string(500)->notNull()->defaultValue(0),
            'scholarship_percent' => $this->string(10)->notNull()->defaultValue(''),
            'scholarship_desc' => $this->string(500)->notNull()->defaultValue(''),

            'dorm_num' => $this->string(200)->notNull()->defaultValue(''),
            'dorm_facility' => $this->string(200)->notNull()->defaultValue(''),
            'dress_code' => $this->string(200)->notNull()->defaultValue(''),

            'class_size' => $this->integer(3)->notNull()->defaultValue(0),
            'class_size_desc' => $this->string()->notNull()->defaultValue(''),
            'sat_avg' => $this->integer(5)->notNull()->defaultValue(0),
            'sat_avg_desc' => $this->string()->notNull()->defaultValue(''),
            'staff_stu_ratio_desc' => $this->string()->notNull()->defaultValue(''),
            'faculty_degree' => $this->string(100)->notNull()->defaultValue(''),
            'faculty_degree_desc' => $this->string()->notNull()->defaultValue(''),

            'class_structure' => $this->string(300)->notNull()->defaultValue(''),
            'stu_structure' => $this->string(500)->notNull()->defaultValue(''),

            'extra_activity' => $this->string(500)->notNull()->defaultValue(''),
            'extra_activity_desc' => $this->string()->notNull()->defaultValue(''),
            'extra_activity_url' => $this->string(100)->notNull()->defaultValue(''),

            'ap_courses' => $this->string(1000)->notNull()->defaultValue(''),
            'ap_courses_link' => $this->string(100)->notNull()->defaultValue(''),
            'ap_courses_desc' => $this->string()->notNull()->defaultValue(''),

            'description' => $this->text()
        ]);

        $this->createTable('ap_courses', [
            'id' => $this->primaryKey(),
            'name' => $this->string(50)->notNull()->defaultValue(''),
            'name_en' => $this->string(50)->notNull()->defaultValue(''),
        ]);

        $this->createTable('middle_school_ap_courses', [
            'school_id' => $this->integer()->notNull(),
            'course_id' => $this->integer()->notNull(),
        ]);

        $this->createTable('middle_school_admit_info', [
            'middle_school_id' => $this->integer()->notNull(),
            'year' => $this->string('50')->notNull(),
            'school' => $this->string(100)->notNull()->defaultValue(''),
            'school_en' => $this->string(100)->notNull()->defaultValue(''),
            'school_type' => $this->smallInteger(1)->defaultValue(1),
            'rank' => $this->smallInteger(3)->notNull()->defaultValue(0),
            'admit_num' => $this->string(10)->notNull()->defaultValue(''),
        ]);
    }

    public function down()
    {
        echo "m151027_064536_init cannot be reverted.\n";

        return false;
    }

    /*
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}
