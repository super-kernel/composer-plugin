<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Abstract;

use Composer\ClassMapGenerator\ClassMapGenerator;
use Composer\Composer;
use Composer\Util\Filesystem;
use FilesystemIterator;
use Phar;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Class_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ClosureUse;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\PrettyPrinter\Standard;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SuperKernel\ComposerPlugin\Contract\CommandHandlerInterface;
use SuperKernel\ComposerPlugin\Enum\Target;
use SuperKernel\ComposerPlugin\Generator\PackageGenerator;
use SuperKernel\ComposerPlugin\Generator\ProjectFileGenerator;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function array_merge;
use function array_unique;
use function array_values;
use function basename;
use function count;
use function dirname;
use function file_put_contents;
use function iterator_to_array;

abstract class CommandHandlerAbstract implements CommandHandlerInterface
{

	final protected Composer $composer;

	final protected InputInterface $input;


	final protected OutputInterface $output;

	private array $annotations = [];

	private string $pharStub;

	public function __construct(Composer $composer, InputInterface $input, OutputInterface $output)
	{
		$this->composer = $composer;
		$this->input    = $input;
		$this->output   = $output;

		$this->getTarget()->readyDirectory();
	}

	abstract protected function getTarget(): Target;

	final protected function handlePackages(): void
	{
		$requireDev          = $this->getTarget() === Target::DEBUG_DIR;
		$installationManager = $this->composer->getInstallationManager();
		$repositoryManager   = $this->composer->getRepositoryManager();
		$installedRepo       = $repositoryManager->getLocalRepository();

		// 开始扫描开发者安装的packages
		foreach ($installedRepo->getPackages() as $package) {
			if ($requireDev === false && $package->isDev()) {
				continue;
			}

			if ($package->getType() === 'library') {
				$this->output->writeln("<info>[HIT]</info> 📦   {$package->getName()} ({$package->getPrettyVersion()})");

				$sourcePath = $installationManager->getInstallPath($package);
				$iterable   = new PackageGenerator($package->getName(), $sourcePath)->generate($this->output);

				foreach ($iterable as $annotations) {
					$this->annotations = array_merge_recursive($this->annotations, $annotations);
				}
			}
		}
	}

	final protected function handleSourceCode(): void
	{
		$requireDev = $this->getTarget() === Target::DEBUG_DIR;
		$sourcePath = dirname($this->composer->getConfig()->get('vendor-dir'));
		$psr4Dirs   = $this->composer->getPackage()->getAutoload()['psr-4'] ?? [];

		if ($requireDev === true) {
			$psr4Dirs = array_merge($psr4Dirs, $this->composer->getPackage()->getDevAutoload()['psr-4'] ?? []);
		}

		$psr4Dirs = array_unique(array_values($psr4Dirs));

		$projectFileGenerator = new ProjectFileGenerator($psr4Dirs, $sourcePath);
		$steps                = $projectFileGenerator->steps();

		$progress = new ProgressBar($this->output, $steps);
		$progress->setFormat('[%bar%] %percent%% %elapsed:10s%');
		$progress->start();

		$iterable = $projectFileGenerator->generate($this->output);

		foreach ($iterable as $annotations) {
			// TODO:合并同 key 元素，并对第二维进行去重
			$annotationsArray = array_merge_recursive($this->annotations, $annotations);
			foreach ($annotationsArray as &$annotation) {
				$annotation = array_values(array_unique($annotation));
			}
			$this->annotations = $annotationsArray;

			$progress->advance();
		}

		$progress->finish();
		$this->output->writeln('');
	}

	final protected function generatePharStub(): void
	{
		$classmap = ClassMapGenerator::createMap(Target::RUNTIME_DIR->get());

		$factory = new BuilderFactory();
		$stmts   = [];

		// 添加 <?php declare(strict_types=1);
		$stmts[] = new Stmt\Declare_(
			[
				new Stmt\DeclareDeclare(
					'strict_types',
					new  LNumber(1),
				),
			],
		);

		// 构建 $classmap = [...]
		$items = [];
		foreach ($classmap as $class => $path) {
			$relative = ltrim(str_replace(Target::RUNTIME_DIR->get(), '', new Filesystem()->normalizePath($path)), '/\\');

			$items[] = new ArrayItem(
			// key 使用 Class::class
				new Concat(
					new ConstFetch(new Name('__DIR__')),
					new String_('/' . $relative),
				),
				new ClassConstFetch(new Name($class), 'class'),
			);
		}
		$stmts[] = new Stmt\Expression(
			new Assign(
				$factory->var('classmap'),
				new Array_($items, ['kind' => Array_::KIND_SHORT]),
			),
		);

		// 定义 define 全局变量
		$arrayItems = [];

		// 遍历每个注解并为其创建具有键值的 ArrayItem
		foreach ($this->annotations as $key => $annotation) {
			$subArrayItems = [];
			foreach ($annotation as $item) {
				$subArrayItems[] = new ArrayItem(
					new ClassConstFetch(new Name('\\' . $item), 'class'), // 值
				);
			}
			// 使用原始键值作为每个子数组的键
			$arrayItems[] = new ArrayItem(
				new Array_($subArrayItems),
				new ClassConstFetch(new Name($key), 'class'),
			);
		}

		$attributes = new Array_($arrayItems);

		// 构建 spl_autoload_register
		$closure = new Closure(
			[
				'params' => [new Param(new Variable('class'))],
				'uses'   => [new ClosureUse(new Variable('classmap'))],
				'stmts'  => [
					new Stmt\If_(
						new FuncCall(
							new Name('isset'),
							[
								new Arg(
									new ArrayDimFetch(
										new Variable('classmap'),
										new Variable('class'),
									),
								),
							],
						),
						[
							'stmts' => [
								new Stmt\Expression(
									new Include_(
										new ArrayDimFetch(
											new Variable('classmap'),
											new Variable('class'),
										),
										Include_::TYPE_REQUIRE,
									),
								),
							],
						],
					),
				],
			],
		);

		$stmts[] = new Stmt\Expression(
			new FuncCall(
				new Name('spl_autoload_register'),
				[
					new Arg($closure),
					new Arg(new ConstFetch(new Name('true'))),
				],
			),
		);

		// 添加框架运行逻辑
		$stmts[] = new Stmt\Expression(
			new MethodCall(
				new MethodCall(
					new New_(
						new Name('\SuperKernel\Di\Container'),
						[
							new Arg($attributes),
						],
					),
					new Identifier('get'),
					[
						new Arg(
							new ClassConstFetch(
								new Name('\SuperKernel\Contract\ApplicationInterface'),
								'class',
							),
						),
					],
				),
				new Identifier('run'),
			),
		);

		// 将 AST 语法转换成 PHP code
		$printer        = new Standard();
		$this->pharStub = $printer->prettyPrintFile($stmts);

		// 写入引导文件
		file_put_contents(Target::RUNTIME_DIR->get() . 'bin.php', $this->pharStub);
	}

	final protected function buildPharFile(): void
	{
		$sourceDir  = Target::RUNTIME_DIR->get();
		$outputPhar = $this->getTarget()->get() . 'bin.phar';

		$this->getTarget()->readyDirectory();

		$this->output->writeln("Creating PHAR from: $sourceDir");

		$phar = new Phar(
			$outputPhar,
			FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
			basename($outputPhar),
		);

		$phar->startBuffering();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY,
		);

		$steps = count(iterator_to_array($iterator));

		$progress = new ProgressBar($this->output, $steps);
		$progress->setFormat('[%bar%] %percent%% %elapsed:10s%');
		$progress->start();

		foreach ($iterator as $file) {
			/** @var SplFileInfo $file */
			$realPath  = $file->getRealPath();
			$localPath = ltrim(substr($realPath, strlen($sourceDir)), '/');

			$phar->addFile($realPath, $localPath);
			$progress->advance();
		}

		$progress->finish();
		$this->output->writeln('');

		// 设置可执行入口
		$phar->setStub("#!/usr/bin/env php\n" . $this->pharStub . "\n__HALT_COMPILER();");

		// 启用压缩（非必须）
		if (Phar::canCompress(Phar::GZ)) {
			$phar->compressFiles(Phar::GZ);
		}

		$phar->stopBuffering();

		$this->output->writeln("✅   PHAR built successfully: $outputPhar");
	}
}