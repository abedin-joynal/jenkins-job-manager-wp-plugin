<?php
// namespace UTTPluginLib;

class recipientEditor
{
    private $utt;
    public function __construct($obj)
    {
        $this->setupAjaxHandlers();
        $this->defineActions();
        $this->utt = $obj;
    }

	public function defineActions() 
    {
		add_action( 'init', array($this, 'enqueueScripts'));
    }
    
    function enqueueScripts()
    {
		wp_register_script('recipient_editor_script', plugins_url('js/recipient-editor.js', __FILE__));
		wp_enqueue_script('recipient_editor_script');
		wp_register_style('recipient_editor_style', plugins_url('css/recipient-editor.css', __FILE__));
		wp_enqueue_style('recipient_editor_style');
    }

    function setupAjaxHandlers()
    {
        add_action( 'wp_ajax_get_recipient_editor', array($this, 'getRecipientEditor'));
        // add_action( 'wp_ajax_nopriv_get_recipient_editor', array($this, 'getRecipientEditor'));
        add_action( 'wp_ajax_save_recipient', array($this, 'saveRecipient'));
    }
    
    public function getRecipientEditor()
    {
        $dir = "{$this->utt->result_dir}{$_GET['related_schema']}/{$_GET['process_id']}";
        $data_json = $this->utt->getDataJson($dir);
        if($data_json){
            $data_json_arr = json_decode($data_json, true);
        }
        $email_recipients = isset($data_json_arr['email_recipients']) && !empty($data_json_arr['email_recipients']) ? $data_json_arr['email_recipients'] : [];

        $er_li_empty = "<li class='er_li_empty list-group-item list-group-item-warning'>
                            Email recipient list is empty
                        </li>";
        $er_li_basic = "<li class='er_li list-group-item d-flex justify-content-between align-items-center'>
                            <div class='er col col-7 col-md-9'>
                                %s
                            </div>
                            <div class='col col-5 col-md-3 text-right'>
                                <button type='button' class='er_edit_btn btn btn-outline-info' data-toggle='tooltip' title='edit'><i class='fa fa-pencil'></i></button>
                                <button type='button' class='er_delete_btn btn btn-outline-danger' data-toggle='tooltip' title='delete'><i class='fa fa-minus-circle'></i></button>
                            </div>
                            <small class='er_validation_error form-text text-danger hide m-0 p-0'></small>
                        </li>";
        $er_li = '';
        if($email_recipients) {
            foreach($email_recipients as $er) {
                $er_li .= sprintf($er_li_basic, $er);    
            }
        } else {
            $er_li = $er_li_empty;
        }

        $modal = "
            <div class='modal' id='recipientEditorModal' tabindex='-1' role='dialog' aria-labelledby='recipientEditorLabel' aria-hidden='true'>
                <div class='modal-dialog' role='document'>
                    <div class='modal-content'>
                    <div class='modal-header'>
                        <h5 class='modal-title' id='recipientEditorLabel'>Modify Email Recipient</h5>
                        <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                        <span aria-hidden='true'>&times;</span>
                        </button>
                    </div>
                    <div class='modal-body'>
                        <div id='er_msg' class='alert alert-success hide'>Message</div>
                        <div class='text-left'>
                            <button type='button' class='er_add_btn btn btn-outline-info' data-toggle='tooltip' title='Add (Alt+n)'><i class='fa fa-plus'></i> Add</button>
                        </div>
                        <div id='er_ul_container'>
                            <ul id='er_ul' class='list-group'>
                                {$er_li}
                            </ul>
                        </div>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-secondary' data-dismiss='modal'>Close</button>
                        <button type='button' id='er_save_btn' class='btn btn-info' data-toggle='tooltip' title='Save (Alt+Enter)'>Save changes</button>
                    </div>
                    </div>
                </div>
            </div>
        ";
        

        $response = [];
        $response['email_recipients'] = $email_recipients;
        $response['modal'] = $modal;
        $response['er_li_empty'] = $er_li_empty;
        $response['er_li_basic'] = $er_li_basic;

        echo json_encode($response);
        wp_die();
    }

    public function saveRecipient()
    {
        $response = [];
        if(isset($_POST['process_id']) && isset($_POST['related_schema']) && isset($_POST['recipients'])) {
            $process_id = $_POST['process_id'];
            $related_schema = $_POST['related_schema'];
            $dir = "{$this->utt->result_dir}{$related_schema}/{$process_id}";
            $data_json = $this->utt->getDataJson($dir);
            if($data_json){
                $data_json_arr = json_decode($data_json, true);

                $sql = "SELECT `complete` FROM ".$this->utt->process_list_table." WHERE `process_id` ='{$process_id}'";
                $result = $this->utt->db->get_row($sql);
                $status = $result->complete;
                if($this->utt->execution_status['scheduled'] == $status) {
                    if(!empty(trim($_POST['recipients']))) {
                        $email_recipient_arr = explode(",", $_POST['recipients']);
                        $data_json_arr['email_recipients'] = $email_recipient_arr;
                    } else {
                        unset($data_json_arr['email_recipients']);
                    }
                    $fp = file_put_contents($dir.'/data.json', json_encode($data_json_arr));
                    if(!$fp){
                        $response['status'] = false;
                        $response['msg'] = "<i class='fa fa-warning'></i> Unable to modify data.json";
                    }else{
                        $response['status'] = true;
                        $response['msg'] = "<i class='fa fa-check-circle'></i> Recipients were saved successfully.";
                    }
                } else {
                    $response['status'] = false;
                    $response['msg'] = "<i class='fa fa-warning'></i> Could not modify email recipient infomations 
                                        as the process exited scheduled state.";
                }
            } else {
                $response['status'] = false;
                $response['msg'] = "<i class='fa fa-warning'></i> Something went wrong while saving recipients.";
            }  

        } else {
            $response['status'] = false;
            $response['msg'] = "<i class='fa fa-warning'></i> Something is wrong with inputs";
        }
        echo json_encode($response);
        wp_die();
    }
}
