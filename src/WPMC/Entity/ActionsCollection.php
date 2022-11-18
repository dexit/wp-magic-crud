<?php

namespace WPMC\Entity;

use WPMC\Entity;
use WPMC\Action;
use WPMC\Action\ArrayActionsMapper;
use WPMC\Action\BackgroundAction;
use WPMC\Action\ITriggerableAction;
use WPMC\Action\IResttableAction;
use Illuminate\Support\Collection;
use Exception;

class ActionsCollection extends Collection
{
    public function __construct($items = [], private Entity $rootEntity)
    {
        $actions = (new ArrayActionsMapper($rootEntity, $items))->resolveActions();
        parent::__construct($actions);
    }

    /**
     * @return Action[]
     */
    public function actionItems()
    {
        return $this->items;
    }

    /**
     * @return Action
     */
    public function getActionByAlias($alias)
    {
        $actions = $this->actionItems();

        foreach ( $actions as $action ) {
            if ( $action->getAlias() == $alias ) {
                return $action;
            }
        }

        throw new Exception('Action not found: ' . $alias);
    }

    /**
     * @return Action[]
     */
    public function getAfterCreatedActions() {
        return array_filter($this->actionItems(), function (Action $action) {
            return ( $action instanceof ITriggerableAction && $action->getRunAfterCreate() ) ?
                $action->getRunner()->setContext(Action::CONTEXT_RUN_ON_CREATE) :
                false;
        });
    }
    
    /**
     * @return Action[]
     */
    public function getAfterUpdatedActions() {
        return array_filter($this->actionItems(), function (Action $action) {
            return ( $action instanceof ITriggerableAction && $action->getRunAfterUpdate() ) ?
                $action->getRunner()->setContext(Action::CONTEXT_RUN_ON_UPDATE) :
                false;
        });
    }

    /**
     * @return BackgroundAction
     */
    public function getBackgroundJobAction($alias)
    {
        return $this->getActionByAlias($alias)
            ->getRunner()->setContext(Action::CONTEXT_BACKGROUND_JOB);
    }

    /**
     * @return BackgroundAction[]
     */
    public function getBackgroundJobActions() {
        return array_filter($this->actionItems(), function (Action $action) {
            return $action instanceof BackgroundAction ?
                $action->getRunner()->setContext(Action::CONTEXT_BACKGROUND_JOB) :
                false;
        });
    }

    /**
     * @return BackgroundAction[]
     */
    public function getAutoRunJobActions() {
        return array_filter($this->actionItems(), function (Action $action) {
            return $action instanceof BackgroundAction && $action->hasAutoRun() ?
                $action->getRunner()->setContext(Action::CONTEXT_AUTORUN_JOB) :
                false;
        });
    }
    
    /**
     * @return Action[]
     */
    public function getFieldableActionUIs()
    {
        return array_filter($this->actionItems(), function (Action $action) {
            return $action->hasUIForm() ?
                $action->getRunner()->setContext(Action::CONTEXT_UI_FORM) :
                false;
        });
    }

    /**
     * @return Action[]|IResttableAction[]
     */
    public function getResttableActions()
    {
        return array_filter($this->actionItems(), function (Action $action) {
            return $action instanceof IResttableAction && $action->getExposeAsRest() ?
                $action->getRunner()->setContext(Action::CONTEXT_API_REST) :
                false;
        });
    }

    public function validateDefinitions()
    {
        foreach ( $this->actionItems() as $action ) {
            $action->validateDefinitions();
        }
    }

    public function exportArray()
    {
        $arr = [];

        foreach ( $this->actionItems() as $action ) {
            $arr['actions'][$action->getAlias()] = $action->toArray();
        }

        return $arr;
    }
}