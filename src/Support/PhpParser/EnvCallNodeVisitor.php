<?php

declare(strict_types=1);

namespace Worksome\Envy\Support\PhpParser;

use Illuminate\Support\Collection;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use Worksome\Envy\Support\EnvironmentCall;

final class EnvCallNodeVisitor extends NodeVisitorAbstract
{
    /**
     * A collection of discovered env calls.
     *
     * @var Collection<int, EnvironmentCall>
     */
    private Collection $environmentVariables;

    /**
     * The printer to format env calls with.
     */
    private PrettyPrinterAbstract $printer;

    public function __construct(private string $filePath)
    {
        $this->environmentVariables = collect();
        $this->printer = new Standard();
    }

    public function enterNode(Node $node): Node
    {
        if (! $node instanceof Node\Expr\FuncCall) {
            return $node;
        }

        if (! $this->isEnvCall($node)) {
            return $node;
        }

        $this->environmentVariables->push(new EnvironmentCall(
            $this->filePath,
            $node->getStartLine(),
            $this->print($node->getArgs()[0]->value),
            $this->getDefaultValue($node),
            $this->getComment($node),
        ));

        return $node;
    }

    /**
     * @return Collection<int, EnvironmentCall>
     */
    public function getEnvironmentVariables(): Collection
    {
        return $this->environmentVariables;
    }

    private function isEnvCall(Node\Expr\FuncCall $node): bool
    {
        if (! $node->name instanceof Node\Name) {
            return false;
        }

        if (! $node->name->isUnqualified()) {
            return false;
        }

        if ($node->name->toLowerString() !== 'env') {
            return false;
        }

        if (count($node->getArgs()) === 0) {
            return false;
        }

        return true;
    }

    private function getDefaultValue(Node\Expr\FuncCall $node): mixed
    {
        if (count($node->getArgs()) < 2) {
            return null;
        }

        $providedDefault = $node->getArgs()[1]->value;

        if ($providedDefault instanceof Node\Expr\CallLike) {
            return null;
        }

        return $this->print($providedDefault);
    }

    private function getComment(Node\Expr\FuncCall $node): string|null
    {
        /** @var Node|null $previousNode */
        $previousNode = $node->getAttribute('previous');

        if ($previousNode === null) {
            return null;
        }

        /** @var array<int, Comment>|null $comments */
        $comments = $previousNode->getAttribute('comments');

        if ($comments === null || count($comments) === 0) {
            return null;
        }

        return strval($comments[0]->getReformattedText());
    }

    private function print(Node $node): string
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        return $this->printer->prettyPrint([$node]);
    }
}
