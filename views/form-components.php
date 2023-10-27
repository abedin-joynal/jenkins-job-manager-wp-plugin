<?php
$schedule_build ='
    <div class="mb-4">
        <h3>Build Triggers</h3>
        <div class="form-check">
            <input type="checkbox" name="sch_build" id="sch_build" class="form-check-input" /> 
            <label class="">Run Periodically</label>
        </div>
      
        <div class="hide_element form-row">
            <div class="row">
                <div class="col-3" style="padding-right:0px;">
                    Date
                    <input type="date" id="sch_date" name="sch_date" />
                </div>
                <div class="col-3" style="padding-right:0px;">
                    Time
                    <input type="time" id="sch_time" name="sch_time" />
                </div>
                <div class="col-6">
                    Recurrence<br>
                    <select name="sch_recur" id="sch_recur">
                        <option value="once"> Once </option>
                        <option value="15min"> 15 Min </option>
                        <option value="hourly"> Hourly </option>
                        <option value="daily"> Daily </option>
                        <option value="weekly"> Weekly </option>
                    </select>
                </div>
            </div>
        </div>
    </div>';

$email_notification = '
    <div class="mb-4">
        <h3>E-mail Notification</h3>
        <label> Upon completion, Send execution result to </label><br>
        <div class="form-check">
            <input type="checkbox" name="mail_self" class="form-check-input" /> 
            <label class="">Only me</label>
        </div>
        <div class="form-check">
            <input type="checkbox" name="mail_others" id="mail_others" class="form-check-input" id="mail_others" /> 
            <label class="">Other recipients</label>
        </div>
        <div class="hide_element">
            <input type="text" name="other_recipients_mail" id="mail_other_recipients" 
                placeholder="Separate mail IDs by semicolon(;) for multiple recipients" />
        </div>
    </div>';

$trigger_new_build = '
    <div class="mb-4">
        <h3>Trigger build on other plugins</h3>
        <div class="form-group">
            <label for="plugins_to_build">Plugins to build</label>
            <input type="text" name="plugins_to_build" id="plugins_to_build" 
            placeholder="Separate plugins by comma(,) for multiple plugins" />
        </div>
    </div>
';

$html = "";

foreach($components as $c):
    if(isset($$c)):
        $html .= $$c;
    else:
        $html .= "<p class='text-danger'><strong>Error:</strong> Form component $c were not found </p>\n";
    endif;
endforeach;

echo $html;