<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin;

use Composer\Composer;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 为 `SKernel` 工具提供全局应用上下文必要功能
 */
final class ApplicationContext
{
	private static ?Composer $composer = null;

	private static ?OutputInterface $output = null;

	public static function setComposer(Composer $composer): void
	{
		self::$composer ??= $composer;
	}

	public static function getComposer(): Composer
	{
		if (null === self::$composer) {
			throw new RuntimeException('Composer not set');
		}

		return self::$composer;
	}

	public static function setOutput(OutputInterface $output): void
	{
		self::$output ??= $output;
	}

	public static function getOutput(): OutputInterface
	{
		if (null === self::$output) {
			throw new RuntimeException('Output not set');
		}

		return self::$output;
	}
}