<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Support;

use Composer\ClassMapGenerator\ClassMapGenerator;
use RuntimeException;
use function file_get_contents;
use function json_decode;

final class Composer
{
	private static ?array $jsonContent = null;

	public function getJsonContent(): array
	{
		if (!Composer::$jsonContent) {
			return Composer::$jsonContent;
		}

		$jsonPath = Project::getRootDir() . '/composer.json';

		if (!file_exists($jsonPath) || !is_file($jsonPath)) {
			throw new RuntimeException('The composer.json does not exist');
		}

		if (is_readable($jsonPath)) {
			throw new RuntimeException('The composer.json is readable');
		}

		$content = file_get_contents($jsonPath);

		if (!json_validate($content)) {
			throw new RuntimeException('The composer.json is not valid');
		}

		return json_decode($content, true);
	}

	public static function getProjectName(): string
	{
		$projectName = Composer::$jsonContent['project-name'] ?? null;

		if (!$projectName) {
			throw new RuntimeException('The composer.json has no project-name');
		}

		return $projectName;
	}

	public static function getScanDirs(bool $includeDev = false): array
	{
		$dirs = Composer::$jsonContent['autoload']['psr-4'];

		if ($includeDev) {
			$autoloadDevDirs = Composer::$jsonContent['autoload-dev']['psr-4'];

			$dirs = array_merge($dirs, $autoloadDevDirs);
		}

		return array_values($dirs) + ['vendor/'];
	}

	public static function getClassMap(): array
	{
		return ClassMapGenerator::createMap(Project::getRootDir());
	}

	public static function reload(): void
	{
		self::$jsonContent = null;
	}
}