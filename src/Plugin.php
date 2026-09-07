<?php
/**
 * Plugin bootstrap.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions;

use LogicanvasAuctions\Admin\AuctionEditor;
use LogicanvasAuctions\Admin\Menu;
use LogicanvasAuctions\Admin\Notices;
use LogicanvasAuctions\Admin\PermalinkSettings;
use LogicanvasAuctions\Admin\ReviewPrompt;
use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\CLI\Commands;
use LogicanvasAuctions\Core\Capabilities;
use LogicanvasAuctions\Core\ClockInterface;
use LogicanvasAuctions\Core\Dependencies;
use LogicanvasAuctions\Core\Hooks;
use LogicanvasAuctions\Core\Installer;
use LogicanvasAuctions\Core\Logger;
use LogicanvasAuctions\Core\PostTypes;
use LogicanvasAuctions\Core\SystemClock;
use LogicanvasAuctions\Elementor\Loader as ElementorLoader;
use LogicanvasAuctions\Frontend\AccessGuard;
use LogicanvasAuctions\Frontend\Assets;
use LogicanvasAuctions\Frontend\AuthPages;
use LogicanvasAuctions\Frontend\Shortcodes;
use LogicanvasAuctions\Frontend\TemplateLoader;
use LogicanvasAuctions\Frontend\WinnerPaymentRedirect;
use LogicanvasAuctions\Infrastructure\Database\Migrator;
use LogicanvasAuctions\Infrastructure\Realtime\PollingTransport;
use LogicanvasAuctions\Infrastructure\Scheduler\AuctionScheduler;
use LogicanvasAuctions\Infrastructure\WooCommerce\Compatibility;
use LogicanvasAuctions\Infrastructure\WooCommerce\MyAccount;
use LogicanvasAuctions\Infrastructure\WooCommerce\OrderObserver;
use LogicanvasAuctions\Notifications\NotificationService;
use LogicanvasAuctions\Privacy\PersonalData;
use LogicanvasAuctions\REST\RestController;
use LogicanvasAuctions\Support\Diagnostics;
use LogicanvasAuctions\Support\SeoCompat;

final class Plugin {

	private static ?self $instance = null;

	private Container $container;

	private bool $booted = false;

	private function __construct() {
		$this->container = new Container();
		$this->register_services();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function container(): Container {
		return $this->container;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;


		$dependencies = new Dependencies();
		$notices      = new Notices();
		$notices->register();
		( new ReviewPrompt() )->register();
		( new PermalinkSettings() )->register();

		if ( ! $dependencies->meets_php() ) {
			$notices->add_php_notice();
			return;
		}

		if ( ! $dependencies->woocommerce_active() ) {
			$notices->add_woocommerce_notice();
			return;
		}

		/** @var Migrator $migrator */
		$migrator = $this->container->get( Migrator::class );
		$migrator->maybe_upgrade();

		/** @var Installer $installer */
		$installer = $this->container->get( Installer::class );
		$installer->maybe_install();

		$this->register_runtime();
	}


	private function register_services(): void {
		$c = $this->container;

		$c->set(
			ClockInterface::class,
			static fn () => new SystemClock()
		);
		$c->set( Logger::class, static fn () => new Logger() );
		$c->set( Settings::class, static fn () => new Settings() );
		$c->set( Migrator::class, static fn () => new Migrator() );
		$c->set( Installer::class, static fn () => new Installer() );
		$c->set( Capabilities::class, static fn () => new Capabilities() );
		$c->set( PostTypes::class, static fn () => new PostTypes() );
		$c->set( Compatibility::class, static fn () => new Compatibility() );
		$c->set( AuctionScheduler::class, static fn () => new AuctionScheduler() );
		$c->set( NotificationService::class, static fn () => new NotificationService() );
		$c->set( PollingTransport::class, static fn () => new PollingTransport() );
		$c->set( RestController::class, static fn () => new RestController() );
		$c->set( Menu::class, static fn () => new Menu() );
		$c->set( AuctionEditor::class, static fn () => new AuctionEditor() );
		$c->set( Assets::class, static fn () => new Assets() );
		$c->set( Shortcodes::class, static fn () => new Shortcodes() );
		$c->set( TemplateLoader::class, static fn () => new TemplateLoader() );
		$c->set( OrderObserver::class, static fn () => new OrderObserver() );
		$c->set( PersonalData::class, static fn () => new PersonalData() );
		$c->set( Diagnostics::class, static fn () => new Diagnostics() );
		$c->set( ElementorLoader::class, static fn () => new ElementorLoader() );
		$c->set( Commands::class, static fn () => new Commands() );
		$c->set( Hooks::class, static fn () => new Hooks() );
	}

	private function register_runtime(): void {
		/** @var PostTypes $post_types */
		$post_types = $this->container->get( PostTypes::class );
		$post_types->register();

		/** @var Settings $settings */
		$settings = $this->container->get( Settings::class );
		$settings->register();

		/** @var Compatibility $compat */
		$compat = $this->container->get( Compatibility::class );
		$compat->register();

		/** @var AuctionScheduler $scheduler */
		$scheduler = $this->container->get( AuctionScheduler::class );
		$scheduler->register();

		/** @var RestController $rest */
		$rest = $this->container->get( RestController::class );
		$rest->register();

		/** @var Menu $menu */
		$menu = $this->container->get( Menu::class );
		$menu->register();

		/** @var AuctionEditor $editor */
		$editor = $this->container->get( AuctionEditor::class );
		$editor->register();

		/** @var Assets $assets */
		$assets = $this->container->get( Assets::class );
		$assets->register();

		/** @var Shortcodes $shortcodes */
		$shortcodes = $this->container->get( Shortcodes::class );
		$shortcodes->register();

		( new AccessGuard() )->register();
		( new AuthPages() )->register();
		( new WinnerPaymentRedirect() )->register();

		/** @var TemplateLoader $templates */
		$templates = $this->container->get( TemplateLoader::class );
		$templates->register();

		/** @var OrderObserver $orders */
		$orders = $this->container->get( OrderObserver::class );
		$orders->register();

		( new MyAccount() )->register();

		/** @var NotificationService $notifications */
		$notifications = $this->container->get( NotificationService::class );
		$notifications->register();

		/** @var PersonalData $privacy */
		$privacy = $this->container->get( PersonalData::class );
		$privacy->register();

		/** @var Hooks $hooks */
		$hooks = $this->container->get( Hooks::class );
		$hooks->register();

		( new SeoCompat() )->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			/** @var Commands $cli */
			$cli = $this->container->get( Commands::class );
			$cli->register();
		}

		if ( did_action( 'elementor/loaded' ) ) {
			/** @var ElementorLoader $loader */
			$loader = $this->container->get( ElementorLoader::class );
			$loader->register();
		} else {
			add_action(
				'elementor/loaded',
				function (): void {
					/** @var ElementorLoader $loader */
					$loader = $this->container->get( ElementorLoader::class );
					$loader->register();
				}
			);
		}
	}
}
