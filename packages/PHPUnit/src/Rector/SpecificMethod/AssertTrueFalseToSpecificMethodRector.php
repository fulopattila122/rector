<?php declare(strict_types=1);

namespace Rector\PHPUnit\Rector\SpecificMethod;

use PhpParser\Node;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Builder\IdentifierRenamer;
use Rector\Node\NodeFactory;
use Rector\NodeAnalyzer\MethodCallAnalyzer;
use Rector\Rector\AbstractPHPUnitRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class AssertTrueFalseToSpecificMethodRector extends AbstractPHPUnitRector
{
    /**
     * @var string[][]|false[][]
     */
    private $defaultOldToNewMethods = [
        'is_readable' => ['assertIsReadable', 'assertNotIsReadable'],
        'array_key_exists' => ['assertArrayHasKey', 'assertArrayNotHasKey'],
        'array_search' => ['assertContains', 'assertNotContains'],
        'in_array' => ['assertContains', 'assertNotContains'],
        'empty' => ['assertEmpty', 'assertNotEmpty'],
        'file_exists' => ['assertFileExists', 'assertFileNotExists'],
        'is_dir' => ['assertDirectoryExists', 'assertDirectoryNotExists'],
        'is_infinite' => ['assertInfinite', 'assertFinite'],
        'is_null' => ['assertNull', 'assertNotNull'],
        'is_writable' => ['assertIsWritable', 'assertNotIsWritable'],
        'is_nan' => ['assertNan', false],
        'is_a' => ['assertInstanceOf', 'assertNotInstanceOf'],
    ];

    /**
     * @var string[][]|false[][]
     */
    private $activeOldToNewMethods = [];

    /**
     * @var MethodCallAnalyzer
     */
    private $methodCallAnalyzer;

    /**
     * @var IdentifierRenamer
     */
    private $identifierRenamer;

    /**
     * @var NodeFactory
     */
    private $nodeFactory;

    /**
     * @var string|null
     */
    private $activeFuncCallName;

    /**
     * @param string[][] $activeMethods
     */
    public function __construct(
        array $activeMethods = [],
        MethodCallAnalyzer $methodCallAnalyzer,
        IdentifierRenamer $identifierRenamer,
        NodeFactory $nodeFactory
    ) {
        $this->activeOldToNewMethods = $this->filterActiveOldToNewMethods($activeMethods);
        $this->methodCallAnalyzer = $methodCallAnalyzer;
        $this->identifierRenamer = $identifierRenamer;
        $this->nodeFactory = $nodeFactory;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Turns true/false comparisons to their method name alternatives in PHPUnit TestCase when possible',
            [
                new CodeSample(
                    '$this->assertTrue(is_readable($readmeFile), "message");',
                    '$this->assertIsReadable($readmeFile, "message");'
                ),
            ]
        );
    }

    public function isCandidate(Node $node): bool
    {
        if (! $this->isInTestClass($node)) {
            return false;
        }

        if (! $this->methodCallAnalyzer->isMethods($node, ['assertTrue', 'assertFalse'])) {
            return false;
        }

        /** @var MethodCall $methodCallNode */
        $methodCallNode = $node;

        if (! isset($methodCallNode->args[0])) {
            return false;
        }

        $firstArgumentValue = $methodCallNode->args[0]->value;

        $funcCallName = $this->resolveFunctionName($firstArgumentValue);
        if ($funcCallName === null) {
            return false;
        }

        $this->activeFuncCallName = $funcCallName;

        return isset($this->activeOldToNewMethods[$funcCallName]);
    }

    /**
     * @param MethodCall $methodCallNode
     */
    public function refactor(Node $methodCallNode): ?Node
    {
        $this->renameMethod($methodCallNode);
        $this->moveFunctionArgumentsUp($methodCallNode);

        return $methodCallNode;
    }

    private function renameMethod(MethodCall $methodCallNode): void
    {
        /** @var Identifier $identifierNode */
        $identifierNode = $methodCallNode->name;
        $oldMethodName = $identifierNode->toString();

        [$trueMethodName, $falseMethodName] = $this->activeOldToNewMethods[$this->activeFuncCallName];

        if ($oldMethodName === 'assertTrue' && $trueMethodName) {
            $this->identifierRenamer->renameNode($methodCallNode, $trueMethodName);
        } elseif ($oldMethodName === 'assertFalse' && $falseMethodName) {
            $this->identifierRenamer->renameNode($methodCallNode, $falseMethodName);
        }
    }

    /**
     * Before:
     * - $this->assertTrue(array_key_exists('...', ['...']), 'second argument');
     *
     * After:
     * - $this->assertArrayHasKey('...', ['...'], 'second argument');
     */
    private function moveFunctionArgumentsUp(MethodCall $methodCallNode): void
    {
        $funcCallOrEmptyNode = $methodCallNode->args[0]->value;
        if ($funcCallOrEmptyNode instanceof FuncCall) {
            $funcCallOrEmptyNodeName = (string) $funcCallOrEmptyNode->name;
            $funcCallOrEmptyNodeArgs = $funcCallOrEmptyNode->args;
            $oldArguments = $methodCallNode->args;
            unset($oldArguments[0]);

            $methodCallNode->args = $this->buildNewArguments(
                $funcCallOrEmptyNodeName,
                $funcCallOrEmptyNodeArgs,
                $oldArguments
            );
        }

        if ($funcCallOrEmptyNode instanceof Empty_) {
            $methodCallNode->args[0] = $this->nodeFactory->createArg($funcCallOrEmptyNode->expr);
        }
    }

    private function resolveFunctionName(Node $node): ?string
    {
        if ($node instanceof FuncCall
            && $node->name instanceof Name
        ) {
            /** @var Name $nameNode */
            $nameNode = $node->name;

            return $nameNode->toString();
        }

        if ($node instanceof Empty_) {
            return 'empty';
        }

        return null;
    }

    /**
     * @param string[][]|false[][] $activeMethods
     * @return string[][]|false[][]
     */
    private function filterActiveOldToNewMethods(array $activeMethods = []): array
    {
        if ($activeMethods) {
            return array_filter($this->defaultOldToNewMethods, function (string $method) use ($activeMethods): bool {
                return in_array($method, $activeMethods, true);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $this->defaultOldToNewMethods;
    }

    /**
     * @param mixed[] $funcCallOrEmptyNodeArgs
     * @param mixed[] $oldArguments
     * @return mixed[]
     */
    private function buildNewArguments(
        string $funcCallOrEmptyNodeName,
        array $funcCallOrEmptyNodeArgs,
        array $oldArguments
    ): array {
        if (in_array($funcCallOrEmptyNodeName, ['in_array', 'array_search'], true)
            && count($funcCallOrEmptyNodeArgs) === 3) {
            unset($funcCallOrEmptyNodeArgs[2]);

            return array_merge($funcCallOrEmptyNodeArgs, $oldArguments);
        }

        if ($funcCallOrEmptyNodeName === 'is_a') {
            [$object, $class] = $funcCallOrEmptyNodeArgs;

            return array_merge([$class, $object], $oldArguments);
        }

        return array_merge($funcCallOrEmptyNodeArgs, $oldArguments);
    }
}
