<?php
interface PermissionServiceInterface
{
    /**
     * Check if the user has permission to perform the action.
     *
     * @param string $action
     * @param mixed $user
     * @return bool
     */
    public function hasPermission(string $action, $user): bool;

    /**
     * Get all permissions for a user.
     *
     * @param mixed $user
     * @return array
     */
    public function getPermissionsForUser($user): array;

    /**
     * Assign a permission to a user.
     *
     * @param string $permission
     * @param mixed $user
     * @return void
     */
    public function assignPermission(string $permission, $user): void;

    /**
     * Revoke a permission from a user.
     *
     * @param string $permission
     * @param mixed $user
     * @return void
     */
    public function revokePermission(string $permission, $user): void;
}
