<?php declare(strict_types=1);

namespace Rector\Rector\Dynamic;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use Rector\BetterPhpDocParser\NodeAnalyzer\DocBlockAnalyzer;
use Rector\Node\Attribute;
use Rector\Rector\AbstractPHPUnitRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class AnnotationReplacerRector extends AbstractPHPUnitRector
{
    /**
     * @var string[][]
     */
    private $classToAnnotationMap = [];

    /**
     * @var DocBlockAnalyzer
     */
    private $docBlockAnalyzer;

    /**
     * @var string[]
     */
    private $activeAnnotationMap = [];

    /**
     * @param string[][] $classToAnnotationMap
     */
    public function __construct(array $classToAnnotationMap, DocBlockAnalyzer $docBlockAnalyzer)
    {
        $this->docBlockAnalyzer = $docBlockAnalyzer;
        $this->classToAnnotationMap = $classToAnnotationMap;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            '[Dynamic] Turns defined annotations above properties and methods to their new values.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
/** @test */
public function someMethod() {};
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
/** @scenario */
public function someMethod() {};
CODE_SAMPLE
                ),
            ]
        );
    }

    public function isCandidate(Node $node): bool
    {
        if ($this->shouldSkip($node)) {
            return false;
        }

        $parentNode = $node->getAttribute(Attribute::PARENT_NODE);
        foreach ($this->classToAnnotationMap as $type => $annotationMap) {
            if (! in_array($type, $parentNode->getAttribute(Attribute::TYPES), true)) {
                continue;
            }

            $this->activeAnnotationMap = $annotationMap;

            if ($this->hasAnyAnnotation($node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ClassMethod|Property $node
     */
    public function refactor(Node $node): ?Node
    {
        foreach ($this->activeAnnotationMap as $oldAnnotation => $newAnnotation) {
            $this->docBlockAnalyzer->replaceAnnotationInNode($node, $oldAnnotation, $newAnnotation);
        }

        return $node;
    }

    private function shouldSkip(Node $node): bool
    {
        if (! $node instanceof ClassMethod && ! $node instanceof Property) {
            return true;
        }

        /** @var Node|null $parentNode */
        $parentNode = $node->getAttribute(Attribute::PARENT_NODE);
        if (! $parentNode) {
            return true;
        }

        return false;
    }

    private function hasAnyAnnotation(Node $node): bool
    {
        foreach ($this->activeAnnotationMap as $oldAnnotation => $newAnnotation) {
            if ($this->docBlockAnalyzer->hasTag($node, $oldAnnotation)) {
                return true;
            }
        }

        return false;
    }
}
