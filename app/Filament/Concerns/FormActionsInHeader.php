<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

/**
 * The buttons of a create / edit page sit at the top right, with the page's own: Delete (apart), View, Cancel, then Save
 * or Create at the far right — nothing at the bottom of the form (user, 16 Nov 2026: every page with such buttons).
 * The page's usual form actions are taken as they are and moved; Save / Create still submit the form (so the fields
 * are validated as before, and Ctrl+S still saves).
 */
trait FormActionsInHeader
{
    /** @var array<Action> */
    protected array $topFormActions = [];

    public function bootedFormActionsInHeader(): void
    {
        // as the form listed them (Save, Cancel) but turned round: the main button is the last, at the far right
        $this->topFormActions = array_reverse(array_values(array_filter(
            parent::getFormActions(),
            fn ($action) => $action instanceof Action,
        )));

        foreach ($this->topFormActions as $action) {
            // a submit button outside the form names the form it submits
            if ($action->canSubmitForm()) {
                $action->formId('form');
            }

            $this->cacheAction($action);
        }
    }

    /**
     * The order of the buttons (user, 16 Nov 2026, after the usual guidance — Delete kept apart from Save so it is
     * not clicked by mistake, the main button last, one order on every page): Delete first, outlined and with a gap
     * after it; then the page's other buttons (View …); then Cancel; then Save / Create at the far right.
     */
    public function getCachedHeaderActions(): array
    {
        $own = parent::getCachedHeaderActions();
        $isDelete = fn ($action): bool => $action instanceof Action && in_array($action->getName(), ['delete', 'forceDelete', 'restore'], true);

        $delete = array_values(array_filter($own, $isDelete));
        $others = array_values(array_filter($own, fn ($action) => ! $isDelete($action)));

        foreach ($delete as $i => $action) {
            $action->outlined();
            // the gap that sets Delete apart from the rest
            if ($i === count($delete) - 1) {
                $action->extraAttributes(['style' => 'margin-inline-end: 1rem'], merge: true);
            }
        }

        return [...$delete, ...$others, ...$this->topFormActions];
    }

    /** Nothing under the form. */
    protected function getFormActions(): array
    {
        return [];
    }
}
