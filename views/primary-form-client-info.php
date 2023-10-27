<?php
$html ='
    <h3>Client information</h3>
        <table>
            <tr>
                <td class="header" ><label for="time">time</label></td>
                <td><input type="text" name="time" id="time" value="'.$current_time.'" /></td></tr>
            <tr>
                <td class="header" ><label for="knox_id">Knox ID</label></td>
                <td><input type="text" name="knox_id" id="knox_id" value="'.$current_user->user_login.'" /></td></tr>
            <tr>
                <td class="header" ><label for="email">email</label></td>
                <td><input type="email" name="email" id="email" value="'.$current_user->user_email.'" /></td></tr>
            <tr>
                <td class="header" ><label for="full_name">full name</label></td>
                <td><input type="text" name="full_name" id="full_name" value="'.$current_user->display_name.'" /></td></tr>
            <tr>
                <td class="header" ><label for="employee_number">employee number</label></td>
                <td><input type="text" name="employee_number" id="employee_number" value="'.$current_user->mo_ldap_local_custom_attribute_employeenumber.'" /></td></tr>
            <tr>
                <td class="header" ><label for="department_name">department name</label></td>
                <td><input type="text" name="department_name" id="department_name" value="'.$distinguish_name.'" /></td></tr>
            <tr>
                <td class="header" ><label for="user_information">user information</label></td>
                <td><input type="text" width="100%" name="user_information" id="user_information" value="'.$current_user->mo_ldap_local_custom_attribute_displayname.'" /></td></tr>
                
            <input type="hidden" name="action" value="process_form_data">
        </table>';

echo $html;