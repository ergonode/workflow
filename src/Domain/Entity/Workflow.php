<?php

/**
 * Copyright © Bold Brand Commerce Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types = 1);

namespace Ergonode\Workflow\Domain\Entity;

use Ergonode\Core\Domain\Entity\AbstractId;
use Ergonode\EventSourcing\Domain\AbstractAggregateRoot;
use Ergonode\Workflow\Domain\Event\Workflow\WorkflowCreatedEvent;
use Ergonode\Workflow\Domain\Event\Workflow\WorkflowDefaultStatusSetEvent;
use Ergonode\Workflow\Domain\Event\Workflow\WorkflowStatusAddedEvent;
use Ergonode\Workflow\Domain\Event\Workflow\WorkflowStatusRemovedEvent;
use Ergonode\Workflow\Domain\Event\Workflow\WorkflowTransitionAddedEvent;
use Ergonode\Workflow\Domain\Event\Workflow\WorkflowTransitionChangedEvent;
use Ergonode\Workflow\Domain\Event\Workflow\WorkflowTransitionRemovedEvent;
use Ergonode\Workflow\Domain\ValueObject\StatusCode;
use Ergonode\Workflow\Domain\ValueObject\Transition;
use JMS\Serializer\Annotation as JMS;
use Webmozart\Assert\Assert;

/**
 */
class Workflow extends AbstractAggregateRoot
{
    public const DEFAULT = 'default';

    /**
     * @var WorkflowId
     *
     * @JMS\Type("Ergonode\Workflow\Domain\Entity\WorkflowId")
     */
    private $id;

    /**
     * @var string
     *
     * @JMS\Type("string")
     */
    private $code;

    /**
     * @var StatusCode[]
     *
     * @JMS\Type("array<Ergonode\Workflow\Domain\ValueObject\StatusCode>")
     */
    private $statuses;

    /**
     * @var Transition[]
     *
     * @JMS\Type("array<string, Ergonode\Workflow\Domain\ValueObject\Transition>")
     */
    private $transitions;

    /**
     * @var StatusCode
     */
    private $defaultStatus;

    /**
     * @param WorkflowId   $id
     * @param string       $code
     * @param StatusCode[] $statuses
     *
     * @throws \Exception
     */
    public function __construct(WorkflowId $id, string $code, array $statuses = [])
    {
        Assert::allIsInstanceOf($statuses, StatusCode::class);

        $this->apply(new WorkflowCreatedEvent($id, $code, array_values($statuses)));
    }

    /**
     * @return WorkflowId
     */
    public function getId(): AbstractId
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param StatusCode $code
     *
     * @return bool
     */
    public function hasStatus(StatusCode $code): bool
    {
        return isset($this->statuses[$code->getValue()]);
    }

    /**
     * @param StatusCode $code
     *
     * @throws \Exception
     */
    public function setDefaultStatus(StatusCode $code): void
    {
        if (!$this->hasStatus($code)) {
            throw  new \RuntimeException(sprintf('Status "%s" not exists', $code->getValue()));
        }

        if ($this->defaultStatus && !$code->isEqual($this->defaultStatus)) {
            $this->apply(new WorkflowDefaultStatusSetEvent($code));
        }
    }

    /**
     * @return bool
     */
    public function hasDefaultStatus(): bool
    {
        return null !== $this->defaultStatus;
    }

    /**
     * @return StatusCode
     */
    public function getDefaultStatus(): StatusCode
    {
        if (!$this->hasDefaultStatus()) {
            throw  new \RuntimeException('Default status not exists');
        }

        return $this->defaultStatus;
    }

    /**
     * @param StatusCode $source
     * @param StatusCode $destination
     *
     * @return bool
     */
    public function hasTransition(StatusCode $source, StatusCode $destination): bool
    {
        foreach ($this->transitions as $key => $transition) {
            if ($source->isEqual($transition->getSource()) && $destination->isEqual($transition->getDestination())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param StatusCode $code
     *
     * @throws \Exception
     */
    public function addStatus(StatusCode $code): void
    {
        if ($this->hasStatus($code)) {
            throw  new \RuntimeException(sprintf('Status "%s" already exists', $code->getValue()));
        }

        $this->apply(new WorkflowStatusAddedEvent($code));
    }

    /**
     * @param Transition $transition
     *
     * @throws \Exception
     */
    public function addTransition(Transition $transition): void
    {
        $source = $transition->getSource();
        $destination = $transition->getDestination();

        if ($this->hasTransition($source, $destination)) {
            throw  new \RuntimeException(sprintf('Transition from "%s" to "%s" already exists', $source->getValue(), $destination->getValue()));
        }

        if (!$this->hasStatus($source)) {
            throw  new \RuntimeException(sprintf('Transition source status "%s" not exists', $source->getValue()));
        }

        if (!$this->hasStatus($destination)) {
            throw  new \RuntimeException(sprintf('Transition destination status "%s" not exists', $destination->getValue()));
        }

        $this->apply(new WorkflowTransitionAddedEvent($transition));
    }

    /**
     * @param StatusCode $source
     * @param StatusCode $destination
     * @param Transition $transition
     *
     * @throws \Exception
     */
    public function changeTransition(StatusCode $source, StatusCode $destination, Transition $transition): void
    {
        if (!$this->hasTransition($source, $destination)) {
            throw  new \RuntimeException('Transition not exists');
        }

        if (!$this->hasStatus($source)) {
            throw  new \RuntimeException(sprintf('Transition source status "%s" not exists', $source->getValue()));
        }

        if (!$this->hasStatus($destination)) {
            throw  new \RuntimeException(sprintf('Transition destination status "%s" not exists', $destination->getValue()));
        }

        $this->apply(new WorkflowTransitionChangedEvent($source, $destination, $this->getTransition($source, $destination), $transition));
    }

    /**
     * @param StatusCode $source
     * @param StatusCode $destination
     *
     * @throws \Exception
     */
    public function removeTransition(StatusCode $source, StatusCode $destination): void
    {
        $this->apply(new WorkflowTransitionRemovedEvent($source, $destination));
    }

    /**
     * @param StatusCode $source
     * @param StatusCode $destination
     *
     * @return Transition
     */
    public function getTransition(StatusCode $source, StatusCode $destination): Transition
    {
        foreach ($this->transitions as $key => $transition) {
            if ($source->isEqual($transition->getSource()) && $destination->isEqual($transition->getDestination())) {
                return $transition;
            }
        }

        throw  new \RuntimeException(sprintf('Transition from "%s" to "%s" not exists', $source->getValue(), $destination->getValue()));
    }

    /**
     * @return Transition[]
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /**
     * @param StatusCode $code
     *
     * @return Transition[]
     */
    public function getTransitionsFromStatus(StatusCode $code): array
    {
        $transitions = [];
        foreach ($this->transitions as $transition) {
            if ($code->isEqual($transition->getSource())) {
                $transitions[] = $transition;
            }
        }

        return $transitions;
    }

    /**
     * @param StatusCode $id
     *
     * @throws \Exception
     */
    public function removeStatus(StatusCode $id): void
    {
        if (!$this->hasStatus($id)) {
            throw  new \RuntimeException(sprintf('Status ID "%s" not exists', $id));
        }

        $this->apply(new WorkflowStatusRemovedEvent($id));
    }

    /**
     * @return StatusCode[]
     */
    public function getStatuses(): array
    {
        return array_values($this->statuses);
    }

    /**
     * @param WorkflowCreatedEvent $event
     */
    protected function applyWorkflowCreatedEvent(WorkflowCreatedEvent $event): void
    {
        $this->id = $event->getId();
        $this->code = $event->getCode();
        $this->statuses = [];
        $this->transitions = [];
        foreach ($event->getStatuses() as $status) {
            if (null === $this->defaultStatus) {
                $this->defaultStatus = $status;
            }
            $this->statuses[$status->getValue()] = $status;
        }
    }

    /**
     * @param WorkflowStatusAddedEvent $event
     */
    protected function applyWorkflowStatusAddedEvent(WorkflowStatusAddedEvent $event): void
    {
        $this->statuses[$event->getcode()->getValue()] = $event->getCode();

        if (null === $this->defaultStatus) {
            $this->defaultStatus = $event->getCode();
        }
    }

    /**
     * @param WorkflowStatusRemovedEvent $event
     */
    protected function applyWorkflowStatusRemovedEvent(WorkflowStatusRemovedEvent $event): void
    {
        unset($this->statuses[$event->getCode()->getValue()]);

        if ($this->defaultStatus->isEqual($event->getCode())) {
            $this->defaultStatus = null;
        }

        if (!empty($this->statuses)) {
            $this->defaultStatus = reset($this->statuses);
        }
    }

    /**
     * @param WorkflowTransitionAddedEvent $event
     */
    protected function applyWorkflowTransitionAddedEvent(WorkflowTransitionAddedEvent $event): void
    {
        $this->transitions[] = $event->getTransition();
    }

    /**
     * @param WorkflowTransitionChangedEvent $event
     */
    protected function applyWorkflowTransitionChangedEvent(WorkflowTransitionChangedEvent $event): void
    {
        foreach ($this->transitions as $key => $transition) {
            if ($event->getSource()->isEqual($transition->getSource()) && $event->getDestination()->isEqual($transition->getDestination())) {
                $this->transitions[$key] = $event->getTo();
            }
        }
    }

    /**
     * @param WorkflowTransitionRemovedEvent $event
     */
    protected function applyWorkflowTransitionRemovedEvent(WorkflowTransitionRemovedEvent $event): void
    {
        foreach ($this->transitions as $key => $transition) {
            if ($event->getSource()->isEqual($transition->getSource()) && $event->getDestination()->isEqual($transition->getDestination())) {
                unset($this->transitions[$key]);
            }
        }
    }

    /**
     * @param WorkflowDefaultStatusSetEvent $event
     */
    protected function applyWorkflowDefaultStatusSetEvent(WorkflowDefaultStatusSetEvent $event): void
    {
        $this->defaultStatus = $event->getCode();
    }
}
