<?php
   $status_inqueue =  $execution_status['in_queue'];
   $status_inprogress =  $execution_status['in_progress'];
   $status_success =  $execution_status['success'];
   $status_failed =  $execution_status['failed'];
   $status_scheduled =  $execution_status['scheduled'];
   $status_aborted =  $execution_status['aborted'];

   $msg_scheduled = "{$notification_msg_prefix} is scheduled.";
   $msg_inqueue = "{$notification_msg_prefix} is in queue.";
   $msg_inprogress = "{$notification_msg_prefix} is in progress.";
   $msg_complete = "{$notification_msg_prefix} is complete.";
   $msg_aborted = "{$notification_msg_prefix} was aborted.";
   $cancel_sch_tablink = "";
   $modify_sch_tablink = "";
   $modify_erecipient_tablink = "";
   $abort_build_tablink = "<li id='abort_build_li'><i class='fa fa-ban fa-custom'></i><a class='tablink abort_build_btn'>Abort Build </a></li>";
   
   $result_msg = "Status will be available after process completion.";

   if($complete == $status_success || $complete == $status_failed) {
        //$msg_inqueue = "Please Wait!! Your requested operation in progress.";
       $msg_inprogress = "Please Wait!! Your requested operation in progress.";
       $msg_complete = "Processing Complete. Here is the result.";
       $notification_msg = $msg_inprogress;
   } else if($complete == $status_scheduled) {
       $notification_msg = '<i class="fa fa-clock-o"></i> ' . $msg_scheduled;
       $cancel_sch_tablink = "<li id='cancel_schedule_li'><i class='fa fa-times fa-custom'></i><a class='tablink cancel_schedule_btn' data-sig='{$sig}' data-scheduled-time='{$scheduled_time}'>Cancel Schedule </a></li>";
       $modify_sch_tablink = "<li id='modify_schedule_li'><i class='fa fa-edit fa-custom'></i><a class='tablink modify_schedule_btn' data-sig='{$sig}' data-scheduled-time='{$scheduled_time}'>Modify Schedule </a></li>";
       $modify_erecipient_tablink = "<li id='recipient_editor_li'><i class='fa fa-pencil fa-custom'></i><a class='tablink recipient_editor_btn' data-json=''>Modify Email Recipient</a></li>";
   } else if($complete == $status_inqueue) {
       $notification_msg = '<i class="fa fa-check-circle-o"></i> ' . $msg_inqueue;
   } else if($complete == $status_inprogress) {
       $notification_msg = '<i class="fa fa-hourglass-2"></i> ' . $msg_inprogress;
   } else if($complete == $status_aborted) {
       $notification_msg = '<i class="fa fa-times"></i> ' . $msg_aborted;
       $result_msg = "No result is available.";
   }

   $sd = '';
   foreach($parameters as $pm):
        $sd .= "<tr>
                    <td class='header'>{$pm[0]}</td>
                    <td>{$pm[1]}</td>
                </tr>";
   endforeach;

   $submitted_data = "
        <h3> Parameters </h3>
        <table>
           {$sd}
        </table>";

   $tab_data = 
        '<div class="row" id="page-container">
            <div class="col-md-4">
                <div class="tab">
                    <div id="back-to-form-container">
                        <a href="'.$cur_page_url.'" class="btn btn-info mb-2"> <i class="fa fa-arrow-circle-left"></i> Go back to form</a>
                    </div>  
                    <ul>
                        <li><i class="fa fa-search fa-custom"></i><a class="tablink active" onclick="openTab(event, \'result\')">Result</a></li>
                        <li><i class="fa fa-terminal fa-custom"></i><a class="tablink" onclick="openTab(event, \'console_output_container\')">Console Output</a></li>
                        <li><i class="fa fa-cog fa-custom"></i><a class="tablink" onclick="openTab(event, \'parameters\')">Parameters</a></li>
                        '.$modify_sch_tablink.'
                        '.$cancel_sch_tablink.'
                        '.$modify_erecipient_tablink.'
                        '.$abort_build_tablink.'
                    </ul>
                    <br>
                    ' . $process_list . '
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="alert alert-info" id="primary-msg">
                    ' . $notification_msg . '
                </div>

                <div id="result" class="tabcontent">
                    <h3>Result</h3>
                    <div id="live_console_result_txt"> 
                    ' . $result_msg . '
                        <span class="console_output_loader_icon"><i class="fa fa-refresh fa-spin"></i>
                    </div>
                </div>
                <div id="console_output_container" class="tabcontent"> 
                    <h3>Console Output : <span class="console_output_loader_icon"><i class="fa fa-refresh fa-spin"></i></span> </h3>
                    <pre id="console_output" class="my-pre"><div id="console_output_txt"></div><div class="console_output_loading_txt"><div id="console_output_loader"><span>Retrieving Console Data</span> <span class="console_output_loader_icon"><i class="fa fa-circle-o-notch fa-spin"></i></span></div></div></pre>
                </div>
                <div id="parameters" class="tabcontent">
                    '. $submitted_data .'
                </div>
                <div>
                    <div id="er_modal"></div>
                    <div id="er_li_empty" class="hide"></div>
                    <div id="er_li_basic" class="hide"></div>
                </div>
            </div>
        </div>
        ';

    echo $tab_data;
?>

<script type="text/javascript">
 
    var msg_scheduled = "<?php echo $msg_scheduled;?>";
    var msg_inqueue = "<?php echo $msg_inqueue;?>";
    var msg_inprogress = "<?php echo $msg_inprogress;?>";
    var msg_complete = "<?php echo $msg_complete;?>";

    var process_id = "<?php echo $process_id; ?>";
    var related_schema = "<?php echo $related_schema; ?>";
    var db_complete_status = "<?php echo $complete; ?>";

    var status_scheduled = "<?php echo $status_scheduled; ?>";
    var status_inqueue = "<?php echo $status_inqueue; ?>";
    var status_inprogress = "<?php echo $status_inprogress; ?>";
    var status_success = "<?php echo $status_success; ?>";
    var status_failed = "<?php echo $status_failed; ?>";
    var status_aborted = "<?php echo $status_aborted; ?>";

    var process_complete = false; //indicates if overall page execution is complete
    var retry_count = 0;
    var retry_limit = 3;
    var console_pointer = 0;
    var interval_counter = 0;

    (function($) {
        var scrolled = false;
        var intrval_get_console_text;

        $(document).on('mouseover', "#console_output", function() {
            scrolled = true;
        });

        $(document).on('mouseout', "#console_output", function() {
            scrolled = false;
        });

        $(document).ready(function() {
            if(db_complete_status == status_scheduled || db_complete_status == status_aborted) {
                $(".loader_img").hide();
                $("#console_output_loader").hide();
                $(".console_output_loader_icon").hide();
            } else {
                $(".loader_img").show();
                $("#console_output_loader").show();
                $(".console_output_loader_icon").show();
            }

            // scrollToCurrentProcessListData();
            getJenkinsConsoleText();
            processProgressCheckInterval();
            if(process_id){
                scrollToCurrentId();
            }
            // declareFocusNBlurEvents();
        });
        
        function declareFocusNBlurEvents() {
            $(window).focus(function() {
                processProgressCheckInterval();
            });

            $(window).blur(function() {
                clearInterval(intrval_get_console_text);
                intrval_get_console_text = 0;
            });
        }
        
        function processProgressCheckInterval() {
            intrval_get_console_text = setInterval(function() {
                getJenkinsConsoleText();
            }, 3000);
        }
        
        function scrollToBottom(obj) {
            if(!scrolled) {
                setTimeout(function() {
                    $(obj).animate({ scrollTop: $("#console_output").prop('scrollHeight') }, 1000);            
                }, 100);
            }
        }

        function scrollToCurrentId(){
            // console.log('dfdfdf');
            if ($("#plt_tr_"+process_id).length) {
                $("div.process-list").animate({ scrollTop: $("#plt_tr_"+process_id).offset().top-$("div.process-list").offset().top }, 1000);
                clearTimeout(checkExist);
                return;
            }
            var checkExist = setTimeout(scrollToCurrentId, 200); // check every 100ms
        }

        function initializeImageSlider(){
            var tryCount = 0;
            //var checkExist = setInterval(function(){
            if($('.custom-carousal').length){
                $('.custom-carousal').slick({
                    dots: false,
                    draggable: false,
                    lazyLoad: 'ondemand',
                });

                $(document).on("mouseenter", ".custom-carousal", function(e) {
                    $(".slick-next").after().css("color", "gray");
                    $(".slick-prev").after().css("color", "gray");
                    $(".slick-next").after().css("background-color", "#cccc");
                    $(".slick-prev").after().css("background-color", "#cccc");
                });
        
                $(document).on("mouseleave", ".custom-carousal", function(e) {
                    $(".slick-next").after().css("color", "transparent");
                    $(".slick-prev").after().css("color", "transparent");
                    $(".slick-next").after().css("background-color", "transparent");
                    $(".slick-prev").after().css("background-color", "transparent");
                });
                clearInterval(checkExist);
            }else{
                console.log('No slider detected');
                // tryCount++;
                // if(tryCount > 1000){
                //     clearInterval(checkExist);
                // }
            }
        //    }, 1000);
        }

        function getJenkinsConsoleText() {
            interval_counter++;
            if(process_complete == true) {
                return false;
            }
            jQuery.ajax({
                async: false,
                dataType: 'json',
                url: ajax_param.ajaxurl,
                method : "POST",
                cache : false,
                data: 'process_id=' + process_id + '&action=get_jenkins_console_text&related_schema=' + related_schema + '&start=' + console_pointer + '&interval_counter=' + interval_counter,
                beforeSend : function () {
                    
                },
                success : function(response) {
                    if(response.status == true) {
                        if(response.console_text !== null) {
                            if(response.response_header !== null) {
                                console_pointer = response.response_header.x_text_size;
                                $("#console_output_txt").append(response.console_text);
                                scrollToBottom("#console_output");
                            }
                        }

                        if (response.complete == status_scheduled) {
                            // $("#primary-msg").html("<i class='fa fa-check-circle-o'></i> " + msg_inprogress ).removeClass('alert-success').addClass('alert-info');
                        } else if (response.complete == status_inqueue) {
                            $("#cancel_schedule_li").hide();
                            $("#modify_schedule_li").hide();
                        } else if (response.complete == status_inprogress) {
                            $("#primary-msg").html("<i class='fa fa-check-circle-o'></i> " + msg_inprogress ).removeClass('alert-success').addClass('alert-info');
                            $("#cancel_schedule_li").hide(); 
                            $("#modify_schedule_li").hide();                           
                            $("#recipient_editor_li").hide();
                            $("#abort_build_li").show();
                        } else if(response.complete == status_success || response.complete == status_failed) {
                            process_complete = true;

                            scrollToBottom("#console_output");
                            clearInterval(intrval_get_console_text);
                            $(".console_output_loading_txt").slideUp().remove();
                            $(".loader_img").slideUp();
                            $(".console_output_loader_icon").hide();
                            
                            $("#live_console_result_txt").html(response.result_text);
                            $("#console_output_txt").html(response.console_text);
                            initializeImageSlider();
                            $("#primary-msg").html("<i class='fa fa-check-circle-o'></i> " + msg_complete ).removeClass('alert-info').addClass('alert-success');
                            $("#cancel_schedule_li").hide();
                            $("#modify_schedule_li").hide();
                            $("#recipient_editor_li").hide();
                            $("#abort_build_li").hide();
                        } else if (response.complete == status_aborted) {
                            $("#cancel_schedule_li").hide();
                            $("#modify_schedule_li").hide();
                            $("#recipient_editor_li").hide();
                            $("#abort_build_li").hide();
                        }
                    } else {
                        if(response.in_queue == false) {
                            retry_count++;
                            if(retry_count >= retry_limit) {
                                clearInterval(intrval_get_console_text);
                                $(".console_output_loading_txt").slideUp().remove();
                                $(".loader_img").slideUp();
                                $(".console_output_loader_icon").hide();
                                $("#live_console_result_txt").html(response.result_text);
                                $("#console_output_txt").html(" Could not retrieve console output data");
                                $("#primary-msg").html("<i class='fa fa-warning'></i> " + response.err_msg).removeClass('alert-info').addClass('alert-danger');
                            }
                        }
                    }
                },
                error : function(xhr, status, errorThrown) {
                    // var err = JSON.parse(xhr.responseText);
                    console.log(status);
                    console.log("Error : " +errorThrown);
                    clearInterval(intrval_get_console_text);
                    $("#primary-msg").html("<i class='fa fa-warning'></i> Something Went Wrong").removeClass('alert-info').addClass('alert-danger');
                },
                complete : function() {
                   
                }
            }); 
        }
    })( jQuery );

    
    (function($) {
        $(document).on("click", ".cancel_schedule_btn", function() {
            var r = confirm("Do you want to delete this schedule?");
            if (r == false) {
               return false;
            }
            var sig = $(this).attr("data-sig");
            var stime = $(this).attr("data-scheduled-time");
            $("#live_console_result_txt").html("No result is available.");

            jQuery.ajax({
                async: false,
                dataType: 'json',
                url: ajax_param.ajaxurl,
                method : "POST",
                cache : false,
                data: 'action=cancel_build_schedule&process_id='+ process_id +'&sig=' + sig + '&stime=' + stime,
                beforeSend : function () {
                    
                },
                success : function(response) {
                    if(response) {
                        $("#cancel_schedule_li").hide();
                        $("#modify_schedule_li").hide();
                        $("#recipient_editor_li").hide();
                        $("#abort_build_li").hide();
                        $("#primary-msg").html("<i class='fa fa-check-circle-o'></i> Process was unscheduled successfully").removeClass('alert-info').removeClass('alert-danger').addClass('alert-success');
                    } else {
                        $("#primary-msg").html("<i class='fa fa-warning'></i> Could not unscheduled this process").removeClass('alert-info').removeClass('alert-success').addClass('alert-danger');
                    }
                },
                error : function(xhr, status, errorThrown) {
                    console.log("Error : " +errorThrown);
                    $("#primary-msg").html("<i class='fa fa-warning'></i> Something Went Wrong").removeClass('alert-info').addClass('alert-danger');
                },
                complete : function() {
                   
                }
            }); 
        });

        $(document).on("click", ".abort_build_btn", function() {
            var r = confirm("Do you want to abort this build?");
            if (r == false) {
               return false;
            }
            jQuery.ajax({
                async: false,
                dataType: 'json',
                url: ajax_param.ajaxurl,
                method : "POST",
                cache : false,
                data: 'action=abort_current_build&process_id='+ process_id+'&related_schema='+related_schema,
                beforeSend : function () {
                    
                },
                success : function(response) {
                    console.log(response);
                },
                error : function(xhr, status, errorThrown) {
                    console.log("Error : " +errorThrown);
                    // $("#primary-msg").html("<i class='fa fa-warning'></i> Something Went Wrong").removeClass('alert-info').addClass('alert-danger');
                },
            });

        });

        $(document).on("click", "#sch_save_btn", function() {
            var sig = $('.modify_schedule_btn').attr("data-sig");
            var stime = $('.modify_schedule_btn').attr("data-scheduled-time");
            var nstime = $('#sch_date').val()+ " " +$('#sch_time').val();
            var srecur = $("#sch_recur").val();
            // console.log(nstime);
            // console.log(sig);
            // console.log(stime);
            // let sch_datetime = strtotime($date_time);
            jQuery.ajax({
                async: false,
                dataType: 'json',
                url: ajax_param.ajaxurl,
                method : "POST",
                cache : false,
                data: 'action=modify_schedule&nstime='+ nstime +'&sig=' + sig + '&stime=' + stime + '&srecur=' + srecur + '&process_id='+ process_id + '&related_schema='+ related_schema,
                success : function(response) {
                    console.log(response);
                    if(response.status) {
                        $("#er_msg").removeClass("alert-danger").addClass("alert-success").hide().slideDown().html(response.msg);
                        close_modal_timer = setTimeout(function() {
                            $('#scheduleEditorModal').modal('hide');
                        }, 5000);
                        $(".modify_schedule_btn").attr('data-sig', response['sig']);
                        $(".modify_schedule_btn").attr('data-scheduled-time', response['scheduled_time']);
                        $(".cancel_schedule_btn").attr('data-sig', response['sig']);
                        $(".cancel_schedule_btn").attr('data-scheduled-time', response['scheduled_time']);
                        
                    } else {
                        $("#er_msg").removeClass("alert-success").addClass("alert-danger").slideDown().html(response.msg);
                    }
                    $("#er_ul_container").animate({ scrollTop: 0 }, 1000);
                },
                error : function(errorThrown) {
                    console.log("Error : " + errorThrown);
                }
            }); 
        });

        $(document).on("click", ".modify_schedule_btn", function() {
            var sig = $(this).attr("data-sig");
            var stime = $(this).attr("data-scheduled-time");
            jQuery.ajax({
                async: false,
                dataType: 'json',
                url: ajax_param.ajaxurl,
                method : "POST",
                cache : false,
                data: 'action=get_schedule_editor&process_id='+ process_id +'&sig=' + sig + '&stime=' + stime,
                beforeSend : function () {
                    
                },
                success : function(response) {
                    // console.log(response);
                    $("#er_modal").html(response.modal);
                    $("#sch_date").val(response.sch_date);
                    $("#sch_time").val(response.sch_time);
                    $("#sch_recur").val(response.sch_recur);
                    // console.log(response.dt.substr(11));

                    // email_recipients = response.email_recipients;
                    // er_li_empty = response.er_li_empty;
                    // er_li_basic = response.er_li_basic;
                    $('#scheduleEditorModal').modal({backdrop: 'static', keyboard: true});
                },
                error : function(errorThrown) {
                    console.log("Error : " + errorThrown);
                }
            }); 

        });

    })( jQuery );
</script>
