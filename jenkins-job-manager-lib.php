<?php
/*
Plugin Name: Jenkins Job Manager Wordpress
Plugin URI: 
Description: Jenkins Job Manager Wordpress
Version: 1.0
Author: MD. Joynal Abedin Parag
Author URI: 
License: GPL2
*/

// date_default_timezone_set(UTT_TIMEZONE);

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once(__DIR__ . '/jenkins-helper.php');
require_once(__DIR__ . '/recipient-editor.php');

Class UTTPluginLib
{
	public $process_id, $process_list_table = "wp_process_list";

	public $result_dir_name, $result_dir, $result_dir_url;

	public $execution_status = array("in_progress" => 0, "success" => 1, "failed" => 2, "scheduled" => 3, "in_queue" => 4, "aborted" => 5);

	public $jenkins_status_map =  array(0 => "In Progress", 1 => "SUCCESS", 2 => "FAILED", 3 => "Scheduled", 4 => "In Queue", 5 => "Aborted");

	public $schedule_hook_name = 'schedule_build_job_hook';

	public $csv_file_url, $log_file_path;

	public $current_time, $current_user;

	public $table_name;

	public $instance;

	public $jenkins;

	public $api_page_name = "api";

	public $notification_msg_prefix;

	private $schema_class_map = array(
		"wp_brokenlink_dev" => array("BrokenLinkChecker", "0brokenlink-checker-dev-psr4/broken-link-checker-dev.php"), // className, file_location
		"wp_misspelling_dev" => array("MisSpellingChecker", "0misspelling-checker-dev/misspelling-checker-dev.php"), // className, file_location
		"wp_image_dev" => array("ImageQualityChecker", "0image-checker-dev/image-checker-dev.php"), // className, file_location
		"wp_consistency_dev" => array("ConsistencyChecker", "0consistency-checker-dev/consistency-checker-dev.php") // className, file_location
	);

	public $downstream_project_list = array(
		"Misspelling Checker" => array(
			"class" => "MisSpellingChecker",
			"related_schema" => "wp_misspelling_dev",
			"plugin_uri" => "0misspelling-checker-dev/misspelling-checker-dev.php",
			"page_id" => 321
		),
		"Image Quality Checker" => array(
			"class" => "ImageQualityChecker",
			"related_schema" => "wp_image_dev",
			"plugin_uri" => "0image-checker-dev/image-checker-dev.php",
			"page_id" => 1763
		),
		"Consistency Checker" => array(
			"class" => "ConsistencyChecker",
			"related_schema" => "wp_consistency_dev",
			"plugin_uri" => "0consistency-checker-dev/consistency-checker-dev.php",
			"page_id" => 1926
		)
	);

	function __construct($obj)
	{
		global $wpdb;
		$this->db = $wpdb;
		$this->charset_collate = $this->db->get_charset_collate();
		
		$root_url = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '';
		
		$this->result_dir = $_SERVER['DOCUMENT_ROOT'] . "/result/";
		$this->result_dir_url = $root_url . "/result/";

		if((is_object($obj) && property_exists($obj, 'table_name'))) :
			$this->result_dir = $_SERVER['DOCUMENT_ROOT'] . "/result/{$obj->table_name}/";
			$this->result_dir_url = $root_url . "/result/{$obj->table_name}/";
		endif;

		// date_default_timezone_set(UTT_TIMEZONE);
		$this->current_time = date('Y-m-d H:i:s');
		$this->current_user = wp_get_current_user();

		$this->defineActions();
		$this->setupAjaxHandlers();
		// $this->instance = $obj; /* Optional! can be removed. Must be placed at the end of constructor */
		$this->jenkins = new JenkinsHelper();
		$this->recipient_editor = new recipientEditor($this);

        $this->initializeNotificationApiShortcode();
	}

	function setProcessId() 
	{
		$this->process_id = uniqid(); /* uniqid() generates 13 digit alphanumeric characters every time */
	}

	function activationDeactivationHook()
	{
		register_activation_hook(__FILE__, array($this, 'createApiPageNtableNshortcode'));
		register_deactivation_hook(__FILE__, array( $this, 'deactivateLib') );
	}

	function deactivateLib()
	{
		if(wp_style_is('utt_lib_style', $list='registered')) {
			wp_deregister_style('utt_lib_style');
		}
		if(wp_style_is('utt_lib_tab_style', $list='registered')) {
			wp_deregister_style('utt_lib_tab_style');
		}
		if(wp_script_is('utt_lib_tab_script', $list='registered')) {
			wp_deregister_script('utt_lib_tab_script');
		}
		if(wp_style_is('utt_lib_style', $list='enqueued')) {
			wp_dequeue_style('utt_lib_style');
		}
		if(wp_style_is('utt_lib_tab_style', $list='enqueued')) {
			wp_dequeue_style('utt_lib_tab_style');
		}
		if(wp_script_is('utt_lib_tab_script', $list='enqueued')) {
			wp_dequeue_script('utt_lib_tab_script');
		}
		if(wp_script_is('jquery', $list='enqueued')) {
			wp_dequeue_script('jquery');
		}
	}

	function createApiPageNtableNshortcode()
	{
		$this->createDbTable();
		$check_page_exist = get_page_by_title($this->api_page_name, 'OBJECT', 'page');
		if($check_page_exist):
			wp_delete_post( $check_page_exist->ID, true );
		endif;
		$page_id = wp_insert_post(
			array(
				'comment_status' => 'close',
				'ping_status'    => 'close',
				'post_author'    => 1,
				'post_title'     => ucwords($this->api_page_name),
				'post_name'      => $this->api_page_name,
				'post_status'    => 'publish',
				'post_content'   => "[$this->api_page_name]",
				'post_type'      => 'page',
			)
		);
	}

	function createDbTable()
	{
		$columns = array('id' => 'INT(11) NOT NULL AUTO_INCREMENT',
						'job_id' => 'INT(11) NOT NULL',
						'process_id' => 'VARCHAR(20) NOT NULL',
						'related_schema' => 'VARCHAR(50) NOT NULL',
						'queue_id' => 'INT(20) NULL DEFAULT NULL',
						'build_id' => 'INT(20) NULL DEFAULT NULL',
						'complete' => "BOOLEAN NOT NULL DEFAULT {$this->execution_status['success']}",
						'date' => "DATETIME NULL"); /*@TODO: Automate */
		$this->createTableNrestoreData($this->process_list_table, $columns);
	}

	function registerScript()
	{
		wp_register_style('utt_lib_style', plugins_url('css/style.css', __FILE__));	
		wp_register_script('utt_lib_script', plugins_url('js/main.js', __FILE__));
		wp_register_style('utt_lib_tab_style', plugins_url('css/tab.css', __FILE__));	
		wp_register_script('utt_lib_tab_script', plugins_url('js/tab.js', __FILE__));
		if ( ! wp_script_is('jquery', 'enqueued')) {
			wp_enqueue_script('jquery');
		}
	}

	function enqueueScripts()
	{	
		/* @TODO: Enque only when this plugin is called */
		// wp_enqueue_script('slick_jquery', plugins_url( 'js/jquery-1.11.0.js', __FILE__) );
		wp_enqueue_style('utt_lib_style');
		wp_enqueue_script('utt_lib_script');
		wp_enqueue_style('utt_lib_tab_style');
		wp_enqueue_script('utt_lib_tab_script');
		wp_enqueue_style('slick_theme_css', 'http://kenwheeler.github.io/slick/slick/slick-theme.css');
		wp_enqueue_style('slick_css', plugins_url('js/slick/slick.css', __FILE__) );
		wp_enqueue_script('slick_script', plugins_url( 'js/slick/slick.js', __FILE__) );
		
		// wp_enqueue_script('jquery_res', 'https://code.jquery.com/jquery-1.12.4.js');
		wp_enqueue_script('jquery_ui_script', 'https://code.jquery.com/ui/1.12.1/jquery-ui.js');
		wp_enqueue_style('jquery_ui_css', 'http://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
	}

	public function initializeNotificationApiShortcode()
    {
        add_shortcode($this->api_page_name, array($this, 'apiRouter'));
	}
	
	public function redirectGuestUsers()
	{
        if(!is_user_logged_in() && !is_page(array('Log In', 'Api', 'Introduction', 'About', 'pwdChangeNoti'))) {
            // wp_redirect( wp_login_url(get_permalink()));
            wp_redirect( wp_login_url($_SERVER['REQUEST_URI']));
            exit;
        }
    }

	public function defineActions() 
	{
		add_action( 'init', array($this, 'registerScript'));
		add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));
		add_action('template_redirect', array($this, 'redirectGuestUsers'));
		add_action('phpmailer_init', array($this, 'send_smtp_email'));
		add_filter('cron_schedules', array($this, 'addCronSchedules'));
		add_action ("schedule_build_job_hook", array($this, 'buildScheduledJob'), 10, 1);
		add_action ("wp_mail_failed", array($this, 'schedule_build_job_hook'), 10, 1);

	}

	function action_wp_mail_failed($wp_error) 
	{
		file_put_contents('/var/www/html/wp-content/abcd3.txt', print_r($wp_error, true));
		return error_log(print_r($wp_error, true));
	}

	public function load_page_template()
	{
		$page_template = dirname( __FILE__ ) . '/page-templates/template-full-width-stretched-right-sidebar.php';
    	return $page_template;
	}

	function setupAjaxHandlers()
	{
		add_action('wp_ajax_get_jenkins_console_text', array( $this, 'getJenkinsConsoleText' ));		
		add_action('wp_ajax_nopriv_get_jenkins_console_text', array( $this, 'getJenkinsConsoleText' ));

		add_action( 'wp_ajax_check_process_status_bl', array($this, 'getProcessListData'));
		add_action( 'wp_ajax_nopriv_check_process_status_bl',  array($this, 'getProcessListData'));

		add_action( 'wp_ajax_cancel_build_schedule', array($this, 'cancelBuildSchedule'));
		add_action( 'wp_ajax_nopriv_cancel_build_schedule',  array($this, 'cancelBuildSchedule'));

		add_action( 'wp_ajax_get_schedule_editor', array($this, 'getScheduleEditor'));
		add_action( 'wp_ajax_nopriv_get_schedule_editor',  array($this, 'getScheduleEditor'));

		add_action( 'wp_ajax_modify_schedule', array($this, 'modifySchedule'));
		add_action( 'wp_ajax_nopriv_modify_schedule',  array($this, 'modifySchedule'));

		add_action( 'wp_ajax_get_help_text', array($this, 'getHelpText' ));
		add_action( 'wp_ajax_nopriv_get_help_text', array($this, 'getHelpText' ));

		add_action( 'wp_ajax_abort_current_build', array($this, 'abortCurrentBuild'));
		add_action( 'wp_ajax_nopriv_abort_current_build',  array($this, 'abortCurrentBuild'));
	}

	function getHelpText()
	{
		$help_json_path = dirname(__FILE__) . "/help-text-dict/help.json";
		$result_str = "";
		if(file_exists($help_json_path)) {
			$help_json = file_get_contents($help_json_path);
			$help_array = json_decode($help_json, true);
			$result_str = json_encode($help_array);
		}
		echo $result_str;
		wp_die();
	}

	function addCronSchedules($schedules) 
	{
		if(!isset($schedules["15min"])){
			$schedules["15min"] = array(
				'interval' => 15*60,
				'display' => __('Once every 15 minutes'));
		}
		if(!isset($schedules["30min"])){
			$schedules["30min"] = array(
				'interval' => 30*60,
				'display' => __('Once every 30 minutes'));
		}
		if(!isset($schedules["weekly"])){
			$schedules["weekly"] = array(
				'interval' => 7*86400,
				'display' => __('Once every week'));
		}
		return $schedules;
	}

	function send_smtp_email( $phpmailer )
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "account_pwd";
		$secret = DB_SECRET_KEY;
		$account = 'knox';
		$result = $wpdb->get_results( "SELECT user, aes_decrypt(password, '{$secret}') as password FROM $table_name WHERE `account` = '{$account}' LIMIT 1");
		// print_r ($result[0]->user);
		$phpmailer->isSMTP();
		$phpmailer->Host	= SMTP_HOST;
		$phpmailer->SMTPAuth = SMTP_AUTH;
		$phpmailer->Port = SMTP_PORT;
		$phpmailer->Username = $result[0]->user;
		$phpmailer->Password = $result[0]->password;
		$phpmailer->SMTPSecure = SMTP_SECURE;
		$phpmailer->From = SMTP_FROM;
		$phpmailer->FromName = SMTP_Name;
	}

	public function checkDirectoriesNPermissions():bool 
	{
		$error = null;
		if(!is_dir($this->result_dir)) {
			mkdir($this->result_dir);
			chmod($this->result_dir, 0775);
		}
		if (!is_writable($this->result_dir)) {
			$error = "The Result directory does not have write permission ! You will not be able to see log later !!";
		}
		echo (!is_null($error)) ? "<div class='alert alert-warning'><i class='fa fa-exclamation'></i> $error </div>" : "";
		return is_null($error) ? true : false;
	}

	public function createFile($location, $filename = null, $content = null) 
	{
		if(!is_dir($location)) {
			mkdir($location);
			chmod($location, 0775);
		}
		if(!is_null($content) && !is_null($filename)) {  
			file_put_contents("{$location}/{$filename}", $content);
		}
	}

	public function getClientInfoView(array $data):string 
	{
		return $this->view('views/primary-form-client-info', $data);
	}

	public function getFormComponents($components) 
	{
		$data = array("components" => $components);
		return $this->view('views/form-components', $data);
	}

	public function apiRouter() 
	{
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : false;
		$payload = file_get_contents("php://input");
		$response = [];
		$response['callback'] = false;
		$build_id = null;
		switch ($action) {
			case "updateBuildID" :
				$this->updateBuildID($payload);
			break;
			case "updateBuildStatus" :
				$this->updateBuildStatus($payload);
			break;
			default:
				echo json_encode(array("status" => false, "msg" => "No Action Found"));
			break;
		}
		return $response;
	}

	function getDataJson($json_dir_path)
	{
		if(file_exists("{$json_dir_path}/data.json")) {
			$data_json = @file_get_contents("{$json_dir_path}/data.json");
			return $data_json;
		}
		return False;
	}

	// Build Schedule Job
	function buildScheduledJob($data)
    {
		$queue_id = $this->jenkins->buildJob($data['command'], $data['table_name'], $data['process_id']);
        $update_process_list = $this->db->update(
            $data['process_list_table'],
            array(
                'queue_id' => $queue_id,
                'complete' => $this->execution_status['in_queue'],
			),
			array('process_id' => $data['process_id'])
		);
		$hook = $data['hook_name'];
		
		if($data['sch_recur'] != 'once') {
			$next_scheduled_run = wp_next_scheduled($hook, array($data));
			if($next_scheduled_run) {
				wp_unschedule_event($next_scheduled_run, $hook, array($data));
				$dir = "{$this->result_dir}{$data['table_name']}/{$data['process_id']}";
				$data_json = $this->getDataJson($dir);
				if($data_json) {
					$data_json_arr = json_decode($data_json, true);
					$data['prev_sch_permalink'] = isset($data_json_arr['permalink']) ? $data_json_arr['permalink'] : '';
					$data['prev_email_recipients'] = isset($data_json_arr['email_recipients']) ? serialize($data_json_arr['email_recipients']) : '';
					$data['prev_target_url'] = isset($data_json_arr['prev_target_url']) ? $data_json_arr['prev_target_url'] : '';
				}
				$data['next_run'] = $next_scheduled_run;
				$data['prev_sch_process_id'] = $data['process_id'];
				$ins_associated_plugin = $this->getAssociatedPluginInstance($data['table_name']);
				$ins_associated_plugin->primaryForm($data);
			}
		}
	}

	function updateBuildID($payload)
	{
		if($payload) {
			$build_data = json_decode($payload)->build;
			$queue_id = $build_data->queue_id;
			$build_id = $build_data->number;
			$related_schema = $build_data->parameters->plugin;
			$process_id = $build_data->parameters->process_id;
			$update = $this->db->update(
				$this->process_list_table,
				array (
					'build_id' => $build_id,
					'complete' => $this->execution_status['in_progress'],
					'date' => $this->current_time
				),
				array('related_schema' => $related_schema, 'queue_id' => $queue_id, 'process_id' => $process_id)
			);
		} else {
			$this->Notify("You do not have permission to access this page !!");
		}
	}

	function updateBuildStatus($payload)
	{
		if($payload) {
			$error = [];
			$build_data = json_decode($payload)->build;
			$build_id = $build_data->number;
			$status = $build_data->status == "SUCCESS" ? $this->execution_status['success'] : $this->execution_status['failed'];
			$related_schema = $build_data->parameters->plugin;
			$process_id = $build_data->parameters->process_id;

			$sql_job_id = "SELECT plt.`job_id`, plt.`related_schema`, plt.`process_id`, t.* FROM {$this->process_list_table} plt 
						LEFT JOIN {$related_schema} t ON t.`id` = plt.`job_id`
						WHERE plt.`related_schema` = '{$related_schema}' 
								AND plt.`build_id` = '{$build_id}' 
								AND plt.`complete` = '{$this->execution_status['in_progress']}'
								AND plt.`process_id` = '{$process_id}'";
			$result_job_id = $this->db->get_row($sql_job_id);
			
			$dir = "{$this->result_dir}{$related_schema}/{$process_id}";

			if(!empty($result_job_id)) {
				$job_id = $result_job_id->job_id;
				$process_id = $result_job_id->process_id;
				$result_file_url = $result_job_id->result_url;
				$email_login = $result_job_id->email;
				$path_parts = pathinfo($result_file_url);
				$result_file_name = $path_parts['filename'] . '.' .$path_parts['extension'];
				$log_file_name = $path_parts['filename'] . ".log";

				$update = $this->db->update (
					$this->process_list_table,
					array (
						'complete' => $status,
					),
					array('job_id' => $job_id)
				);

				$console_text = $this->jenkins->getFullConsoleText($build_id);
				$this->createFile($dir, $log_file_name, $console_text);
			
				$ins_associated_plugin = $this->getAssociatedPluginInstance($related_schema);
				$ins_associated_plugin->onJobCompletion($job_id, $build_id);
				$attachments = array();
				if(filesize("{$dir}/{$log_file_name}")*10**-6 <= 10){
					array_push($attachments, "{$dir}/{$log_file_name}");
				}
				if(filesize("{$dir}/{$result_file_name}")*10**-6 <= 10){
					array_push($attachments, "{$dir}/{$result_file_name}");
				}
				// $attachments = array("{$dir}/{$log_file_name}", "{$dir}/{$result_file_name}");
				$data_json = $this->getDataJson($dir);
				if($data_json) {
					$data_json_arr = json_decode($data_json, true);
					
					$email = $data_json_arr['email_recipients'];
					
					
					if(isset($email) && !empty($email) && $status == $this->execution_status['success']) {
						// $result_dir = "{$this->result_dir}{$related_schema}/";
						// file_put_contents('/var/www/html/wp-content/abcd.txt', print_r($result_dir, true));
						$this->mailResults($ins_associated_plugin, $data_json_arr, $attachments, $console_text, $result_job_id, $status);
						
					} else {
						$error[] = "Emails did not exist on data.json file! Could not send email.";
					}

					// ==========================================================
					$down_project = json_decode($data_json, true)['downstream_project'];
					if(isset($down_project) && !empty($down_project)){
						$data = [];
						$data['target_urls'] = explode('/', $result_file_url, 5)[4];
						$data['prev_target_url'] = $result_job_id->target_url;
						$data['prev_email_recipients'] = isset($email) ? serialize($email) : '';
						$data['time'] = $this->current_time;
						$data['knox_id'] = $result_job_id->knox_id;
						$data['email'] = $email_login;
						$data['full_name'] = $result_job_id->full_name;
						$data['employee_number'] = $result_job_id->employee_number;
						$data['department_name'] = $result_job_id->department_name;
						$data['user_information'] = $result_job_id->user_information;
						
						$plugins = explode(",", $down_project);
						// file_put_contents('/var/www/html/wp-content/abcd2.txt', print_r($plugins,true));
						foreach ($plugins as $dp){
							$dp = trim($dp);
							if($dp){
								$data['prev_sch_permalink'] = get_permalink($this->downstream_project_list[$dp]['page_id']);
								$data['prev_sch_process_id'] = $process_id;
								$data['triggered_by_parent'] = TRUE;
								// file_put_contents('/var/www/html/wp-content/abcd.txt', print_r($data,true));
								$schema = $this->downstream_project_list[$dp]['related_schema'];
								$ins_associated_plugin = $this->getAssociatedPluginInstance($schema);
								$ins_associated_plugin->primaryForm($data);
							}
							
						} 
						
					}
					// ==========================================================
				} else {
					$error[] = "data.json file does not exist! Could not send email.";
				}
			} else {
				$error[] = "Error While Executing Following query :  \n" . $sql_job_id;
			}
			if(!empty($error)) {
				$error_content = "Error while updating build staus: \n " . implode("\n", $error);
				$this->createFile("{$dir}", "error.log", $error_content);
			}
		} else {
			$this->Notify("You do not have permission to access this page !!");
		}
	}

	public function modifySchedule(){
		$crons = _get_cron_array();
		$sch = $crons[$_POST['stime']][$this->schedule_hook_name][$_POST['sig']];
		
		// console.log($_POST['nstime']);
		// console.log($_POST['srecur']);
		$response = [];
		if (isset($sch)) {
			$args = $sch['args'];
			// $response['sch'] = $sch;
			$sch_datetime = strtotime($_POST['nstime']);
			// $response['time'] = $_POST['stime'];
			wp_unschedule_event( $_POST['stime'], $this->schedule_hook_name, $args );
			$args[0]['sch_recur']=$_POST['srecur'];
			$args[0]['sch_date'] = substr($_POST['nstime'],0,10);
			$args[0]['sch_time'] = substr($_POST['nstime'],11);
			$res = wp_schedule_event($sch_datetime, $_POST['srecur'], $this->schedule_hook_name, $args);
			// $response['res'] = $res;
			// if($res){
				if(isset($_POST['process_id'])) {
					$update = $this->db->update(
						$this->process_list_table,
						array (
							'date' => $_POST['nstime']
						),
						array('process_id' => $_POST['process_id'])
					);
					$dir = "{$this->result_dir}{$_POST['related_schema']}/{$_POST['process_id']}";
					// $response['dir'] = $dir;
					$data_json = $this->getDataJson($dir);
					$data = json_decode($data_json, true);
					$data['scheduled_time'] = $sch_datetime;
					file_put_contents($dir.'/data.json', json_encode($data));
					// $response['data'] = $data;
				}
				$crons = _get_cron_array();
				$response['status'] = true;
				$response['msg'] = "<i class='fa fa-check-circle'></i> Build schedule modified successfully";
				$cron_list_hook_data = isset($crons[$sch_datetime][$this->schedule_hook_name]) ? $crons[$sch_datetime][$this->schedule_hook_name] : false;
				$response['scheduled_time'] = $sch_datetime;
				$response['_sig'] = $cron_list_hook_data;
				$response['sig'] = isset($cron_list_hook_data) && !empty($cron_list_hook_data) ? array_keys(array_slice($cron_list_hook_data, 0, 1, TRUE))[0] : null;
				
				// }else{
			// 	$response['status'] = false;
			// 	$response['msg'] = "<i class='fa fa-warning'></i> Couldn't modify build schedule";
			// }
		}else{
			$response['status'] = false;
			$response['msg'] = "<i class='fa fa-warning'></i> Couldn't find build schedule"; 
		}
		echo json_encode($response);
        wp_die();
	}

	public function getScheduleEditor(){
		$dt = date('Y-m-d H:i', $_POST['stime']);
		$modal = "
            <div class='modal' id='scheduleEditorModal' tabindex='-1' role='dialog' aria-labelledby='scheduleEditorLabel' aria-hidden='true'>
                <div class='modal-dialog' role='document'>
                    <div class='modal-content'>
                    <div class='modal-header'>
                        <h5 class='modal-title' id='recipientEditorLabel'>Modify Build Schedule</h5>
                        <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                        <span aria-hidden='true'>&times;</span>
                        </button>
                    </div>
                    <div class='modal-body'>
                        <div id='er_msg' class='alert alert-success hide'>Message</div>
                        <div class='form-row'>
							<div class='row'>
								<div class='col-4' style='padding-right:0px;'>
									Date
									<input type='date' id='sch_date' name='sch_date' />
								</div>
								<div class='col-4' style='padding-right:0px;'>
									Time
									<input type='time' id='sch_time' name='sch_time' />
								</div>
								<div class='col-4'>
									Recurrence<br>
									<select name='sch_recur' id='sch_recur'>
										<option value='once'> Once </option>
										<option value='15min'> 15 Min </option>
										<option value='hourly'> Hourly </option>
										<option value='daily'> Daily </option>
										<option value='weekly'> Weekly </option>
									</select>
								</div>
							</div>
        				</div>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-secondary' data-dismiss='modal'>Close</button>
                        <button type='button' id='sch_save_btn' class='btn btn-info' data-toggle='tooltip' title='Save (Alt+Enter)'>Save changes</button>
                    </div>
                    </div>
                </div>
            </div>
        ";
        
		$crons = _get_cron_array();
		$sch = $crons[$_POST['stime']][$this->schedule_hook_name][$_POST['sig']];
		$schObj = $sch['args'][0];
        $response = [];
        // $response['email_recipients'] = $email_recipients;
        $response['modal'] = $modal;
		$response['sch_recur'] = $schObj['sch_recur'];
		$response['sch_date'] = substr($dt, 0, 10);
		$response['sch_time'] = substr($dt, 11);
		// console.log(response)
		// $response['dt'] = $dt;
		// $response['dt_time'] = $dt_time;
		
        // $response['er_li_empty'] = $er_li_empty;
        // $response['er_li_basic'] = $er_li_basic;

        echo json_encode($response);
        wp_die();
		// $crons = _get_cron_array();
		// $sch = $crons[$_POST['stime']][$this->schedule_hook_name][$_POST['sig']];

	}
	
	public function cancelBuildSchedule()
	{
		$crons = _get_cron_array();
		$sch = $crons[$_POST['stime']][$this->schedule_hook_name][$_POST['sig']];

		$response = false;
		if (isset($sch)) {
			$args = $sch['args'];
			$res = wp_unschedule_event( $_POST['stime'], $this->schedule_hook_name, $args );
			if(isset($_POST['process_id'])) {
				$update = $this->db->update(
					$this->process_list_table,
					array (
						'complete' => $this->execution_status['aborted']
					),
					array('process_id' => $_POST['process_id'])
				);
			}
			$response = true;
		}
		echo $response;
	}

	public function abortCurrentBuild(){
		$process_id = $_POST['process_id'];
		$related_schema = $_POST['related_schema'];
		$sql_build_id = "SELECT build_id FROM {$this->process_list_table} 
							WHERE process_id = '{$process_id}'
							AND complete = '{$this->execution_status['in_progress']}'
							AND related_schema = '{$related_schema}'";
		
		$result_build_id = $this->db->get_row($sql_build_id);
		$status = $this->jenkins->stopJob($result_build_id->build_id);
		echo $status;
		// echo $result_build_id->build_id;
		wp_die();
	}

	
	private function mailResults($ins_associated_plugin, $data_json_arr, $attachments, $console_text, $result, $status)
	{
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$prev_target_url = $data_json_arr['prev_target_url'];
		$t_url = null;
		if(isset($prev_target_url) && !empty($prev_target_url)){
			$t_url = $prev_target_url;
		}else{
			$t_url = $result->target_url;
		}

		$host = parse_url($t_url,  PHP_URL_HOST);
		$path = rtrim(parse_url($t_url,  PHP_URL_PATH), "/ ");
		$path = ($path ? str_replace("/", ">",$path) : '');

		$subject = $ins_associated_plugin->notifier_mail_subject." @ ".$host.$path;
		
		$data = [];
		$data['process_id'] = $result->process_id; 
		$data['console_text'] = $console_text; 
		$data['result_url'] = $result->result_url; 
		$data['target_url'] = $result->target_url; 
		$data['complete'] = $status; 
		$data['jenkins_status_map'] = $this->jenkins_status_map;
		$data['execution_status'] = $this->execution_status;
		$data['result_dir'] = "{$this->result_dir}{$result->related_schema}/";
		$data['process_list_table'] = $this->process_list_table;
		// $msg_body = $ins_associated_plugin->generateResultText($data);
		$msg_body = $ins_associated_plugin->generateMessageBody($data);
		
		$page_url = $data_json_arr['permalink'];
		$msg_body .= "<br>URL: <a href='{$page_url}?id={$result->process_id}'>{$page_url}?id={$result->process_id}</a>";
		$msg_body.= "<style>
		a{
			color: #188fe0;
			text-decoration: none;
			outline: none;
		}
		a:hover{
			text-decoration: underline;
		}
		</style>";
		$email = $data_json_arr['email_recipients'];
		$result = wp_mail($email, $subject, $msg_body, $headers, $attachments);
		if(!$result){
			// file_put_contents('/var/www/html/wp-content/abcd2.txt', print_r($msg_body, true));
			global $ts_mail_errors;
			global $phpmailer;
		
			if (!isset($ts_mail_errors)) $ts_mail_errors = array();
		
			if (isset($phpmailer)) {
				$ts_mail_errors[] = $phpmailer->ErrorInfo;
			}
		
			file_put_contents('/var/www/html/wp-content/mail_error.txt',print_r($ts_mail_errors, true));
		}else{
			// file_put_contents('/var/www/html/wp-content/abcd2.txt', print_r($email, true));
		}
	}

	public function processList():string
	{	
		$process_id = isset($_GET['id']) ? $_GET['id'] : '';
		$data = array("related_schema" => $this->table_name, "current_proc_id" => $process_id);
		return $html = $this->view('views/process-list', $data);
	}

	public function getProcessListData():string
	{	
		$related_schema = $_POST['related_schema'];
		$current_proc_id = $_POST['current_id'];
		$sql = "SELECT plt.process_id, plt.complete, plt.build_id, plt.queue_id, plt.date, t.target_url FROM {$this->process_list_table} plt 
				LEFT JOIN {$related_schema} t ON plt.`job_id` = t.`id` 
				WHERE plt.`related_schema` = '{$related_schema}' AND `knox_id` = '{$this->current_user->user_login}' 
				ORDER BY plt.`date` DESC";
		$results = $this->db->get_results($sql);
		
		if(!empty($results)) {
			foreach($results as $result) {
				$dir = "{$this->result_dir}{$related_schema}/{$result->process_id}";
				$data_json = $this->getDataJson($dir);
				$data = json_decode($data_json, true);
				if(isset($data['scheduled_time'])) {
					$result->scheduled_time = $data['scheduled_time'];
				}
			}
		}
		$data['processes'] = (array) $results;
		$data['execution_status'] = $this->execution_status;
		$data['current_proc_id'] = $current_proc_id;
		$table = $this->view('views/process-list-table', $data);
		echo $table;
		return $table;
	}

	public function processUrl():bool
	{
		$process_id = isset($_GET['id']) ? $_GET['id'] : '';	
		if($process_id) {
			$process_details = $this->getProcessDetails($process_id);
			return true;
		} else {
			return false;
		}
	}

	private function getProcessDetails(string $process_id):bool
	{
		$sql = "SELECT t.*, plt.process_id, plt.complete, plt.date FROM ".$this->process_list_table." plt LEFT JOIN ".$this->table_name." t ON plt.`job_id` = t.`id` WHERE plt.`process_id` ='{$process_id}'";
		$result = $this->db->get_row($sql);
		if(!empty($result)) {
		$data = (array) $result;
		$data['cur_page_url'] = $this->cur_page_url;
		$data['complete'] = $result->complete;
		$data['process_list'] = $this->processList();
		$data['execution_status'] = $this->execution_status;
		$data['notification_msg_prefix'] = $this->notification_msg_prefix;
		$data['related_schema'] = $this->table_name;
		$data_json = $this->getDataJson("{$this->result_dir}/{$process_id}");
		$data_json_arr = json_decode($data_json, true);

		if(isset($data_json_arr['scheduled_time'])) {
			$cron_list = _get_cron_array();
			$cron_list_hook_data = isset($cron_list[$data_json_arr['scheduled_time']][$this->schedule_hook_name]) ? $cron_list[$data_json_arr['scheduled_time']][$this->schedule_hook_name] : false;

			$data['scheduled_time'] = isset($data_json_arr['scheduled_time']) ? $data_json_arr['scheduled_time'] : null;
			$data['sig'] = isset($cron_list_hook_data) && !empty($cron_list_hook_data) ? array_keys(array_slice($cron_list_hook_data, 0, 1, TRUE))[0] : null;
		}
		$ins_associated_plugin = $this->getAssociatedPluginInstance($this->table_name);
		$data['parameters'] = $ins_associated_plugin->prepareSubmittedDataArr($result);
		echo $html = $this->view('views/process-progress', $data);
		return true;
		} else {
			$msg = "<div class = 'alert alert-danger'><i class='fa fa-warning'></i> Invalid Process Id!</div>";
			$this->output($msg);
			return false;
		}
	}

	public function getAssociatedPluginInstance($related_schema)
	{
		$associated_plugin = $this->schema_class_map[$related_schema];
		require_once WP_PLUGIN_DIR . '/' . $associated_plugin[1];
		$ins = new $associated_plugin[0]();
		return $ins;
	}

	public function getJenkinsConsoleText()
	{
		$res = array();
		$related_schema = isset($_POST['related_schema']) ? trim($_POST['related_schema']) : null;

		$ins_associated_plugin = $this->getAssociatedPluginInstance($related_schema);

		$process_id = isset($_POST['process_id']) ? trim($_POST['process_id']) : null;
		$start = isset($_POST['start']) ? trim($_POST['start']) : null;

		if(!is_null($process_id)) {
			$sql = "SELECT plt.`complete`, plt.`build_id`, t.`result_url`, t.`target_url` FROM {$this->process_list_table} plt 
					LEFT JOIN {$related_schema} as t ON plt.`job_id` = t.`id`
					WHERE plt.`process_id`='{$process_id}'";
			$result = $this->db->get_row($sql);
			$build_id = $result->build_id;
			$res['complete'] = $result->complete;
			$res['result_text'] = null;
			$res['err_msg'] = "Could Not retrieve data from jenkins";
			$res['ct_url'] = null;
			$res['response_header'] = null;
			$res['ct_response_size'] = null;
			
			if(!is_null($build_id)) {
				// $res['complete'] = $result->complete;
				if($result->complete == $this->execution_status['in_queue']) {
					// $res['status'] = $console_data['response_header']['http_code'] == 404 ? true : true;
					$res['status'] = true;
				} else if ($result->complete == $this->execution_status['in_progress']) {
					$console_data = $this->jenkins->getConsoleText($build_id, $start);
					$res['ct_url'] = $console_data['ct_url'];
					$res['response_header'] = $console_data['response_header'];
					$res['ct_response_size'] = $console_data['ct_response_size'];
					$res['console_text'] = $console_data['console_text'];
					$res['status'] = $console_data['response_header']['http_code'] == 404 ? false : true;
				} else if ($result->complete == $this->execution_status['success'] || $result->complete == $this->execution_status['failed']) {
					
					// $res['console_text'] = $console_data['console_text'];
					$res['status'] = true;
					$data = [];
					$data['process_id'] = $process_id;

					$log_dir = "{$this->result_dir}{$related_schema}/{$process_id}";

					$log_file_name = $log_dir.'/'.pathinfo($result->result_url, PATHINFO_FILENAME).'.log';
					if(file_exists($log_file_name)) {
						$data['console_text'] = file_get_contents($log_file_name);
						$res['console_text'] = $this->jenkins->suppressOutput($data['console_text']);
					} else {
						$res['console_text'] = 'Log file not found in server';
					}
					// $data['console_text'] = $this->jenkins->getFullConsoleText($build_id); 
					$data['result_url'] = $result->result_url; 
					$data['target_url'] = $result->target_url; 
					$data['complete'] = $result->complete; 
					$data['jenkins_status_map'] = $this->jenkins_status_map; 
					$data['execution_status'] = $this->execution_status;
					$data['result_dir'] = $this->result_dir . $related_schema . "/";
					$data['process_list_table'] = $this->process_list_table;
					$res['result_text'] = $ins_associated_plugin->generateResultText($data);
				}
			} else {
				$res['status'] = false;
				$res['in_queue'] = true;
				$res['result_text'] = null;
				$res['err_msg'] = 'Process still in queue. Please wait.';
			}
		} else {
			$res['status'] = false;
			$res['in_queue'] = false;
			$res['result_text'] = null;
			$res['err_msg'] = 'Invalid Request! No process id were received. ';
		}
		echo json_encode($res);
		wp_die();
	}

	public function view( string $filepath, array $param):string
	{
		ob_start();
		extract($param);
		include($filepath.".php");
		return $output = ob_get_clean();
	}
	
	public function output(string $data)
	{
		$process_list = $this->processList();
		$html = '<div class="row" id="page-container"> 
					<div class="col-md-4">
						' . $process_list . '
					</div>	
					<div class="col-md-8">
						' . $data . '
					</div>
				</div>	
				';
		echo $html;
	}

	public function createTableNrestoreData($table_name, $columns, $restore_data = true) 
	{
		$create_table_sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (";
		foreach($columns as $column_name => $column_details) {
			$create_table_sql .= $column_name . " ". $column_details . ", ";
		}
        $create_table_sql .= "PRIMARY KEY  (id) ) ". $this->charset_collate;
		$drop_table_sql = "DROP TABLE IF EXISTS " . $table_name;

		if ($restore_data) {
			$records = $this->db->get_results("SELECT * FROM {$table_name}");
			$no_of_records = $this->db->num_rows;
			$records = (array) $records;
			
			if($this->db->query($drop_table_sql)) {
				if($this->db->query($create_table_sql)) {
					if ($no_of_records >= 1) {
						$insert_sql = '';
						$keys = "";
						foreach((array)$records[0] as $rs_key => $rs_val) {
							$keys .= array_key_exists($rs_key, $columns) ? $rs_key . ", " : "";
						}
						$keys = rtrim($keys, ", ");
						foreach ($records as $record) {
							$values = '';
							foreach((array)$record as $rs_key => $rs_val) {
								$values .= array_key_exists($rs_key, $columns) ? '"' . $rs_val . '", '  : "";
							}
							$values .='';
							$values = rtrim($values, ", ");
							$insert_sql .= "INSERT INTO {$table_name} ( $keys ) VALUES ( " . $values . " ) ; ";
						}
						dbDelta($insert_sql);
					}
				}
			}
		} else {
			$this->db->query($drop_table_sql);
			$this->db->query($create_table_sql);
			add_option($this->db_option_name, $this->plugin_ver);
		}
	}

	public function Notify($msg, $type = "danger") 
	{
		echo "<div class='alert alert-{$type}'>{$msg}</div>";
	}
}

$obj = new UTTPluginLib(false);
$obj->activationDeactivationHook();