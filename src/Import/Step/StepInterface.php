<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;

interface StepInterface extends \Serializable
{
    /**
     * The default priority to run this step with. Higher priorities get executed first
     * You can use one of the constants defined in `ImportEngine`
     * @return int - default priority
     */
    public function getDefaultPriority(): int;

    /**
     * Activate the current step. This happens when the step is about to run.
     * In some situations, this can get called multiple times
     * @param ImportEngine $engine - the import engine
     */
    public function activate(ImportEngine $engine): void;

    /**
     * Update the current step. Return `true` if the step needs to run again (eg. return `false` when complete)
     * @param ImportEngine $engine
     * @return bool - whether or not the import-step has more steps
     */
    public function run(ImportEngine $engine): bool;

    /**
     * Shut down/cleanup
     * @param ImportEngine $engine
     */
    public function deactivate(ImportEngine $engine): void;
}
