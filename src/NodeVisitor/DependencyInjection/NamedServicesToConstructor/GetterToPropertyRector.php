<?php declare(strict_types=1);

namespace Rector\NodeVisitor\DependencyInjection\NamedServicesToConstructor;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitorAbstract;
use Rector\Builder\Class_\ClassPropertyCollector;
use Rector\Builder\Kernel\ServiceFromKernelResolver;
use Rector\Builder\Naming\NameResolver;
use Rector\Tests\NodeVisitor\DependencyInjection\NamedServicesToConstructorRector\Source\LocalKernel;

/**
 * Converts all:
 * $this->get('some_service') # where "some_service" is name of the service in container.
 *
 * into:
 * $this->someService # where "someService" is type of the service
 */
final class GetterToPropertyRector extends NodeVisitorAbstract
{
    /**
     * @var string
     */
    private $className;

    /**
     * @var NameResolver
     */
    private $nameResolver;

    /**
     * @var ServiceFromKernelResolver
     */
    private $serviceFromKernelResolver;

    /**
     * @var ClassPropertyCollector
     */
    private $classPropertyCollector;

    public function __construct(
        NameResolver $nameResolver,
        ServiceFromKernelResolver $serviceFromKernelResolver,
        ClassPropertyCollector $classPropertyCollector
    ) {
        $this->nameResolver = $nameResolver;
        $this->serviceFromKernelResolver = $serviceFromKernelResolver;
        $this->classPropertyCollector = $classPropertyCollector;
    }

    /**
     * @param Node[] $nodes
     * @return null|Node[]
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->className = null;

        foreach ($nodes as $node) {
            if ($node instanceof Class_) {
                $this->className = (string) $node->name;
            }
        }

        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        if ($this->isCandidate($node)) {
            $this->reconstruct($node);

            return $node;
        }

        return null;
    }

    private function isCandidate(Node $node): bool
    {
        // $var = $this->get('some_service');
        // $var = $this->get('some_service')->getData();
        if ($node instanceof Assign && ($node->expr instanceof MethodCall || $node->var instanceof MethodCall)) {
            if ($this->isContainerGetCall($node->expr)) {
                return true;
            }
        }

        // ['var => $this->get('some_service')->getData()]
        if ($node instanceof MethodCall && $node->var instanceof MethodCall) {
            if ($this->isContainerGetCall($node->var)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Assign|MethodCall $assignOrMethodCallNode
     */
    private function reconstruct(Node $assignOrMethodCallNode): void
    {
        if ($assignOrMethodCallNode instanceof Assign) {
            $refactoredMethodCall = $this->processMethodCallNode($assignOrMethodCallNode->expr);
            if ($refactoredMethodCall) {
                $assignOrMethodCallNode->expr = $refactoredMethodCall;
            }
        }

        if ($assignOrMethodCallNode instanceof MethodCall) {
            $refactoredMethodCall = $this->processMethodCallNode($assignOrMethodCallNode->var);
            if ($refactoredMethodCall) {
                $assignOrMethodCallNode->var = $refactoredMethodCall;
            }
        }
    }

    /**
     * Is "$this->get('string')" statements?
     */
    private function isContainerGetCall(MethodCall $methodCall): bool
    {
        if ($methodCall->var->name !== 'this') {
            return false;
        }

        if ((string) $methodCall->name !== 'get') {
            return false;
        }

        if (! $methodCall->args[0]->value instanceof String_) {
            return false;
        }

        return true;
    }

    private function processMethodCallNode(MethodCall $methodCall): ?PropertyFetch
    {
        /** @var String_ $argument */
        $argument = $methodCall->args[0]->value;
        $serviceName = $argument->value;

        $serviceType = $this->serviceFromKernelResolver->resolveServiceClassByNameFromKernel(
            $serviceName,
            LocalKernel::class
        );

        if ($serviceType === null) {
            return null;
        }

        $propertyName = $this->nameResolver->resolvePropertyNameFromType($serviceType);

        $this->classPropertyCollector->addPropertyForClass($this->className, $serviceType, $propertyName);

        return $this->createPropertyFetch($propertyName);
    }

    /**
     * Creates "$this->propertyName".
     */
    private function createPropertyFetch(string $propertyName): PropertyFetch
    {
        return new PropertyFetch(
            new Variable('this', [
                'name' => $propertyName,
            ]),
            $propertyName
        );
    }
}