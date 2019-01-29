$j( document ).ready( function() {
	// Load this file only on form editor page
	if( ! $j( 'form#bookacti-form-editor-page-form' ).length || typeof bookacti.form_editor === 'undefined' ) { return; }
	
	// Add / remove form managers
	bookacti_init_add_and_remove_items();
	
	// Specific dialogs
	$j( '#bookacti-form-field-dialog-free_text' ).dialog( 'option', 'width', 540 );
	$j( '#bookacti-form-field-dialog-terms' ).dialog( 'option', 'width', 540 );

	// Init form editor actions
	bookacti_init_form_editor_actions();
	
	// Minimize / Maximize field
	$j( '#bookacti-form-editor' ).on( 'click', '.bookacti-form-editor-field-header', function( e ) {
		if( $j( e.target ).hasClass( 'bookacti-form-editor-field-action' ) ) { return; }
	
		var icon = $j( this ).find( '.bookacti-field-toggle' );
		icon.toggleClass( 'dashicons-arrow-up dashicons-arrow-down' );
		icon.closest( '.bookacti-form-editor-field' ).find( '.bookacti-form-editor-field-body' ).toggle();
		
		var is_visible		= icon.closest( '.bookacti-form-editor-field' ).find( '.bookacti-form-editor-field-body' ).is( ':visible' );
		var booking_system	= icon.closest( '.bookacti-form-editor-field' ).find( '.bookacti-booking-system' );
		if( is_visible && booking_system.length ) {
			bookacti_booking_method_rerender_events( booking_system );
		}
    });
	
	// Sort form fields in editor
	$j( '#bookacti-form-editor' ).sortable( { 
		items: '.bookacti-form-editor-field:not(.ui-state-disabled):not(.bookacti-form-editor-promo-field)',
		handle: '.bookacti-form-editor-field-title',
		placeholder: 'bookacti-form-editor-field-placeholder',
		update: function( e, ui ) { bookacti_save_form_field_order(); }
	});
	$j( '#bookacti-form-editor' ).disableSelection();
	
	// Save a form (create or update)
	$j( 'form#bookacti-form-editor-page-form' ).on( 'submit', function( e ) {
		if( ! $j( 'form#bookacti-form-editor-page-form' ).length ) { return; }
		e.preventDefault();
		bookacti_save_form();
	});
	
	// Field-specific actions when a user open its dialog
	$j( '#bookacti-form-editor' ).on( 'bookacti_field_update_dialog', function( e, field_id, field_name ){
		if( field_name === 'calendar' ) {
			// Fill fields with raw values
			//bookacti_fill_fields_from_array( bookacti.form_editor.fields[ field_id ].template_data, '', 'form#bookacti-form-field-form-' + field_name );
			bookacti_fill_fields_from_array( bookacti.form_editor.fields[ field_id ].template_data.settings, '', 'form#bookacti-form-field-form-' + field_name );
			bookacti_fill_fields_from_array( bookacti.form_editor.fields[ field_id ].raw, '', 'form#bookacti-form-field-form-' + field_name );
			
			// Calendars and Activities array: if empty, select all
			if( bookacti.form_editor.fields[ field_id ].calendars.length === 0 ) {
				if( $j( '#bookacti-multiple-select-_bookacti_template' ).length ) {
					$j( '#bookacti-multiple-select-_bookacti_template' ).prop( 'checked', true );
					bookacti_switch_select_to_multiple( '#bookacti-multiple-select-_bookacti_template' );
				}
				$j( '#_bookacti_template option' ).prop( 'selected', true );
				$j( '#_bookacti_template' ).trigger( 'change' );
			}
			if( bookacti.form_editor.fields[ field_id ].activities.length === 0 ) {
				if( $j( '#bookacti-multiple-select-activities' ).length ) {
					$j( '#bookacti-multiple-select-activities' ).prop( 'checked', true );
					bookacti_switch_select_to_multiple( '#bookacti-multiple-select-activities' );
				}
				$j( '#activities option' ).prop( 'selected', true );
			}
		}
	});
	
	
	/**
	 * Add / remove activity row in the "redirect URL" table according to the currently selected activities
	 * @since 1.7.0
	 */
	$j( '#bookacti-form-field-dialog-calendar' ).on( 'change', 'select#bookacti-activities', function( e ){
		var activities = $j( this ).val();
		
		// If no activities, leave as is.
		if( ! activities || ( ! $j.isArray( activities ) && ! $j.isNumeric( activities ) ) ) { return; }
		if( ! $j.isArray( activities ) ) { activities = [ activities ]; }
		
		var tbody = $j( '.bookacti-activities-actions-options-table tbody' );
		
		// Add corresponding rows
		$j.each( activities, function( i, activity_id ) {
			var activity_title = $j( 'option#bookacti-activities_' + activity_id ).text();
			// If the row already exists, leave it as is
			if( tbody.find( '.bookacti-column-redirect_url input[name="redirect_url_by_activity[' + activity_id + ']"]' ).length ) {
				var row = tbody.find( '.bookacti-column-redirect_url input[name="redirect_url_by_activity[' + activity_id + ']"]' ).closest( 'tr' );
				row.data( 'activity_id', activity_id );
				row.attr( 'data-activity_id', activity_id );
				row.find( '.bookacti-column-activity' ).text( activity_title ).attr( 'title', activity_title );
				row.find( ':input' ).prop( 'disabled', false );
				row.show();
				return true; // continue
			// If the row doesn't exists, create it
			} else {
				tbody.find( 'tr:first' ).clone().appendTo( tbody );
				tbody.find( 'tr:last :input' ).each( function() {
					var field_name_raw	= $j( this ).attr( 'name' );
					var field_name		= field_name_raw.substring( 0, field_name_raw.lastIndexOf( '[' ) );
					$j( this ).attr( 'name', field_name + '[' + activity_id + ']' );
				});
				tbody.find( 'tr:last td.bookacti-column-activity' ).text( activity_title ).attr( 'title', activity_title );
				tbody.find( 'tr:last :input' ).val( '' );
				tbody.find( 'tr:last' ).closest( 'tr' ).data( 'activity_id', activity_id );
				tbody.find( 'tr:last' ).closest( 'tr' ).attr( 'data-activity_id', activity_id );
			}
		});
		
		// Remove the other rows
		tbody.find( 'tr' ).each( function() {
			var row = $j( this );
			if( ! row.data( 'activity_id' ) ) { $j( this ).remove(); return true; }
			var activity_id = row.data( 'activity_id' );
			if( $j.inArray( activity_id, activities ) === -1 ) {
				row.find( ':input' ).prop( 'disabled', true );
				row.hide();
			}
		});
	});
	
	
	/**
	 * Add / remove group category row in the "redirect URL" table according to the currently selected group categories
	 * @since 1.7.0
	 */
	$j( '#bookacti-form-field-dialog-calendar' ).on( 'change', 'select#bookacti-group_categories', function( e ){
		var group_categories = $j( this ).val();
		
		// If no group category is selected, hide the group categories actions table
		var was_displayed = $j( '.bookacti-group-categories-actions-options-table' ).is( ':visible' );
		if( group_categories === 'none' ) {
			$j( '.bookacti-group-categories-actions-options-table' ).hide();
			$j( '.bookacti-group-categories-actions-options-table :input' ).prop( 'disabled', true );
		}
		
		// If no group_categories, leave as is.
		if( ! group_categories || ( ! $j.isArray( group_categories ) && ! $j.isNumeric( group_categories ) ) ) { return; }
		if( ! $j.isArray( group_categories ) ) { group_categories = [ group_categories ]; }
		
		// Display the group categories actions table
		if( was_displayed ) { $j( '.bookacti-group-categories-actions-options-table' ).show(); }
		$j( '.bookacti-group-categories-actions-options-table :input' ).prop( 'disabled', false );
		
		var tbody = $j( '.bookacti-group-categories-actions-options-table tbody' );
		
		// Add corresponding rows
		$j.each( group_categories, function( i, category_id ) {
			var group_category_title = $j( 'option#bookacti-group_categories_' + category_id ).text();
			// If the row already exists, leave it as is
			if( tbody.find( '.bookacti-column-redirect_url input[name="redirect_url_by_group_category[' + category_id + ']"]' ).length ) {
				var row = tbody.find( '.bookacti-column-redirect_url input[name="redirect_url_by_group_category[' + category_id + ']"]' ).closest( 'tr' );
				row.data( 'category_id', category_id );
				row.attr( 'data-category_id', category_id );
				row.find( '.bookacti-column-group_category' ).text( group_category_title ).attr( 'title', group_category_title );
				row.find( ':input' ).prop( 'disabled', false );
				row.show();
				return true; // continue
			// If the row doesn't exists, create it
			} else {
				tbody.find( 'tr:first' ).clone().appendTo( tbody );
				tbody.find( 'tr:last :input' ).each( function() {
					var field_name_raw	= $j( this ).attr( 'name' );
					var field_name		= field_name_raw.substring( 0, field_name_raw.lastIndexOf( '[' ) );
					$j( this ).attr( 'name', field_name + '[' + category_id + ']' );
				});
				tbody.find( 'tr:last td.bookacti-column-group_category' ).text( group_category_title ).attr( 'title', group_category_title );
				tbody.find( 'tr:last :input' ).val( '' );
				tbody.find( 'tr:last' ).closest( 'tr' ).data( 'category_id', category_id );
				tbody.find( 'tr:last' ).closest( 'tr' ).attr( 'data-category_id', category_id );
			}
		});
		
		// Remove the other rows
		tbody.find( 'tr' ).each( function() {
			var row = $j( this );
			if( ! row.data( 'category_id' ) ) { $j( this ).remove(); return true; }
			var category_id = row.data( 'category_id' );
			if( $j.inArray( category_id, group_categories ) === -1 ) {
				row.find( ':input' ).prop( 'disabled', true );
				row.hide();
			}
		});
	});
	
	
	/**
	 * Toggle the actions fields according to the currently selected form action
	 * @since 1.7.0
	 */
	$j( '#bookacti-form-field-dialog-calendar' ).on( 'change', 'select#bookacti-form_action', function( e ) {
		// Show / hide when to perform the form action field
		if( $j( this ).val() === 'default' ) {
			$j( '.bookacti-when-perform-form-action-container' ).hide();
		} else {
			$j( '.bookacti-when-perform-form-action-container' ).show();
		}
		
		// Show / hide the columns displayed in the "redirect URL" tables
		$j( '.bookacti-activities-actions-options-table, .bookacti-group-categories-actions-options-table' ).show();
		if( $j( this ).val() === 'default' ) {
			$j( '.bookacti-activities-actions-options-table, .bookacti-group-categories-actions-options-table' ).hide();
		} else if( $j( this ).val() === 'redirect_to_url' ) {
			$j( '.bookacti-activities-actions-options-table .bookacti-column-redirect_url, .bookacti-group-categories-actions-options-table .bookacti-column-redirect_url' ).show();
		}
		// Always hide categories table if no categories are selected
		if( $j( 'select#bookacti-group_categories' ).val() === 'none' ) { $j( '.bookacti-group-categories-actions-options-table' ).hide(); }
	});
	
	
	/**
	 * Rerender field HTML after settings update
	 * @since 1.5.0
	 * @version 1.6.0
	 */
	$j( '#bookacti-form-editor' ).on( 'bookacti_field_updated bookacti_field_reset', function( e, field_id, field_name ){
		if( field_name === 'calendar' ) {
			var booking_system		= $j( '#bookacti-form-editor-field-' + field_id + ' .bookacti-booking-system' );
			var booking_system_id	= booking_system.attr( 'id' );
			
			// Clear booking system
			booking_system.empty();
			bookacti_clear_booking_system_displayed_info( booking_system );

			// Reload booking system
			bookacti.booking_system[ booking_system_id ] = [];
			bookacti.booking_system[ booking_system_id ] = $j.extend( true, {}, bookacti.form_editor.fields[ field_id ] ); // Clone field data, else changing booking_system data will change field data
			
			bookacti_reload_booking_system( booking_system );
		}
		
		else if( field_name === 'login' ) {
			var login_field_container = $j( '#bookacti-form-editor .bookacti-form-field-container.bookacti-form-field-type-login' );
			bookacti_show_hide_register_fields( login_field_container );
		}
	});
	
	// Confirm before leaving if the form isn't published
	$j( window ).on( 'beforeunload', function( e ){
		if( $j( '#major-publishing-actions' ).data( 'popup' ) ) { return true; } // Confirm before redirect
		else { e = null; } // Redirect
	});
	
	
	/**
	 * If an error occurs, stop loading and allow every interactions
	 * @since 1.7.0
	 * @param {string} errorMsg
	 * @param {string} url
	 * @param {int} lineNumber
	 * @param {int} column
	 * @param {Error} errorObj
	 */
	window.onerror = function ( errorMsg, url, lineNumber, column, errorObj ) {
		$j( '#bookacti-fatal-error' ).show();
	};
	$j( '#bookacti-exit-loading' ).on( 'click', function(){
		bookacti_form_editor_exit_loading_state();
		var booking_system = $j( '#bookacti-booking-system-form-editor-container .bookacti-booking-system' );
		if( booking_system.length ) {
			bookacti_stop_loading_booking_system( booking_system, true );
		}
	});
});


/**
 * Save form data
 * @since 1.5.0
 */
function bookacti_save_form() {
	// Select all form managers
	$j( '#bookacti-form-managers-select-box option' ).prop( 'selected', true );
	
	// Move form editor outside the <form> before serialize
	$j( '#bookacti-form-editor-container' ).appendTo( '#bookacti-form-editor-page-container' );
	
	// Serialize form values
	var form		= $j( 'form#bookacti-form-editor-page-form' );
	var is_active	= form.find( 'input[name="is_active"]' ).val();
	var data		= form.serialize();
	
	// Move form editor back inside the <form> after serialize
	$j( '#bookacti-form-editor-container' ).appendTo( '#postdivrich' );
	
	// Display spinner
	$j( '#publishing-action .spinner' ).css( 'visibility', 'visible' );
	bookacti_form_editor_enter_loading_state();

	// Save the new form in database
	$j.ajax({
		url: ajaxurl, 
		data: data, 
		type: 'POST',
		dataType: 'json',
		success: function( response ){

			// Remove current notices about the form
			$j( '.bookacti-form-notice' ).remove();

			if( response.status === 'success' ) {
				
				$j( 'body' ).trigger( 'bookacti_form_updated' );
				
				// If the form was inactive, redirect
				if( is_active == 0 ) { 
					$j( '#major-publishing-actions' ).data( 'popup', 0 ); // Required, else a confirm pop-up will appear
					window.location.replace( form.attr( 'action' ) + '&notice=published' ); 
				}
				
				// Else, Display feedback
				else { $j( '#bookacti-form-editor-page-container' ).before( '<div class="notice notice-success is-dismissible bookacti-form-notice" ><p>' + response.message + '</p></div>' ); }
				
			} else if( response.status === 'failed' ) {
				var error_message = bookacti_localized.error_update_form;
				if( response.error === 'not_allowed' ) {
					error_message += '\n' + bookacti_localized.error_not_allowed;
				}

				// Display feedback
				$j( '#bookacti-form-editor-page-container' ).before( '<div class="notice notice-error is-dismissible bookacti-form-notice" ><p>' + error_message + '</p></div>' );

				console.log( response );
			}
		},
		error: function( e ){
			var error_message = 'AJAX ' + bookacti_localized.error_update_form;

			// Display feedback
			$j( '#bookacti-form-editor-page-container' ).before( '<div class="notice notice-error is-dismissible bookacti-form-notice" ><p>' + error_message + '</p></div>' );

			console.log( e );
		},
		complete: function() { 
			// Stop the spinner
			$j( '#publishing-action .spinner' ).css( 'visibility', 'hidden' );
			bookacti_form_editor_exit_loading_state();
		}
	});
}


/**
 * Save field order
 * @since 1.5.0
 */
function bookacti_save_form_field_order() {
	var form_id = $j( '#bookacti-form-id' ).val();
	
	if( ! $j.isNumeric( form_id ) ) { return; }
	
	// Get field in document order
	var field_order = [];
	$j( '.bookacti-form-editor-field' ).each( function(){
		field_order.push( $j( this ).data( 'field-id' ) );
	});
	
	if( ! field_order.length ) { return; }
	
	var nonce = $j( '#bookacti_nonce_form_field_order' ).val();
	var data = {
			'action': 'bookactiSaveFormFieldOrder',
			'form_id': form_id,
			'field_order': field_order,
			'nonce': nonce
		};
	
	bookacti_form_editor_enter_loading_state();
	
	// Save the new field order in database
	$j.ajax({
		url: ajaxurl, 
		data: data, 
		type: 'POST',
		dataType: 'json',
		success: function( response ){
			
			if( response.status === 'success' ) {
				bookacti.form_editor.form.field_order = response.field_order;
				
				$j( '#bookacti-form-editor' ).trigger( 'bookacti_form_field_order_updated' );
				
			} else if( response.status === 'failed' ) {
				var error_message = bookacti_localized.error_order_form_fields;
				if( response.error === 'not_allowed' ) {
					error_message += '\n' + bookacti_localized.error_not_allowed;
				}
				console.log( error_message );
				console.log( response );
			}
		},
		error: function( e ){
			var error_message = 'AJAX ' + bookacti_localized.error_order_form_fields;

			console.log( error_message );
			console.log( e );
		},
		complete: function() { bookacti_form_editor_exit_loading_state(); }
	});
}


function bookacti_form_editor_enter_loading_state() {
	$j( '.bookacti-form-editor-action, .bookacti-form-editor-field-action' ).addClass( 'bookacti-disabled' );
}


function bookacti_form_editor_exit_loading_state() {
	$j( '.bookacti-form-editor-action, .bookacti-form-editor-field-action' ).removeClass( 'bookacti-disabled' );
}