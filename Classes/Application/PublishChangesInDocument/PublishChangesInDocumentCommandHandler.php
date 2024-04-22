<?php

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\Neos\Ui\Application\PublishChangesInDocument;

use Neos\ContentRepository\Core\Feature\WorkspaceRebase\Exception\WorkspaceRebaseFailed;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateCurrentlyDoesNotExist;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Workspace\WorkspaceProvider;
use Neos\Neos\Ui\Application\Shared\Conflicts;
use Neos\Neos\Ui\Application\Shared\ConflictsOccurred;
use Neos\Neos\Ui\Application\Shared\PublishSucceeded;

/**
 * The application layer level command handler to perform publication of
 * all changes recorded for a given document
 *
 * @internal for communication within the Neos UI only
 */
#[Flow\Scope("singleton")]
final class PublishChangesInDocumentCommandHandler
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected WorkspaceProvider $workspaceProvider;

    /**
     * @throws NodeAggregateCurrentlyDoesNotExist
     */
    public function handle(
        PublishChangesInDocumentCommand $command
    ): PublishSucceeded|ConflictsOccurred {
        try {
            $workspace = $this->workspaceProvider->provideForWorkspaceName(
                $command->contentRepositoryId,
                $command->workspaceName
            );
            $publishingResult = $workspace->publishChangesInDocument($command->documentId);

            return new PublishSucceeded(
                numberOfAffectedChanges: $publishingResult->numberOfPublishedChanges,
                baseWorkspaceName: $workspace->getCurrentBaseWorkspaceName()?->value
            );
        } catch (NodeAggregateCurrentlyDoesNotExist $e) {
            throw new NodeAggregateCurrentlyDoesNotExist(
                'Node could not be published, probably because of a missing parentNode. Please check that the parentNode has been published.',
                1682762156
            );
        } catch (WorkspaceRebaseFailed $e) {
            $conflictsBuilder = Conflicts::builder(
                contentRepository: $this->contentRepositoryRegistry
                    ->get($command->contentRepositoryId),
                workspaceName: $command->workspaceName,
                preferredDimensionSpacePoint: $command->preferredDimensionSpacePoint
            );

            foreach ($e->commandsThatFailedDuringRebase as $commandThatFailedDuringRebase) {
                $conflictsBuilder->addCommandThatFailedDuringRebase($commandThatFailedDuringRebase);
            }

            return new ConflictsOccurred(
                conflicts: $conflictsBuilder->build()
            );
        }
    }
}
