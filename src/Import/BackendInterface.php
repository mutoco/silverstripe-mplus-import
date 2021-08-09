<?php

namespace Mutoco\Mplus\Import;

use Mutoco\Mplus\Import\Step\StepInterface;
use Mutoco\Mplus\Parse\Result\TreeNode;

interface BackendInterface extends \Serializable
{
    public function addStep(StepInterface $step, int $priority): void;

    public function getNextStep(?int &$priority): ?StepInterface;

    public function getRemainingSteps(): int;

    /**
     * Check if a tree has been imported for the given module and id
     * @param string $module
     * @param string $id
     * @return bool - true if the tree exists
     */
    public function hasImportedTree(string $module, string $id): bool;

    /**
     * Get the imported tree for the given module and id.
     * @param string $module
     * @param string $id
     * @return TreeNode|null - the found tree or `null`
     */
    public function getImportedTree(string $module, string $id): ?TreeNode;

    /**
     * Store an imported tree
     * @param string $module
     * @param string $id
     * @param TreeNode $tree
     */
    public function setImportedTree(string $module, string $id, TreeNode $tree): void;

    /**
     * Clear an imported tree
     * @param string $module
     * @param string $id
     * @return bool - true if successfully cleared
     */
    public function clearImportedTree(string $module, string $id): bool;

    /**
     * Report an imported relation
     * @param string $class
     * @param string $id
     * @param string $name
     * @param array $ids
     */
    public function reportImportedRelation(string $class, string $id, string $name, array $ids): void;

    /**
     * Check if relation was imported
     * @param string $class
     * @param string $id
     * @param string $name
     * @return bool
     */
    public function hasImportedRelation(string $class, string $id, string $name): bool;

    /**
     * Get the IDs of an imported relation
     * @param string $class
     * @param string $id
     * @param string $name
     * @return array – array of IDs, will return an empty array for non-existing relations
     */
    public function getRelationIds(string $class, string $id, string $name): array;

    /**
     * Report an imported module
     * @param string $name - the name of the Module
     * @param string $id - the M+ ID of the Module
     */
    public function reportImportedModule(string $name, string $id): void;

    /**
     * Check whether or not a module was imported
     * @param string $name - the name of the Module
     * @param string|null $id - if the ID is set, the check will look for a module with that ID, otherwise *any* ID
     * @return bool - true if the module was found
     */
    public function hasImportedModule(string $name, ?string $id = null): bool;

    /**
     * Get all IDs that were imported for the given module
     * @param string $module - the name of the Module
     * @return array - an array of the imported IDs, empty array if none were found
     */
    public function getImportedIds(string $module): array;

    /**
     * Destroy all data in this backend
     */
    public function clear(): void;
}
