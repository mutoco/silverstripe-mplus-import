<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;

interface StepInterface extends \Serializable
{
    /**
     * The default queue to run this step in.
     * Use one of the constants defined in `ImportEngine`
     * @return string - default queue name
     */
    public function getDefaultQueue(): string;

    /**
     * Activate the current step
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
