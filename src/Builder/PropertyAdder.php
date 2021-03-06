<?php declare(strict_types=1);

namespace Rector\Builder;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use Rector\PhpParser\NodeVisitor\PropertyAddingNodeVisitor;

/**
 * Adds new properties to class and to constructor
 */
final class PropertyAdder
{
    /**
     * @var NodeTraverser
     */
    private $nodeTraverser;

    public function __construct(PropertyAddingNodeVisitor $propertyAddingNodeVisitor)
    {
        $this->nodeTraverser = new NodeTraverser();
        $this->nodeTraverser->addVisitor($propertyAddingNodeVisitor);
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function addPropertiesToNodes(array $nodes): array
    {
        return $this->nodeTraverser->traverse($nodes);
    }
}
