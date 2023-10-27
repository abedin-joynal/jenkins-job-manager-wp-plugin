
(function($) {
    $(document).ready(function() {

        loadHelpIcon();

        $(document).on("click", "a.help-button", function(e) {
            e.preventDefault();
            $(this).closest("tr").next(".help-area").toggle();
        });

        $(document).on("click", ".collapsible", function() {
            this.classList.toggle("active");
            $(this).next().slideToggle();
        });

        $(document).on("click", "#sch_build", function() {
           $(this).parent().next().slideToggle();
        });

        $(document).on("click", "#mail_others", function() {
            $(this).parent().next().slideToggle();
        });

        function loadHelpIcon() {
            // let json_data = "";
            console.log('loadHelpIcon');
            if(typeof(ajax_param) !== "undefined") {
                $.ajax({
                    method : "get",
                    url: ajax_param.ajaxurl,
                    data: {'action': 'get_help_text'},
                    cache: false,
                    success: function(response) {
                        result = $.parseJSON(response);
                        $(".custom-table input,.custom-table select").each(function(){
                            var container = $(this).parent();
                            let inp_id = $(this).get(0).id;
                            var help_cell = ' \
                                <td class="help"> \
                                <a class="help-button" href="${inp_id}"> \
                                <img src="/images/help.png" alt="Help for input: ${inp_id}" style="width: 16px; height: 16px;" class="icon-help icon-sm"> \
                                </a> \
                                </td>';
                            $(help_cell).insertAfter(container);
                            help_content = result[inp_id];
                            if (!help_content){
                                help_content = "<strong>ERROR</strong>: Failed to load help file: Not Found";
                            }
                            // console.log(help_content);
                            let help_container = ' \
                                    <tr class="help-area"> \
                                    <td></td> \
                                    <td><div class="help-area-content">'+help_content+'</div></td> \
                                    <td></td> \
                                    </tr>';
                            $(help_container).insertAfter(container.parent());
                        });
                    },
                    error: function(xhr, status, error) {
                        console.log(xhr.responseText);
                    }
                });
            }
        }
    });
})( jQuery );
function isUrl(s) {
    var pattern2 = /https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9\-]+\.[a-zA-Z0-9]((?:\.?)(?:[a-zA-Z0-9\-\/_?=]+))+$/i;
    var is_valid = pattern2.test(s);
    return is_valid;
}

function isCsv(s){
    var pattern = /\.csv$/i;
    return pattern.test(s);
}
