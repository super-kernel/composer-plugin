<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Support;

use BadMethodCallException;
use Composer\Util\Filesystem as ComposerUtilFilesystem;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function getcwd;
use function is_dir;
use function lcfirst;
use function property_exists;
use function rmdir;
use function str_starts_with;
use function substr;
use function unlink;

/**
 * @method static string getRootDir() 项目根目录
 * @method static string getTargetDir() skernel 运行时目录
 * @method static string getBuildDir() 构建 build 目录
 */
final class Project
{
	private ?string $rootDir = null {
		get => $this->rootDir ??= new ComposerUtilFilesystem()->normalizePath(getcwd());
	}

	private ?string $targetDir = null {
		get => $this->targetDir ??= $this->rootDir . '/target';
	}

	private ?string $buildDir = null {
		get => $this->buildDir ??= $this->targetDir . '/build';
	}

	private static function getInstance(): Project
	{
		return Project::$util ??= new Project();
	}

	public static function __callStatic(string $name, array $arguments)
	{
		if (str_starts_with($name, 'get')) {
			$property = lcfirst(substr($name, 3));

			if (property_exists(Project::class, $property)) {
				return self::getInstance()->$property;
			}
		}

		throw new BadMethodCallException("Call to undefined method Util::$name()");
	}

	private static ?Project $util = null;

	private function __construct()
	{
	}

	public static function readyDirectory(string $directory, int $permissions = 0777): void
	{
		if (!is_dir($directory)) {
			mkdir($directory, $permissions, true);
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($iterator as $fileInfo) {
			$fileInfo->isDir()
				? rmdir($fileInfo->getRealPath())
				: unlink($fileInfo->getRealPath());
		}
	}
}