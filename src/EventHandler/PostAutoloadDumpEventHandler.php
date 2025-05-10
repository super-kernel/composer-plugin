<?php /** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection DuplicatedCode */
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\EventHandler;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\InstalledVersions;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Package\RootAliasPackage;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\PlatformRepository;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use LogicException;
use UnexpectedValueException;

/**
 * @PostAutoloadDumpEventHandler
 * @\ComposerPlugin\EventHandler\PostAutoloadDumpEventHandler
 */
final readonly class PostAutoloadDumpEventHandler implements EventSubscriberInterface
{
	private Filesystem $filesystem;

	public function __construct()
	{
		$this->filesystem = new Filesystem;
	}

	public static function getSubscribedEvents(): array
	{
		return [
			ScriptEvents::POST_AUTOLOAD_DUMP => 'handle',
		];
	}

	public function handle(object $event): void
	{
		if (!$event instanceof Event) {
			return;
		}

		$io                  = $event->getIO();
		$installationManager = $event->getComposer()->getInstallationManager();
		$installedRepository = $event->getComposer()->getRepositoryManager()->getLocalRepository();
		$repoDir             = $event->getComposer()->getConfig()->get('vendor-dir') . '/composer';
		$installPaths        = $this->getInstallPaths($installedRepository, $installationManager, $repoDir);
		$devMode             = InstalledVersions::getAllRawData()[0]['root']['dev'];
		$versions            = $this->generateInstalledVersions($io, $event->getComposer(), $installedRepository, $installPaths, $devMode, $repoDir);

		$this->filesystem->filePutContentsIfModified(
			path   : $repoDir . '/installed.php',
			content: '<?php return ' . $this->dumpToPhpCode($versions) . ';' . "\n",
		);
	}

	/**
	 * @param array $array
	 * @param int   $level
	 *
	 * @return string
	 * @noinspection DuplicatedCode
	 */
	private function dumpToPhpCode(array $array = [], int $level = 0): string
	{
		$lines = "array(\n";
		$level++;

		foreach ($array as $key => $value) {
			$lines .= str_repeat('    ', $level);
			$lines .= is_int($key) ? $key . ' => ' : var_export($key, true) . ' => ';

			if (is_array($value)) {
				if (!empty($value)) {
					$lines .= $this->dumpToPhpCode($value, $level);
				} else {
					$lines .= "array(),\n";
				}
			} elseif ($key === 'install_path' && is_string($value)) {
				if ($this->filesystem->isAbsolutePath($value)) {
					$lines .= var_export($value, true) . ",\n";
				} else {
					$lines .= "__DIR__ . " . var_export('/' . $value, true) . ",\n";
				}
			} elseif (is_string($value)) {
				$lines .= var_export($value, true) . ",\n";
			} elseif (is_bool($value)) {
				$lines .= ($value ? 'true' : 'false') . ",\n";
			} elseif (is_null($value)) {
				$lines .= "null,\n";
			} else {
				throw new UnexpectedValueException('Unexpected type ' . gettype($value));
			}
		}

		$lines .= str_repeat('    ', $level - 1) . ')' . ($level - 1 === 0 ? '' : ",\n");

		return $lines;
	}

	private function getInstallPaths(
		InstalledRepositoryInterface $installedRepository,
		InstallationManager          $installationManager,
		string                       $repoDir,
	): array
	{
		$installPaths = [];

		foreach ($installedRepository->getCanonicalPackages() as $package) {
			$path        = $installationManager->getInstallPath($package);
			$installPath = null;
			if ('' !== $path && null !== $path) {
				$normalizedPath = $this->filesystem->normalizePath($this->filesystem->isAbsolutePath($path) ? $path : Platform::getCwd() . '/' . $path);
				$installPath    = $this->filesystem->findShortestPath($repoDir, $normalizedPath, true);
			}
			$installPaths[$package->getName()] = $installPath;
		}

		return $installPaths;
	}

	private function generateInstalledVersions(
		IOInterface                  $io,
		Composer                     $composer,
		InstalledRepositoryInterface $installedRepository,
		array                        $installPaths,
		mixed                        $devMode,
		string                       $repoDir,
	): array
	{
		$devPackages = array_flip($installedRepository->getDevPackageNames());
		$packages    = $installedRepository->getPackages();
		if (null === $composer->getPackage()) {
			throw new LogicException('It should not be possible to dump packages if no root package is given');
		}
		$packages[] = $rootPackage = $composer->getPackage();

		while ($rootPackage instanceof RootAliasPackage) {
			$rootPackage = $rootPackage->getAliasOf();
			$packages[]  = $rootPackage;
		}
		$versions = [
			'root'     => $this->dumpRootPackage($io, $rootPackage, $installPaths, $devMode, $repoDir, $devPackages),
			'versions' => [],
		];

		// add real installed packages
		foreach ($packages as $package) {
			if ($package instanceof AliasPackage) {
				continue;
			}

			$versions['versions'][$package->getName()] = $this->dumpInstalledPackage($io, $package, $installPaths, $repoDir, $devPackages);
		}

		// add provided/replaced packages
		foreach ($packages as $package) {
			$isDevPackage = isset($devPackages[$package->getName()]);
			foreach ($package->getReplaces() as $replace) {
				// exclude platform replaces as when they are really there we can not check for their presence
				if (PlatformRepository::isPlatformPackage($replace->getTarget())) {
					continue;
				}
				if (!isset($versions['versions'][$replace->getTarget()]['dev_requirement'])) {
					$versions['versions'][$replace->getTarget()]['dev_requirement'] = $isDevPackage;
				} elseif (!$isDevPackage) {
					$versions['versions'][$replace->getTarget()]['dev_requirement'] = false;
				}
				$replaced = $replace->getPrettyConstraint();
				if ($replaced === 'self.version') {
					$replaced = $package->getPrettyVersion();
				}
				if (!isset($versions['versions'][$replace->getTarget()]['replaced']) || !in_array($replaced, $versions['versions'][$replace->getTarget()]['replaced'], true)) {
					$versions['versions'][$replace->getTarget()]['replaced'][] = $replaced;
				}
			}
			foreach ($package->getProvides() as $provide) {
				// exclude platform provides as when they are really there we can not check for their presence
				if (PlatformRepository::isPlatformPackage($provide->getTarget())) {
					continue;
				}
				if (!isset($versions['versions'][$provide->getTarget()]['dev_requirement'])) {
					$versions['versions'][$provide->getTarget()]['dev_requirement'] = $isDevPackage;
				} elseif (!$isDevPackage) {
					$versions['versions'][$provide->getTarget()]['dev_requirement'] = false;
				}
				$provided = $provide->getPrettyConstraint();
				if ($provided === 'self.version') {
					$provided = $package->getPrettyVersion();
				}
				if (!isset($versions['versions'][$provide->getTarget()]['provided']) || !in_array($provided, $versions['versions'][$provide->getTarget()]['provided'], true)) {
					$versions['versions'][$provide->getTarget()]['provided'][] = $provided;
				}
			}
		}

		// add aliases
		foreach ($packages as $package) {
			if (!$package instanceof AliasPackage) {
				continue;
			}
			$versions['versions'][$package->getName()]['aliases'][] = $package->getPrettyVersion();
			if ($package instanceof RootPackageInterface) {
				$versions['root']['aliases'][] = $package->getPrettyVersion();
			}
		}

		ksort($versions['versions']);
		ksort($versions);

		/* @var string $name */
		foreach ($versions['versions'] as $name => $version) {
			foreach (
				[
					'aliases',
					'replaced',
					'provided',
				] as $key
			) {
				if (isset($versions['versions'][$name][$key])) {
					sort($versions['versions'][$name][$key], SORT_NATURAL);
				}
			}
		}

		return $versions;
	}


	/**
	 * @param array<string, string> $installPaths
	 * @param array<string, int>    $devPackages
	 *
	 * @return array{name: string, pretty_version: string, version: string, reference: string|null, type: string,
	 *                     install_path: string, aliases: string[], dev: bool}
	 */
	private function dumpRootPackage(
		IOInterface          $io,
		RootPackageInterface $package,
		array                $installPaths,
		bool                 $devMode,
		string               $repoDir,
		array                $devPackages,
	): array
	{
		$data = $this->dumpInstalledPackage($io, $package, $installPaths, $repoDir, $devPackages);

		return [
			'name'           => $package->getName(),
			'pretty_version' => $data['pretty_version'],
			'version'        => $data['version'],
			'reference'      => $data['reference'],
			'type'           => $data['type'],
			'install_path'   => $data['install_path'],
			'aliases'        => $data['aliases'],
			'dev'            => $devMode,
			'extra'          => $package->getExtra(),
		];
	}

	/**
	 * @param array<string, string> $installPaths
	 * @param array<string, int>    $devPackages
	 *
	 * @return array{pretty_version: string, version: string, reference: string|null, type: string, install_path:
	 *                               string, aliases: string[], dev_requirement: bool}
	 */
	private function dumpInstalledPackage(
		IOInterface      $io,
		PackageInterface $package,
		array            $installPaths,
		string           $repoDir,
		array            $devPackages,
	): array
	{
		$reference = null;
		if ($package->getInstallationSource()) {
			$reference = $package->getInstallationSource() === 'source' ? $package->getSourceReference() : $package->getDistReference();
		}
		if (null === $reference) {
			$reference = ($package->getSourceReference() ?: $package->getDistReference()) ?: null;
		}

		if ($package instanceof RootPackageInterface) {
			$to          = $this->filesystem->normalizePath(realpath(Platform::getCwd()));
			$installPath = $this->filesystem->findShortestPath($repoDir, $to, true);
		} else {
			$installPath = $installPaths[$package->getName()];
		}

		if (!empty($package->getExtra())) {
			$io->write("<info>[HIT]</info> 📦 {$package->getName()} ({$package->getPrettyVersion()})");
		}

		return [
			'pretty_version'  => $package->getPrettyVersion(),
			'version'         => $package->getVersion(),
			'reference'       => $reference,
			'type'            => $package->getType(),
			'install_path'    => $installPath,
			'aliases'         => [],
			'dev_requirement' => isset($devPackages[$package->getName()]),
			'extra'           => $package->getExtra(),
		];
	}
}