<?php
/**
 * LOCAL TEST HARNESS ONLY (never deploy): with the request header "X-TDD-Query-Log: 1", records every
 * database query of the request and writes a summary to tests/perf/out/queries-<slug>.json.
 */
if ( empty( $_SERVER['HTTP_X_TDD_QUERY_LOG'] ) ) { // phpcs:ignore
	return;
}
define( 'SAVEQUERIES', true );
add_action(
	'shutdown',
	static function () {
		global $wpdb;
		$rows = array();
		$time = 0;
		foreach ( (array) $wpdb->queries as $q ) {
			$time  += $q[1];
			$caller = implode( ' < ', array_slice( array_reverse( array_map( 'trim', explode( ',', $q[2] ) ) ), 0, 4 ) );
			$rows[] = array( 'ms' => round( $q[1] * 1000, 2 ), 'sql' => substr( preg_replace( '/\s+/', ' ', $q[0] ), 0, 300 ), 'caller' => $caller );
		}
		$slug = sanitize_title( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ?: 'home'; // phpcs:ignore
		$dir  = getenv( 'TDD_QUERY_LOG_DIR' ) ?: '/home/claude/wp06/tests/perf/out';
		wp_mkdir_p( $dir );
		file_put_contents( "$dir/queries-$slug.json", wp_json_encode( array( 'uri' => $_SERVER['REQUEST_URI'] ?? '', 'count' => count( $rows ), 'ms' => round( $time * 1000, 1 ), 'peak_mb' => round( memory_get_peak_usage() / 1048576, 1 ), 'queries' => $rows ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore
	},
	PHP_INT_MAX
);
