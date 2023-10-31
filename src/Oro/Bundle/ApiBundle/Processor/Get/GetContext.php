<?php

namespace Oro\Bundle\ApiBundle\Processor\Get;

use Oro\Bundle\ApiBundle\Metadata\Extra\ActionMetadataExtra;
use Oro\Bundle\ApiBundle\Processor\SingleItemContext;

/**
 * The execution context for processors for "get" action.
 */
class GetContext extends SingleItemContext
{
    /** the name of the action which causes this action, e.g. "create" or "update" */
    private const PARENT_ACTION = 'parentAction';

    /**
     * {@inheritDoc}
     */
    protected function initialize(): void
    {
        parent::initialize();
        $this->set(self::PARENT_ACTION, '');
    }

    /**
     * Gets the name of the action which causes this action, e.g. "create" or "update".
     */
    public function getParentAction(): ?string
    {
        $action = $this->get(self::PARENT_ACTION);

        return '' !== $action ? $action : null;
    }

    /**
     * Sets the name of the action which causes this action, e.g. "create" or "update".
     */
    public function setParentAction(?string $action): void
    {
        $this->set(self::PARENT_ACTION, $action ?? '');
    }

    /**
     * {@inheritDoc}
     */
    public function getNormalizationContext(): array
    {
        $normalizationContext = parent::getNormalizationContext();
        $parentAction = $this->getParentAction();
        if ($parentAction) {
            $normalizationContext[self::PARENT_ACTION] = $parentAction;
        }

        return $normalizationContext;
    }

    /**
     * {@inheritDoc}
     */
    protected function createActionMetadataExtra(string $action): ActionMetadataExtra
    {
        $parentAction = $this->getParentAction();

        return $parentAction
            ? new ActionMetadataExtra($action, $parentAction)
            : parent::createActionMetadataExtra($action);
    }
}
