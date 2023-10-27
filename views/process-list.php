<?php
    $icon_in_progress = '<i class="fa fa-refresh fa-spin"></i>';
    $table = '
        <div class="panel panel-primary" id="process-list-container">
            <div class="panel-heading">
                <p class="panel-title mb-0"><i class="fa fa-certificate fa-custom fa-golden"></i>Run history</p>
            </div>
            <div class="panel-body">
                <div class="process-list">
                    <table class="">
                        <thead>
                            <tr>
                                <th class="text-align-center" style="width: 40%">Process ID</th>
                                <th class="text-align-center" style="width: 55%">Url</th>
                                <th class="text-align-center" style="width: 5%">#</th>
                            </tr>
                        </thead>
                        <tbody class="process-list-tbody">
                            <tr>
                                <td colspan="4"> Getting Process History <i class="fa fa-refresh fa-spin"></i></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        ';

    echo $table;
?>

<script type="text/javascript">
    var related_schema = "<?php echo $related_schema;?>";
    var current_proc_id = "<?php echo $current_proc_id;?>";
    (function($) {
        var ps_intrval;
        $(document).ready(function() {
            $(document).on('click', '.terminate_btn', function() {
                var pid = $(this).attr('data-pid');
                jQuery.ajax({
                    dataType: 'text',
                    url: ajax_param.ajaxurl,
                    method : "POST",
                    data: 'pid=' + pid + '&action=check_terminate_process_bl',
                    beforeSend : function () {
                        // console.log("Checking Application Status!! Ajax request is sent");
                    },
                    success : function(response) {
                        var response = extractJsonFromText ( response );

                        $.each(response, function(process_id, complete) {
                            icon = complete ==1 ? icon_complete : icon_in_progress;
                            $("#pr_icon_" + process_id).html(icon);
                        });
                    },
                    error : function(errorThrown) {
                        console.log(errorThrown);
                    },
                    complete : function() {
                        
                    }
                }); 
            });
            
            declareFocusNBlurEvents();
            startProcessCheckInterval();
        });
        
        function declareFocusNBlurEvents() {
            $(window).focus(function() {
                startProcessCheckInterval();
            });

            $(window).blur(function() {
                clearInterval(ps_intrval);
                ps_intrval = 0;
            });
        }

        function startProcessCheckInterval() {
            processStatusAjax();
            if (!ps_intrval) {
                ps_intrval = setInterval(function() {
                    processStatusAjax();
                }, 3000);
            }    
        }

        function processStatusAjax() {  
            jQuery.ajax({
                dataType: 'text',
                url: ajax_param.ajaxurl,
                method : "POST",
                data: '&action=check_process_status_bl&related_schema='+related_schema+'&current_id='+current_proc_id,
                beforeSend : function () {
                    
                },
                success : function(response) {
                    $(".process-list-tbody").html(response);
                },
                error : function(errorThrown) {
                    console.log(errorThrown);
                },
                complete : function() {
                    
                }
            }); 
        }
   
    })( jQuery );
</script>