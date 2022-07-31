<?php

namespace Neos\Neos\Ui\Domain\Model\Changes;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\SharedModel\Node\OriginDimensionSpacePoint;
use Neos\ContentRepository\Projection\ContentGraph\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\SharedModel\Node\NodeName;
use Neos\ContentRepository\Feature\NodeDuplication\Command\CopyNodesRecursively;
use Neos\ContentRepository\Feature\NodeDuplication\NodeDuplicationCommandHandler;
use Neos\ContentRepository\SharedModel\User\UserIdentifier;

class CopyInto extends AbstractStructuralChange
{
    protected ?string $parentContextPath;

    protected ?NodeInterface $cachedParentNode;

    public function setParentContextPath(string $parentContextPath): void
    {
        $this->parentContextPath = $parentContextPath;
    }

    public function getParentNode(): ?NodeInterface
    {
        if ($this->cachedParentNode === null) {
            $this->cachedParentNode = $this->parentContextPath
                ? $this->nodeService->getNodeFromContextPath($this->parentContextPath)
                : null;
        }

        return $this->cachedParentNode;
    }

    /**
     * "Subject" is the to-be-copied node; the "parent" node is the new parent
     */
    public function canApply(): bool
    {
        $parentNode = $this->getParentNode();

        return $this->subject
            && $parentNode
            && $this->isNodeTypeAllowedAsChildNode($parentNode, $this->subject->getNodeType());
    }

    public function getMode(): string
    {
        return 'into';
    }

    /**
     * Applies this change
     */
    public function apply(): void
    {
        $subject = $this->getSubject();
        $parentNode = $this->getParentNode();
        if ($parentNode && $subject && $this->canApply()) {
            $targetNodeName = NodeName::fromString(uniqid('node-'));
            $command = CopyNodesRecursively::create(
                $this->contentGraph->getSubgraphByIdentifier(
                    $subject->getSubgraphIdentity()->contentStreamIdentifier,
                    $subject->getSubgraphIdentity()->dimensionSpacePoint,
                    $subject->getSubgraphIdentity()->visibilityConstraints
                ),
                $subject,
                OriginDimensionSpacePoint::fromDimensionSpacePoint($subject->getSubgraphIdentity()->dimensionSpacePoint),
                UserIdentifier::forSystemUser(), // TODO
                $parentNode->getNodeAggregateIdentifier(),
                null,
                $targetNodeName
            );

            $contentRepository = $this->contentRepositoryRegistry->get($subject->getSubgraphIdentity()->contentRepositoryIdentifier);
            $contentRepository->handle($command)->block();

            /** @var NodeInterface $newlyCreatedNode */
            $newlyCreatedNode = $this->nodeAccessorFor($parentNode)->findChildNodeConnectedThroughEdgeName(
                $parentNode,
                $targetNodeName
            );
            // we render content directly as response of this operation,
            // so we need to flush the caches at the copy target
            $this->contentCacheFlusher->flushNodeAggregate(
                $newlyCreatedNode->getSubgraphIdentity()->contentRepositoryIdentifier,
                $newlyCreatedNode->getSubgraphIdentity()->contentStreamIdentifier,
                $newlyCreatedNode->getNodeAggregateIdentifier()
            );
            $this->finish($newlyCreatedNode);
            // NOTE: we need to run "finish" before "addNodeCreatedFeedback"
            // to ensure the new node already exists when the last feedback is processed
            $this->addNodeCreatedFeedback($newlyCreatedNode);
        }
    }
}
