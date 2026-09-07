<?php
/**
 * Integration tests require WordPress + WooCommerce test libraries.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase {

	public function test_plugin_file_exists(): void {
		$this->assertFileExists( dirname( __DIR__, 2 ) . '/logicanvas-auctions.php' );
	}
}
