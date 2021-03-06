<?php declare(strict_types=1);

namespace Rector\NodeTypeResolver\PerNodeTypeResolver;

use PhpParser\Node;
use PhpParser\Node\Param;
use Rector\BetterPhpDocParser\NodeAnalyzer\DocBlockAnalyzer;
use Rector\Node\Attribute;
use Rector\NodeTypeResolver\Contract\NodeTypeResolverAwareInterface;
use Rector\NodeTypeResolver\Contract\PerNodeTypeResolver\PerNodeTypeResolverInterface;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\NodeTypeResolver\TypeContext;

final class ParamTypeResolver implements PerNodeTypeResolverInterface, NodeTypeResolverAwareInterface
{
    /**
     * @var TypeContext
     */
    private $typeContext;

    /**
     * @var NodeTypeResolver
     */
    private $nodeTypeResolver;

    /**
     * @var DocBlockAnalyzer
     */
    private $docBlockAnalyzer;

    public function __construct(TypeContext $typeContext, DocBlockAnalyzer $docBlockAnalyzer)
    {
        $this->typeContext = $typeContext;
        $this->docBlockAnalyzer = $docBlockAnalyzer;
    }

    /**
     * @return string[]
     */
    public function getNodeClasses(): array
    {
        return [Param::class];
    }

    /**
     * @param Param $paramNode
     * @return string[]
     */
    public function resolve(Node $paramNode): array
    {
        $variableName = (string) $paramNode->var->name;

        // 1. method(ParamType $param)
        if ($paramNode->type) {
            $variableTypes = $this->nodeTypeResolver->resolve($paramNode->type);
            if ($variableTypes) {
                $this->typeContext->addVariableWithTypes($variableName, $variableTypes);

                return $variableTypes;
            }
        }

        // 2. @param ParamType $param
        /* @var \PhpParser\Node\Stmt\ClassMethod $classMethod */
        $classMethod = $paramNode->getAttribute(Attribute::PARENT_NODE);

        // resolve param type from docblock
        $paramType = $this->docBlockAnalyzer->getTypeForParam($classMethod, $variableName);
        if ($paramType === null) {
            return [];
        }

        if ($paramType) {
            $this->typeContext->addVariableWithTypes($variableName, [$paramType]);
        }

        // already resolved in doc block
        return [$paramType];
    }

    public function setNodeTypeResolver(NodeTypeResolver $nodeTypeResolver): void
    {
        $this->nodeTypeResolver = $nodeTypeResolver;
    }
}
