<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\CodeParser;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\UseItem;
use PhpParser\NodeVisitorAbstract;

final class AnnotationExtractor extends NodeVisitorAbstract
{
	private string $namespace;

	private array $uses;

	private array $annotations;

	private string $class;

	private bool $hasAnnotation = false;

	public function enterNode(Node $node): void
	{
		if ($node instanceof Node\Stmt\Namespace_) {
			$this->namespace = $node->name->name;
		}

		if ($node instanceof Node\Stmt\Use_) {
			/* @var UseItem $useItem */
			foreach ($node->uses as $useItem) {
				$name      = $useItem->name->name;
				$aliasName = $useItem->alias?->name;

				if (is_null($aliasName)) {
					$namespaces = explode('\\', $name);
					$aliasName  = end($namespaces);
				}

				$this->uses[$aliasName] = $name;
			}
		}

		if ($node instanceof class_) {
			$classname = $node->name?->name;

			if (!is_null($classname)) {
				$this->class = $classname;
			}

			if (!empty($node->attrGroups)) {
				$this->hasAnnotation = true;
			}

			/* @var AttributeGroup $attrGroup */
			foreach ($node->attrGroups as $attrGroup) {
				/* @var Attribute $attr */
				foreach ($attrGroup->attrs as $attr) {
					$this->annotations[] = $attr->name->name;
				}
			}
		}
	}

	public function hasAnnotation(): bool
	{
		if (!isset($this->namespace)) {
			return false;
		}

		return $this->hasAnnotation;
	}

	public function getAnnotations(): array
	{
		$annotations = [];

		foreach ($this->annotations as $annotation) {

			$annotationName                 = $this->uses[$annotation] ?? $annotation;
			$classname                      = $this->namespace . '\\' . $this->class;
			$annotations[$annotationName][] = $classname;
		}

		return $annotations;
	}
}