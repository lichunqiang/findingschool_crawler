<?php namespace app\controllers;

use app\models\School;
use app\models\Admit;
use app\models\APCourses;
use app\models\SchoolCourse;
use yii\console\Controller;
use Yii;
use DiDom\Document;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelector;

class DefaultController extends Controller
{
	/**
	 * Document
	 */
	public function actionIndex()
	{
		// $html = file_get_contents(Yii::getAlias('@app/runtime/example2.html'));
		// $this->parse($html, 121);
		// exit;
		$back_set = 's_school_list_31';
		$source_set = 's_school_list_b';
		$redis = Yii::$app->redis;
		while ($link = $redis->spop($source_set)) {
			try {
				$html = $this->getHtml('http://www.findingschool.net' . $link);

				echo $this->_school($html), PHP_EOL;
				$redis->sadd($back_set, $link);
				sleep(1);
			} catch (\Exception $e) {
				$redis->sadd($source_set, $link);
				echo $link, PHP_EOL;
				$this->write($link . PHP_EOL, true);
				//throw $e;
				//echo $e->getMessage(), PHP_EOL, 'rollback...', PHP_EOL;
			}

		}
		// $link =	$redis->srandmember('s_school_list');

	}
	/**
	 * 获取所有学校的链接地址发到一个队列
	 */
	public function actionLinks()
	{
		$agent_pool = [
			'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.93 Safari/537.36',
			'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.71 Safari/537.1 LBBROWSER',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; 360SE)',
			'Mozilla/5.0 (iPad; U; CPU OS 4_2_1 like Mac OS X; zh-cn) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8C148 Safari/6533.18.5'
		];
		$redis = Yii::$app->redis;
		$KEY = 's_school_list_b';
		$total_page = 38;
		$link = 'http://www.findingschool.net/schoolinfo/v2/searchschool_list.php?cid=3&page={page}&pnum=25&html_pnum=25&q_s=&searsid=-&searcid=-&address=&get_lat=&get_lng=&nav_sc=1&radius=50&visits=&type=&searclass=0&studnum=0;4500&tuition=0;150000&apbox=&grades=&stud=0&s_sam=0&s_re=0&s_im=0&s_qa=0&s_esl=0&letter=';
		$page_idx = 23;
		$total = 0;
		while ($page_idx > 10) {
			$context = stream_context_create([
				'http' =>[
					'User-Agent' => $agent_pool[array_rand($agent_pool)],
					'Referer' => 'http://www.findingschool.net/browse'
				]
			]);
			$url = strtr($link, ['{page}' => $page_idx]);
			$html = file_get_contents($url, null, $context);
			preg_match_all('/href=\"(\/.*?)\"\s+?title/', $html, $matches);
			// $this->write(array_unique($matches[1]));
			$link_list = $matches[1];// array_unique($matches[1]);
			array_unshift($link_list, $KEY);
			$total =  $total + $redis->executeCommand('sadd', $link_list);
			echo "Get {$total} records...", PHP_EOL;
			$page_idx--;
			sleep(5);
		}

	}

	public function actionTest()
	{
		// file_put_contents(Yii::getAlias('@app/runtime/xx.html'),
		// 	file_get_contents('http://www.findingschool.net/Concordia-Junior-Senior-High-School')
		// );
		// exit;
		$html = file_get_contents(Yii::getAlias('@app/runtime/xx.html'));

		$crawler = new Crawler($html);
		$result = [];
		$school = new School;
		//名称
		$overview = $crawler->filter('#overview');
		$title = $overview->filter('.f-24x')->html();
		preg_match('/([^\(]+?)\((.+)\)/s', $title, $matched);
		if (count($matched) != 3) {
			throw new \Exception('parse title error');
		}
		list($result['name'], $result['name_en']) = array_slice($matched, 1, 2);
		//介绍
		$description = $crawler->filter('p.f-16x')->html();
		//去除更多,更少
		$description = preg_replace('/(<a href=\"javascript:;\"[^>]+?>.*?<\/a>)/', '', $description);
		$result['description'] = trim(strip_tags($description, '<br>'));
		//基本概况
		$key_map = [
			'网站' => 'website', '建校' => 'found_at', '类型' => 'school_type', '年级' => 'grade',
			'国际学生比例' => 'is_percent', '地址' => 'address', '所在州' => 'state',
			'在校人数' => 'school_enrollments', '面积' => 'acreage', '校友捐赠' => 'endowment',
			'综合申请录取率' => 'admission_rate', '教育认证和会员' => 'certification'
		];
		$tbody = $crawler->filter('i.fa-list-alt')->parents()->siblings()->html();//tbody
		preg_match_all('/<td>(.*?)<\/td>/is', $tbody, $matchted);
		$items = array_chunk($matchted[1], 2);

		foreach ($items as $item) {
			list($_key, $_val) = $item;
			$_key = $key_map[$_key];

			if ($_key == 'state') {
				preg_match('/href=\"\/state\/(.+?)\"/i', $_val, $matchted);
				$result['state_en'] = $matchted[1];
			}
			if (in_array($_key, ['website', 'address', 'state', 'certification'])) {
				$_val = strip_tags($_val);
			}
			if ($_key == 'certification') {
				$_val = preg_replace('/\s/', '', $_val);
			}
			$result[$_key] = trim($_val);
		}
		try {
			//指标排名
			$tbody = $crawler->filter('i.fa-list-ol')->parents()->nextAll()->first();
			// $node = $crawler->filter('.fa-money')->parents()->nextAll()->first();
			// $tbody = $tbody->filter('table')->last();
			$spans = $tbody->filter('span.label');
			//学费
			$node = $spans->eq(0);
			$result['rank_tuition_rank'] = str_replace('名', '', $node->html());
			$__pre = $node->siblings()->html();
			preg_match('/\((.*)\)/', $__pre, $matched);
			if (isset($matched[1])) {
				$result['rank_tuition_info'] = $matched[1];
			}
			//在校学生排名
			$result['rank_enrollment_rank'] = str_replace('名', '', $spans->eq(1)->html());
			//校园面积
			$result['rank_acreage_rank'] = str_replace('名', '', $spans->eq(2)->html());
			//ap课程数
			$node = $spans->eq(3);
			$result['rank_ap_course_num_rank'] = str_replace('名', '', $node->html());
			$__pre = $node->siblings()->html();
			preg_match('/(\d+)/', $__pre, $matched);
			if (isset($matched[1])) {
				$result['rank_ap_course_num'] = $matched[1];
			}
			//国际生比例
			$result['rank_is_percent_rank'] = str_replace('名', '', $spans->eq(4)->html());
			//师承比例
			$node = $spans->eq(5);
			$result['rank_staff_stu_ratio_rank'] = str_replace('名', '', $node->html());
			$__pre = $node->siblings()->html();
			preg_match('/\((.*)\)/', $__pre, $matched);
			if (isset($matched[1])) {
				$result['rank_staff_stu_ratio'] = $matched[1];
			}
		} catch (\Exception $e) {
			echo 'rank info not found', PHP_EOL;
		}

		//申请信息
		$node = $crawler->filter('div.well.alert-info.mar-t10x');
		try {
			$_codes = [];
			$node->filter('.fa-question-circle')->each(function(Crawler $n) use (&$_codes) {
				$__s = trim($n->parents()->text());
				preg_match('/([a-zA-Z]+)\s+(\d+)/', $__s, $matched);
				if (count($matched) == 3) {
					$_codes[$matched[1]] = $matched[2];
				}
			});
			if ($_codes) {
				$result['send_points_code'] = serialize($_codes);
			}
		} catch (\Exception $e) {
			echo 'no found application information', PHP_EOL;
		}

		$__html = $node->html();
		preg_match('/申请日期: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['application_date'] = $matched[1];
		}
		preg_match('/SSAT: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['need_ssat'] = $matched[1] == 'Yes' ? 1 : 0;
		}
		preg_match('/SSAT平均百分比: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['ssat_percent'] = $matched[1];
		}
		preg_match('/ESL: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['provide_esl'] = $matched[1] == '不提供' ? 0 : 1;
		}
		preg_match('/申请材料: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			preg_match('/href=\"(.*?)\"/', $matched[1], $matched);
			if (isset($matched[1])) {
				$result['application_material_url'] = $matched[1];
			}
		}
		preg_match('/常见问题: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			preg_match('/href=\"(.*?)\"/', $matched[1], $matched);
			if (isset($matched[1])) {
				$result['application_qa_url'] = $matched[1];
			}
		}
		preg_match('/要求英语测试: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['english_tests'] = $matched[1];
		}
		preg_match('/邮件: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['email'] = strip_tags($matched[1]);
		}
		preg_match('/电话: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['telphone'] = $matched[1];
		}
		//SAT成绩
		try {
			$node = $crawler->filter('.fa-bell')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$__sat = [];
			$node->each(function(Crawler $c) use (&$__sat) {
				$__tr = $c->html();
				preg_match_all('/<td>(\d+)<\/td>/', $__tr, $matched);
				$__sat[] = $matched[1];
			});
			$result['sat_score'] = serialize($__sat);
		} catch (\Exception $e) {
			echo 'Not found sat information, passed.', PHP_EOL;
		}
		//费用以及奖学金
		try {
			$node = $crawler->filter('.fa-money')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$node->each(function(Crawler $c, $idx) use (&$result) {
				$_title = trim(strip_tags($c->filter('td')->eq(0)->html()));
				$__tr = $c->html();
				$fee = $c->filter('td')->eq(1)->html();
				if ($_title != '奖学金比例') {
					$fee = str_replace('.', '', str_replace(',', '', str_replace('$', '', $fee)));
				}
				//parse desc
				preg_match('/data-content=\"(.*?)\"/', $__tr, $matched);
				$desc = strip_tags(htmlspecialchars_decode($matched[1]), '<br>');
				$desc = preg_replace('/<br \/>/', PHP_EOL, $desc);
				if ($_title == '寄宿') {
					$result['boarding_fee'] = $fee;
					$result['boarding_fee_desc'] = $desc;
				} elseif ($_title == '走读') {
					$result['day_fee'] = $fee;
					$result['day_fee_desc'] = $desc;
				} elseif ($_title == '奖学金比例') {
					$result['scholarship_percent'] = $fee;
					$result['scholarship_desc'] = $desc;
				}
			});
		} catch (\Exception $e) {
			echo 'Not found fee information, passed.';
		}
		//学校住宿
		try {
			$node = $crawler->filter('.fa-cutlery')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$node->each(function(Crawler $c, $idx) use (&$result) {
				$_content = $c->filter('td')->eq(1)->html();
				if ($idx ==0) {
					$result['dorm_num'] = $_content;
				} elseif ($idx == 1) {
					$result['dorm_facility'] = $_content;
				} elseif ($idx == 2) {
					$result['dress_code'] = $_content;
				}
			});
		} catch (\Exception $e) {
			echo 'Not found dorm_facility information, passed.', PHP_EOL;
		}
		//教学质量
		try {
			$node = $crawler->filter('.fa-gavel')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$node->each(function(Crawler $c, $idx) use (&$result) {
				$_title = trim(strip_tags($c->filter('td')->eq(0)->html()));
				preg_match('/data-content=\"(.*?)\"/', $c->html(), $matched);
				$desc = strip_tags(htmlspecialchars_decode($matched[1]), '<br>');
				$desc = preg_replace('/<br \/>/', PHP_EOL, $desc);
				if ($_title == '班级大小') {
					//人数
					$_b = $c->filter('td')->eq(1)->html();
					$_b = str_replace('人', '', $_b);
					$result['class_size'] = $_b;
					$result['class_size_desc'] =  $desc;
				} elseif ($_title == 'SAT平均分') {
					$result['sat_avg'] = $c->filter('td')->eq(1)->html();
					$result['sat_avg_desc'] = $desc;
				} elseif ($_title == '师生比例') {
					$result['staff_stu_ratio_desc'] = $desc;
				} elseif ($_title == '教授学历') {
					$result['faculty_degree'] = $c->filter('td')->eq(1)->html();
					$result['faculty_degree_desc'] = $desc;
				}
			});
		} catch (\Exception $e) {
			echo 'Not found class information, passed.', PHP_EOL;
		}
		//年级人数构成
		try {
			$node = $crawler->filter('.fa-tasks')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$_structure = [];
			$node->each(function(Crawler $c, $idx) use (&$_structure) {
				$_tds = $c->filter('td');
				if (count($_tds) != 2) {
					return null;
				}
				$_g = $_tds->first()->html();
				preg_match('/(\d+)/', $_g, $matched);
				$_g = $matched[1];
				$_num = $_tds->last()->html();
				$_structure[$_g] = $_num;
			});
			$result['class_structure'] = serialize($_structure);
		} catch (\Exception $e) {
			echo 'Not found tasks information, passed.', PHP_EOL;
		}
		//学生构成
		try {
			$node = $crawler->filter('.fa-male')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$_structure = [];
			$node->each(function(Crawler $c, $idx) use (&$_structure) {
				$_tds = $c->filter('td');
				if (count($_tds) != 3) {
					return null;
				}
				$_i = [];
				$_tds->each(function(Crawler $bb) use (&$_i) {
					// var_dump($bb->html());
					$_i[] = $bb->html();
				});
				$_structure[] = $_i;
				//unset($_i);
			});
			$result['stu_structure'] = serialize($_structure);
		} catch (\Exception $e) {
			echo 'Not found stu_structure information, passed.', PHP_EOL;
		}
		//ap课程
		try {
			$stoped = false;
			$next_nodes = $crawler->filter('.fa-bookmark')->parents()->nextAll()->reduce(function(Crawler $n, $i)
			use (&$stoped) {
				if ($stoped) {
					return false;
				}
				if ('h4' == $n->nodeName()) {
					$stoped = true;
					return false;
				}
				return true;
			});

			$detail_nodes = $next_nodes->filter('table.mar-t-0x');
			if (count($detail_nodes) == 2) {
				$courses = [];
				$detail_nodes->first()->filter('tbody tr')->each(function(Crawler $n) use (&$courses) {
					$courses[] = $n->filter('td')->first()->html();
				});
				$link = $detail_nodes->last()->filter('a')->extract('href');
				$result['ap_courses'] = implode(',', $courses);
				$result['ap_courses_link'] = $link[0];
			} elseif (count($detail_nodes) == 1) {
				//没有列表
				if (count($next_nodes->filter('table.word-all'))) {
					$_html = trim(strip_tags($next_nodes->filter('table.word-all')->html()));
					$result['ap_courses_desc'] = $_html;
					$link = $detail_nodes->first()->filter('a')->extract('href');
					$result['ap_courses_link'] = $link[0];
				} else {
					$detail_nodes->first()->filter('tbody tr')->each(function(Crawler $n) use (&$courses) {
						$courses[] = $n->filter('td')->first()->html();
					});
					$result['ap_courses'] = implode(',', $courses);
				}
			}
		} catch (\Exception $e) {
			echo 'Not found ap_course information, passed.', PHP_EOL;
		}
		//课外活动
		try {
			$stoped = false;
			$next_nodes = $crawler->filter('.fa-lightbulb-o')->parents()->nextAll()->reduce(function(Crawler $n, $i)
			use (&$stoped) {
				if ($stoped) {
					return false;
				}
				if ('h4' == $n->nodeName()) {
					$stoped = true;
					return false;
				}
				return true;
			});
			$acts = [];
			$_cc = $next_nodes->filter('table')->first()->filter('div')->first()->extract('data-content');
			$result['extra_activity_desc'] = preg_replace('/<br \/>/', PHP_EOL, strip_tags($_cc[0], '<br>'));
			$next_nodes->filter('table.mar-t-0x')->first()->filter('tr')->each(function(Crawler $n) use(&$acts) {
				$__t = [];
				$n->filter('td')->each(function(Crawler $b) use (&$__t) {
					$__t[] = trim(str_replace(' ', '', $b->html()));
				});
				$acts[] = implode(',', array_filter($__t));
			});
			$result['extra_activity'] = implode(',', $acts);
			$link = $next_nodes->filter('table.mar-t-0x')->eq(1)->filter('a')->extract('href');
			$result['extra_activity_url'] = $link[0];
		} catch (\Exception $e) {
			echo 'Not found activity information, passed.', PHP_EOL;
		}
		$this->write($result);
	}

	public function actionExample()
	{
		$redis = Yii::$app->redis;
		while ($link = $redis->spop('s_school_list_today')) {
			$redis->sadd('s_school_list_b', $link);
		}
		//$html = $this->getHtml('http://www.findingschool.net/St-Pauls-School');
		// file_put_contents(Yii::getAlias('@app/runtime/example2.html'), $html);

	}

	public function actionAp()
	{
		// $html = file_get_contents(Yii::getAlias('@runtime/main.html'));
		// $crawler = new Crawler($html);
		// $html = $crawler->filter('#more_aplist')->html();
		// preg_match_all('/ap=\"(.*?)\".*title=\"(.*?)\"/', $html, $matched);

		// $result = array_map(null, $matched[1], $matched[2]);

		// $temp = [];
		// array_walk($result, function($item) use(&$temp) {
		// 	$temp[] = array_combine(['name', 'name_en'], $item);
		// });
		// $this->write($temp);
		// file_put_contents(Yii::getAlias('@app/migrations/data/ap_course.json'), json_encode($temp));
		$db = \Yii::$app->db;
		$data = json_decode(file_get_contents(Yii::getAlias('@app/migrations/data/ap_course.json')), true);
		echo $db->createCommand()->batchInsert('ap_courses', ['name', 'name_en'], $data)->execute();
	}

	public function actionSelector()
	{
		print CssSelector::toXPath('div#item > h4 > a');
	}

	/**
	 * Get html content
	 * @param  sring $url
	 * @return string
	 */
	protected function getHtml($url)
	{
		$agent_pool = [
			'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.93 Safari/537.36',
			'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.71 Safari/537.1 LBBROWSER',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; 360SE)',
			'Mozilla/5.0 (iPad; U; CPU OS 4_2_1 like Mac OS X; zh-cn) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8C148 Safari/6533.18.5'
		];
		$context = stream_context_create([
			'http' =>[
				'User-Agent' => $agent_pool[array_rand($agent_pool)],
				'Referer' => 'http://www.baidu.com'
			]
		]);
		return file_get_contents($url, null, $context);
	}

	protected function write($data, $append = false)
	{
		$data = is_array($data) ? print_r($data, true) : $data;
		if ($append) {
			file_put_contents(Yii::getAlias('@app/runtime/result.log'), $data, FILE_APPEND);
		} else {
			file_put_contents(Yii::getAlias('@app/runtime/result.log'), $data);
		}
	}

	protected function parse($html, $school_id)
	{
		$crawler = new Crawler($html);
		// $this->t($crawler);
		$result = [];
		$db = Yii::$app->db;
		try {
			$stoped = false;
			$_t_node = $crawler->filter('.fa-flask')->parents();
			preg_match('/\((.*)\)/', $_t_node->html(), $matched);
			$_year = $matched[1];

			$next_nodes = $_t_node->nextAll()->reduce(function(Crawler $n, $i)
			use (&$stoped) {
				if ($stoped) {
					return false;
				}
				if ('h4' == $n->nodeName()
					&& !in_array(trim($n->html()), ['文理学院', '全国性综合大学'])) {
					$stoped = true;
					return false;
				}
				if ('h4' == $n->nodeName()) {
					return false;
				}
				return true;
			});
			$ranks = [];

			$next_nodes->filter('tbody')->each(function(Crawler $n, $i) use (&$ranks, $_year, $school_id) {
				$_items = [];
				$n->filter('tr')->each(function (Crawler $t) use(&$_items, $i, $_year, $school_id) {
					$_items[] = [
						$t->filter('td')->eq(0)->html(),
						trim(strip_tags($t->filter('td')->eq(1)->html())),
						$t->filter('td')->eq(2)->html(),
						$i + 1,
						$_year,
						$school_id
					];
				});
				$ranks[] = $_items;
			});
			foreach ($ranks as $rank) {
				$db->createCommand()->batchInsert('middle_school_admit_info',
					['rank', 'school', 'admit_num', 'school_type', 'year', 'middle_school_id'],
					$rank
				)->execute();
			}
		} catch (\Exception $e) {
			echo 'Not found rankssss... information, passed.', PHP_EOL;
		}
	}

	protected function _school($html)
	{
		$crawler = new Crawler($html);
		$result = [];
		$school = new School;
		//名称
		$overview = $crawler->filter('#overview');
		$title = $overview->filter('.f-24x')->html();
		preg_match('/([^\(]+?)\((.+)\)/s', $title, $matched);
		if (count($matched) != 3) {
			throw new \Exception('parse title error');
		}
		list($result['name'], $result['name_en']) = array_slice($matched, 1, 2);
		//介绍
		$description = $crawler->filter('p.f-16x')->html();
		//去除更多,更少
		$description = preg_replace('/(<a href=\"javascript:;\"[^>]+?>.*?<\/a>)/', '', $description);
		$result['description'] = trim(strip_tags($description, '<br>'));
		//基本概况
		$key_map = [
			'网站' => 'website', '建校' => 'found_at', '类型' => 'school_type', '年级' => 'grade',
			'国际学生比例' => 'is_percent', '地址' => 'address', '所在州' => 'state',
			'在校人数' => 'school_enrollments', '面积' => 'acreage', '校友捐赠' => 'endowment',
			'综合申请录取率' => 'admission_rate', '教育认证和会员' => 'certification'
		];
		$tbody = $crawler->filter('i.fa-list-alt')->parents()->siblings()->html();//tbody
		preg_match_all('/<td>(.*?)<\/td>/is', $tbody, $matchted);
		$items = array_chunk($matchted[1], 2);

		foreach ($items as $item) {
			list($_key, $_val) = $item;
			$_key = $key_map[$_key];

			if ($_key == 'state') {
				preg_match('/href=\"\/state\/(.+?)\"/i', $_val, $matchted);
				$result['state_en'] = $matchted[1];
			}
			if (in_array($_key, ['website', 'address', 'state', 'certification'])) {
				$_val = strip_tags($_val);
			}
			if ($_key == 'certification') {
				$_val = preg_replace('/\s/', '', $_val);
			}
			$result[$_key] = trim($_val);
		}
		try {
			//指标排名
			$tbody = $crawler->filter('i.fa-list-ol')->parents()->nextAll()->first();
			// $node = $crawler->filter('.fa-money')->parents()->nextAll()->first();
			// $tbody = $tbody->filter('table')->last();
			$spans = $tbody->filter('span.label');
			//学费
			$node = $spans->eq(0);
			$result['rank_tuition_rank'] = str_replace('名', '', $node->html());
			$__pre = $node->siblings()->html();
			preg_match('/\((.*)\)/', $__pre, $matched);
			if (isset($matched[1])) {
				$result['rank_tuition_info'] = $matched[1];
			}
			//在校学生排名
			$result['rank_enrollment_rank'] = str_replace('名', '', $spans->eq(1)->html());
			//校园面积
			$result['rank_acreage_rank'] = str_replace('名', '', $spans->eq(2)->html());
			//ap课程数
			$node = $spans->eq(3);
			$result['rank_ap_course_num_rank'] = str_replace('名', '', $node->html());
			$__pre = $node->siblings()->html();
			preg_match('/(\d+)/', $__pre, $matched);
			if (isset($matched[1])) {
				$result['rank_ap_course_num'] = $matched[1];
			}
			//国际生比例
			$result['rank_is_percent_rank'] = str_replace('名', '', $spans->eq(4)->html());
			//师承比例
			$node = $spans->eq(5);
			$result['rank_staff_stu_ratio_rank'] = str_replace('名', '', $node->html());
			$__pre = $node->siblings()->html();
			preg_match('/\((.*)\)/', $__pre, $matched);
			if (isset($matched[1])) {
				$result['rank_staff_stu_ratio'] = $matched[1];
			}
		} catch (\Exception $e) {
			echo 'rank info not found', PHP_EOL;
		}

		//申请信息
		$node = $crawler->filter('div.well.alert-info.mar-t10x');
		try {
			$_codes = [];
			$node->filter('.fa-question-circle')->each(function(Crawler $n) use (&$_codes) {
				$__s = trim($n->parents()->text());
				preg_match('/([a-zA-Z]+)\s+(\d+)/', $__s, $matched);
				if (count($matched) == 3) {
					$_codes[$matched[1]] = $matched[2];
				}
			});
			if ($_codes) {
				$result['send_points_code'] = serialize($_codes);
			}
		} catch (\Exception $e) {
			echo 'no found application information', PHP_EOL;
		}

		$__html = $node->html();
		preg_match('/申请日期: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['application_date'] = $matched[1];
		}
		preg_match('/SSAT: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['need_ssat'] = $matched[1] == 'Yes' ? 1 : 0;
		}
		preg_match('/SSAT平均百分比: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['ssat_percent'] = $matched[1];
		}
		preg_match('/ESL: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['provide_esl'] = $matched[1] == '不提供' ? 0 : 1;
		}
		preg_match('/申请材料: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			preg_match('/href=\"(.*?)\"/', $matched[1], $matched);
			if (isset($matched[1])) {
				$result['application_material_url'] = $matched[1];
			}
		}
		preg_match('/常见问题: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			preg_match('/href=\"(.*?)\"/', $matched[1], $matched);
			if (isset($matched[1])) {
				$result['application_qa_url'] = $matched[1];
			}
		}
		preg_match('/要求英语测试: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['english_tests'] = $matched[1];
		}
		preg_match('/邮件: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['email'] = strip_tags($matched[1]);
		}
		preg_match('/电话: (.*)<\/p>/', $__html, $matched);
		if (isset($matched[1])) {
			$result['telphone'] = $matched[1];
		}
		//SAT成绩
		try {
			$node = $crawler->filter('.fa-bell')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$__sat = [];
			$node->each(function(Crawler $c) use (&$__sat) {
				$__tr = $c->html();
				preg_match_all('/<td>(\d+)<\/td>/', $__tr, $matched);
				$__sat[] = $matched[1];
			});
			$result['sat_score'] = serialize($__sat);
		} catch (\Exception $e) {
			echo 'Not found sat information, passed.', PHP_EOL;
		}
		//费用以及奖学金
		try {
			$node = $crawler->filter('.fa-money')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$node->each(function(Crawler $c, $idx) use (&$result) {
				$_title = trim(strip_tags($c->filter('td')->eq(0)->html()));
				$__tr = $c->html();
				$fee = $c->filter('td')->eq(1)->html();
				if ($_title != '奖学金比例') {
					$fee = str_replace('.', '', str_replace(',', '', str_replace('$', '', $fee)));
				}
				//parse desc
				preg_match('/data-content=\"(.*?)\"/', $__tr, $matched);
				$desc = strip_tags(htmlspecialchars_decode($matched[1]), '<br>');
				$desc = preg_replace('/<br \/>/', PHP_EOL, $desc);
				if ($_title == '寄宿') {
					$result['boarding_fee'] = $fee;
					$result['boarding_fee_desc'] = $desc;
				} elseif ($_title == '走读') {
					$result['day_fee'] = $fee;
					$result['day_fee_desc'] = $desc;
				} elseif ($_title == '奖学金比例') {
					$result['scholarship_percent'] = $fee;
					$result['scholarship_desc'] = $desc;
				}
			});
		} catch (\Exception $e) {
			echo 'Not found fee information, passed.';
		}
		//学校住宿
		try {
			$node = $crawler->filter('.fa-cutlery')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$node->each(function(Crawler $c, $idx) use (&$result) {
				$_content = $c->filter('td')->eq(1)->html();
				if ($idx ==0) {
					$result['dorm_num'] = $_content;
				} elseif ($idx == 1) {
					$result['dorm_facility'] = $_content;
				} elseif ($idx == 2) {
					$result['dress_code'] = $_content;
				}
			});
		} catch (\Exception $e) {
			echo 'Not found dorm_facility information, passed.', PHP_EOL;
		}
		//教学质量
		try {
			$node = $crawler->filter('.fa-gavel')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$node->each(function(Crawler $c, $idx) use (&$result) {
				$_title = trim(strip_tags($c->filter('td')->eq(0)->html()));
				preg_match('/data-content=\"(.*?)\"/', $c->html(), $matched);
				$desc = strip_tags(htmlspecialchars_decode($matched[1]), '<br>');
				$desc = preg_replace('/<br \/>/', PHP_EOL, $desc);
				if ($_title == '班级大小') {
					//人数
					$_b = $c->filter('td')->eq(1)->html();
					$_b = str_replace('人', '', $_b);
					$result['class_size'] = $_b;
					$result['class_size_desc'] =  $desc;
				} elseif ($_title == 'SAT平均分') {
					$result['sat_avg'] = $c->filter('td')->eq(1)->html();
					$result['sat_avg_desc'] = $desc;
				} elseif ($_title == '师生比例') {
					$result['staff_stu_ratio_desc'] = $desc;
				} elseif ($_title == '教授学历') {
					$result['faculty_degree'] = $c->filter('td')->eq(1)->html();
					$result['faculty_degree_desc'] = $desc;
				}
			});
		} catch (\Exception $e) {
			echo 'Not found class information, passed.', PHP_EOL;
		}
		//年级人数构成
		try {
			$node = $crawler->filter('.fa-tasks')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$_structure = [];
			$node->each(function(Crawler $c, $idx) use (&$_structure) {
				$_tds = $c->filter('td');
				if (count($_tds) != 2) {
					return null;
				}
				$_g = $_tds->first()->html();
				preg_match('/(\d+)/', $_g, $matched);
				$_g = $matched[1];
				$_num = $_tds->last()->html();
				$_structure[$_g] = $_num;
			});
			$result['class_structure'] = serialize($_structure);
		} catch (\Exception $e) {
			echo 'Not found tasks information, passed.', PHP_EOL;
		}
		//学生构成
		try {
			$node = $crawler->filter('.fa-male')->parents()->nextAll()->first();
			$node = $node->filter('tbody tr');
			$_structure = [];
			$node->each(function(Crawler $c, $idx) use (&$_structure) {
				$_tds = $c->filter('td');
				if (count($_tds) != 3) {
					return null;
				}
				$_i = [];
				$_tds->each(function(Crawler $bb) use (&$_i) {
					// var_dump($bb->html());
					$_i[] = $bb->html();
				});
				$_structure[] = $_i;
				//unset($_i);
			});
			$result['stu_structure'] = serialize($_structure);
		} catch (\Exception $e) {
			echo 'Not found stu_structure information, passed.', PHP_EOL;
		}
		//ap课程
		try {
			$stoped = false;
			$next_nodes = $crawler->filter('.fa-bookmark')->parents()->nextAll()->reduce(function(Crawler $n, $i)
			use (&$stoped) {
				if ($stoped) {
					return false;
				}
				if ('h4' == $n->nodeName()) {
					$stoped = true;
					return false;
				}
				return true;
			});

			$detail_nodes = $next_nodes->filter('table.mar-t-0x');
			if (count($detail_nodes) == 2) {
				$courses = [];
				$detail_nodes->first()->filter('tbody tr')->each(function(Crawler $n) use (&$courses) {
					$courses[] = $n->filter('td')->first()->html();
				});
				$link = $detail_nodes->last()->filter('a')->extract('href');
				$result['ap_courses'] = implode(',', $courses);
				$result['ap_courses_link'] = $link[0];
			} elseif (count($detail_nodes) == 1) {
				//没有列表
				if (count($next_nodes->filter('table.word-all'))) {
					$_html = trim(strip_tags($next_nodes->filter('table.word-all')->html()));
					$result['ap_courses_desc'] = $_html;
					$link = $detail_nodes->first()->filter('a')->extract('href');
					$result['ap_courses_link'] = $link[0];
				} else {
					$detail_nodes->first()->filter('tbody tr')->each(function(Crawler $n) use (&$courses) {
						$courses[] = $n->filter('td')->first()->html();
					});
					$result['ap_courses'] = implode(',', $courses);
				}
			}
		} catch (\Exception $e) {
			echo 'Not found ap_course information, passed.', PHP_EOL;
		}
		//课外活动
		try {
			$stoped = false;
			$next_nodes = $crawler->filter('.fa-lightbulb-o')->parents()->nextAll()->reduce(function(Crawler $n, $i)
			use (&$stoped) {
				if ($stoped) {
					return false;
				}
				if ('h4' == $n->nodeName()) {
					$stoped = true;
					return false;
				}
				return true;
			});
			$acts = [];
			$_cc = $next_nodes->filter('table')->first()->filter('div')->first()->extract('data-content');
			$result['extra_activity_desc'] = preg_replace('/<br \/>/', PHP_EOL, strip_tags($_cc[0], '<br>'));
			$next_nodes->filter('table.mar-t-0x')->first()->filter('tr')->each(function(Crawler $n) use(&$acts) {
				$__t = [];
				$n->filter('td')->each(function(Crawler $b) use (&$__t) {
					$__t[] = trim(str_replace(' ', '', $b->html()));
				});
				$acts[] = implode(',', array_filter($__t));
			});
			$result['extra_activity'] = implode(',', $acts);
			$link = $next_nodes->filter('table.mar-t-0x')->eq(1)->filter('a')->extract('href');
			$result['extra_activity_url'] = $link[0];
		} catch (\Exception $e) {
			echo 'Not found activity information, passed.', PHP_EOL;
		}


		$school->setAttributes($result, false);
		if ($school->insert()) {
			$this->parse($html, $school->id);
		} else {
			var_dump($school->getErrors());
			throw new \Exception('insert error');
		}
		return $school->id;
	}
}