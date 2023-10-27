
(function($) {
    $(document).ready(function() {
        var er_input = '<div class=""> \
                            <div class="input-group"> \
                                <input type="text" class="form-control er_edit_input" placeholder="Recipient Email"> \
                                <input type="hidden" class="er_input_storage" value=""/> \
                                <div class="input-group-append"> \
                                    <button class="btn btn-sm btn-outline-info e_edit_save_btn ml-1" type="button" data-toggle="tooltip" title="save(Enter)"> \
                                        <i class="fa fa-check"></i> \
                                    </button> \
                                    <button class="btn btn-sm btn-outline-danger er_edit_save_cancel" type="button" data-toggle="tooltip" title="discard(Esc)"> \
                                        <i class="fa fa-times"></i> \
                                    </button> \
                                </div> \
                            </div> \
                        </div>';
        var er_li_empty = "";
        var er_li_basic = "";
        var close_modal_timer;
        var email_recipients = [];

        $(document).on("click", ".recipient_editor_btn", function() {
            var data_json = $(this).attr('data-json');
            jQuery.ajax({
                async: false,
                dataType: 'json',
                url: ajax_param.ajaxurl,
                method : "GET",
                cache : false,
                data: 'action=get_recipient_editor&data_json=' + data_json +'&process_id=' + process_id + '&related_schema=' + related_schema,
                beforeSend : function () {
                    
                },
                success : function(response) {
                    $("#er_modal").html(response.modal);
                    email_recipients = response.email_recipients;
                    er_li_empty = response.er_li_empty;
                    er_li_basic = response.er_li_basic;
                    $('#recipientEditorModal').modal({backdrop: 'static', keyboard: true});
                },
                error : function(errorThrown) {
                    console.log("Error : " + errorThrown);
                }
            }); 
        });

        $(document).on("click", ".er_delete_btn", function() {
           let er_li = $(this).closest(".er_li");
           er_li.slideUp('slow');
           er_html = er_li.find('.er').html().trim();
           er_li.remove();
           email_recipients = email_recipients.filter(function(value) { return value !== er_html });
           console.log(email_recipients);
           showHideEmptyListMsg();
        });

        $(document).on("click", ".er_edit_btn", function() {
           let er_li = $(this).closest(".er_li"); 
           let er = er_li.find(".er");
           let er_html = er.html().trim();
           er.html(er_input);
           er.find(".er_edit_input").val(er_html);
           er.find(".er_edit_input").focus();
           er.find(".er_input_storage").val(er_html);
           disableNewEntry();
        });

        $(document).on("click", ".er_add_btn", function() {
            let elb = $(er_li_basic).find(".er_edit_btn").prop("disabled", true).closest(".er_li").wrapAll("<div>").parent().html();
            elb = elb.replace("%s", er_input);
            let er_ul = $("#er_ul");
            er_ul.append(elb);
            showHideEmptyListMsg();
            disableNewEntry();
            let last_added_input = $(".er_edit_input").last().attr('data-new-input', true);
            last_added_input.focus();
            last_added_input.closest('.er_li').css("padding", "0px");
            $("#er_ul_container").animate({ scrollTop: $("#er_ul_container").prop('scrollHeight') }, 1000);
        });

        $(document).on("click", ".er_edit_save_btn", function() {
            saveInputChanges($(this));
        });

        $(document).on("click", ".er_edit_save_cancel", function() {
            discardInputChanges($(this));
        });
        
        $(document).on("click", "#er_save_btn", function() {
            jQuery.ajax({
                async: false,
                dataType: 'json',
                url: ajax_param.ajaxurl,
                method : "POST",
                cache : false,
                data: 'action=save_recipient&recipients=' + email_recipients + '&process_id=' + process_id + '&related_schema=' + related_schema,
                success : function(response) {
                    if(response.status) {
                        $("#er_msg").removeClass("alert-danger").addClass("alert-success").hide().slideDown().html(response.msg);
                        close_modal_timer = setTimeout(function() {
                            $('#recipientEditorModal').modal('hide');
                        }, 5000);
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

        $(document).on("keyup focusin", ".er_edit_input", function(e) {
            let er_li = $(this).closest(".er_li");
            er_li.find(".er_validation_error").addClass('hide');
            if (e.keyCode == 13) {
                saveInputChanges($(this));
            }
            if(e.key === "Escape") {
                e.preventDefault();
                discardInputChanges($(this));
            }
        });

        $(document).on("click", "#recipientEditorModal", function(e) {
            if($(e.target).is('#er_save_btn')) {
                return;
            }
            clearTimeout(close_modal_timer);
        });

        var ctrlDown = false, ctrlKey = 18, cmdKey = 91;
        $(document).keydown(function(e) {
            if (e.keyCode == ctrlKey || e.keyCode == cmdKey) ctrlDown = true;
        }).keyup(function(e) {
            if (e.keyCode == ctrlKey || e.keyCode == cmdKey) ctrlDown = false;
        });

        $(document).on("keyup", "body", function(e) {
            // 78 = n
            if (ctrlDown && e.keyCode == '78' && $('#recipientEditorModal').is(':visible')) {
                $(".er_add_btn").trigger('click');
            }
            if (ctrlDown && e.keyCode == '13' && $('#recipientEditorModal').is(':visible')) {
                $("#er_save_btn").trigger('click');
            }   
        });

        function saveInputChanges(elem) {
           let er_li = elem.closest(".er_li");
           let er = er_li.find(".er");
           let er_input = er.find(".er_edit_input");
           let is_new_input = er.find(".er_edit_input").attr('data-new-input');
           let er_input_val = er_input.val().trim();
           let old_input_val = er.find(".er_input_storage").val().trim();

           let error = false;
           if(er_input_val == '') {
               er_li.find(".er_validation_error").removeClass('hide').html("This field can not be empty!");
               error = true;
           } else if (!validateEmail(er_input_val)) {
               er_li.find(".er_validation_error").removeClass('hide').html("Invalid email address!");
               error = true;
            } else if(email_recipients.includes(er_input_val) && er_input_val !== old_input_val) {
               er_li.find(".er_validation_error").removeClass('hide').html("Duplicate email entry!");
               error = true;
            } else {
                er_li.find(".er_validation_error").addClass('hide');
                er.html(er_input_val);
                if(is_new_input) {
                    email_recipients.push(er_input_val);
                } else {
                    let index = email_recipients.indexOf(old_input_val);
                    email_recipients[index] = er_input_val;
                }
                enableNewEntry();
                let index = email_recipients.indexOf(old_input_val);
                if (index > -1) { email_recipients.splice(index, 1); }
           }
           if(error && is_new_input) {
                $("#er_ul_container").animate({ scrollTop: $(".er_validation_error:visible").offset().top }, 1000);
           } 
        }

        function discardInputChanges(elem) {
            let er_li = elem.closest(".er_li");
            let er = er_li.find(".er");
            let old_input_val = er.find(".er_input_storage").val();
            er.html(old_input_val);
            if(old_input_val == '') {
                er_li.remove();  
            }
            er_li.find(".er_validation_error").hide();
            showHideEmptyListMsg();
            enableNewEntry();
        }

        function showHideEmptyListMsg() {
           let er_ul = $("#er_ul");
           let li_count = er_ul.find(".er_li").length;
           if( li_count <= 0 ) {
                er_ul.html(er_li_empty);
           } else {
                $(".er_li_empty").remove();
           }
        }

        function disableNewEntry() {
            $(".er_edit_btn").prop("disabled", true);
            $(".er_add_btn").prop("disabled", true);
            $(".er_delete_btn").prop("disabled", true);
            $("#er_save_btn").prop("disabled", true);
        }

        function enableNewEntry() {
            $(".er_edit_btn").removeAttr("disabled");
            $(".er_add_btn").removeAttr("disabled");
            $(".er_delete_btn").removeAttr("disabled");
            $("#er_save_btn").removeAttr("disabled");
        }

        function validateEmail(email) {
            var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
    });
})( jQuery );
