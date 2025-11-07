<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CapabilityCommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

final class Plugin implements PluginInterface, Capable
{
	/**
	 * @param Composer    $composer
	 * @param IOInterface $io
	 *
	 * @return void
	 */
	public function activate(Composer $composer, IOInterface $io)
	{
		// TODO: Implement activate() method.
	}

	/**
	 * @param Composer    $composer
	 * @param IOInterface $io
	 *
	 * @return void
	 */
	public function deactivate(Composer $composer, IOInterface $io)
	{
		// TODO: Implement deactivate() method.
	}

	/**
	 * @param Composer    $composer
	 * @param IOInterface $io
	 *
	 * @return void
	 */
	public function uninstall(Composer $composer, IOInterface $io)
	{
		// TODO: Implement uninstall() method.
	}

	/**
	 * @return string[]
	 */
	public function getCapabilities(): array
	{
		return [
			CapabilityCommandProvider::class => CommandProvider::class,
		];
	}
}