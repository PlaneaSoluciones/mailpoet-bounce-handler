<?php
/**
 * Constantes y clases que PHPStan no puede descubrir: las define WordPress en wp-config.php,
 * el propio plugin en tiempo de ejecución o MailPoet (que no publica stubs).
 *
 * @package MailPoet_Bounce_Handler
 */

namespace {
	define( 'AUTH_KEY', '' );
	define( 'SECURE_AUTH_KEY', '' );
	define( 'MBH_VERSION', '' );
	define( 'MBH_PLUGIN_FILE', '' );
	define( 'MBH_PLUGIN_DIR', '' );
}

namespace MailPoet\API {
	/**
	 * Stub de la API pública de MailPoet.
	 */
	class API {
		/**
		 * Devuelve la API de la versión indicada.
		 *
		 * @param string $version Versión de la API.
		 * @return object
		 */
		public static function MP( string $version ) {}
	}
}
