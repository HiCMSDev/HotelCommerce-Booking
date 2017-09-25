<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }


// GENERAL
	
	/**
	 * Register an hourly reccuring event (used to clean expired bookings)
	 */
	function bookacti_register_houly_clean_expired_bookings() {
		wp_schedule_event( time(), 'hourly', 'bookacti_hourly_event' );
	}
	add_action( 'woocommerce_installed', 'bookacti_register_houly_clean_expired_bookings' );
	add_action( 'bookacti_activate', 'bookacti_register_houly_clean_expired_bookings' );

	
	/**
	 * Deregister the hourly reccuring event (to be called on wp_roles_init)
	 */
	function bookacti_clear_houly_clean_expired_bookings_on_woocommerce_uninstall() {
		if( defined( 'WP_UNINSTALL_PLUGIN' ) && WP_UNINSTALL_PLUGIN === 'woocommerce/woocommerce.php' ) {
			bookacti_cancel_in_cart_bookings();
			bookacti_clear_houly_clean_expired_bookings();
		}
	}
	add_action( 'wp_roles_init', 'bookacti_clear_houly_clean_expired_bookings_on_woocommerce_uninstall' );
	
	
	/**
	 * Deregister the hourly reccuring event
	 */
	function bookacti_clear_houly_clean_expired_bookings() {
		wp_clear_scheduled_hook( 'bookacti_hourly_event' );
	}
	add_action( 'bookacti_deactivate', 'bookacti_clear_houly_clean_expired_bookings' );
	
	
	/**
	 * Add 'in_cart' state to active states
	 * 
	 * @param array $active_states
	 * @return array
	 */
	function bookacti_add_woocommerce_active_booking_states( $active_states ) {
		$active_states[] = 'in_cart';
		return $active_states;
	}
	add_filter( 'bookacti_active_booking_states', 'bookacti_add_woocommerce_active_booking_states' );
	
	
	/**
	 * Add booking states labels related to cart
	 * 
	 * @param array $labels
	 * @return array
	 */
	function bookacti_add_in_cart_state_label( $labels ) {
		$labels[ 'in_cart' ] =  array( 'display_state' => 'warning',	'label' => __( 'In cart', BOOKACTI_PLUGIN_NAME ) );
		$labels[ 'expired' ] =  array( 'display_state' => 'bad',		'label' => __( 'Expired', BOOKACTI_PLUGIN_NAME ) );
		$labels[ 'removed' ] =  array( 'display_state' => 'bad',		'label' => __( 'Removed', BOOKACTI_PLUGIN_NAME ) );
		
		return $labels;
	}
	add_filter( 'bookacti_booking_states_labels_array', 'bookacti_add_in_cart_state_label', 10, 1 );
	
	
	/**
	 * Deactivate expired bookings with cron (to be called with wp_schedule_event())
	 *
	 * @since	1.0.6
	 * @version	1.1.0
	 */
	function bookacti_controller_deactivate_expired_bookings() {

		$deactivated_ids = bookacti_deactivate_expired_bookings();

		if( $deactivated_ids === false ) { 
			/* translators: 'cron' is a robot that execute scripts every X hours. Don't try to translate it. */
			$log = esc_html__( 'The expired bookings were not correctly deactivated by cron.', BOOKACTI_PLUGIN_NAME );
			bookacti_log( $log, 'error' );
		}
	}
	add_action( 'bookacti_hourly_event', 'bookacti_controller_deactivate_expired_bookings' );
	
	
	/**
	 * Change booking state to 'removed' or 'in_cart' depending on its quantity
	 * 
	 * @since 1.1.0
	 * 
	 * @param array $data
	 * @param object $booking
	 * @return array
	 */
	function bookacti_change_booking_state_to_removed_or_in_cart_depending_on_its_quantity( $data, $booking ) {
		
		// If quantity is null, change booking state to 'removed' and keep the booking quantity
		if( $data[ 'quantity' ] <= 0 ) { 
			
			$data[ 'state' ]	= $data[ 'context' ] === 'frontend' ? 'removed' : 'cancelled'; 
			$data[ 'quantity' ]	= intval( $booking->quantity ); 
			$data[ 'active' ]	= 0; 
		
		// If the booking was removed and its quantity is raised higher than 0, turn its state back to 'in_cart'
		} else if( $booking->state === 'removed' ) {
			
			$data[ 'state' ]	= $data[ 'context' ] === 'frontend' ? 'in_cart' : 'pending'; 
			$data[ 'active' ]	= 1; 
		}
		
		return $data;
	}
	add_filter( 'bookacti_update_booking_quantity_data', 'bookacti_change_booking_state_to_removed_or_in_cart_depending_on_its_quantity', 10, 2 );
	
	
	

// ORDER AND BOOKING STATUS
	
	/**
	 * Turn a temporary booking to permanent if order gets complete
	 * 
	 * @param int $order_id
	 */
	function bookacti_turn_temporary_booking_to_permanent( $order_id ) {
		
		//Change state of all bookings of the order from 'pending' to 'booked'
		bookacti_turn_order_bookings_to( $order_id, 'booked', true );
		
		//It is possible that pending bookings remain bound to the order if the user change his mind after he placed the order, but before he paid it.
		//He then changed his cart, placed a new order, paid it, and only part of the old order is booked (or even nothing), the rest is still 'pending'
		//Then we just turn 'pending' booking bound to this order to 'cancelled'
		bookacti_cancel_order_pending_bookings( $order_id );
	}
	add_action( 'woocommerce_order_status_completed', 'bookacti_turn_temporary_booking_to_permanent', 10, 1 );
	
	
	/**
	 * Cancel the temporary booking if it failed
	 * 
	 * @param int $order_id
	 */
	function bookacti_cancelled_order( $order_id ) {
		
		//Change state of all bookings of the order to 'cancelled' and free the bookings
		bookacti_turn_order_bookings_to( $order_id, 'cancelled', false );
		
		// It is possible that 'pending' bookings remain if the user has changed his cart before payment, we must cancel them
		bookacti_cancel_order_pending_bookings( $order_id );
	}
	add_action( 'woocommerce_order_status_cancelled', 'bookacti_cancelled_order', 10, 1 );
	
	
	/**
	 * Turn paid order status to complete if the order has only activities
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * 
	 * @param string $order_status
	 * @param int $order_id
	 * @return string
	 */
	function bookacti_set_order_status_to_completed_after_payment( $order_status, $order_id ) {
		
		if( $order_status === 'processing' || $order_status === 'pending' ) {
		
			$order = wc_get_order( $order_id );
			if( ! empty( $order ) ) {

				// Retrieve bought items
				$items = $order->get_items();

				// Determine if the order has at least 1 activity
				$has_activities = false;
				foreach( $items as $item ) {
					if( isset( $item[ 'bookacti_booking_id' ] ) || isset( $item[ 'bookacti_booking_group_id' ] ) ) {
						$has_activities = true;
						break;
					}
				}
				
				// Determine if the order is only composed of activities
				$are_activities = true;
				foreach( $items as $item ) {
					if( ! isset( $item[ 'bookacti_booking_id' ] ) && ! isset( $item[ 'bookacti_booking_group_id' ] )  ) {
						$are_activities = false;
						break;
					}
				}
				
				// If there are only activities, mark the order as 'completed' and 
				// a function hooked to woocommerce_order_status_completed will mark the activities as 'booked'
				if( $are_activities ) {
					return 'completed';
					
				// If there are at least one activity in the middle of other products, 
				// we won't mark the order as 'completed', but we still need to mark the activities as 'booked'
				} else if( $has_activities ) {
					bookacti_turn_temporary_booking_to_permanent( $order_id );
				}
			}
		}
		
		return $order_status;
	}
	add_filter( 'woocommerce_payment_complete_order_status', 'bookacti_set_order_status_to_completed_after_payment', 10, 2 );
	
	
	
	
// MY ACCOUNT
	
	/**
	 * Include dialogs related to bookings
	 * 
	 * @param int $order_id
	 */
	function bookacti_add_booking_dialogs( $order_id ){
		include_once( WP_PLUGIN_DIR . '/' . BOOKACTI_PLUGIN_NAME . '/view/view-bookings-dialogs.php' );
	}
	add_action( 'woocommerce_view_order', 'bookacti_add_booking_dialogs', 100, 1 );
	add_action( 'woocommerce_thankyou', 'bookacti_add_booking_dialogs', 100, 1 );
	
	
	/**
	 * Add actions html elements to booking rows
	 * 
	 * @param int $item_id
	 * @param WC_Order_item $item
	 * @param WC_Order $order
	 * @param boolean $plain_text
	 */
	function bookacti_add_actions_to_bookings( $item_id, $item, $order, $plain_text = true ) {
		if( $plain_text ) {
			if( isset( $item['bookacti_booking_id'] ) ) {
				echo bookacti_get_booking_actions_html( $item['bookacti_booking_id'], 'front', false, true );
			} else if( isset( $item['bookacti_booking_group_id'] ) ) {
				echo bookacti_get_booking_group_actions_html( $item['bookacti_booking_group_id'], 'front', false, true );
			}
		}
	}
	add_action( 'woocommerce_order_item_meta_end', 'bookacti_add_actions_to_bookings', 10, 4 );
	
	
	
	
// BOOKING LIST

	/**
	 * Fill booking list columns
	 * 
	 * @param array $booking_item
	 * @param object $booking
	 * @param WP_User $user
	 * @return array
	 */
	function bookacti_woocommerce_fill_booking_list_custom_columns( $booking_item, $booking, $user ) {
		
		if( $user ) {
			if( is_numeric( $booking->user_id ) && $user->billing_first_name && $user->billing_last_name ) {
				$customer = '<a '
							. ' href="' . esc_url( get_admin_url() . 'user-edit.php?user_id=' . $booking->user_id ) . '" '
							. ' target="_blank" '
							. ' >'
								. esc_html( $user->billing_first_name . ' ' . $user->billing_last_name )
						. ' </a>';
			}
		}
		
		$booking_item[ 'customer' ]	= $customer ? $customer : $booking_item[ 'customer' ];
		$booking_item[ 'email' ]	= $user->billing_email;
		$booking_item[ 'phone' ]	= $user->billing_phone;
		
		return $booking_item;
	}
	add_filter( 'bookacti_booking_list_booking_columns', 'bookacti_woocommerce_fill_booking_list_custom_columns', 20, 3 );
	
	
	/**
	 * Add columns to bookings list
	 * 
	 * @param array $columns
	 * @return array
	 */
	function bookacti_woocommerce_add_booking_list_custom_columns( $columns ) {
		
		$columns[ 'email' ] = __( 'Email', BOOKACTI_PLUGIN_NAME );
		$columns[ 'phone' ] = __( 'Phone', BOOKACTI_PLUGIN_NAME );
		
		return $columns;
	}
	add_filter( 'bookacti_booking_list_columns', 'bookacti_woocommerce_add_booking_list_custom_columns', 10, 1 );
	
	
	/**
	 * Reorder columns from booking list
	 * 
	 * @param array $columns_order
	 * @return array
	 */
	function bookacti_woocommerce_order_booking_list_custom_columns( $columns_order ) {
		
		$columns_order[ 24 ] = 'email';
		$columns_order[ 27 ] = 'phone';
		
		return $columns_order;
	}
	add_filter( 'bookacti_booking_list_columns_order', 'bookacti_woocommerce_order_booking_list_custom_columns', 10, 1 );
	
	
	/**
	 * Filter booking list before retrieving bookings
	 * 
	 * @param array $bookings_data
	 * @return array
	 */
	function bookacti_filter_bookings_list_before_retrieving_bookings( $bookings_data ) {
		$show_temporary_bookings = bookacti_get_setting_value_by_user( 'bookacti_bookings_settings', 'show_temporary_bookings' );
		if( intval( $show_temporary_bookings ) === 0 ) { $bookings_data[ 'state_not_in' ][] = 'in_cart'; }
		
		return $bookings_data;
	}
	add_filter( 'bookacti_get_bookings_data_for_bookings_list', 'bookacti_filter_bookings_list_before_retrieving_bookings', 10 );
	add_filter( 'bookacti_get_booking_groups_data_for_bookings_list', 'bookacti_filter_bookings_list_before_retrieving_bookings', 10 );




// BOOKING ACTIONS: CANCEL / REFUND / RESCHEDULE
	
	
	/**
	 * Whether to give the possibility to a user to cancel or reschedule a booking
	 * Also add woocommerce specifique actions
	 * 
	 * @since 1.1.0
	 * 
	 * @param array $booking_actions
	 * @param int $booking_id
	 * @return array
	 */
	function bookacti_display_actions_buttons_on_booking_items( $booking_actions, $booking_id ){
		$order_id = bookacti_get_booking_order_id( $booking_id );
		return bookacti_display_actions_buttons_on_items( $booking_actions, $order_id );
	}
	add_filter( 'bookacti_booking_actions', 'bookacti_display_actions_buttons_on_booking_items', 10, 2 );
	
	
	/**
	 * Whether to give the possibility to a user to cancel or reschedule a booking group
	 * Also add woocommerce specifique actions
	 * 
	 * @since 1.1.0
	 * 
	 * @param array $booking_actions
	 * @param int $booking_group_id
	 * @return array
	 */
	function bookacti_display_actions_buttons_on_booking_group_items( $booking_actions, $booking_group_id ){
		$order_id = bookacti_get_booking_group_order_id( $booking_group_id );
		return bookacti_display_actions_buttons_on_items( $booking_actions, $order_id );
	}
	add_filter( 'bookacti_booking_group_actions', 'bookacti_display_actions_buttons_on_booking_group_items', 10, 2 );
	
	
	/**
	 * Filter refund actions by booking
	 * 
	 * @version 1.1.0
	 * 
	 * @param array $possible_actions
	 * @param int $booking_id
	 * @return array
	 */
	function bookacti_filter_refund_actions_by_booking( $possible_actions, $booking_id ) {
		$order_id = bookacti_get_booking_order_id( $booking_id );
		return bookacti_filter_refund_actions_by_order( $possible_actions, $order_id );
	}
	add_filter( 'bookacti_refund_actions_by_booking', 'bookacti_filter_refund_actions_by_booking', 10, 2 );
	
	
	/**
	 * Filter refund actions by booking group
	 * 
	 * @since 1.1.0
	 * 
	 * @param array $possible_actions
	 * @param int $booking_group_id
	 * @return array
	 */
	function bookacti_filter_refund_actions_by_booking_group( $possible_actions, $booking_group_id ) {
		$order_id = bookacti_get_booking_group_order_id( $booking_group_id );
		return bookacti_filter_refund_actions_by_order( $possible_actions, $order_id );
	}
	add_filter( 'bookacti_refund_actions_by_booking_group', 'bookacti_filter_refund_actions_by_booking_group', 10, 2 );
	
	
	/**
	 * Add price to be refunded in refund dialog
	 * 
	 * @param string $text
	 * @param int $booking_id
	 * @param string $booking_type
	 * @return string
	 */
	function bookacti_display_price_to_be_refunded( $text, $booking_id, $booking_type ) {
		
		if( $booking_type === 'single' ) {
			$item = bookacti_get_order_item_by_booking_id( $booking_id );
		} else if( $booking_type === 'group' ) {
			$item = bookacti_get_order_item_by_booking_group_id( $booking_id );
		}
		
		if( $item ) {
			$refund_amount = wc_price( (float) $item[ 'line_total' ] + (float) $item[ 'line_tax' ] );
			$text .= '<div id="bookacti-refund-amount">' . esc_html__( 'Refund amount:', BOOKACTI_PLUGIN_NAME ) . ' <strong>' . $refund_amount . '</strong></div>';
		}
		return $text;
	}
	add_filter( 'bookacti_before_refund_actions', 'bookacti_display_price_to_be_refunded', 10, 3 );
	
	
	/**
	 * Add WooCommerce related refund actions
	 * 
	 * @param array $possible_actions_array
	 * @return array
	 */
	function bookacti_add_woocommerce_refund_actions( $possible_actions_array ) {

		$possible_actions_array[ 'coupon' ] = array(
				'id'			=> 'coupon',
				'label'			=> __( 'Coupon', BOOKACTI_PLUGIN_NAME ),
				'description'	=> __( 'Create a coupon worth the price paid. The coupon can be used once for any orders at any time. ', BOOKACTI_PLUGIN_NAME )
			);
		$possible_actions_array[ 'auto' ] = array(
				'id'			=> 'auto',
				'label'			=> __( 'Auto refund', BOOKACTI_PLUGIN_NAME ),
				'description'	=> __( 'Refund automatically via the gateway used for payment.', BOOKACTI_PLUGIN_NAME )
			);

		return $possible_actions_array;
	}
	add_filter( 'bookacti_refund_actions', 'bookacti_add_woocommerce_refund_actions', 10, 1 );
	

	/**
	 * Turn order item booking status meta to new status according to given booking id
	 *
	 * @since 1.2.0 (was bookacti_woocommerce_turn_booking_meta_state_to_new_state before)
	 * 
	 * @param int $booking_id
	 * @param string $new_state
	 * @param array $args
	 */
	function bookacti_update_order_item_booking_status_by_booking_id( $booking_id, $new_state, $args = array() ) {
		
		if( ! $booking_id ) { return; }
		
		$order_id = bookacti_get_booking_order_id( $booking_id );
		
		if( ! $order_id ) { return; }
		
		$order = wc_get_order( $order_id );
		
		if( ! $order ) { return; }
		
		$item = bookacti_get_order_item_by_booking_id( $booking_id );
		
		bookacti_update_order_item_booking_status( $item, $new_state, $args );
	}
	add_action( 'bookacti_booking_state_changed', 'bookacti_update_order_item_booking_status_by_booking_id', 10 , 3 );
	
	
	/**
	 * Turn order item booking status meta to new status according to given booking group id
	 *
	 * @since 1.2.0 (was named bookacti_woocommerce_turn_booking_group_meta_state_to_new_state before)
	 * 
	 * @param int $booking_group_id
	 * @param string $new_state
	 * @param array $args
	 * @return void
	 */
	function bookacti_update_order_item_booking_group_status_by_booking_group_id( $booking_group_id, $new_state, $args = array() ) {
		
		if( ! $booking_group_id ) { return; }
		
		$order_id = bookacti_get_booking_group_order_id( $booking_group_id );
		
		if( ! $order_id ) {	return;	}
		
		$order = wc_get_order( $order_id );
		
		if( ! $order ) { return; }
		
		$item = bookacti_get_order_item_by_booking_group_id( $booking_group_id );
		
		if( ! $item || ! isset( $item[ 'bookacti_booking_group_id' ] ) ) { return; }
		
		bookacti_update_order_item_booking_status( $item, $new_state, $args );
	}
	add_action( 'bookacti_booking_group_state_changed', 'bookacti_update_order_item_booking_group_status_by_booking_group_id', 10 , 3 );
	
	
	/**
	 * Turn order items booking status meta to new status
	 *
	 * @since 1.2.0
	 * 
	 * @param int $order_id
	 * @param string $new_state
	 * @param array $args
	 */
	function bookacti_update_order_items_booking_status_by_order_id( $order_id, $new_state, $args ) {
		
		if( ! $order_id ) { return; }
		
		$order = wc_get_order( $order_id );
		
		if( ! $order ) { return; }
		
		$items = $order->get_items();
		
		if( ! $items ) { return; }
		
		foreach( $items as $key => $item ) {
			bookacti_update_order_item_booking_status( $item, $new_state, $args );
		}
	}
	add_action( 'bookacti_order_bookings_state_changed', 'bookacti_update_order_item_booking_status_by_order_id', 10, 2 );
	
	
	/**
	 * Trigger WooCommerce refund process according to the refund action
	 * 
	 * @version 1.1.0
	 * 
	 * @param array $return_array
	 * @param int $booking_id
	 * @param string $refund_action
	 * @param string $refund_message
	 * @return array
	 */
	function bookacti_woocommerce_refund_booking( $return_array, $booking_id, $booking_type, $refund_action, $refund_message ) {
		
		if( $booking_type === 'single' ) {
			$order_id = bookacti_get_booking_order_id( $booking_id );
			$possibles_actions = array_keys( bookacti_get_refund_actions_by_booking_id( $booking_id ) );
		} else if( $booking_type === 'group' ) {
			$order_id = bookacti_get_booking_group_order_id( $booking_id );
			$possibles_actions = array_keys( bookacti_get_refund_actions_by_booking_group_id( $booking_id ) );
		}
		
		if( in_array( $refund_action, $possibles_actions, true ) ) {
			if( $refund_action === 'coupon' ) {
				$return_array = bookacti_refund_booking_with_coupon( $booking_id, $booking_type, $refund_message );
			} else if( $refund_action === 'auto' && bookacti_does_order_support_auto_refund( $order_id ) ) {
				$return_array = bookacti_auto_refund_booking( $booking_id, $booking_type, $refund_message );
			}
		}
		
		return $return_array;
	}
	add_filter( 'bookacti_refund_booking', 'bookacti_woocommerce_refund_booking', 10 , 5 );
	
	
	/**
	 * Check if a booking can be refunded
	 * 
	 * @param boolean $true
	 * @param int $booking_id
	 * @return boolean
	 */
	function bookacti_woocommerce_booking_can_be_refunded( $true, $booking_id ) {
		
		if( $true && ! current_user_can( 'bookacti_edit_bookings' ) ) {
			// Init var
			$order_id	= bookacti_get_booking_order_id( $booking_id );
			$order		= wc_get_order( $order_id );
			if( $order ) {
				$item		= bookacti_get_order_item_by_booking_id( $booking_id );
				$is_paid	= get_post_meta( $order_id, '_paid_date', true );
				
				if( $order->get_status() === 'pending' 
				||  $item[ 'line_total' ] <= 0
				||  ! $is_paid ) { $true = false; }
			}
		}
		
		return $true;
	}
	add_filter( 'bookacti_booking_can_be_refunded', 'bookacti_woocommerce_booking_can_be_refunded', 10, 2 );
	
	
	/**
	 * Check if a booking group can be refunded
	 * 
	 * @param boolean $true
	 * @param int $booking_group_id
	 * @return boolean
	 */
	function bookacti_woocommerce_booking_group_can_be_refunded( $true, $booking_group_id ) {
		
		if( $true && ! current_user_can( 'bookacti_edit_bookings' ) ) {
			
			$order_id	= bookacti_get_booking_group_order_id( $booking_group_id );
			$order		= wc_get_order( $order_id );
			
			if( $order ) {
				$item		= bookacti_get_order_item_by_booking_group_id( $booking_group_id );
				$is_paid	= get_post_meta( $order_id, '_paid_date', true );
				
				if( $order->get_status() === 'pending' 
				||  $item[ 'line_total' ] <= 0
				||  ! $is_paid ) { $true = false; }
			}
		}
		
		return $true;
	}
	add_filter( 'bookacti_booking_group_can_be_refunded', 'bookacti_woocommerce_booking_group_can_be_refunded', 10, 2 );
	
	
	/**
	 * Check if a booking can be completed
	 * 
	 * @version 1.1.0
	 * 
	 * @param boolean $true
	 * @param int $booking_id
	 * @return boolean
	 */
	function bookacti_booking_state_can_be_changed_to_booked( $true, $booking_id, $new_state ) {
		
		if( $true && $new_state === 'booked' && ! current_user_can( 'bookacti_edit_bookings' ) ) {
			
			$order_id	= bookacti_get_booking_order_id( $booking_id );
			
			if( ! $order_id || ! bookacti_is_wc_order( $order_id ) ) { return $true; }
			
			$order = wc_get_order( $order_id );
			
			if( empty( $order ) ) { return false; }
			
			if( ! in_array( $order->get_status(), array( 'pending', 'processing', 'on-hold' ), true ) ) { $true = false; }
		}
		
		return $true;
	}
	add_filter( 'bookacti_booking_state_can_be_changed', 'bookacti_booking_state_can_be_changed_to_booked', 10, 3 );
	
	
	/**
	 * Check if a booking group can be completed
	 * 
	 * @since 1.1.0
	 * 
	 * @param boolean $true
	 * @param int $booking_id
	 * @return boolean
	 */
	function bookacti_booking_group_state_can_be_changed_to_booked( $true, $booking_group_id, $new_state ) {
		
		if( $true && $new_state === 'booked' && ! current_user_can( 'bookacti_edit_bookings' ) ) {
			
			$order_id = bookacti_get_booking_group_order_id( $booking_group_id );
			
			if( ! $order_id || ! bookacti_is_wc_order( $order_id ) ) { return $true; }
			
			$order = wc_get_order( $order_id );
			
			if( empty( $order ) ) { return false; }
			
			if( ! in_array( $order->get_status(), array( 'pending', 'processing', 'on-hold' ), true ) ) { $true = false; }
		}
		
		return $true;
	}
	add_filter( 'bookacti_booking_group_state_can_be_changed', 'bookacti_booking_group_state_can_be_changed_to_booked', 10, 3 );
	
	
	/**
	 * Refund request email to
	 * 
	 * @version 1.1.0
	 * 
	 * @param array $recipients
	 * @return array
	 */
	function bookacti_woocommerce_add_refund_request_email_recipients( $recipients ) {
		
		$recipients[] = get_option( 'woocommerce_stock_email_recipient' );
		
		return $recipients;
	}
	add_filter( 'bookacti_refund_request_email_to', 'bookacti_woocommerce_add_refund_request_email_recipients', 10 );
	
	
	/**
	 * Refund request email data
	 * 
	 * @version 1.1.0
	 * 
	 * @param array $data
	 * @param int $booking_id
	 * @param string $booking_type
	 * @return array
	 */
	function bookacti_woocommerce_add_refund_request_email_data( $data, $booking_id, $booking_type ) {
		
		if( $booking_type === 'single' ) {
			$item = bookacti_get_order_item_by_booking_id( $booking_id );
		} else if( $booking_type === 'group' ) {
			$item = bookacti_get_order_item_by_booking_group_id( $booking_id );
		}
		
		if( empty( $item ) ) { return $data; }
		
		$data['product']			= array();
		$data['product']['name']	= apply_filters( 'bookacti_translate_text', $item['name'] );
		if( $item['variation_id'] ){
			$variation	= new WC_Product_Variation( $item['variation_id'] );
			$attributes	= $variation->get_variation_attributes();
			$data['product']['attributes'] = implode( ' / ', $attributes );
		}
		$data['product']['price'] = wc_price( (float) $item['line_total'] + (float) $item['line_tax'] );
		
		return $data;
	}
	add_filter( 'bookacti_refund_request_email_data', 'bookacti_woocommerce_add_refund_request_email_data', 10, 3 );
	
	
	/**
	 * Refund request email message
	 * 
	 * @version 1.1.0
	 * 
	 * @param string $message
	 * @param int $booking_id
	 * @param string $booking_type
	 * @param array $data
	 * @param string $user_message
	 * @return string
	 */
	function bookacti_woocommerce_add_refund_request_email_message( $message, $booking_id, $booking_type, $data, $user_message ) {
		
		if( $booking_type === 'single' ) {
			$order_id = bookacti_get_booking_order_id( $booking_id );
		} else if( $booking_type === 'group' ) {
			$order_id = bookacti_get_booking_group_order_id( $booking_id );
		}
		
		if( ! $order_id ) { return $message; } 
		
		$go_to_order =	'<div style="background-color: #f5faff; padding: 10px; border: 1px solid #abc; margin-bottom: 30px;" >' 
							. esc_html__( 'Click here to go to the order page and process the refund:', BOOKACTI_PLUGIN_NAME ) 
							. ' <a href="' . admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' ) . '" target="_blank" >' 
								. esc_html__( 'Go to refund page', BOOKACTI_PLUGIN_NAME ) 
							. '</a>'
						. '</div>';
		
		return $go_to_order . $message;
	}
	add_filter( 'bookacti_refund_request_email_message', 'bookacti_woocommerce_add_refund_request_email_message', 10, 5 );
	
	
	/**
	 * Update dates after reschedule
	 * 
	 * @param int $booking_id
	 * @param string $event_start
	 * @param string $event_end
	 * @return void
	 */
	function bookacti_woocommerce_update_booking_dates( $booking_id, $event_start, $event_end, $old_booking ) {
		
		$item = bookacti_get_order_item_by_booking_id( $booking_id );
			
		if( empty( $item ) ) {
			return;
		}
		
		$booked_events = wc_get_order_item_meta( $item[ 'id' ], 'bookacti_booked_events' );
		
		if( ! empty( $booked_events ) ) {
			
			$booked_events = (array) json_decode( $booked_events );
			
			foreach( $booked_events as $i => $booked_event ) {
				if( intval( $booked_event->id ) === intval( $booking_id ) ) {
					$key = $i;
					break;
				}
			}
			
			if( ! isset( $key ) ) {
				return;
			}
			
			// Update only start and end of the desired booking
			
			$booked_events[ $key ]->event_start	= $event_start;
			$booked_events[ $key ]->event_end	= $event_end;
			
			wc_update_order_item_meta( $item[ 'id' ], 'bookacti_booked_events', json_encode( $booked_events ) );
			
		// For bookings made before Booking Activities 1.1.0
		} else {
			// Delete old data
			wc_delete_order_item_meta( $item[ 'id' ], 'bookacti_event_start' );
			wc_delete_order_item_meta( $item[ 'id' ], 'bookacti_event_end' );
			
			// Insert new booking data
			$event = bookacti_get_booking_event_data( $booking_id );
			wc_add_order_item_meta( $item[ 'id' ], 'bookacti_booked_events', json_encode( array( $event ) ) );
		}
	}
	add_action( 'bookacti_booking_rescheduled', 'bookacti_woocommerce_update_booking_dates', 10, 4 );