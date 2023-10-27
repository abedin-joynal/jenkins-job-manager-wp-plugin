function openTab(evt, tab_id) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablink");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(tab_id).style.display = "block";
  jQuery('.custom-carousal').slick('unslick').slick();
  evt.currentTarget.className += " active";
}

window.onload = function() {
  var active_tab = document.querySelectorAll(".active");  
  if(typeof active_tab[0] !== 'undefined') {
    active_tab[0].click();
  }
}

jQuery(document).ready(function() {
    var window_height = jQuery(window).height();
    var new_height = parseInt(window_height)-parseInt(370);
    jQuery(".my-pre").height(new_height);
});