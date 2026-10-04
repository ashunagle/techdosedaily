<?php
/**
 * LOCAL TEST HARNESS ONLY (never deploy): connects TechDoseDaily Core's purge signal to the local
 * nginx fastcgi_cache used for Phase 7 cache-correctness tests (tests/perf/nginx.conf).
 * On staging/production the LiteSpeed Cache plugin receives the purge instead (built into Core).
 */
add_action(
	'tdd_core_cache_purge',
	static function ( string $why ) {
		$dir = getenv( 'TDD_LOCAL_PAGE_CACHE' ) ?: '/tmp/claude-0/ng/cache';
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); // phpcs:ignore
		}
		error_log( 'tdd local page cache purged: ' . $why ); // phpcs:ignore
	}
);
