<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

// DATABASE BACKUP AND ARCHIVE

// 1. DB BACKUP ANALYSIS

/**
 * Get mysql bin path
 * @since 1.7.0
 * @global type $bookacti_mysql_bin_path
 * @global wpdb $wpdb
 * @return false|string
 */
function bookacti_get_mysql_bin_path() {
	global $bookacti_mysql_bin_path;
	if( isset( $bookacti_mysql_bin_path ) ) { return $bookacti_mysql_bin_path; }
	
	global $wpdb;
	
	$mysql_dir = $wpdb->get_var( 'SELECT @@basedir' );
	
	if( ! $mysql_dir ) { 
		$bookacti_mysql_bin_path = false; 
		return false;
	}
	
	$bookacti_mysql_bin_path = str_replace( '\\', '/', $mysql_dir );
	
	if( substr( $bookacti_mysql_bin_path, -1 ) !== '/' ) {
		$bookacti_mysql_bin_path .= '/';
	}
	
	$bookacti_mysql_bin_path .= 'bin/';
	
	return $bookacti_mysql_bin_path;
}


/**
 * Get mysql temp path
 * @since 1.7.0
 * @global type $bookacti_mysql_temp_path
 * @global wpdb $wpdb
 * @return false|string
 */
function bookacti_get_mysql_temp_path() {
	global $bookacti_mysql_temp_path;
	if( isset( $bookacti_mysql_temp_path ) ) { return $bookacti_mysql_temp_path; }
	
	global $wpdb;
	
	$temp_dir = $wpdb->get_var( 'SELECT @@tmpdir' );
	
	if( ! $temp_dir ) { 
		$bookacti_mysql_temp_path = false; 
		return false;
	}
	
	$bookacti_mysql_temp_path = str_replace( '\\', '/', $temp_dir );
	
	if( substr( $bookacti_mysql_temp_path, -1 ) !== '/' ) {
		$bookacti_mysql_temp_path .= '/';
	}
	
	return $bookacti_mysql_temp_path;
}


/**
 * Get events prior to a date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @return array
 */
function bookacti_get_events_prior_to( $date ) {
	global $wpdb;
	
	$query	= ' SELECT E.id, E.start, E.end, E.repeat_freq, E.repeat_from, E.repeat_to FROM ' . BOOKACTI_TABLE_EVENTS . ' as E '
			. ' WHERE ( ( E.repeat_freq IS NULL OR E.repeat_freq = "none" ) AND E.end < %s ) '
			. ' OR ( ( E.repeat_freq IS NOT NULL AND E.repeat_freq != "none" ) AND E.repeat_to < %s )'
			. ' ORDER BY E.id DESC';
	
	$variables	= array( $date . ' 00:00:00', $date );
	$query		= $wpdb->prepare( $query, $variables );
	$events		= $wpdb->get_results( $query, OBJECT );
	
	return $events;
}


/**
 * Get repeated events that have started as of a specific date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @return array
 */
function bookacti_get_started_repeated_events_as_of( $date ) {
	global $wpdb;
	
	$query	= ' SELECT E.id, E.start, E.end, E.repeat_freq, E.repeat_from, E.repeat_to FROM ' . BOOKACTI_TABLE_EVENTS . ' as E '
			. ' WHERE ( E.repeat_freq IS NOT NULL AND E.repeat_freq != "none" ) '
			. ' AND E.repeat_to > %s '
			. ' AND E.repeat_from < %s '
			. ' ORDER BY E.id DESC';
	
	$variables	= array( $date, $date );
	$query		= $wpdb->prepare( $query, $variables );
	$events		= $wpdb->get_results( $query, OBJECT );
	
	return $events;
}


/**
 * Get groups of events prior to a date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @return array
 */
function bookacti_get_group_of_events_prior_to( $date ) {
	global $wpdb;
	
	$query	= ' SELECT GE.group_id as id, MIN( GE.event_start ) as min_event_start, MAX( GE.event_end ) as max_event_end FROM ' . BOOKACTI_TABLE_GROUPS_EVENTS . ' as GE '
			. ' GROUP BY GE.group_id '
			. ' HAVING max_event_end < %s '
			. ' ORDER BY GE.group_id DESC';
	
	$variables	= array( $date . ' 00:00:00' );
	$query		= $wpdb->prepare( $query, $variables );
	$groups		= $wpdb->get_results( $query, OBJECT );
	
	return $groups;
}


/**
 * Get bookings prior to a date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @return array
 */
function bookacti_get_bookings_prior_to( $date ) {
	global $wpdb;
	
	$query	= ' SELECT B.id, B.event_start, B.event_end, B.group_id FROM ' . BOOKACTI_TABLE_BOOKINGS . ' as B '
			. ' WHERE B.event_end < %s '
			. ' ORDER BY B.id DESC';
	
	$variables	= array( $date . ' 00:00:00' );
	$query		= $wpdb->prepare( $query, $variables );
	$bookings	= $wpdb->get_results( $query, OBJECT );
	
	return $bookings;
}


/**
 * Get booking groups prior to a date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @return array
 */
function bookacti_get_booking_groups_prior_to( $date ) {
	global $wpdb;
	
	$query	= ' SELECT B.group_id as id, MIN( event_start ) as min_event_start, MAX( event_end ) as max_event_end FROM ' . BOOKACTI_TABLE_BOOKINGS . ' as B '
			. ' WHERE B.group_id IS NOT NULL '
			. ' GROUP BY B.group_id '
			. ' HAVING max_event_end < %s '
			. ' ORDER BY B.group_id DESC';
	
	$variables	= array( $date . ' 00:00:00' );
	$query		= $wpdb->prepare( $query, $variables );
	$booking_groups	= $wpdb->get_results( $query, OBJECT );
	
	return $booking_groups;
}




// 2. DB BACKUP DUMP

/**
 * Create a .sql file to archive events prior to a date
 * @since 1.7.0
 * @param string $date
 * @return int|false
 */
function bookacti_archive_events_prior_to( $date ) {
	$filename	= $date . '-events.sql';
	$table		= BOOKACTI_TABLE_EVENTS;
	$where		= sprintf( "( ( repeat_freq IS NULL OR repeat_freq = 'none' ) AND end < '%s' ) OR ( ( repeat_freq IS NOT NULL AND repeat_freq != 'none' ) AND repeat_to < '%s' )", $date . ' 00:00:00', $date . ' 00:00:00' );
	return bookacti_archive_database( $filename, $table, $where );
}


/**
 * Create a .sql file to archive repeated events exceptions prior to a date
 * @since 1.7.0
 * @param string $date
 * @return int|false
 */
function bookacti_archive_repeated_events_exceptions_prior_to( $date ) {
	$filename	= $date . '-repeated-events-exceptions.sql';
	$table		= BOOKACTI_TABLE_EXCEPTIONS;
	$where		= sprintf( "exception_type = 'date' AND exception_value < '%s'", $date );
	return bookacti_archive_database( $filename, $table, $where );
}


/**
 * Create a .sql file to archive repeated events that have started as of a specific date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @return int|false
 */
function bookacti_archive_started_repeated_events_as_of( $date ) {
	global $wpdb;
	$wpdb->hide_errors();
	
	// Remove the temporary table if it already exists
	$temp_table = $wpdb->prefix . 'bookacti_temp_events';
	$delete_query = 'DROP TABLE IF EXISTS ' . $temp_table . '; ';
	$wpdb->query( $delete_query );
	
	// Create a table to store the old repeat_from values
	$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';
	$temp_table_events_query = 'CREATE TABLE ' . $temp_table . ' ( 
		id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT, 
		repeat_from DATE, 
		PRIMARY KEY ( id ) ) ' . $collate . ';';
	
	if( ! function_exists( 'dbDelta' ) ) { require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); }
	
	dbDelta( $temp_table_events_query );
	
	// Fill the temp table
	$insert_query	= ' INSERT INTO ' . $temp_table . ' (id, repeat_from) '
					. ' SELECT E.id, E.repeat_from FROM ' . BOOKACTI_TABLE_EVENTS . ' as E '
					. ' WHERE ( E.repeat_freq IS NOT NULL AND E.repeat_freq != "none" ) '
					. ' AND E.repeat_to >= %s '
					. ' AND E.repeat_from <= %s ';
	$insert_query		= $wpdb->prepare( $insert_query, array( $date, $date ) );
	$inserted	= $wpdb->query( $insert_query );
	
	if( ! $inserted ) { return $inserted; }
	
	// Dump the table
	$filename = $date . '-started-repeated-events.sql';
	$dumped = bookacti_archive_database( $filename, $temp_table, '', false );
	
	// Remove the table
	$delete_query = 'DROP TABLE IF EXISTS ' . $temp_table . '; ';
	$wpdb->query( $delete_query );
	
	if( $dumped !== true ) { return $dumped; }
	
	// Add the UPDATE and DELETE queries to the backup file
	$update_query	= 'UPDATE ' . BOOKACTI_TABLE_EVENTS . ' as E '
					. ' INNER JOIN ' . $temp_table . ' as TE ON E.id = TE.id'
					. ' SET E.repeat_from = TE.repeat_from'
					. ' WHERE TE.repeat_from < E.repeat_from;';
	
	$uploads_dir= wp_upload_dir();
	$file		= trailingslashit( str_replace( '\\', '/', $uploads_dir[ 'basedir' ] ) ) . BOOKACTI_PLUGIN_NAME . '/archives/' . $filename;
	$handle		= fopen( $file, 'a' );
	$write		= 0;
	if( $handle !== false ) {
		$text	= PHP_EOL . '-- Update `' . BOOKACTI_TABLE_EVENTS . '` repeat_from with the values of the temporary table `' . $temp_table . '`';
		$text	.= PHP_EOL . $update_query . PHP_EOL;
		$text	.= PHP_EOL . '-- Delete the temporary table `' . $temp_table . '`';
		$text	.= PHP_EOL . $delete_query. PHP_EOL;
		$write	= fwrite( $handle, $text );
		fclose( $handle );
	}
	
	return $write ? true : false;
}


/**
 * Create a .sql file to archive group of events prior to a date
 * @since 1.7.0
 * @param string $date
 * @return boolean
 */
function bookacti_archive_group_of_events_prior_to( $date ) {
	$filename_groups	= $date . '-group-of-events.sql';
	$table_groups		= BOOKACTI_TABLE_EVENT_GROUPS;
	$where_groups		= sprintf( "id IN ( SELECT group_id FROM " . BOOKACTI_TABLE_GROUPS_EVENTS . " GROUP BY group_id HAVING MAX( event_end ) < '%s' )", $date . ' 00:00:00' );
	$archive_groups		= bookacti_archive_database( $filename_groups, $table_groups, $where_groups );
	
	$filename_events	= $date . '-groups-events.sql';
	$table_events		= BOOKACTI_TABLE_GROUPS_EVENTS;
	$where_events		= sprintf( "TRUE GROUP BY group_id HAVING MAX( event_end ) < '%s'", $date . ' 00:00:00' );
	$archive_events		= bookacti_archive_database( $filename_events, $table_events, $where_events );
	
	return $archive_groups === true && $archive_events === true;
}


/**
 * Create a .sql file to archive bookings prior to a date
 * @since 1.7.0
 * @param string $date
 * @return int|false
 */
function bookacti_archive_bookings_prior_to( $date ) {
	$filename	= $date . '-bookings.sql';
	$table		= BOOKACTI_TABLE_BOOKINGS;
	$where		= sprintf( "event_end < '%s'", $date . ' 00:00:00' );
	return bookacti_archive_database( $filename, $table, $where );
}


/**
 * Create a .sql file to archive booking groups prior to a date
 * @since 1.7.0
 * @param string $date
 * @return int|false
 */
function bookacti_archive_booking_groups_prior_to( $date ) {
	$filename	= $date . '-booking-groups.sql';
	$table		= BOOKACTI_TABLE_BOOKING_GROUPS;
	$where		= sprintf( "id IN ( SELECT B.group_id FROM " . BOOKACTI_TABLE_BOOKINGS . " as B WHERE B.group_id IS NOT NULL GROUP BY B.group_id HAVING MAX( event_end ) < '%s' )", $date . ' 00:00:00' );
	return bookacti_archive_database( $filename, $table, $where );
}


/**
 * Create a .sql file to archive events, group of events, booking and booking groups meta prior to a date
 * @since 1.7.0
 * @param string $date
 * @return int|false
 */
function bookacti_archive_metadata_prior_to( $date ) {
	$filename	= $date . '-metadata.sql';
	$table		= BOOKACTI_TABLE_META;
	
	// Where clauses
	$where_events			= sprintf( "( ( repeat_freq IS NULL OR repeat_freq = 'none' ) AND end < '%s' ) OR ( ( repeat_freq IS NOT NULL AND repeat_freq != 'none' ) AND repeat_to < '%s' )", $date . ' 00:00:00', $date . ' 00:00:00' );
	$where_group_of_events	= sprintf( "GROUP BY group_id HAVING MAX( event_end ) < '%s'", $date . ' 00:00:00' );
	$where_bookings			= sprintf( "event_end < '%s'", $date . ' 00:00:00' );
	$where_booking_groups	= sprintf( "id IN ( SELECT B.group_id FROM " . BOOKACTI_TABLE_BOOKINGS . " as B WHERE B.group_id IS NOT NULL GROUP BY B.group_id HAVING MAX( event_end ) < '%s' )", $date . ' 00:00:00' );
	
	$where	= "( object_type = 'event' AND object_id IN ( SELECT id FROM " . BOOKACTI_TABLE_EVENTS . " WHERE " . $where_events . " ) ) ";
	$where .= "OR ( object_type = 'group_of_events' AND object_id IN ( SELECT group_id FROM " . BOOKACTI_TABLE_GROUPS_EVENTS . " " . $where_group_of_events . " ) ) ";
	$where .= "OR ( object_type = 'booking' AND object_id IN ( SELECT id FROM " . BOOKACTI_TABLE_BOOKINGS . " WHERE " . $where_bookings . " ) ) ";
	$where .= "OR ( object_type = 'booking_group' AND object_id IN ( SELECT id FROM " . BOOKACTI_TABLE_BOOKING_GROUPS . " WHERE " . $where_booking_groups . " ) ) ";
	
	return bookacti_archive_database( $filename, $table, $where );
}




// 3. DB BACKUP DELETION

/**
 * Delete a large amount of rows from a table without using DELETE FROM query
 * Create a temp table, insert non-deleted rows, drop original table, rename temp table to the original table name
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $original_table
 * @param string $where
 * @param array $variables
 * @return boolean
 */
function bookacti_delete_rows_from_table( $original_table, $where, $variables ) {
	global $wpdb;
	$wpdb->hide_errors();
	$temp_table = $original_table . '_temp';
	
	// Remove the temporary table if it already exists
	$delete_temp_table_query = 'DROP TABLE IF EXISTS ' . $temp_table . '; ';
	$wpdb->query( $delete_temp_table_query );
	
	// Create a table with only the non-deleted data
	$create_temp_table_query = 'CREATE TABLE ' . $temp_table . ' LIKE ' . $original_table . ';';
	if( ! function_exists( 'dbDelta' ) ) { require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); }
	dbDelta( $create_temp_table_query );
	
	// Fill the temp table
	$insert_query	= ' INSERT INTO ' . $temp_table
					. ' SELECT * FROM ' . $original_table . ' as E '
					. ' WHERE NOT(' . $where . ' )';
	if( $variables ) {
		$insert_query	= $wpdb->prepare( $insert_query, $variables );
	}
	$inserted = $wpdb->query( $insert_query );
	
	if( $inserted === false ) { return false; }
	
	// Change the auto_increment value
	$ai_query	= ' SELECT `AUTO_INCREMENT` ' 
				. ' FROM  INFORMATION_SCHEMA.TABLES '
				. ' WHERE TABLE_SCHEMA = "' . DB_NAME . '"'
				. ' AND   TABLE_NAME   = "' . $original_table . '";';
	$ai_value	= intval( $wpdb->get_var( $ai_query ) );
	
	if( ! $ai_value ) { return false; }
	
	$update_temp_table_ai_query = 'ALTER TABLE ' . $temp_table . ' AUTO_INCREMENT = %d;';
	$update_temp_table_ai_query = $wpdb->prepare( $update_temp_table_ai_query, $ai_value );
	$wpdb->query( $update_temp_table_ai_query );
	
	// Remove original table
	$delete_table_query = 'DROP TABLE IF EXISTS ' . $original_table . '; ';
	$wpdb->query( $delete_table_query );
	
	// Rename temp table to original table name
	$delete_table_query = 'RENAME TABLE ' . $temp_table . ' TO ' . $original_table . '; ';
	$wpdb->query( $delete_table_query );
	
	return true;
}


/**
 * Delete events prior to a date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @param boolean $delete_meta
 * @return array
 */
function bookacti_delete_events_prior_to( $date, $delete_meta = true ) {
	global $wpdb;
	
	$variables = array( $date . ' 00:00:00', $date );
	
	// Remove metadata first
	if( $delete_meta ) {
		$where_meta	= 'object_type = "event" AND object_id IN( '
						. ' SELECT id FROM ' . BOOKACTI_TABLE_EVENTS . ' as E '
						. ' WHERE ( ( E.repeat_freq IS NULL OR E.repeat_freq = "none" ) AND E.end < %s ) '
						. ' OR ( ( E.repeat_freq IS NOT NULL AND E.repeat_freq != "none" ) AND E.repeat_to < %s )'
					. ' )';
		bookacti_delete_rows_from_table( BOOKACTI_TABLE_META, $where_meta, $variables );
	}
	
	
	// Remove repetead events exceptions
	$where_exceptions	= 'exception_type = "date" AND exception_value < %s';
	bookacti_delete_rows_from_table( BOOKACTI_TABLE_EXCEPTIONS, $where_exceptions, array( $date ) );
	
	// Count the initial amount of rows
	$count_query	= 'SELECT COUNT(*) FROM ' . BOOKACTI_TABLE_EVENTS ;
	$count_before	= intval( $wpdb->get_var( $count_query ) );
	
	// Remove the rows
	$where	= ' ( ( repeat_freq IS NULL OR repeat_freq = "none" ) AND end < %s ) '
			. ' OR ( ( repeat_freq IS NOT NULL AND repeat_freq != "none" ) AND repeat_to < %s )';
	$deleted = bookacti_delete_rows_from_table( BOOKACTI_TABLE_EVENTS, $where, $variables );
	
	if( $deleted === false ) { return false; }
	
	// Count the current amount of rows
	$count_after = intval( $wpdb->get_var( $count_query ) );
	
	return $count_before - $count_after;
}


/**
 * Retrict repeated events that have started before a specific date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @return array
 */
function bookacti_restrict_started_repeated_events_to( $date ) {
	global $wpdb;
	
	$query	= ' UPDATE ' . BOOKACTI_TABLE_EVENTS
			. ' SET repeat_from = %s '
			. ' WHERE ( repeat_freq IS NOT NULL AND repeat_freq != "none" ) '
			. ' AND repeat_to >= %s '
			. ' AND repeat_from <= %s ';
	
	$variables	= array( $date, $date, $date );
	$query		= $wpdb->prepare( $query, $variables );
	$updated	= $wpdb->query( $query, OBJECT );
	
	return $updated;
}


/**
 * Delete groups of events prior to a date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @param boolean $delete_meta
 * @return array
 */
function bookacti_delete_group_of_events_prior_to( $date, $delete_meta = true ) {
	global $wpdb;
	
	$select_query	= ' SELECT GE.group_id FROM ' . BOOKACTI_TABLE_GROUPS_EVENTS . ' as GE '
					. ' GROUP BY GE.group_id '
					. ' HAVING MAX( GE.event_end ) < %s ';
	
	$variables	= array( $date . ' 00:00:00' );
	
	// Remove metadata first
	if( $delete_meta ) {
		$where_meta	= 'object_type = "group_of_events" AND object_id IN( ' . $select_query . ' )';
		bookacti_delete_rows_from_table( BOOKACTI_TABLE_META, $where_meta, $variables );
	}
	
	
	// Remove group of events before the events themselves!
	// Count the initial amount of rows
	$count_query	= 'SELECT COUNT(*) FROM ' . BOOKACTI_TABLE_EVENT_GROUPS ;
	$count_before	= intval( $wpdb->get_var( $count_query ) );
	
	// Remove the rows
	$where_groups_of_events	= 'id IN( ' . $select_query . ' )';
	$deleted_groups_of_events = bookacti_delete_rows_from_table( BOOKACTI_TABLE_EVENT_GROUPS, $where_groups_of_events, $variables );
	
	if( $deleted_groups_of_events === false ) { return false; }
	
	// Count the current amount of rows
	$count_after = intval( $wpdb->get_var( $count_query ) );
	
	
	// Remove events of groups
	$where_events_of_groups	= 'group_id IN( '
								. ' SELECT group_id FROM ( ' . $select_query . ' ) as TEMPTABLE '
							. ' )';
	$deleted_events_of_groups = bookacti_delete_rows_from_table( BOOKACTI_TABLE_GROUPS_EVENTS, $where_events_of_groups, $variables );
	
	if( $deleted_events_of_groups === false ) { return false; }
	
	return $count_before - $count_after;
}


/**
 * Delete bookings prior to a date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @param boolean $delete_meta
 * @return array
 */
function bookacti_delete_bookings_prior_to( $date, $delete_meta = true ) {
	global $wpdb;
	
	$variables = array( $date . ' 00:00:00' );
	
	// Remove metadata first
	if( $delete_meta ) {
		$where_meta	= 'object_type = "booking" '
					. ' AND object_id IN( ' 
						. 'SELECT id FROM ' . BOOKACTI_TABLE_BOOKINGS . ' as B WHERE B.event_end < %s '
					. ' )';
		bookacti_delete_rows_from_table( BOOKACTI_TABLE_META, $where_meta, $variables );
	}
	
	// Remove bookings
	// Count the initial amount of rows
	$count_query	= 'SELECT COUNT(*) FROM ' . BOOKACTI_TABLE_BOOKINGS ;
	$count_before	= intval( $wpdb->get_var( $count_query ) );
	
	// Remove the rows
	$where	= 'event_end < %s';
	$deleted = bookacti_delete_rows_from_table( BOOKACTI_TABLE_BOOKINGS, $where, $variables );
	
	if( $deleted === false ) { return false; }
	
	// Count the current amount of rows
	$count_after = intval( $wpdb->get_var( $count_query ) );
	
	return $count_before - $count_after;
}


/**
 * Delete booking groups prior to a date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @param boolean $delete_meta
 * @return array
 */
function bookacti_delete_booking_groups_prior_to( $date, $delete_meta = true ) {
	global $wpdb;
	
	$select_query	= ' SELECT B.group_id FROM ' . BOOKACTI_TABLE_BOOKINGS . ' as B '
					. ' WHERE B.group_id IS NOT NULL '
					. ' GROUP BY B.group_id '
					. ' HAVING MAX( B.event_end ) < %s ';
	
	$variables	= array( $date . ' 00:00:00' );
	
	// Remove metadata first
	if( $delete_meta ) {
		$where_meta	= 'object_type = "booking_group" AND object_id IN( ' . $select_query . ' )';
		bookacti_delete_rows_from_table( BOOKACTI_TABLE_META, $where_meta, $variables );
	}
	
	
	// Remove booking groups
	// Count the initial amount of rows
	$count_query	= 'SELECT COUNT(*) FROM ' . BOOKACTI_TABLE_BOOKING_GROUPS ;
	$count_before	= intval( $wpdb->get_var( $count_query ) );
	
	// Remove the rows
	$where	= 'id IN ( ' . $select_query . ' )';
	$deleted = bookacti_delete_rows_from_table( BOOKACTI_TABLE_BOOKING_GROUPS, $where, $variables );
	
	if( $deleted === false ) { return false; }
	
	// Count the current amount of rows
	$count_after = intval( $wpdb->get_var( $count_query ) );
	
	return $count_before - $count_after;
}


/**
 * Delete metadata for bookings and events (groups) prior to a date
 * @since 1.7.0
 * @global wpdb $wpdb
 * @param string $date
 * @param boolean $delete_meta
 * @return array
 */
function bookacti_delete_bookings_and_events_meta_prior_to( $date ) {
	global $wpdb;
	
	// Where clauses
	$where_events			= '( ( repeat_freq IS NULL OR repeat_freq = "none" ) AND end < %s ) OR ( ( repeat_freq IS NOT NULL AND repeat_freq != "none" ) AND repeat_to < %s )';
	$where_group_of_events	= 'GROUP BY group_id HAVING MAX( event_end ) < %s';
	$where_bookings			= 'event_end < %s';
	$where_booking_groups	= 'id IN ( SELECT B.group_id FROM ' . BOOKACTI_TABLE_BOOKINGS . ' as B WHERE B.group_id IS NOT NULL GROUP BY B.group_id HAVING MAX( event_end ) < %s )';
	
	$where	= '( object_type = "event" AND object_id IN ( SELECT id FROM ' . BOOKACTI_TABLE_EVENTS . ' WHERE ' . $where_events . ' ) ) ';
	$where .= 'OR ( object_type = "group_of_events" AND object_id IN ( SELECT group_id FROM ' . BOOKACTI_TABLE_GROUPS_EVENTS . ' ' . $where_group_of_events . ' ) ) ';
	$where .= 'OR ( object_type = "booking" AND object_id IN ( SELECT id FROM ' . BOOKACTI_TABLE_BOOKINGS . ' WHERE ' . $where_bookings . ' ) ) ';
	$where .= 'OR ( object_type = "booking_group" AND object_id IN ( SELECT id FROM ' . BOOKACTI_TABLE_BOOKING_GROUPS . ' WHERE ' . $where_booking_groups . ' ) ) ';
	
	$variables = array( $date . ' 00:00:00', $date . ' 00:00:00', $date . ' 00:00:00', $date . ' 00:00:00', $date . ' 00:00:00' );
	
	// Remove meta
	// Count the initial amount of rows
	$count_query	= 'SELECT COUNT(*) FROM ' . BOOKACTI_TABLE_META ;
	$count_before	= intval( $wpdb->get_var( $count_query ) );
	
	// Remove the rows
	$deleted = bookacti_delete_rows_from_table( BOOKACTI_TABLE_META, $where, $variables );
	
	if( $deleted === false ) { return false; }
	
	// Count the current amount of rows
	$count_after = intval( $wpdb->get_var( $count_query ) );
	
	return $count_before - $count_after;
}