jQuery(document).ready(function(){
/* on widget settings tab click -> display appropriate panel */
  jQuery(document).on( "click", '.cca-widget-admin-tab', function(){
    cca_id = this.id;
    cca_id_prefix = cca_id.substring(0, cca_id.lastIndexOf('-') + 1);
		cca_id_suffix = cca_id.replace(cca_id_prefix,"");
		jQuery("[id^=" + cca_id_prefix + "].cca-widget-admin-tab").removeClass( "cca-tab-active" );
    jQuery( "#" + this.id).addClass( "cca-tab-active" );
		jQuery( "[id^=" + cca_id_prefix + "].cca-widget-panel-container").removeClass( "cca-panel-active" );
    jQuery( "#" + this.id + "Panel").addClass( "cca-panel-active" );
		jQuery("#" + cca_id_prefix + "current_panel" ).val(cca_id_suffix);
		if (this.id.match(/displayTab$/)) { jQuery("#" + cca_id_prefix + "action" ).val("display");}
		if (this.id.match(/contentTab$/)) { jQuery("#" + cca_id_prefix + "action" ).val("new");}
	});
/* on radio select widget entry type  */
  jQuery(document).on( "click", '.cca-radio_widtype', function(){
    cca_id = this.id; cca_id_prefix = cca_id.substring(0, cca_id.lastIndexOf('-') + 1);  cca_id_suffix = cca_id.replace(cca_id_prefix,"");
		jQuery( "[id^=" + cca_id_prefix + "].cca-widget-entry-div").removeClass( "cca-widget-entry-div-active" );
    jQuery( "#" + this.id + "_div").addClass( "cca-widget-entry-div-active" );
	});
/* on edit tab's button click set "action" to tell wp_widget that we want to delete/edit a particular entry */
  jQuery(document).on( "click", '.cca-button', function(){
  	cca_id_prefix = this.id.substring(0, this.id.lastIndexOf('-'));
		if (this.id.match(/editbutton$/)) { jQuery("#" + cca_id_prefix + "-action" ).val("edit");  jQuery("#" + cca_id_prefix + "-savewidget" ).trigger( "click" );}
		else if (this.id.match(/delbutton$/)) {jQuery("#" + cca_id_prefix + "-action" ).val("delete");  jQuery("#" + cca_id_prefix + "-savewidget" ).trigger( "click" );}
	});
/* set fields for selected title option */
  jQuery(document).on( "change", '.cca-radio-title', function(){
  	cca_id_prefix = this.id.substring(0, this.id.lastIndexOf('-'));
		if (jQuery("#" + cca_id_prefix + "-customtitle:checked").length > 0) { jQuery("#" + cca_id_prefix + "-cust_title").prop("disabled", false)}
		else {jQuery("#" + cca_id_prefix + "-cust_title").prop("disabled", true); }
  });
/* content widget edit button press */
  jQuery(document).on( "click", '.ccax-button', function(){
    ccax_values = this.id.split("_");
	  jQuery("#ccax_widtype" ).val(ccax_values[1]); jQuery("#ccax_button_action" ).val(ccax_values[2]);
		jQuery("#submit").trigger( "click" );
	});
});