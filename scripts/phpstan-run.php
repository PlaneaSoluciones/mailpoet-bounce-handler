<?php

/**
 * PHPStan launcher with Windows mapped-drive detection.
 *
 * PHP's is_readable() returns false for all files on SMB/UNC shares mapped as
 * drive letters (e.g. Z:\). PHPStan relies on is_readable() internally, so it
 * cannot analyse source files located on such drives.
 *
 * On Windows with a mapped drive: this script exits 0 with an informational
 * message so that `composer check` does not block local workflows.
 * PHPStan analysis runs correctly in CI (ubuntu-latest, no SMB).
 *
 * On Linux / Mac / local-disk Windows: falls through to run PHPStan normally.
 */

$cwd       = getcwd();
$test_file = $cwd . DIRECTORY_SEPARATOR . 'phpstan.neon';

if ( ! is_readable( $test_file ) && file_exists( $test_file ) ) {
	echo '[phpstan] Saltado: is_readable() falla en drives SMB mapeados en Windows.' . PHP_EOL;
	echo '[phpstan] PHPStan se ejecuta en CI (lint-php.yml, ubuntu-latest).' . PHP_EOL;
	exit( 0 );
}

passthru(
	escapeshellcmd( $cwd . '/vendor/bin/phpstan' )
	. ' analyse --memory-limit=768M',
	$exit
);

exit( $exit );
