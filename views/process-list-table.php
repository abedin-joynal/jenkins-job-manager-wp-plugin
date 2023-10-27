<?php

    // date_default_timezone_set(UTT_TIMEZONE);
    
    $status_scheduled =  $execution_status['scheduled'];
    $status_in_queue =  $execution_status['in_queue'];
    $status_in_progress =  $execution_status['in_progress'];
    $status_success =  $execution_status['success'];
    $status_failed =  $execution_status['failed'];
    $status_aborted =  $execution_status['aborted'];

    $icon_scheduled = '<i data-toggle="tooltip" data-placement="top" title="%s"  class="fa fa-custom fa-clock-o"></i>';
    $icon_in_queue = '<i data-toggle="tooltip" data-placement="top" title="In Queue.."  class="fa fa-custom fa-refresh fa-spin"></i>';
    $icon_in_progress = '<i data-toggle="tooltip" data-placement="top" title="In progress.."  class="fa fa-custom fa-circle fa-green blink"></i>';
    $icon_success = '<i data-toggle="tooltip" data-placement="top" title="Success" class="fa fa-custom fa-circle fa-green"></i>';
    $icon_failed = '<i data-toggle="tooltip" data-placement="top" title="Failed" class="fa fa-custom fa-circle fa-crimson"></i>';
    $icon_aborted = '<i data-toggle="tooltip" data-placement="top" title="Aborted" class="fa fa-custom fa-circle fa-gray"></i>';

    $process_list_html = '';
    if(!empty($processes)) {
        foreach($processes as $process) {
            $process_id = $process->process_id;
            $target_url = $process->target_url;
            $created_at = date("M j, Y h:i A",strtotime($process->date));
            
            if($process->complete == $status_scheduled) {
                if(property_exists($process, 'scheduled_time')) {
                    $icon = sprintf($icon_scheduled, date("M jS, Y h:i A",$process->scheduled_time));
                    // $icon = sprintf($icon_scheduled, date("M jS, Y g:i a",$process->scheduled_time));
                    // $icon = sprintf($icon_scheduled, $process->scheduled_time);
                } else {
                    $icon = sprintf($icon_scheduled, "Scheduled");
                }
            } else if($process->complete == $status_in_queue) {
                $icon = $icon_in_queue;
            } else if($process->complete == $status_in_progress) {
                $icon = $icon_in_progress;
            } else if($process->complete == $status_success) {
                $icon = $icon_success;
            } else if($process->complete == $status_failed) {
                $icon = $icon_failed;
            } else if($process->complete == $status_aborted) {
                $icon = $icon_aborted;
            }
            if($process_id == $current_proc_id){
                $process_list_html .= "<tr id='plt_tr_{$process_id}' class='active'>";
            }else{
                $process_list_html .= "<tr id='plt_tr_{$process_id}'>";
            }
            $process_list_html .= "<td>
                                        <a href='?id={$process_id}' data-build-id='{$process->build_id}' data-queue-id='{$process->queue_id}'>{$process_id}</a>
                                        <div class='date_created'>{$created_at}</div>
                                    </td>";
            if(pathinfo($target_url, PATHINFO_EXTENSION)== "csv"){
                $result_path = home_url()."/result/";
                $process_list_html .= "<td><a href='$result_path$target_url' target='_blank'>{$target_url}</a></td>";
            }else{
                $process_list_html .= "<td><a href='{$target_url}' target='_blank'>{$target_url}</a></td>";
            }
            $process_list_html .= "<td status='{$process->complete}'>{$icon}</td>
                                   </tr>";
        }
    } else {
        $process_list_html .= '<tr>
                                 <td class="no-data" colspan="3 "> No Previous Build Found </td>
                               </tr>';
    }

    echo $process_list_html;
?>
