<?php

namespace modules\bulkUserImport\services;

use humhub\modules\user\models\Group;

/**
 * Provides helper methods for resolving group identifiers.
 */
class GroupResolver
{
    /**
     * @var array<int, string>
     */
    private array $groupsById = [];

    /**
     * @var array<string, int>
     */
    private array $groupsByLowerName = [];

    public function __construct()
    {
        foreach (Group::find()->all() as $group) {
            $this->groupsById[(int)$group->id] = $group->name;
            $this->groupsByLowerName[mb_strtolower($group->name)] = (int)$group->id;
        }
    }

    /**
     * Returns all groups as id => name for form dropdowns.
     *
     * @return array<int, string>
     */
    public function getAllOptions(): array
    {
        return $this->groupsById;
    }

    /**
     * Resolves an arbitrary token (id or name) to a group id.
     */
    public function resolveToken(string $token): ?int
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (ctype_digit($token)) {
            $id = (int)$token;
            return isset($this->groupsById[$id]) ? $id : null;
        }

        $lower = mb_strtolower($token);
        return $this->groupsByLowerName[$lower] ?? null;
    }

    /**
     * Returns readable names for multiple group ids.
     *
     * @param int[] $ids
     * @return array<int, string>
     */
    public function getNames(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (isset($this->groupsById[$id])) {
                $result[$id] = $this->groupsById[$id];
            }
        }

        return $result;
    }
}
