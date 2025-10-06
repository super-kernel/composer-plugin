<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Abstract;

use AppendIterator;
use PhpParser\Comment\Doc;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RecursiveIteratorIterator;
use SplFileInfo;
use SuperKernel\ComposerPlugin\CodeParser\AnnotationExtractor;
use Symfony\Component\Console\Output\OutputInterface;
use function copy;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function str_replace;

abstract class GeneratorAbstract
{

	abstract protected function getTargetDir(): string;

	abstract protected function getSourcePath(): string;

	abstract protected function getIterator(): RecursiveIteratorIterator|AppendIterator;

	public function generate(OutputInterface $output): iterable
	{
		$parser  = new ParserFactory()->createForHostVersion();
		$printer = new Standard();

		/* @var SplFileInfo $file */
		foreach ($this->getIterator() as $file) {

			if ($file->isDir()) {
				continue;
			}

			$targetFile = $this->getTargetDir() . str_replace($this->getSourcePath(), '', $file->getRealPath());
			$targetDir  = dirname($targetFile);

			// 确保目录存在
			if (!is_dir($targetDir)) {
				@mkdir($targetDir, 0777, true);
			}

			if ($file->getExtension() !== 'php') {
				copy($file->getRealPath(), $targetFile);
			} else {
				// 对所有 php 文件移除注释以降低占用
				$code = file_get_contents($file->getRealPath());
				try {
					$ast = $parser->parse($code);

					$traverser = new NodeTraverser();
					// 使用自定义的 NodeVisitor 来提取注解信息
					$extractor = new AnnotationExtractor();
					$traverser->addVisitor($extractor);
					$traverser->addVisitor(new class extends NodeVisitorAbstract {
						public function enterNode(Node $node): void
						{
							// 移除普通注释
							$node->setAttribute('comments', []);

							// 移除 docblock，如果节点支持 getDocComment()
							$docComment = $node->getDocComment();
							if ($docComment instanceof Doc) {
								$node->setDocComment(new Doc('')); // 设置为空 Doc 对象
							}
						}
					});
					$ast = $traverser->traverse($ast);

					$newCode = $printer->prettyPrintFile($ast);
					file_put_contents($targetFile, $newCode);

					if ($extractor->hasAnnotation()) {
						yield $extractor->getAnnotations();
					}
				}
				catch (Error $e) {
					$output->writeln("<error> 解析失败: {$file->getRealPath()} -> {$e->getMessage()}</error>");
					continue;
				}
			}
		}
	}
}