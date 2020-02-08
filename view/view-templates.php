<?php
/**
 * Calendar editor page
 * @version 1.7.18
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

$current_user_can_create_template	= current_user_can( 'bookacti_create_templates' );
$current_user_can_edit_template		= current_user_can( 'bookacti_edit_templates' );
$current_user_can_create_activities	= current_user_can( 'bookacti_create_activities' );
$default_template = false;
?>

<div class='wrap'>
<h1 class='wp-heading-inline'><?php esc_html_e( 'Calendar editor', 'booking-activities' ); ?></h1>
<?php do_action( 'bookacti_calendar_editor_page_header' ); ?>
<hr class='wp-header-end'>

<div id='bookacti-fatal-error' class='bookacti-notices' style='display:none;'>
	<ul class='bookacti-error-list'>
		<li><strong>
			<?php 
				/* translators: %s is a link to the official FAQ. The label of this link is "FAQ". */
				echo sprintf( esc_html__( 'A fatal error occurred. Please try to refresh the page. If the error persists, follow the process under "Booking Activities doesn’t work as it should" here: %s.', 'booking-activities' ), '<a href="https://booking-activities.fr/en/documentation/faq">' . esc_html( 'FAQ', 'booking-activities' ) . '</a>' );
			?>
			</strong>
		<li><em><?php esc_html_e( 'Advanced users, you can stop loading and free the fields to try to solve your problem:', 'booking-activities' ); ?></em>
			<input type='button' id='bookacti-exit-loading' value='<?php esc_attr_e( 'Stop loading and free fields', 'booking-activities' ) ?>' />
	</ul>
</div>

<div id='bookacti-template-container'>
    <?php
	$templates = bookacti_fetch_templates();
	?>
    <div id='bookacti-template-sidebar' class='<?php if( ! $templates ) { echo 'bookacti-no-template'; } ?>'>
        
        <div id='bookacti-template-templates-container' class='bookacti-templates-box' >
				<div class='bookacti-template-box-title' >
					<h4><?php echo esc_html__( 'Calendars', 'booking-activities' ); ?></h4>
					<?php if( $current_user_can_create_template ) { ?>
					<div class='bookacti-insert-button dashicons dashicons-plus-alt' id='bookacti-insert-template' ></div>
					<?php } ?>
				</div>
				<div id='bookacti-template-picker-container' >
					<select name='template-picker' id='bookacti-template-picker' >
					<?php
						if( $templates ) {
							$default_template = get_user_meta( get_current_user_id(), 'bookacti_default_template', true );

							$default_template_found = false;
							foreach ( $templates as $template ) {

								$selected = selected( $default_template, $template[ 'id' ], false );

								if( ! empty( $selected ) ) { $default_template_found = true; }

								echo "<option value='"			. esc_attr( $template[ 'id' ] )
									. "' data-template-start='" . esc_attr( $template[ 'start' ] )
									. "' data-template-end='"   . esc_attr( $template[ 'end' ] )
									. "' " . $selected . " >"
									. esc_html( $template[ 'title' ] )
									. "</option>";
							}

							if ( ! $default_template_found ) { 
								reset( $templates );
								$current_template = current( $templates );
								$default_template = $current_template[ 'id' ];
								update_user_meta( get_current_user_id(), 'bookacti_default_template', $default_template );
							}
						}
					?>
					</select>
				</div>
				<?php if( $current_user_can_edit_template ) { ?>
					<div id='bookacti-update-template'><span class='dashicons dashicons-admin-generic'></span></div>
				<?php } ?>
        </div>


        <div id='bookacti-template-activities-container' class='bookacti-templates-box' >
            <div class='bookacti-template-box-title' >
                <h4><?php echo esc_html__( 'Activities', 'booking-activities' ); ?></h4>
				<?php if( $current_user_can_create_activities ) { ?>
                <div class='bookacti-insert-button dashicons dashicons-plus-alt' id='bookacti-insert-activity' ></div>
				<?php } ?>
            </div>
            
            <?php
			// Display list of activities
			$activity_list = '';
			if( $default_template ) {
				$activity_list = bookacti_get_template_activities_list( $default_template ); 
			}
			?>
			<div id='bookacti-template-activity-list' class='bookacti-custom-scrollbar'>
				<?php
				if( ! empty( $activity_list ) ) {
					echo $activity_list;
				} else if( ! $current_user_can_create_activities ) {
					?>
					<div id='bookacti-template-no-activity' >
						<h2>
							<?php esc_html_e( 'There is no activity available, and you are not allowed to create one.', 'booking-activities' ); ?>
						</h2>
					</div>
					<?php
				}
				?>
			</div>
			<?php if( $current_user_can_create_activities ) { ?>
				<div id='bookacti-template-first-activity-container' style='display:<?php echo empty( $activity_list ) ? 'block' : 'none'; ?>;' >
					<h2>
						<?php _e( 'Create your first activity', 'booking-activities' ); ?>
					</h2>
					<div id='bookacti-template-add-first-activity-button' class='dashicons dashicons-plus-alt'></div>
				</div>
			<?php } ?>
        </div>
        
		
		<div id='bookacti-template-groups-of-events-container' class='bookacti-templates-box' >
			<div class='bookacti-template-box-title' >
				<h4><?php echo esc_html__( 'Groups of events', 'booking-activities' ); ?></h4>
				<?php if( $current_user_can_edit_template ) { ?>
                <div class='bookacti-insert-button dashicons dashicons-plus-alt' id='bookacti-insert-group-of-events' ></div>
				<?php } ?>
			</div>
			
			<div id='bookacti-group-categories' class='bookacti-custom-scrollbar'>
				<?php 
					// Display the template's groups of events list
					$groups_list = bookacti_get_template_groups_of_events_list( $default_template );
					if( ! empty( $groups_list ) ) { echo $groups_list; }
				?>
			</div>
			
			<?php
			// If no goup categories exists, display a tuto to create a group of events
			if( $current_user_can_edit_template ) {
				?>
				<p id='bookacti-template-add-group-of-events-tuto-select-events' style='<?php if( ! empty( $groups_list ) ) { echo 'display:none;'; } ?>' >
					<?php _e( 'Select at least 2 events to create a group of events', 'booking-activities' ); ?>
				</p>
				
				<div id='bookacti-template-add-first-group-of-events-container' >
					<h2>
						<?php _e( 'Create your first group of events', 'booking-activities' ); ?>
					</h2>
					<div id='bookacti-template-add-first-group-of-events-button' class='dashicons dashicons-plus-alt' ></div>
				</div>
				<?php
			}
			?>
        </div>
		<div id='bookacti-template-shortcuts-container'>
			<ul>
				<li>
					<strong><?php echo esc_html__( 'Duplicate an event:', 'booking-activities' ); ?></strong>
					<em><?php esc_html_e( 'Hold the "Alt" key while moving the event', 'booking-activities' ); ?></em>
				</li>
				<li>
					<strong><?php esc_html_e( 'Group events:', 'booking-activities' ); ?></strong>
					<em><?php esc_html_e( 'CRTL + G', 'CTRL key', 'booking-activities' ); ?></em>
				</li>
			</ul>
		</div>
    </div>
	
	<div id='bookacti-template-content' >
		<?php
		if( $templates ) {
		?>
			<div id='bookacti-template-calendar' class='bookacti-calendar' ></div>
		<?php
		} else if( $current_user_can_create_template ) {
			?>
			<div id='bookacti-first-template-container'>
				<h2>
					<?php esc_html_e( "Welcome to Booking Activities! Let's start by creating your first calendar", 'booking-activities' ); ?>
				</h2>
				<div id='bookacti-add-first-template-button' class='dashicons dashicons-plus-alt' ></div>
			</div>
			<?php
		} else {
			?>
			<div id='bookacti-no-template-container'>
				<h2>
					<?php esc_html_e( 'There is no calendar available, and you are not allowed to create one.', 'booking-activities' ); ?>
				</h2>
			</div>
			<?php
		}
		?>
	</div>
</div>
<hr/>
<div id='bookacti-calendar-integration-tuto-container' class='<?php if( ! $templates ) { echo 'bookacti-no-template'; } ?>' >
	<?php 
		$template_id = '';
		$activity_ids = array();
		$new_form_basic_url = esc_url( admin_url( 'admin.php' ) );
		$initial_parameters = http_build_query( array( 
			'page' => 'bookacti_forms',
			'action' => 'new',
			'calendars' => $template_id,
			'activities' => 'all',
			'group_categories' => 'all',
		));
		$new_form_initial_url = $new_form_basic_url . '?' . $initial_parameters;
	?>
	<input type='hidden' name='page' value='bookacti_forms'/>
	<input type='hidden' name='action' value='new'/>
	<input type='hidden' name='calendar_field[calendars]' value='<?php echo $template_id; ?>'/>
	<input type='hidden' name='calendar_field[activities]' value='all'/>
	<input type='hidden' name='calendar_field[group_categories]' value='all'/>
	<h3><?php esc_html_e( 'Integrate this calendar to your site', 'booking-activities' ); ?></h3>
	<ol>
		<li>
			<a href='<?php echo $new_form_initial_url; ?>' target='_blank' id='bookacti-create-form-link' data-base-url='<?php echo $new_form_basic_url; ?>'>
				<?php esc_html_e( 'Click here to create a booking form with this calendar', 'booking-activities' ); ?>
			</a>
		</li>
		<li>
			<?php echo apply_filters( 'bookacti_calendar_integration_tuto', esc_html__( 'Copy and paste the booking form shortcode into the desired page or post', 'booking-activities' ), $template_id ); ?>
		</li>
	</ol>
</div>

<?php 
//Include dialogs
include_once( 'view-templates-dialogs.php' );
?>
</div>