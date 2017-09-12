// Init calendar
function bookacti_set_calendar_up( booking_system, reload_events ) {
	
	reload_events = reload_events ? 1 : 0;
	
	var booking_system_id	= booking_system.attr( 'id' );
	var calendar			= booking_system.find( '.bookacti-calendar:first' );
	
	calendar.fullCalendar({

		// Header : Functionnality to Display above the calendar
		header:  {
			left: 'prev,next today',
			center: 'title',
			right: 'month,agendaWeek,agendaDay'
		},

		// OPTIONS
		locale:					bookacti_localized.current_lang_code,
		
		defaultView:            'agendaWeek',
		weekNumbersWithinDays:	1,
		allDaySlot:             false,
		allDayDefault:          false,
		fixedWeekCount:         false,
		contentHeight:			'auto',
		editable:               false,
		droppable:              false,
		eventDurationEditable:  false,
		showNonCurrentDates:	false,
		eventLimit:             2,
		eventLimitClick:        'popover',
		dragRevertDuration:     0,
		slotDuration:           '00:30',
		minTime:                '08:00',
		maxTime:                '20:00',
		
		views: { 
			week:		{ eventLimit: false }, 
			day:		{ eventLimit: false },
			listDay:	{ buttonText: bookacti_localized.calendar_button_list_day },
			listWeek:	{ buttonText: bookacti_localized.calendar_button_list_week },
			listMonth:	{ buttonText: bookacti_localized.calendar_button_list_month },
			listYear:	{ buttonText: bookacti_localized.calendar_button_list_year } 
		},

		//Load an empty array to allow the callback 'loading' to work
		events: function( start, end, timezone, callback ) {
			var empty_array = [];
			callback( empty_array );
		},

		viewRender: function( view ){
		},

		// When an event is rendered
		eventRender: function( event, element, view ) { 

			// Add some info to the event
			element.data( 'event-id',			event.id );
			element.attr( 'data-event-id',		event.id );
			element.data( 'event-start',		event.start.format( 'YYYY-MM-DD HH:mm:ss' ) );
			element.attr( 'data-event-start',	event.start.format( 'YYYY-MM-DD HH:mm:ss' ) );
			element.data( 'event-end',			event.end.format( 'YYYY-MM-DD HH:mm:ss' ) );
			element.attr( 'data-event-end',		event.end.format( 'YYYY-MM-DD HH:mm:ss' ) );
			element.data( 'activity-id',		event.activity_id );
			element.attr( 'data-activity-id',	event.activity_id );
			event.render = 1;
			
			if( view.name.indexOf( 'basic' ) > -1 || view.name.indexOf( 'month' ) > -1 ){
				element.find( 'span.fc-time' ).text( event.start.format( 'HH:mm' ) + ' - ' + event.end.format( 'HH:mm' ) );
			}			
			
			// Add availability div
			if( event.bookings !== undefined && event.availability !== undefined ) {

				var is_available = bookacti_is_event_available( booking_system, event );
				
				// If the event or its group is not available, disable the event
				if( ! is_available ) {
					element.addClass( 'bookacti-event-unavailable' );
				}
				
				var avail_div = bookacti_get_event_availability_div( booking_system, event );
				element.append( avail_div );
			}
			
			// Add background to basic views
			if( view.name === 'month' || view.name === 'basicWeek' || view.name === 'basicDay' ) {
				var bg_div = $j( '<div />', {
					class: 'fc-bg'
				});
				element.append( bg_div );
			}
			
			booking_system.trigger( 'bookacti_event_render', [ event, element, view ] );
			
			if( ! event.render ) { return false; }
		},
		
		eventAfterRender: function( event, element, view ) { 
			bookacti_add_class_according_to_event_size( element );
		},
		

		eventAfterAllRender: function( view ) {
			//Display element as picked or selected if they actually are
			$j.each( bookacti.booking_system[ booking_system_id ][ 'picked_events' ], function( i, picked_event ) {
				calendar.find( '.fc-event[data-event-id="' + picked_event[ 'id' ] + '"][data-event-start="' + picked_event[ 'start' ] + '"]' ).addClass( 'bookacti-picked-event' );
			});
			
			bookacti_refresh_picked_events_on_calendar( booking_system );
		},

		// eventClick : When an event is clicked
		eventClick: function( event, jsEvent, view ) {
			bookacti_event_click( booking_system, event );
		}

	}); 
	
	// Update calendar settings
	bookacti_update_calendar_settings( calendar, bookacti.booking_system[ booking_system_id ][ 'settings' ] );
	
	// Load events on calendar
	if( ! reload_events && bookacti.booking_system[ booking_system_id ][ 'events' ].length ) {
		// Fill calendar with events already fetched
		bookacti_fill_calendar_with_events( booking_system );
		
	} else if( reload_events ) {
		// Fetch events from database
		bookacti_fetch_events( booking_system );
		
	} else if( ! bookacti.booking_system[ booking_system_id ][ 'events' ].length ) {
		// If no events are bookable, display an error
		bookacti_add_error_message( booking_system, bookacti_localized.error_no_events_bookable );
	}
	
	// Refresh the display of selected events when you click on the View More link
	calendar.off( 'click', '.fc-more' ).on( 'click', '.fc-more', function(){
		bookacti_refresh_picked_events_on_calendar( booking_system );
	});
	
	// Init on pick events actions
	booking_system.off( 'bookacti_pick_event' ).on( 'bookacti_pick_event', function( e, picked_event ){
		bookacti_pick_event_on_calendar( $j( this ), picked_event );
	});
	booking_system.off( 'bookacti_unpick_event' ).on( 'bookacti_unpick_event', function( e, event_to_unpick, all ){
		bookacti_unpick_event_on_calendar( $j( this ), event_to_unpick, all );
	});
	booking_system.off( 'bookacti_unpick_all_events' ).on( 'bookacti_unpick_all_events', function(){
		bookacti_unpick_all_events_on_calendar( $j( this ) );
	});
	
	booking_system.trigger( 'bookacti_after_calendar_set_up' );
}


// Fill calendar with events
function bookacti_fill_calendar_with_events( booking_system ) {
	
	var booking_system_id = booking_system.attr( 'id' );
	var calendar = booking_system.find( '.bookacti-calendar:first' );
	
	// Empty the calendar
	calendar.fullCalendar( 'removeEvents' );
	
	// Add events on calendar
	calendar.fullCalendar( 'addEventSource', bookacti.booking_system[ booking_system_id ][ 'events' ] );
	
	// Set calendar period
	bookacti_set_calendar_period( booking_system );
}


// Pick event visually on calendar
function bookacti_pick_event_on_calendar( booking_system, picked_event ) {
	
	var start = picked_event.start instanceof moment ? picked_event.start.format( 'YYYY-MM-DD HH:mm:ss' ) : picked_event.start;
	
	// Because of popover and long events (spreading on multiple days), 
	// the same event can appears twice, so we need to apply changes on each
	var elements = booking_system.find( '.fc-event[data-event-id="' + picked_event.id + '"][data-event-start="' + start + '"]' );
	
	// Format the pciked event (because of popover, the same event can appears twice)
	elements.addClass( 'bookacti-picked-event' );
}


// Unpick event visually on calendar
function bookacti_unpick_event_on_calendar( booking_system, event_to_unpick, all ) {
	
	var start = event_to_unpick.start instanceof moment ? event_to_unpick.start.format( 'YYYY-MM-DD HH:mm:ss' ) : event_to_unpick.start;
	
	// Because of popover and long events (spreading on multiple days), 
	// the same event can appears twice, so we need to apply changes on each
	var elements = booking_system.find( '.fc-event[data-event-id="' + event_to_unpick.id + '"]' );
	if( ! all && event_to_unpick.start ) {
		elements = booking_system.find( '.fc-event[data-event-id="' + event_to_unpick.id + '"][data-event-start="' + start + '"]' );
	}
	
	// Format the picked event(s)
	elements.removeClass( 'bookacti-picked-event' );
}


// Unpick all events visually on calendar
function bookacti_unpick_all_events_on_calendar( booking_system ) {
	booking_system.find( '.bookacti-picked-event' ).removeClass( 'bookacti-picked-event' );
}


// Make sure picked events appears as picked and vice-versa
function bookacti_refresh_picked_events_on_calendar( booking_system ) {
	
	var booking_system_id = booking_system.attr( 'id' );
	
	bookacti_unpick_all_events_on_calendar( booking_system );

	$j.each( bookacti.booking_system[ booking_system_id ][ 'picked_events' ], function( i, picked_event ) {
		var element = booking_system.find( '.fc-event[data-event-id="' + picked_event.id + '"][data-event-start="' + picked_event.start + '"]' );
		// Format picked events
		element.addClass( 'bookacti-picked-event' );
	});
	
	booking_system.trigger( 'bookacti_refresh_picked_events_on_calendar' );
}


//Get calendar period
function bookacti_set_calendar_period( booking_system, refresh ) {
	
	// Sanitize params
	refresh = typeof refresh === 'undefined' ? 1 : parseInt( refresh );
	
	
	// Init variables
	var calendar			= booking_system.find( '.bookacti-calendar' );
	var booking_system_id	= booking_system.attr( 'id' );
	var new_start_template	= false;
	var new_end_template	= false;
	
	var is_template_range	= false;
	var template_range		= calendar.fullCalendar( 'option', 'validRange' );
	if( typeof template_range === 'object' 
	&&  template_range.start instanceof moment 
	&&  template_range.end instanceof moment  ) {
		var start_template	= template_range.start;
		var end_template	= template_range.end.subtract( 1, 'days' );
		is_template_range	= true;
	}
	
	bookacti.booking_system[ booking_system_id ][ 'events' ] = bookacti_sort_events_array_by_dates( bookacti.booking_system[ booking_system_id ][ 'events' ] );
	var is_event_range = false;
	if( bookacti.booking_system[ booking_system_id ][ 'events' ].length > 0 ) {
		var start_first_event	= moment( bookacti.booking_system[ booking_system_id ][ 'events' ][ 0 ][ 'start' ] );
		var end_last_event		= moment( bookacti.booking_system[ booking_system_id ][ 'events' ][ bookacti.booking_system[ booking_system_id ][ 'events' ].length - 1][ 'end' ] );
		is_event_range		= true;
	}
	
	// Choose between template start VS first event, and template end VS last event
	if( is_template_range && is_event_range ) {
		// On booking page, always show all booked event, even outside of templates range
		if( booking_system_id === 'bookacti-booking-system-bookings-page' ) {
			new_start_template	= start_first_event;
			new_end_template	= end_last_event;
			
		} else {
			
			// If template start < event start,	keep event start, 
			// If template start > event start,	keep template start,
			if( start_template.isBefore( start_first_event, 'day' ) ) {
				new_start_template	= start_first_event;
			} else {
				new_start_template	= start_template;
			}

			// If template end < event end,	keep template end, 
			// If template end > event end,	keep event end
			if( end_template.isBefore( end_last_event, 'day' ) ) {
				new_end_template	= end_template;
			} else {
				new_end_template	= end_last_event;
			}
		}
		
	// If template range or event range is missing, just keep the existing one
	} else if( ! is_template_range && is_event_range ) {
		new_start_template	= start_first_event;
		new_end_template	= end_last_event;
	} else if( is_template_range && ! is_event_range ) {
		new_start_template	= start_template;
		new_end_template	= end_template;
	}
	
	// If kept start < now and ! fetch_past_event,	keep now date
	if( new_start_template && ! bookacti.booking_system[ booking_system_id ][ 'past_events' ] && new_start_template.isBefore( moment(), 'day' ) ) {
		new_start_template = moment();
	}
	
	// Format range
	if( new_start_template ) {
		new_start_template	= new_start_template.format( 'YYYY-MM-DD' );
	}
	if( new_end_template ) {
		new_end_template	= new_end_template.format( 'YYYY-MM-DD' );
	}
	
	if( bookacti.booking_system[ booking_system_id ][ 'period' ] === undefined ) {
		bookacti.booking_system[ booking_system_id ][ 'period' ] = [];
	}

	bookacti.booking_system[ booking_system_id ][ 'period' ][ 'start' ]	= new_start_template;
	bookacti.booking_system[ booking_system_id ][ 'period' ][ 'end' ]	= new_end_template;

	if( refresh ) {
		bookacti_refresh_calendar_view( booking_system );
	}
}


//Refresh calendar view
function bookacti_refresh_calendar_view( booking_system ) {
	
	var booking_system_id	= booking_system.attr( 'id' );
	var calendar			= booking_system.find( '.bookacti-calendar' );
	
	if( bookacti.booking_system[ booking_system_id ][ 'period' ] !== undefined ) {

		var start_template	= bookacti.booking_system[ booking_system_id ][ 'period' ][ 'start' ];
		var end_template	= bookacti.booking_system[ booking_system_id ][ 'period' ][ 'end' ];

		var start, end = '';
		if( start_template && end_template ) {
			start = moment( start_template );
			end = moment( end_template );
		}

		if( start !== '' && end !== '' && start <= end && start_template && end_template ) {

			calendar.show();

			booking_system.siblings( '.bookacti-notices ul:not(.bookacti-persistent-notice)' ).remove();

			bookacti_refresh_view( calendar, start, end );

			booking_system.trigger( 'bookacti_view_refreshed' );

		} else {

			bookacti_add_error_message( booking_system, bookacti_localized.error_no_events_bookable );
		}
	}
}

//Refresh view after a change of start and end
function bookacti_refresh_view( calendar, start_template, end_template ) {
	// Update calendar valid range 
	calendar.fullCalendar( 'option', 'validRange', {
		start: start_template,
		end: end_template.add( 1, 'days' )
	});
}


// Add class for formatting
function bookacti_add_class_according_to_event_size( element ) {
	
	var custom_size = bookacti.event_sizes;
	
	$j( element ).trigger( 'bookacti_event_sizes', [ element, custom_size ] );
	
	if( $j( element ).innerHeight() < custom_size.tiny_height )	{ element.addClass( 'bookacti-tiny-event' ); }
	if( $j( element ).innerHeight() < custom_size.small_height ){ element.addClass( 'bookacti-small-event' ); }
	if( $j( element ).innerWidth() < custom_size.narrow_width )	{ element.addClass( 'bookacti-narrow-event' ); }
	if( $j( element ).innerWidth() > custom_size.wide_width )	{ element.addClass( 'bookacti-wide-event' ); element.removeClass( 'fc-short' ); }
}


// Dynamically update calendar settings
function bookacti_update_calendar_settings( calendar, settings ) {
	
	var settings_to_update = {};
	
	if( settings.start && settings.end ) {
		settings_to_update.validRange = {
            start: moment( settings.start ),
            end: moment( settings.end ).add( 1, 'days' )
        };
	}
	
	if( settings.minTime )	{ settings_to_update.minTime	= settings.minTime; }
	if( settings.maxTime )	{ settings_to_update.maxTime	= settings.maxTime === '00:00' ? '24:00' : settings.maxTime; }	
	
	calendar.trigger( 'bookacti_before_update_calendar_settings', [ settings_to_update, settings ] );
	
	if( ! $j.isEmptyObject( settings_to_update ) ) {
		calendar.fullCalendar( 'option', settings_to_update );
	}
	
	calendar.fullCalendar( 'option', 'validRange', settings_to_update.validRange );
	
	calendar.trigger( 'bookacti_calendar_settings_updated', [ settings_to_update, settings ] );
}


//Enter loading state and prevent user from doing anything else
function bookacti_enter_calendar_loading_state( calendar ) {
	calendar.find( '.fc-toolbar button' ).addClass( 'fc-state-disabled' ).attr( 'disabled', true );
	bookacti_append_loading_overlay( calendar.find( '.fc-view-container' ) );
}


//Exit loading state and allow user to keep editing templates
function bookacti_exit_calendar_loading_state( calendar ) {
	calendar.find( '.fc-toolbar button' ).removeClass( 'fc-state-disabled' ).attr( 'disabled', false );
	bookacti_remove_loading_overlay( calendar.find( '.fc-view-container' ) );
}


// Append loading overlay
function bookacti_append_loading_overlay( element ) {
	element.append(
		'<div class="bookacti-loading-overlay" >'
			+ '<div class="bookacti-loading-content" >'
				+ '<div class="bookacti-loading-box" >'
					+ '<div class="bookacti-loading-image" >'
						+ '<img class="bookacti-loader" src="' + bookacti_localized.plugin_path + '/img/ajax-loader.gif" title="' + bookacti_localized.loading + '" />'
					+ '</div>' 
					+ '<div class="bookacti-loading-text" >'
						+ bookacti_localized.loading
					+ '</div>' 
				+ '</div>' 
			+ '</div>' 
		+ '</div>'
	).css( 'position', 'relative' );
}


// Remove loading overlay
function bookacti_remove_loading_overlay( element ) {
	element.find( '.bookacti-loading-overlay' ).remove().css( 'position', 'static' );
}