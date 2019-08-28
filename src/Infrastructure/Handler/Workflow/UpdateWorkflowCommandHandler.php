<?php

/**
 * Copyright © Bold Brand Commerce Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types = 1);

namespace Ergonode\Workflow\Infrastructure\Handler\Workflow;

use Ergonode\Workflow\Domain\Command\Workflow\UpdateWorkflowCommand;
use Ergonode\Workflow\Domain\Repository\WorkflowRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 */
class UpdateWorkflowCommandHandler
{
    /**
     * @var WorkflowRepositoryInterface
     */
    private $repository;

    /**
     * @param WorkflowRepositoryInterface $repository
     */
    public function __construct(WorkflowRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param UpdateWorkflowCommand $command
     *
     * @throws \Exception
     */
    public function __invoke(UpdateWorkflowCommand $command)
    {
        $workflow = $this->repository->load($command->getId());

        Assert::notNull($workflow);

        foreach ($command->getStatuses() as $code => $status) {
            if (!$workflow->hasStatus($code)) {
                $workflow->addStatus($code, $status);
            } else {
                $workflow->changeStatus($code, $status);
            }
        }

        foreach ($workflow->getStatuses() as $code => $status) {
            if (!key_exists($code, $command->getStatuses())) {
                $workflow->removeStatus($code);
            }
        }

        $this->repository->save($workflow);
    }
}
